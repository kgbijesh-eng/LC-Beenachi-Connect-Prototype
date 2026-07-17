<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubAccountingPeriod;
use App\Models\ClubJournalEntry;
use App\Models\User;
use App\Services\Finance\AccountingPeriodCloseRequest;
use App\Services\Finance\ClubAccountingPeriodCloseService;
use App\Services\Finance\ClubAccountingPeriodProvisioningRequest;
use App\Services\Finance\ClubAccountingPeriodProvisioningService;
use App\Services\Finance\ClubJournalPostingService;
use App\Services\Finance\Exceptions\AccountingPeriodCloseConflictException;
use App\Services\Finance\Exceptions\AccountingPeriodCloseIntegrityException;
use App\Services\Finance\JournalLineInput;
use App\Services\Finance\JournalPostingRequest;
use App\Services\Finance\LedgerAccountProvisioningRequest;
use App\Services\Finance\LedgerAccountProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Milestone3iifInternalAccountingPeriodCloseServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_or_balanced_period_closes_without_creating_or_mutating_journals(): void
    {
        $f = $this->fixture();
        $original = $this->postJournal($f);
        $lines = $original->lines()->get()->map->only(['id', 'direction', 'amount_paise'])->all();
        $closed = $this->service()->close($this->request($f));
        $this->assertSame(ClubAccountingPeriod::STATUS_CLOSED, $closed->status);
        $this->assertSame($f['actor']->id, $closed->closed_by_user_id);
        $this->assertNotNull($closed->closed_at);
        $this->assertCount(1, ClubJournalEntry::all());
        $this->assertSame($lines, $original->fresh()->lines()->get()->map->only(['id', 'direction', 'amount_paise'])->all());
        $this->assertSame('finance.accounting_period.closed', AuditLog::firstOrFail()->action);
    }

    public function test_replay_is_actor_bound_and_closed_period_rejects_posting(): void
    {
        $f = $this->fixture();
        $first = $this->service()->close($this->request($f));
        $this->assertSame($first->id, $this->service()->close($this->request($f, ['reason' => 'different but not durable']))->id);
        $other = User::factory()->create();
        $this->expectException(AccountingPeriodCloseConflictException::class);
        $this->service()->close($this->request($f, ['closingActorUserId' => $other->id]));
    }

    public function test_draft_and_unbalanced_entries_block_close(): void
    {
        $f = $this->fixture();
        ClubJournalEntry::create(['club_id' => $f['club']->id, 'accounting_period_id' => $f['period']->id, 'sequence_number' => 1, 'reference' => 'C/2026-27/JRN/000001', 'effective_date' => '2026-07-01', 'posting_date' => '2026-07-01', 'status' => 'draft', 'description' => 'Draft', 'currency' => 'INR', 'created_by_user_id' => $f['actor']->id]);
        $this->expectException(AccountingPeriodCloseIntegrityException::class);
        $this->service()->close($this->request($f));
    }

    private function fixture(): array
    {
        $actor = User::factory()->create();
        $club = Club::create(['name' => 'Close Club', 'code' => 'CLOSE']);
        $period = (new ClubAccountingPeriodProvisioningService)->provision(new ClubAccountingPeriodProvisioningRequest($club->id, '2026-27', $actor->id));
        $provisioner = new LedgerAccountProvisioningService;
        $accounts = [$provisioner->provision(new LedgerAccountProvisioningRequest($club->id, 'CASH', 'Cash', 'asset', 'debit', 'INR', $actor->id)), $provisioner->provision(new LedgerAccountProvisioningRequest($club->id, 'INC', 'Income', 'income', 'credit', 'INR', $actor->id))];

        return compact('actor', 'club', 'period', 'accounts');
    }

    private function request(array $f, array $o = []): AccountingPeriodCloseRequest
    {
        return new AccountingPeriodCloseRequest($o['clubId'] ?? $f['club']->id, $o['accountingPeriodId'] ?? $f['period']->id, $o['closingActorUserId'] ?? $f['actor']->id, $o['expectedLionsYear'] ?? '2026-27', $o['reason'] ?? 'Approved close');
    }

    private function postJournal(array $f): ClubJournalEntry
    {
        return (new ClubJournalPostingService)->post(new JournalPostingRequest($f['club']->id, $f['period']->id, '2026-07-02', 'Entry', 'INR', 'test', '1', 'posted', (string) Str::uuid(), $f['actor']->id, [new JournalLineInput($f['accounts'][0]->id, 'debit', 100), new JournalLineInput($f['accounts'][1]->id, 'credit', 100)]));
    }

    private function service(): ClubAccountingPeriodCloseService
    {
        return new ClubAccountingPeriodCloseService;
    }
}
