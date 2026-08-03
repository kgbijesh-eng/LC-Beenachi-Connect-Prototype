<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubAccountingCutoverAuthorityConsumption;
use App\Models\ClubAccountingCutoverDecisionEvent;
use App\Models\ClubAccountingCutoverExecutionSuccess;
use App\Models\ClubAccountingCutoverPackage;
use App\Models\ClubAccountingPeriod;
use App\Models\ClubJournalEntry;
use App\Models\ClubMembership;
use App\Models\LedgerAccount;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\ClubAccountingCutoverPackageService;
use App\Services\Finance\ClubCutoverAuthorityAdministrationService;
use App\Services\Finance\ClubCutoverLifecycleService;
use Database\Seeders\DefaultRolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class Milestone3iizgPostgresConcurrencyTest extends TestCase
{
    public function test_two_direct_postgresql_inserts_allow_only_one_current_decision_and_replacement_after_revocation(): void
    {
        $this->requireExplicitPostgres();
        $this->seed(DefaultRolesAndPermissionsSeeder::class);
        $fixture = $this->authorizedFixture('decision', 1, false);
        $package = $fixture['package'];
        $review = $package->reviews()->firstOrFail();
        $approval = $package->approvals()->firstOrFail();
        $appointment = $package->authorityAppointments()->where('authority_type', 'independent_ca')->firstOrFail();
        $decisionPermission = $package->permissionAssignments()->where('user_id', $fixture['ca']->id)->whereHas('permission', fn ($query) => $query->where('slug', 'finance.cutover.package.dry-run-accept'))->firstOrFail();
        $rollbackPermission = $package->permissionAssignments()->where('user_id', $fixture['ca']->id)->whereHas('permission', fn ($query) => $query->where('slug', 'finance.cutover.package.rollback-authorize'))->firstOrFail();
        $decision = [
            'public_id' => (string) Str::uuid(), 'club_id' => $package->club_id, 'cutover_package_id' => $package->id,
            'decision_stage' => 'dry_run', 'actor_authority' => 'independent_ca', 'decision_result' => 'accepted',
            'decision_reference' => 'PG-DIRECT-DECISION-ONE', 'package_version' => $package->package_version,
            'canonical_checksum' => $package->canonical_checksum, 'chart_version' => $package->chart_version,
            'lions_year' => $package->lions_year, 'cutover_date' => $package->cutover_date->format('Y-m-d'),
            'dr033_classification' => $package->dr033_classification, 'accounting_review_id' => $review->id,
            'president_approval_id' => $approval->id, 'authority_appointment_id' => $appointment->id,
            'permission_assignment_id' => $decisionPermission->id, 'recorded_by_user_id' => $fixture['ca']->id,
            'reason' => 'Direct PostgreSQL concurrency boundary verification.', 'recorded_at' => now()->toDateTimeString(),
            'created_at' => now()->toDateTimeString(), 'updated_at' => now()->toDateTimeString(),
        ];
        $second = [...$decision, 'public_id' => (string) Str::uuid(), 'decision_reference' => 'PG-DIRECT-DECISION-TWO'];
        $results = $this->raceDirectDecisionInserts($package->id, $decision, $second);

        $this->assertSame(['inserted', 'rejected'], collect($results)->pluck('outcome')->sort()->values()->all());
        $this->assertSame(1, DB::table('club_accounting_cutover_decisions')->where('cutover_package_id', $package->id)->where('decision_stage', 'dry_run')->where('actor_authority', 'independent_ca')->count());
        $winner = DB::table('club_accounting_cutover_decisions')->where('cutover_package_id', $package->id)->where('decision_stage', 'dry_run')->where('actor_authority', 'independent_ca')->first();
        ClubAccountingCutoverDecisionEvent::create(['club_id' => $package->club_id, 'cutover_package_id' => $package->id, 'cutover_decision_id' => $winner->id, 'event_type' => 'revoked', 'event_reference' => 'PG-DIRECT-DECISION-REVOKED', 'reason' => 'Permit a legitimate append-only replacement.', 'recorded_by_user_id' => $fixture['ca']->id, 'permission_assignment_id' => $rollbackPermission->id, 'recorded_at' => now()]);
        DB::table('club_accounting_cutover_decisions')->insert([...$decision, 'public_id' => (string) Str::uuid(), 'decision_reference' => 'PG-DIRECT-DECISION-REPLACEMENT']);
        $this->assertSame(2, DB::table('club_accounting_cutover_decisions')->where('cutover_package_id', $package->id)->where('decision_stage', 'dry_run')->where('actor_authority', 'independent_ca')->count());
        $this->assertSame(1, DB::table('club_accounting_cutover_decisions as d')->where('d.cutover_package_id', $package->id)->where('d.decision_stage', 'dry_run')->where('d.actor_authority', 'independent_ca')->whereNotExists(fn ($query) => $query->selectRaw('1')->from('club_accounting_cutover_decision_events as e')->whereColumn('e.cutover_decision_id', 'd.id')->whereIn('e.event_type', ['revoked', 'invalidated']))->count());
    }

    public function test_two_process_postgresql_activation_concurrency_is_stable(): void
    {
        $this->requireExplicitPostgres();
        $this->seed(DefaultRolesAndPermissionsSeeder::class);

        $outcomes = ['identical' => [], 'changed' => []];
        foreach (['identical', 'changed'] as $scenario) {
            foreach (range(1, 10) as $iteration) {
                $outcomes[$scenario][] = $this->runIteration($scenario, $iteration);
            }
        }

        foreach ($outcomes['identical'] as $result) {
            $this->assertSame(['resolved', 'resolved'], $result['outcomes']);
            $this->assertSame(['execution_replay' => 1, 'execution_success' => 1], $result['terminal_events']);
        }
        foreach ($outcomes['changed'] as $result) {
            $this->assertSame(['conflict', 'resolved'], $result['outcomes']);
            $this->assertSame(['execution_conflict' => 1, 'execution_success' => 1], $result['terminal_events']);
            $this->assertSame(1, $result['not_posted_conflicts']);
        }
    }

    private function raceDirectDecisionInserts(int $packageId, array $first, array $second): array
    {
        $barrier = sys_get_temp_dir().DIRECTORY_SEPARATOR.'3iizg-pg-decision-'.Str::lower(Str::random(8));
        mkdir($barrier, 0700, true);
        file_put_contents($barrier.DIRECTORY_SEPARATOR.'one.request.json', json_encode($first, JSON_THROW_ON_ERROR));
        file_put_contents($barrier.DIRECTORY_SEPARATOR.'two.request.json', json_encode($second, JSON_THROW_ON_ERROR));
        $worker = base_path('tests/Support/Milestone3iizgPostgresDecisionWorker.php');
        $one = new Process([PHP_BINARY, $worker, $barrier.DIRECTORY_SEPARATOR.'one.request.json', $barrier, 'one'], base_path(), timeout: 40);
        $two = new Process([PHP_BINARY, $worker, $barrier.DIRECTORY_SEPARATOR.'two.request.json', $barrier, 'two'], base_path(), timeout: 40);

        DB::beginTransaction();
        DB::select('select id from club_accounting_cutover_packages where id = ? for no key update', [$packageId]);
        try {
            $one->start();
            $two->start();
            $this->awaitFiles([$barrier.DIRECTORY_SEPARATOR.'one.ready', $barrier.DIRECTORY_SEPARATOR.'two.ready']);
            file_put_contents($barrier.DIRECTORY_SEPARATOR.'one.release', 'release');
            file_put_contents($barrier.DIRECTORY_SEPARATOR.'two.release', 'release');
            $this->awaitFiles([$barrier.DIRECTORY_SEPARATOR.'one.attempting', $barrier.DIRECTORY_SEPARATOR.'two.attempting']);
            usleep(100_000);
            $releasedAt = hrtime(true);
            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        $one->wait();
        $two->wait();
        $results = [
            json_decode((string) file_get_contents($barrier.DIRECTORY_SEPARATOR.'one.result.json'), true, flags: JSON_THROW_ON_ERROR),
            json_decode((string) file_get_contents($barrier.DIRECTORY_SEPARATOR.'two.result.json'), true, flags: JSON_THROW_ON_ERROR),
        ];
        $this->assertTrue(collect($results)->every(fn (array $result): bool => $result['started_at_ns'] < $releasedAt));
        $this->assertTrue(max(array_column($results, 'started_at_ns')) < min(array_column($results, 'finished_at_ns')), 'Direct insert worker intervals must genuinely overlap.');

        return $results;
    }

    private function requireExplicitPostgres(): void
    {
        if (env('RUN_3IIZG_POSTGRES_CONCURRENCY') !== '1') {
            $this->markTestSkipped('Requires RUN_3IIZG_POSTGRES_CONCURRENCY=1 and an isolated PostgreSQL database.');
        }
        $this->assertSame('pgsql', DB::getDriverName(), 'This test never falls back to SQLite.');
    }

    private function runIteration(string $scenario, int $iteration): array
    {
        $fixture = $this->authorizedFixture($scenario, $iteration);
        $request = [$fixture['club']->id, $fixture['package']->id, $fixture['package']->lock_version, $fixture['package']->canonical_checksum, "EXEC-{$scenario}-{$iteration}", (string) Str::uuid(), $fixture['operator']->id];
        $second = $request;
        if ($scenario === 'changed') {
            $second[4] .= '-CHANGED';
            $second[5] = (string) Str::uuid();
        }

        $barrier = sys_get_temp_dir().DIRECTORY_SEPARATOR."3iizg-pg-{$scenario}-{$iteration}-".Str::lower(Str::random(8));
        mkdir($barrier, 0700, true);
        file_put_contents($barrier.DIRECTORY_SEPARATOR.'one.request.json', json_encode($request, JSON_THROW_ON_ERROR));
        file_put_contents($barrier.DIRECTORY_SEPARATOR.'two.request.json', json_encode($second, JSON_THROW_ON_ERROR));
        $worker = base_path('tests/Support/Milestone3iizgPostgresConcurrencyWorker.php');
        $one = new Process([PHP_BINARY, $worker, $barrier.DIRECTORY_SEPARATOR.'one.request.json', $barrier, 'one'], base_path(), timeout: 40);
        $two = new Process([PHP_BINARY, $worker, $barrier.DIRECTORY_SEPARATOR.'two.request.json', $barrier, 'two'], base_path(), timeout: 40);

        DB::beginTransaction();
        DB::select('select id from club_accounting_cutover_packages where id = ? for no key update', [$fixture['package']->id]);
        try {
            $one->start();
            $two->start();
            $this->awaitFiles([$barrier.DIRECTORY_SEPARATOR.'one.ready', $barrier.DIRECTORY_SEPARATOR.'two.ready']);
            file_put_contents($barrier.DIRECTORY_SEPARATOR.'one.release', 'release');
            $this->awaitAttemptCount($fixture['package']->id, 1);
            file_put_contents($barrier.DIRECTORY_SEPARATOR.'two.release', 'release');
            $this->awaitAttemptCount($fixture['package']->id, 2);
            $releasedAt = hrtime(true);
            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        $one->wait();
        $two->wait();
        $results = [
            json_decode((string) file_get_contents($barrier.DIRECTORY_SEPARATOR.'one.result.json'), true, flags: JSON_THROW_ON_ERROR),
            json_decode((string) file_get_contents($barrier.DIRECTORY_SEPARATOR.'two.result.json'), true, flags: JSON_THROW_ON_ERROR),
        ];
        $this->assertTrue(collect($results)->every(fn (array $result): bool => $result['started_at_ns'] < $releasedAt), 'Both independent workers must enter activation before the package lock is released.');
        $this->assertTrue(max(array_column($results, 'started_at_ns')) < min(array_column($results, 'finished_at_ns')), 'Worker execution intervals must overlap.');

        $package = $fixture['package']->fresh();
        $journal = ClubJournalEntry::query()->with('lines')->findOrFail($package->opening_journal_entry_id);
        $this->assertSame(ClubAccountingCutoverPackage::STATUS_POSTED, $package->status);
        $this->assertSame(1, ClubJournalEntry::query()->where('club_id', $fixture['club']->id)->where('source_type', 'accounting_opening_package')->count());
        $this->assertSame(1, ClubAccountingCutoverExecutionSuccess::query()->where('cutover_package_id', $package->id)->count());
        $this->assertSame(1, ClubAccountingCutoverAuthorityConsumption::query()->where('cutover_package_id', $package->id)->count());
        $this->assertSame($journal->lines->where('direction', 'debit')->sum('amount_paise'), $journal->lines->where('direction', 'credit')->sum('amount_paise'));
        $terminal = DB::table('club_accounting_cutover_execution_events')->where('cutover_package_id', $package->id)->whereIn('event_type', ['execution_success', 'execution_replay', 'execution_conflict'])->selectRaw('event_type, count(*) as aggregate')->groupBy('event_type')->pluck('aggregate', 'event_type')->map(fn ($count): int => (int) $count)->sortKeys()->all();
        $notPostedConflicts = DB::table('club_accounting_cutover_execution_events')->where('cutover_package_id', $package->id)->where('event_type', 'execution_conflict')->where('result', 'not_posted')->count();

        return ['outcomes' => collect($results)->pluck('outcome')->sort()->values()->all(), 'terminal_events' => $terminal, 'not_posted_conflicts' => $notPostedConflicts, 'released_at_ns' => $releasedAt, 'workers' => $results, 'journal_id' => $journal->id];
    }

    private function awaitFiles(array $files): void
    {
        $deadline = microtime(true) + 20;
        while (! collect($files)->every(fn (string $file): bool => is_file($file))) {
            if (microtime(true) >= $deadline) {
                $this->fail('Workers did not reach the deterministic ready barrier.');
            }
            usleep(5_000);
        }
    }

    private function awaitAttemptCount(int $packageId, int $expected): void
    {
        $deadline = microtime(true) + 20;
        while (DB::table('club_accounting_cutover_execution_events')->where('cutover_package_id', $packageId)->where('event_type', 'execution_attempt')->count() < $expected) {
            if (microtime(true) >= $deadline) {
                $this->fail("Worker attempt {$expected} did not reach PostgreSQL while the package lock was held.");
            }
            usleep(5_000);
        }
    }

    private function authorizedFixture(string $scenario, int $iteration, bool $authorize = true): array
    {
        foreach (['grantor', 'preparer', 'verifier', 'ca', 'president', 'operator'] as $key) {
            $$key = User::factory()->create();
        }
        $club = Club::create(['name' => "Concurrency {$scenario} {$iteration}", 'code' => 'C'.strtoupper(substr($scenario, 0, 1)).str_pad((string) $iteration, 2, '0', STR_PAD_LEFT)]);
        ClubMembership::create(['club_id' => $club->id, 'user_id' => $president->id, 'role_id' => Role::where('slug', 'president-admin')->firstOrFail()->id, 'status' => 'active']);
        $accounts = [];
        foreach ([['cash', 'CASH-ON-HAND', 'asset', 'debit'], ['bank', 'BANK-OPERATING', 'asset', 'debit'], ['receivable', 'MEMBER-RECEIVABLES', 'asset', 'debit'], ['other_asset', 'CLUB-PROPERTY', 'asset', 'debit'], ['liability', 'LIABILITY-GENERAL', 'liability', 'credit'], ['restricted', 'LIABILITY-RESTRICTED-COLLECTION', 'liability', 'credit'], ['equity', 'OPENING-BALANCE-EQUITY', 'equity', 'credit'], ['surplus', 'ACCUMULATED-SURPLUS', 'equity', 'credit']] as [$key, $code, $type, $normal]) {
            $accounts[$key] = LedgerAccount::create(['club_id' => $club->id, 'code' => $code, 'name' => $code, 'account_type' => $type, 'normal_balance' => $normal, 'currency' => 'INR', 'is_active' => true, 'created_by_user_id' => $grantor->id]);
        }
        ClubAccountingPeriod::create(['club_id' => $club->id, 'lions_year' => '2027-28', 'start_date' => '2027-07-01', 'end_date' => '2028-06-30', 'status' => 'open', 'created_by_user_id' => $grantor->id]);
        $service = app(ClubAccountingCutoverPackageService::class);
        $package = $service->createDraft($club->id, $grantor->id);
        $rows = [
            ['schedule_type' => 'cash_on_hand', 'row_key' => 'CASH', 'non_sensitive_label' => 'Cash', 'ledger_account_id' => $accounts['cash']->id, 'direction' => 'debit', 'amount_paise' => 10000],
            ['schedule_type' => 'bank_account', 'row_key' => 'BANK', 'non_sensitive_label' => 'Bank', 'ledger_account_id' => $accounts['bank']->id, 'direction' => 'debit', 'amount_paise' => 20000],
            ['schedule_type' => 'member_receivables', 'row_key' => 'RECEIVABLES', 'non_sensitive_label' => 'Receivables', 'ledger_account_id' => $accounts['receivable']->id, 'direction' => 'debit', 'amount_paise' => 30000],
            ['schedule_type' => 'other_asset', 'row_key' => 'OTHER-ASSET', 'non_sensitive_label' => 'Other asset', 'ledger_account_id' => $accounts['other_asset']->id, 'direction' => 'debit', 'amount_paise' => 0],
            ['schedule_type' => 'liability', 'row_key' => 'LIABILITY', 'non_sensitive_label' => 'Liability', 'ledger_account_id' => $accounts['liability']->id, 'direction' => 'credit', 'amount_paise' => 10000],
            ['schedule_type' => 'external_restriction', 'row_key' => 'RESTRICTED', 'non_sensitive_label' => 'Restricted', 'ledger_account_id' => $accounts['restricted']->id, 'direction' => 'credit', 'amount_paise' => 10000],
            ['schedule_type' => 'general_club_fund', 'row_key' => 'GENERAL', 'non_sensitive_label' => 'General', 'presentation_group' => 'GENERAL-CLUB-FUND', 'direction' => 'credit', 'amount_paise' => 20000],
            ['schedule_type' => 'service_activity_fund', 'row_key' => 'SERVICE', 'non_sensitive_label' => 'Service', 'presentation_group' => 'SERVICE-ACTIVITY-FUND', 'direction' => 'credit', 'amount_paise' => 10000],
            ['schedule_type' => 'fellowship_member_activity_fund', 'row_key' => 'FELLOWSHIP', 'non_sensitive_label' => 'Fellowship', 'presentation_group' => 'FELLOWSHIP-MEMBER-ACTIVITY-FUND', 'direction' => 'credit', 'amount_paise' => 10000],
            ['schedule_type' => 'equity_preview', 'row_key' => 'EQUITY', 'non_sensitive_label' => 'Equity', 'ledger_account_id' => null, 'direction' => 'credit', 'amount_paise' => 40000],
        ];
        $package = $service->replaceSchedules($club->id, $package->id, $grantor->id, $package->lock_version, $rows);
        $evidence = collect(['cash_count', 'bank_reconciliation', 'member_receivables', 'liabilities_restrictions', 'reconciliation'])->map(fn ($type, $index) => ['evidence_type' => $type, 'opaque_reference' => 'EVIDENCE-'.strtoupper(str_replace('_', '-', $type)).'-'.$club->id, 'evidence_date' => '2027-07-01', 'evidence_version' => 'V1', 'digest_sha256' => str_repeat(dechex($index + 1), 64), 'verification_status' => 'verified'])->all();
        $package = $service->replaceEvidenceReferences($club->id, $package->id, $grantor->id, $package->lock_version, $evidence);
        $package = $service->refreshValidation($club->id, $package->id, $grantor->id, $package->lock_version);
        $admin = app(ClubCutoverAuthorityAdministrationService::class);
        foreach ([[$preparer, 'prepare'], [$verifier, 'verify'], [$ca, 'accounting-review'], [$ca, 'dry-run-accept'], [$ca, 'activate-authorize'], [$ca, 'rollback-authorize'], [$president, 'business-approve'], [$president, 'dry-run-accept'], [$president, 'activate-authorize'], [$operator, 'execute']] as $index => [$user, $permission]) {
            $admin->assignPermission($package, $user, "finance.cutover.package.{$permission}", "{$scenario}-{$iteration}-{$index}", $grantor);
        }
        $admin->appoint($package, $ca, 'independent_ca', "CA-{$scenario}-{$iteration}", $grantor);
        $admin->appoint($package, $operator, 'technical_operator', "OP-{$scenario}-{$iteration}", $grantor);
        $life = app(ClubCutoverLifecycleService::class);
        $package = $life->prepare($package, $preparer, $package->lock_version);
        $package = $life->verify($package, $verifier, $package->lock_version);
        $life->review($package, $ca, $package->lock_version, 'OPENING-BALANCE-EQUITY', "CA-REVIEW-{$scenario}-{$iteration}", '2027-07-02', 'Reconciled evidence substantiates opening balance equity.');
        $package = $package->fresh();
        $life->approve($package, $president, $package->lock_version, '2027-07-03', "APPROVAL-{$scenario}-{$iteration}");
        if ($authorize) {
            foreach ([['dry_run', 'president', $president], ['dry_run', 'independent_ca', $ca], ['activation', 'president', $president], ['activation', 'independent_ca', $ca]] as $index => [$stage, $authority, $actor]) {
                $package = $package->fresh();
                $life->decide($package, $actor, $package->lock_version, $stage, $authority, "DECISION-{$scenario}-{$iteration}-{$index}", 'Exact package decision accepted for concurrency verification.');
            }
        }
        $package = $package->fresh();

        return compact('club', 'package', 'operator', 'ca');
    }
}
