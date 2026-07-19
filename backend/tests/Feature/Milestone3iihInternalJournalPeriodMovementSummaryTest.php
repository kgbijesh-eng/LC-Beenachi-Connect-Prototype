<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubAccountingPeriod;
use App\Models\ClubJournalEntry;
use App\Models\ClubJournalLine;
use App\Models\LedgerAccount;
use App\Models\User;
use App\Services\Finance\ClubJournalPeriodMovementService;
use App\Services\Finance\Exceptions\InvalidJournalPeriodMovementQueryException;
use App\Services\Finance\Exceptions\JournalPeriodMovementIntegrityException;
use App\Services\Finance\Exceptions\JournalPeriodMovementScopeException;
use App\Services\Finance\JournalPeriodMovementQuery;
use App\Services\Finance\JournalPeriodMovementResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ReflectionClass;
use Tests\TestCase;

class Milestone3iihInternalJournalPeriodMovementSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_open_period_returns_truthful_zero_movement_and_no_affected_accounts(): void
    {
        $fixture = $this->fixture();
        $result = $this->summarize($fixture);

        $this->assertSame($fixture['club']->id, $result->clubId);
        $this->assertSame($fixture['period']->id, $result->accountingPeriodId);
        $this->assertSame('2026-27', $result->lionsYear);
        $this->assertSame('2026-07-01', $result->periodStartDate);
        $this->assertSame('2027-06-30', $result->periodEndDate);
        $this->assertSame('open', $result->periodStatus);
        $this->assertSame('INR', $result->currency);
        $this->assertSame(0, $result->supportedEntryCount);
        $this->assertSame(0, $result->reversedOriginalCount);
        $this->assertSame(0, $result->postedReversalCount);
        $this->assertSame(0, $result->excludedDraftCount);
        $this->assertSame(0, $result->totalDebitPaise);
        $this->assertSame(0, $result->totalCreditPaise);
        $this->assertSame(0, $result->differencePaise);
        $this->assertSame([], $result->affectedAccounts);
    }

    public function test_multiple_journals_aggregate_integer_paise_formulas_line_counts_and_code_order(): void
    {
        $fixture = $this->fixture();
        $bank = $this->account($fixture, 'BANK', 'asset', 'debit');
        $expense = $this->account($fixture, 'ADMIN', 'expense', 'debit');
        $this->entry($fixture, 1, [[$fixture['cash'], 'debit', 100], [$fixture['income'], 'credit', 100]]);
        $this->entry($fixture, 2, [[$expense, 'debit', 40], [$fixture['cash'], 'credit', 40]]);
        $this->entry($fixture, 3, [[$bank, 'debit', 25], [$fixture['income'], 'credit', 25]]);

        $first = $this->summarize($fixture);
        $repeated = $this->summarize($fixture);

        $this->assertSame(165, $first->totalDebitPaise);
        $this->assertSame(165, $first->totalCreditPaise);
        $this->assertSame(0, $first->differencePaise);
        $this->assertSame(3, $first->supportedEntryCount);
        $this->assertSame(['ADMIN', 'BANK', 'CASH', 'INCOME'], array_column($first->affectedAccounts, 'accountCode'));
        $this->assertSame(
            array_column($first->affectedAccounts, 'accountId'),
            array_column($repeated->affectedAccounts, 'accountId'),
        );
        $cash = collect($first->affectedAccounts)->firstWhere('accountCode', 'CASH');
        $income = collect($first->affectedAccounts)->firstWhere('accountCode', 'INCOME');
        $this->assertSame(100, $cash->debitMovementPaise);
        $this->assertSame(40, $cash->creditMovementPaise);
        $this->assertSame(60, $cash->signedPeriodMovementPaise);
        $this->assertSame(2, $cash->includedLineCount);
        $this->assertSame(125, $income->creditMovementPaise);
        $this->assertSame(125, $income->signedPeriodMovementPaise);
        $this->assertSame(2, $income->includedLineCount);
    }

    public function test_drafts_are_counted_but_excluded_and_unused_accounts_are_absent(): void
    {
        $fixture = $this->fixture();
        $unused = $this->account($fixture, 'UNUSED', 'asset', 'debit');
        $this->entry($fixture, 1, [[$fixture['cash'], 'debit', 100], [$fixture['income'], 'credit', 100]]);
        $this->entry($fixture, 2, [[$fixture['cash'], 'debit', 999], [$fixture['income'], 'credit', 999]], ClubJournalEntry::STATUS_DRAFT);

        $result = $this->summarize($fixture);

        $this->assertSame(1, $result->supportedEntryCount);
        $this->assertSame(1, $result->excludedDraftCount);
        $this->assertSame(100, $result->totalDebitPaise);
        $this->assertNotContains($unused->id, array_column($result->affectedAccounts, 'accountId'));
    }

    public function test_reversed_original_and_posted_reversal_each_count_once_and_net_to_zero(): void
    {
        $fixture = $this->fixture();
        $original = $this->entry($fixture, 1, [[$fixture['cash'], 'debit', 500], [$fixture['income'], 'credit', 500]], ClubJournalEntry::STATUS_REVERSED);
        $this->entry($fixture, 2, [[$fixture['cash'], 'credit', 500], [$fixture['income'], 'debit', 500]], ClubJournalEntry::STATUS_POSTED, $original->id);

        $result = $this->summarize($fixture);

        $this->assertSame(2, $result->supportedEntryCount);
        $this->assertSame(1, $result->reversedOriginalCount);
        $this->assertSame(1, $result->postedReversalCount);
        $this->assertSame(1000, $result->totalDebitPaise);
        $this->assertSame(1000, $result->totalCreditPaise);
        foreach ($result->affectedAccounts as $account) {
            $this->assertSame(0, $account->signedPeriodMovementPaise);
            $this->assertSame(2, $account->includedLineCount);
        }
    }

    public function test_inactive_historical_account_and_closed_period_are_read_without_mutation(): void
    {
        $fixture = $this->fixture();
        $fixture['cash']->update(['is_active' => false]);
        $this->entry($fixture, 1, [[$fixture['cash'], 'debit', 100], [$fixture['income'], 'credit', 100]]);
        DB::table('club_accounting_periods')->where('id', $fixture['period']->id)->update([
            'status' => 'closed', 'closed_at' => '2027-07-01 10:00:00', 'closed_by_user_id' => $fixture['actor']->id,
        ]);
        $before = $this->rawState();

        $result = $this->summarize($fixture);

        $this->assertSame('closed', $result->periodStatus);
        $this->assertFalse(collect($result->affectedAccounts)->firstWhere('accountCode', 'CASH')->isActive);
        $this->assertSame($before, $this->rawState());
    }

    public function test_missing_wrong_scope_and_noncanonical_periods_fail_closed(): void
    {
        $fixture = $this->fixture();
        $foreign = $this->fixture('FOREIGN');
        foreach ([
            [InvalidJournalPeriodMovementQueryException::class, new JournalPeriodMovementQuery(999999, $fixture['period']->id)],
            [InvalidJournalPeriodMovementQueryException::class, new JournalPeriodMovementQuery($fixture['club']->id, 999999)],
            [JournalPeriodMovementScopeException::class, new JournalPeriodMovementQuery($fixture['club']->id, $foreign['period']->id)],
        ] as [$exception, $query]) {
            try {
                $this->service()->summarize($query);
                $this->fail('Invalid period movement identity must fail.');
            } catch (\Throwable $actual) {
                $this->assertInstanceOf($exception, $actual);
            }
        }

        DB::statement('DROP TRIGGER accounting_period_lions_year_check_update');
        DB::table('club_accounting_periods')->where('id', $fixture['period']->id)->update(['start_date' => '2026-04-01']);
        $this->expectException(InvalidJournalPeriodMovementQueryException::class);
        $this->summarize($fixture);
    }

    public function test_unrelated_club_activity_is_excluded(): void
    {
        $fixture = $this->fixture();
        $foreign = $this->fixture('FOREIGN');
        $this->entry($fixture, 1, [[$fixture['cash'], 'debit', 100], [$fixture['income'], 'credit', 100]]);
        $this->entry($foreign, 1, [[$foreign['cash'], 'debit', 900], [$foreign['income'], 'credit', 900]]);

        $result = $this->summarize($fixture);

        $this->assertSame(100, $result->totalDebitPaise);
        $this->assertSame([$fixture['cash']->id, $fixture['income']->id], array_column($result->affectedAccounts, 'accountId'));
    }

    public function test_unbalanced_entry_and_malformed_lifecycle_fail_without_partial_result(): void
    {
        $fixture = $this->fixture();
        $entry = $this->entry($fixture, 1, [[$fixture['cash'], 'debit', 100], [$fixture['income'], 'credit', 100]]);
        DB::statement('DROP TRIGGER journal_line_amount_check_update');
        DB::table('club_journal_lines')->where('journal_entry_id', $entry->id)->where('direction', 'credit')->update(['amount_paise' => 90]);
        $before = $this->rawState();
        try {
            $this->summarize($fixture);
            $this->fail('An unbalanced entry must fail.');
        } catch (JournalPeriodMovementIntegrityException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame($before, $this->rawState());

        DB::statement('DROP TRIGGER journal_entry_status_check_update');
        DB::statement('DROP TRIGGER journal_entry_lifecycle_check_update');
        DB::table('club_journal_entries')->where('id', $entry->id)->update(['status' => 'unknown']);
        $this->expectException(JournalPeriodMovementIntegrityException::class);
        $this->summarize($fixture);
    }

    public function test_reversal_of_reversal_duplicate_and_inconsistent_original_fail_closed(): void
    {
        foreach (['nested', 'duplicate', 'orphan-original'] as $case) {
            $fixture = $this->fixture(strtoupper($case));
            $original = $this->entry($fixture, 1, [[$fixture['cash'], 'debit', 100], [$fixture['income'], 'credit', 100]], ClubJournalEntry::STATUS_REVERSED);
            if ($case === 'nested') {
                $reversal = $this->entry($fixture, 2, [[$fixture['cash'], 'credit', 100], [$fixture['income'], 'debit', 100]], reversalOf: $original->id);
                $this->entry($fixture, 3, [[$fixture['cash'], 'debit', 100], [$fixture['income'], 'credit', 100]], reversalOf: $reversal->id);
            } elseif ($case === 'duplicate') {
                $this->entry($fixture, 2, [[$fixture['cash'], 'credit', 100], [$fixture['income'], 'debit', 100]], reversalOf: $original->id);
                $this->entry($fixture, 3, [[$fixture['cash'], 'credit', 100], [$fixture['income'], 'debit', 100]], reversalOf: $original->id);
            }

            try {
                $this->summarize($fixture);
                $this->fail("Malformed reversal case {$case} must fail.");
            } catch (JournalPeriodMovementIntegrityException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_result_contract_has_no_balance_reconciliation_completeness_or_certification_claims_and_query_is_read_only(): void
    {
        $fixture = $this->fixture();
        $this->entry($fixture, 1, [[$fixture['cash'], 'debit', 100], [$fixture['income'], 'credit', 100]]);
        $before = $this->rawState();
        $result = $this->summarize($fixture);
        $this->assertSame($before, $this->rawState());

        $properties = array_map(fn ($property) => strtolower($property->getName()), (new ReflectionClass(JournalPeriodMovementResult::class))->getProperties());
        foreach (['opening', 'running', 'closing', 'currentbalance', 'available', 'reconciled', 'completeness', 'cutover', 'profit', 'loss', 'fundbalance', 'cashposition', 'bankposition', 'certification'] as $term) {
            $this->assertFalse(collect($properties)->contains(fn (string $name) => str_contains($name, $term)));
        }
        $this->assertSame([], collect(app('router')->getRoutes())->map(fn ($route) => $route->uri())->filter(fn (string $uri) => str_contains($uri, 'period-movement'))->values()->all());
    }

    private function service(): ClubJournalPeriodMovementService
    {
        return app(ClubJournalPeriodMovementService::class);
    }

    private function summarize(array $fixture): JournalPeriodMovementResult
    {
        return $this->service()->summarize(new JournalPeriodMovementQuery($fixture['club']->id, $fixture['period']->id));
    }

    private function fixture(string $suffix = 'SUMMARY'): array
    {
        $club = Club::create(['name' => "Summary {$suffix}", 'code' => substr($suffix, 0, 20)]);
        $actor = User::factory()->create();
        $period = ClubAccountingPeriod::create(['club_id' => $club->id, 'lions_year' => '2026-27', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'status' => 'open', 'created_by_user_id' => $actor->id]);
        $fixture = compact('club', 'actor', 'period');
        $fixture['cash'] = $this->account($fixture, 'CASH', 'asset', 'debit');
        $fixture['income'] = $this->account($fixture, 'INCOME', 'income', 'credit');

        return $fixture;
    }

    private function account(array $fixture, string $code, string $type, string $normal): LedgerAccount
    {
        return LedgerAccount::create(['club_id' => $fixture['club']->id, 'code' => $code, 'name' => $code, 'account_type' => $type, 'normal_balance' => $normal, 'currency' => 'INR', 'is_active' => true, 'created_by_user_id' => $fixture['actor']->id]);
    }

    private function entry(array $fixture, int $sequence, array $lines, string $status = ClubJournalEntry::STATUS_POSTED, ?int $reversalOf = null): ClubJournalEntry
    {
        $entry = ClubJournalEntry::create(['club_id' => $fixture['club']->id, 'accounting_period_id' => $fixture['period']->id, 'sequence_number' => $sequence, 'reference' => sprintf('%s/2026-27/JRN/%06d', $fixture['club']->code, $sequence), 'effective_date' => '2026-07-15', 'posting_date' => '2026-07-15', 'status' => 'draft', 'description' => "Entry {$sequence}", 'currency' => 'INR', 'source_type' => 'fixture', 'source_id' => (string) Str::uuid(), 'source_event' => 'posted', 'idempotency_key' => (string) Str::uuid(), 'reversal_of_entry_id' => $reversalOf, 'created_by_user_id' => $fixture['actor']->id]);
        foreach ($lines as $index => [$account, $direction, $amount]) {
            ClubJournalLine::create(['club_id' => $fixture['club']->id, 'journal_entry_id' => $entry->id, 'ledger_account_id' => $account->id, 'line_number' => $index + 1, 'direction' => $direction, 'amount_paise' => $amount]);
        }
        if ($status !== 'draft') {
            DB::table('club_journal_entries')->where('id', $entry->id)->update(['status' => $status, 'posted_at' => '2026-07-15 10:00:00', 'posted_by_user_id' => $fixture['actor']->id]);
        }

        return $entry->fresh();
    }

    private function rawState(): array
    {
        return collect(['clubs', 'club_memberships', 'ledger_accounts', 'club_accounting_periods', 'club_journal_entries', 'club_journal_lines', 'audit_logs'])->mapWithKeys(fn (string $table) => [$table => DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all()])->all();
    }
}
