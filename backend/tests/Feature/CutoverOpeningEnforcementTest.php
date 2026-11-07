<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubAccountingCutoverExecutionEvent as Audit;
use App\Models\ClubAccountingCutoverExecutionSuccess as Success;
use App\Models\ClubAccountingCutoverAuthorityConsumption as Consumption;
use App\Models\ClubJournalEntry;
use App\Services\Access\OrganisationAccessRejected;
use App\Services\Finance\ClubOpeningJournalActivationService;
use App\Services\Finance\CutoverExecutionRequest;
use App\Services\Finance\Exceptions\CutoverExecutionAuditException;
use App\Services\Finance\Exceptions\CutoverExecutionConflictException;
use App\Services\Finance\Exceptions\CutoverExecutionOutcomeUnknownException;
use Database\Seeders\DefaultRolesAndPermissionsSeeder;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BuildsOpeningExecutionFixture;
use Tests\Support\CutoverPostgresTestCase;
use Tests\Support\InteractsWithCutoverGovernance;

class CutoverOpeningEnforcementTest extends CutoverPostgresTestCase
{
    use BuildsOpeningExecutionFixture, InteractsWithCutoverGovernance;

    private function fixture(): array
    {
        $this->seed(DefaultRolesAndPermissionsSeeder::class);
        return $this->authorizedFixture('opening', 1);
    }

    private function request(array $f): CutoverExecutionRequest
    {
        return new CutoverExecutionRequest($f['club']->id, $f['package']->id, $f['package']->lock_version, $f['package']->canonical_checksum, 'E2-EXECUTION', (string) Str::uuid(), $f['operator']->id);
    }

    private function execute(CutoverExecutionRequest $request): Success
    {
        return app(ClubOpeningJournalActivationService::class)->execute($request);
    }

    private function assertNoFinancialWrites(): void
    {
        $this->assertSame(0, ClubJournalEntry::count());
        $this->assertSame(0, Success::count());
        $this->assertSame(0, Consumption::count());
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_active_execution_and_exact_replay_lock_club_before_financial_resources(): void
    {
        $f = $this->fixture(); $r = $this->request($f); $locks = [];
        DB::listen(function ($event) use (&$locks): void {
            if (str_contains(strtolower($event->sql), 'for update')) { $locks[] = $event->sql; }
        });
        $first = $this->execute($r); $replay = $this->execute($r);
        $this->assertSame($first->id, $replay->id);
        $this->assertSame(1, ClubJournalEntry::count());
        $this->assertSame(1, Consumption::count());
        $this->assertSame(2, Audit::where('event_type', 'execution_attempt')->count());
        $this->assertStringContainsString('"clubs"', $locks[0]);
        foreach ($locks as $index => $sql) {
            if (str_contains($sql, 'club_accounting_cutover_packages')) {
                $this->assertStringContainsString('"clubs"', $locks[$index - 1]);
            }
        }
    }

    public static function denied(): array { return [['suspended'], ['foreign'], ['invalid'], ['missing']]; }

    #[DataProvider('denied')]
    public function test_identity_and_fresh_suspension_deny_finance(string $state): void
    {
        $f = $this->fixture(); $r = $this->request($f);
        if ($state === 'suspended') { $f['club']->update(['is_active' => false]); }
        if ($state === 'foreign') { Club::create(['name' => 'Foreign', 'code' => 'FOREIGN']); config(['instance.organisation_code' => 'FOREIGN']); }
        if ($state === 'invalid') { config(['instance.organisation_code' => null]); }
        if ($state === 'missing') { config(['instance.organisation_code' => 'DOES-NOT-EXIST']); }
        try { $this->execute($r); $this->fail('Denied organisation executed.'); }
        catch (OrganisationAccessRejected) {}
        $this->assertNoFinancialWrites();
        $this->assertSame($state === 'suspended' ? 2 : 0, Audit::count());
    }

    public function test_attempt_audit_failure_is_fail_closed(): void
    {
        $f = $this->fixture();
        Audit::creating(function (): void { throw new \RuntimeException('attempt storage failed'); });
        try { $this->execute($this->request($f)); $this->fail('Audit failure allowed posting.'); }
        catch (CutoverExecutionAuditException $e) { $this->assertSame('attempt', $e->phase); }
        $this->assertNoFinancialWrites(); $this->assertSame(0, Audit::count());
    }

    public function test_terminal_audit_failure_preserves_both_failures_after_atomic_rollback(): void
    {
        $f = $this->fixture();
        Success::creating(function (): void { throw new \RuntimeException('financial failure'); });
        Audit::creating(function ($event): void { if ($event->event_type === 'execution_failure') { throw new \RuntimeException('terminal storage failed'); } });
        try { $this->execute($this->request($f)); $this->fail('Failures were hidden.'); }
        catch (CutoverExecutionAuditException $e) {
            $this->assertSame('terminal', $e->phase);
            $this->assertSame('financial failure', $e->businessFailure->getMessage());
            $this->assertSame('terminal storage failed', $e->auditFailure->getMessage());
        }
        $this->assertNoFinancialWrites(); $this->assertSame(1, Audit::count());
    }

    public static function retries(): array { return [[false], [true]]; }

    #[DataProvider('retries')]
    public function test_three_attempt_budget_rebuilds_transactions_without_duplicate_audit(bool $exhaust): void
    {
        $f = $this->fixture(); $attempts = [];
        Success::creating(function () use (&$attempts, $exhaust): void {
            $attempts[] = DB::selectOne('SELECT pg_current_xact_id()::text AS xid')->xid;
            if ($exhaust || count($attempts) < 3) { DB::unprepared("DO $$ BEGIN RAISE EXCEPTION 'synthetic serialization abort' USING ERRCODE = '40001'; END $$"); }
        });
        try { $this->execute($this->request($f)); $this->assertFalse($exhaust); }
        catch (CutoverExecutionConflictException) { $this->assertTrue($exhaust); }
        $this->assertCount(3, array_unique($attempts));
        $this->assertSame(1, Audit::where('event_type', 'execution_attempt')->count());
        $this->assertSame(1, Audit::where('event_type', $exhaust ? 'execution_conflict' : 'execution_success')->count());
        $this->assertSame($exhaust ? 0 : 1, Success::count());
        $this->assertSame($exhaust ? 0 : 1, Consumption::count());
        $this->assertSame($exhaust ? 0 : 1, ClubJournalEntry::count());
    }

    public function test_lost_commit_acknowledgement_is_unknown_and_exact_replay_recovers(): void
    {
        $f = $this->fixture(); $request = $this->request($f); $lost = false;
        Event::listen(TransactionCommitted::class, function () use (&$lost): void {
            if (! $lost && Success::count() === 1) { $lost = true; throw new \RuntimeException('commit acknowledgement lost'); }
        });
        try { $this->execute($request); $this->fail('Ambiguous outcome was hidden.'); }
        catch (CutoverExecutionOutcomeUnknownException) {}
        $this->assertSame(1, Audit::where('result', 'outcome_unknown')->count());
        $this->assertSame(0, Audit::where('result', 'not_posted')->count());
        $success = $this->execute($request);
        $this->assertSame(1, Success::count()); $this->assertSame(1, Consumption::count());
        $this->assertSame(1, ClubJournalEntry::count()); $this->assertSame(Success::first()->id, $success->id);
    }

    public function test_suspension_denies_exact_replay_without_altering_committed_provenance(): void
    {
        $f = $this->fixture(); $request = $this->request($f); $first = $this->execute($request);
        $f['club']->update(['is_active' => false]);
        try { $this->execute($request); $this->fail('Suspended replay returned protected data.'); }
        catch (OrganisationAccessRejected $e) { $this->assertSame('ORGANISATION_SUSPENDED', $e->reason); }
        $this->assertSame(1, Success::count()); $this->assertSame(1, Consumption::count()); $this->assertSame(1, ClubJournalEntry::count());
        $this->assertSame($first->journal_entry_id, $f['package']->fresh()->opening_journal_entry_id);
        $this->assertSame(2, Audit::where('event_type', 'execution_attempt')->count());
        $this->assertSame(0, Audit::where('event_type', 'execution_replay')->count());
        $this->assertSame(1, Audit::where('event_type', 'execution_failure')->count());
    }

    public function test_retry_rechecks_suspension_after_rolled_back_attempt(): void
    {
        $f = $this->fixture(); $attempts = 0; $suspended = false;
        Success::creating(function () use (&$attempts): void {
            $attempts++;
            DB::unprepared("DO $$ BEGIN RAISE EXCEPTION 'retry suspension abort' USING ERRCODE = '40001'; END $$");
        });
        Event::listen(\Illuminate\Database\Events\TransactionRolledBack::class, function () use ($f, &$suspended): void {
            if (! $suspended && DB::transactionLevel() === 0) {
                $suspended = true;
                Club::whereKey($f['club']->id)->update(['is_active' => false]);
            }
        });
        try { $this->execute($this->request($f)); $this->fail('Retry reused stale eligibility.'); }
        catch (OrganisationAccessRejected $e) { $this->assertSame('ORGANISATION_SUSPENDED', $e->reason); }
        $this->assertSame(1, $attempts); $this->assertNoFinancialWrites();
        $this->assertSame(1, Audit::where('event_type', 'execution_attempt')->count());
        $this->assertSame(1, Audit::where('event_type', 'execution_failure')->count());
    }

    public function test_indeterminate_terminal_audit_failure_exposes_unknown_business_outcome(): void
    {
        $f = $this->fixture(); $lost = false;
        Event::listen(TransactionCommitted::class, function () use (&$lost): void {
            if (! $lost && Success::count() === 1) { $lost = true; throw new \RuntimeException('acknowledgement lost'); }
        });
        Audit::creating(function ($event): void {
            if ($event->result === 'outcome_unknown') { throw new \RuntimeException('unknown audit unavailable'); }
        });
        try { $this->execute($this->request($f)); $this->fail('Indeterminate audit failure hidden.'); }
        catch (CutoverExecutionAuditException $e) {
            $this->assertSame('indeterminate', $e->phase);
            $this->assertInstanceOf(CutoverExecutionOutcomeUnknownException::class, $e->businessFailure);
            $this->assertSame('unknown audit unavailable', $e->auditFailure->getMessage());
        }
        $this->assertSame(1, Success::count()); $this->assertSame(1, Consumption::count()); $this->assertSame(1, ClubJournalEntry::count());
        $this->assertSame(0, Audit::where('result', 'not_posted')->count());
        $this->assertSame(1, Audit::where('event_type', 'execution_attempt')->count());
    }

    public function test_unsupported_ambient_transaction_cannot_even_record_an_attempt(): void
    {
        $f = $this->fixture(); DB::beginTransaction();
        try { $this->execute($this->request($f)); $this->fail('Ambient transaction accepted.'); }
        catch (OrganisationAccessRejected) { $this->assertSame(0, Audit::count()); }
        finally { DB::rollBack(); }
        $this->assertNoFinancialWrites();
    }
}
