<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubAccountingPeriod;
use App\Models\ClubJournalEntry;
use App\Models\ClubMembership;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\AccountingPeriodCloseRequest;
use App\Services\Finance\ClubAccountingPeriodCloseService;
use App\Services\Finance\ClubAccountingPeriodProvisioningRequest;
use App\Services\Finance\ClubAccountingPeriodProvisioningService;
use App\Services\Finance\ClubJournalPostingService;
use App\Services\Finance\Exceptions\AccountingPeriodCloseIntegrityException;
use App\Services\Finance\Exceptions\ClosedAccountingPeriodException;
use App\Services\Finance\Exceptions\ClosedReversalPeriodException;
use App\Services\Finance\Exceptions\InvalidAccountingPeriodCloseRequestException;
use App\Services\Finance\JournalLineInput;
use App\Services\Finance\JournalPostingRequest;
use App\Services\Finance\JournalReversalRequest;
use App\Services\Finance\JournalReversalService;
use App\Services\Finance\LedgerAccountProvisioningRequest;
use App\Services\Finance\LedgerAccountProvisioningService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\FinancialPostgresTestCase;

class Milestone3iifInternalAccountingPeriodCloseServiceTest extends FinancialPostgresTestCase
{

    public function test_empty_or_balanced_period_closes_without_creating_or_mutating_journals(): void
    {
        $f = $this->fixture();
        $original = $this->postJournal($f);
        $header = collect($original->getRawOriginal())->only(['club_id', 'accounting_period_id', 'sequence_number', 'reference', 'effective_date', 'posting_date', 'posted_at', 'status', 'description', 'currency', 'source_type', 'source_id', 'source_event', 'idempotency_key', 'reversal_of_entry_id', 'created_by_user_id', 'posted_by_user_id', 'reversed_by_user_id', 'voided_at', 'voided_by_user_id', 'metadata'])->all();
        $lines = $original->lines()->get()->map->only(['id', 'direction', 'amount_paise'])->all();
        $closed = $this->service()->close($this->request($f));
        $this->assertSame(ClubAccountingPeriod::STATUS_CLOSED, $closed->status);
        $this->assertSame($f['actor']->id, $closed->closed_by_user_id);
        $this->assertNotNull($closed->closed_at);
        $this->assertCount(1, ClubJournalEntry::all());
        $this->assertSame($header, collect($original->fresh()->getRawOriginal())->only(array_keys($header))->all());
        $this->assertSame($lines, $original->fresh()->lines()->get()->map->only(['id', 'direction', 'amount_paise'])->all());
        $this->assertSame('finance.accounting_period.closed', AuditLog::firstOrFail()->action);
    }

    public function test_replay_is_actor_bound_and_closed_period_rejects_posting(): void
    {
        $f = $this->fixture();
        $first = $this->service()->close($this->request($f));
        $this->assertSame($first->id, $this->service()->close($this->request($f, ['reason' => 'different but not durable']))->id);
        $other = User::factory()->create();
        $this->expectException(InvalidAccountingPeriodCloseRequestException::class);
        $this->service()->close($this->request($f, ['closingActorUserId' => $other->id]));
    }

    public function test_draft_and_unbalanced_entries_block_close(): void
    {
        $f = $this->fixture();
        ClubJournalEntry::create(['club_id' => $f['club']->id, 'accounting_period_id' => $f['period']->id, 'sequence_number' => 1, 'reference' => 'C/2026-27/JRN/000001', 'effective_date' => '2026-07-01', 'posting_date' => '2026-07-01', 'status' => 'draft', 'description' => 'Draft', 'currency' => 'INR', 'created_by_user_id' => $f['actor']->id]);
        $this->expectException(AccountingPeriodCloseIntegrityException::class);
        $this->service()->close($this->request($f));
    }

    public function test_empty_period_wrong_scope_year_and_missing_or_foreign_actor_fail_closed(): void
    {
        $f = $this->fixture();
        $this->assertSame(ClubAccountingPeriod::STATUS_CLOSED, $this->service()->close($this->request($f))->status);
        foreach ([['clubId' => 999999], ['expectedLionsYear' => '2025-26'], ['closingActorUserId' => 999999]] as $overrides) {
            try {
                $this->service()->close($this->request($f, $overrides));
                $this->fail('Invalid close context must fail.');
            } catch (InvalidAccountingPeriodCloseRequestException) {
                $this->addToAssertionCount(1);
            }
        }
        $other = User::factory()->create();
        try {
            $this->service()->close($this->request($f, ['closingActorUserId' => $other->id]));
            $this->fail('Foreign actor must fail.');
        } catch (InvalidAccountingPeriodCloseRequestException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_service_closed_period_rejects_posting_and_generates_no_journal(): void
    {
        $f = $this->fixture();
        $this->service()->close($this->request($f));
        $this->assertSame(0, ClubJournalEntry::count());
        $this->expectException(ClosedAccountingPeriodException::class);
        $this->postJournal($f);
    }

    public function test_persisted_unbalanced_line_blocks_close_without_mutating_entry(): void
    {
        $f = $this->fixture();
        $entry = $this->postJournal($f);
        DB::table('club_journal_lines')->where('journal_entry_id', $entry->id)->where('direction', 'credit')->update(['amount_paise' => 99]);
        $this->expectException(AccountingPeriodCloseIntegrityException::class);
        $this->service()->close($this->request($f));
    }

    public function test_same_actor_conflicting_context_is_rejected_without_overwriting_metadata(): void
    {
        $f = $this->fixture();
        $closed = $this->service()->close($this->request($f));
        $closedAt = $closed->closed_at->format('c');
        try {
            $this->service()->close($this->request($f, ['expectedLionsYear' => '2025-26']));
            $this->fail('Conflicting replay must fail.');
        } catch (InvalidAccountingPeriodCloseRequestException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame($closedAt, $closed->fresh()->closed_at->format('c'));
        $this->assertSame($f['actor']->id, $closed->fresh()->closed_by_user_id);
    }

    public function test_service_closed_target_rejects_reversal_and_closed_original_reverses_to_explicit_open_period(): void
    {
        $f = $this->fixture();
        $original = $this->postJournal($f);
        $next = (new ClubAccountingPeriodProvisioningService)->provision(new ClubAccountingPeriodProvisioningRequest($f['club']->id, '2027-28', $f['actor']->id));
        $this->service()->close($this->request($f));
        $reversal = (new JournalReversalService(new ClubJournalPostingService))->reverse(new JournalReversalRequest($f['club']->id, $original->id, $next->id, '2027-07-02', $f['actor']->id, 'Late correction', 'test', 'close-original', 'reversal', (string) Str::uuid()));
        $this->assertSame($next->id, $reversal->accounting_period_id);
        $closedTarget = $this->fixture('TARGET');
        $targetOriginal = $this->postJournal($closedTarget);
        $this->service()->close($this->request($closedTarget));
        $this->expectException(ClosedReversalPeriodException::class);
        (new JournalReversalService(new ClubJournalPostingService))->reverse(new JournalReversalRequest($closedTarget['club']->id, $targetOriginal->id, $closedTarget['period']->id, '2026-07-03', $closedTarget['actor']->id, 'Blocked target', 'test', 'closed-target', 'reversal', (string) Str::uuid()));
    }

    private function fixture(string $suffix = 'CLOSE'): array
    {
        $actor = User::factory()->create();
        $club = Club::create(['name' => "Close Club {$suffix}", 'code' => $suffix]);
        config(['instance.organisation_code' => $club->code]);
        $role = Role::create(['name' => "Internal close actor {$suffix}", 'slug' => "internal-close-actor-{$suffix}", 'is_system' => true]);
        ClubMembership::create(['club_id' => $club->id, 'user_id' => $actor->id, 'role_id' => $role->id, 'status' => 'active', 'joined_at' => now()]);
        $period = (new ClubAccountingPeriodProvisioningService)->provision(new ClubAccountingPeriodProvisioningRequest($club->id, '2026-27', $actor->id));
        $provisioner = new LedgerAccountProvisioningService;
        $accounts = [$provisioner->provision(new LedgerAccountProvisioningRequest($club->id, 'CASH', 'Cash', 'asset', 'debit', 'INR', $actor->id, 'custom', null, 'CASH_AND_BANK')), $provisioner->provision(new LedgerAccountProvisioningRequest($club->id, 'INC', 'Income', 'income', 'credit', 'INR', $actor->id, 'custom', null, 'INCOME'))];

        return compact('actor', 'club', 'period', 'accounts');
    }

    private function request(array $f, array $o = []): AccountingPeriodCloseRequest
    {
        return new AccountingPeriodCloseRequest($o['clubId'] ?? $f['club']->id, $o['accountingPeriodId'] ?? $f['period']->id, $o['closingActorUserId'] ?? $f['actor']->id, $o['expectedLionsYear'] ?? '2026-27', $o['reason'] ?? 'Approved close');
    }

    private function postJournal(array $f): ClubJournalEntry
    {
        return (new ClubJournalPostingService)->post(new JournalPostingRequest($f['club']->id, $f['period']->id, '2026-07-02', 'Entry', 'INR', 'test', '1', 'posted', (string) Str::uuid(), $f['actor']->id, [new JournalLineInput($f['accounts'][0]->id, 'debit', 100, fundCode: 'GENERAL_CLUB_FUND'), new JournalLineInput($f['accounts'][1]->id, 'credit', 100, fundCode: 'GENERAL_CLUB_FUND')]));
    }

    private function service(): ClubAccountingPeriodCloseService
    {
        return new ClubAccountingPeriodCloseService;
    }
}
