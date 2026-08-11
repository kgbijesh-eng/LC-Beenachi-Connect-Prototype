<?php

namespace Tests\Feature;

use App\Models\AccountingChargeCategory;
use App\Models\Club;
use App\Models\ClubAccountingPeriod;
use App\Models\LedgerAccount;
use App\Models\User;
use App\Services\Finance\ApprovedLedgerAccountTemplateCatalog;
use App\Services\Finance\ClubJournalPostingService;
use App\Services\Finance\Exceptions\InvalidJournalRequestException;
use App\Services\Finance\Exceptions\UnbalancedJournalException;
use App\Services\Finance\FundCode;
use App\Services\Finance\JournalLineInput;
use App\Services\Finance\JournalPostingRequest;
use App\Services\Finance\JournalReversalRequest;
use App\Services\Finance\JournalReversalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class Milestone3iizkACanonicalAccountingMetadataFundAttributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_installs_exact_approved_and_disabled_categories(): void
    {
        $this->assertSame([
            'event_contribution' => [null, false],
            'fellowship_member_activity_contribution' => ['FELLOWSHIP_MEMBER_ACTIVITY_FUND', true],
            'general_club_event_contribution' => ['GENERAL_CLUB_FUND', true],
            'installation_contribution' => ['FELLOWSHIP_MEMBER_ACTIVITY_FUND', true],
            'membership_dues' => ['GENERAL_CLUB_FUND', true],
            'other_contribution' => [null, false],
            'service_project_contribution' => ['SERVICE_ACTIVITY_FUND', true],
        ], AccountingChargeCategory::query()->orderBy('code')->get()->mapWithKeys(fn ($row) => [$row->code => [$row->fund_code, $row->is_accounting_enabled]])->all());
    }

    public function test_every_template_has_canonical_classification_normal_balance_and_fund_rule(): void
    {
        foreach (app(ApprovedLedgerAccountTemplateCatalog::class)->definitions() as $template) {
            $this->assertNotSame('', $template->statementClassification);
            $this->assertContains($template->normalBalance, ['debit', 'credit']);
            $this->assertNotSame('', $template->fundUsageRule);
        }
    }

    public function test_schema_contains_frozen_fund_origin_and_relational_trace_fields(): void
    {
        foreach ([
            'ledger_accounts' => ['origin_kind', 'origin_template_code', 'statement_classification'],
            'club_charges' => ['fund_code'], 'payment_allocations' => ['fund_code'],
            'club_accounting_cutover_schedule_rows' => ['fund_code'],
            'club_journal_lines' => ['fund_code', 'opening_schedule_row_id', 'reversal_of_line_id'],
            'payment_allocation_journal_lines' => ['payment_allocation_id', 'journal_line_id', 'line_role', 'amount_paise'],
        ] as $table => $columns) {
            foreach ($columns as $column) {
                $this->assertTrue(Schema::hasColumn($table, $column), "Missing {$table}.{$column}");
            }
        }
    }

    public function test_posting_requires_approved_funds_and_independent_fund_balance(): void
    {
        $f = $this->fixture();
        $this->expectException(InvalidJournalRequestException::class);
        $this->postJournal($f, [new JournalLineInput($f['cash']->id, 'debit', 100), new JournalLineInput($f['income']->id, 'credit', 100)]);
    }

    public function test_cross_fund_balancing_is_rejected_and_reversal_preserves_funds(): void
    {
        $f = $this->fixture();
        try {
            $this->postJournal($f, [
                new JournalLineInput($f['cash']->id, 'debit', 100, fundCode: FundCode::GENERAL_CLUB_FUND->value),
                new JournalLineInput($f['income']->id, 'credit', 100, fundCode: FundCode::SERVICE_ACTIVITY_FUND->value),
            ]);
            $this->fail('Cross-fund balancing must fail.');
        } catch (UnbalancedJournalException) {
            $this->assertDatabaseCount('club_journal_entries', 0);
        }
        $original = $this->postJournal($f, [
            new JournalLineInput($f['cash']->id, 'debit', 60, fundCode: FundCode::GENERAL_CLUB_FUND->value),
            new JournalLineInput($f['income']->id, 'credit', 60, fundCode: FundCode::GENERAL_CLUB_FUND->value),
            new JournalLineInput($f['cash']->id, 'debit', 40, fundCode: FundCode::SERVICE_ACTIVITY_FUND->value),
            new JournalLineInput($f['income']->id, 'credit', 40, fundCode: FundCode::SERVICE_ACTIVITY_FUND->value),
        ]);
        $reversal = app(JournalReversalService::class)->reverse(new JournalReversalRequest($f['club']->id, $original->id, $f['period']->id, '2026-07-03', $f['user']->id, 'Approved correction', 'test', 'funded', 'reversed', (string) Str::uuid()));
        $this->assertSame($original->lines->pluck('fund_code')->all(), $reversal->lines->pluck('fund_code')->all());
        $this->assertSame($original->lines->pluck('id')->all(), $reversal->lines->pluck('reversal_of_line_id')->all());
    }

    private function fixture(): array
    {
        $club = Club::create(['name' => 'Fund Test', 'code' => 'FUND']);
        $user = User::factory()->create();
        $period = ClubAccountingPeriod::create(['club_id' => $club->id, 'lions_year' => '2026-27', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'status' => 'open', 'created_by_user_id' => $user->id]);
        $cash = LedgerAccount::create(['club_id' => $club->id, 'code' => 'CASH', 'name' => 'Cash', 'account_type' => 'asset', 'normal_balance' => 'debit', 'currency' => 'INR', 'is_active' => true, 'created_by_user_id' => $user->id]);
        $income = LedgerAccount::create(['club_id' => $club->id, 'code' => 'INCOME', 'name' => 'Income', 'account_type' => 'income', 'normal_balance' => 'credit', 'currency' => 'INR', 'is_active' => true, 'created_by_user_id' => $user->id]);

        return compact('club', 'user', 'period', 'cash', 'income');
    }

    private function postJournal(array $f, array $lines)
    {
        return app(ClubJournalPostingService::class)->post(new JournalPostingRequest($f['club']->id, $f['period']->id, '2026-07-02', 'Fund test', 'INR', 'test', (string) Str::uuid(), 'posted', (string) Str::uuid(), $f['user']->id, $lines));
    }
}
