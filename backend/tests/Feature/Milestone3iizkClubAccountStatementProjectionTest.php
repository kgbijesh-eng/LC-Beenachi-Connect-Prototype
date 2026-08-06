<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubAccountingCutoverPackage;
use App\Models\ClubAccountingCutoverScheduleRow;
use App\Models\ClubAccountingPeriod;
use App\Models\ClubJournalEntry;
use App\Models\ClubMembership;
use App\Models\LedgerAccount;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\ClubJournalPostingService;
use App\Services\Finance\JournalLineInput;
use App\Services\Finance\JournalPostingRequest;
use App\Services\Finance\JournalReversalRequest;
use App\Services\Finance\JournalReversalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Milestone3iizkClubAccountStatementProjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_same_club_officer_receives_balanced_canonical_statement(): void
    {
        $fixture = $this->fixture();
        Sanctum::actingAs($fixture['officer']);

        $response = $this->getJson($this->endpoint($fixture));

        $response->assertOk()
            ->assertJsonPath('data.contract_version', 'CLUB-ACCOUNT-STATEMENT-2026-01')
            ->assertJsonPath('data.currency', 'INR')
            ->assertJsonPath('data.amount_unit', 'paise')
            ->assertJsonPath('data.funds.0.fund_code', 'GENERAL_CLUB_FUND')
            ->assertJsonPath('data.funds.1.fund_code', 'SERVICE_ACTIVITY_FUND')
            ->assertJsonPath('data.funds.2.fund_code', 'FELLOWSHIP_MEMBER_ACTIVITY_FUND')
            ->assertJsonPath('data.overall_trial_balance.opening_debits_paise', 1000)
            ->assertJsonPath('data.overall_trial_balance.opening_credits_paise', 1000)
            ->assertJsonPath('data.overall_trial_balance.period_debits_paise', 200)
            ->assertJsonPath('data.overall_trial_balance.period_credits_paise', 200)
            ->assertJsonPath('data.integrity.fund_balances_reconcile', true);

        $cash = collect($response->json('data.funds.0.classifications.0.accounts'))->firstWhere('account_code', 'CASH-ON-HAND');
        $this->assertSame(1000, $cash['opening_balance_paise']);
        $this->assertSame(200, $cash['period_debit_paise']);
        $this->assertSame(1200, $cash['closing_balance_paise']);
        $this->assertCount(2, $response->json('data.traceability.journals'));
        $this->assertCount(8, $response->json('data.funds.0.classifications'));
        $this->assertNotNull($response->json('data.traceability.journals.0.lines.0.opening_schedule_row_id'));
    }

    public function test_statement_permission_is_required_and_club_scoped(): void
    {
        $fixture = $this->fixture();
        $memberRole = Role::create(['name' => 'Member', 'slug' => 'member', 'description' => 'Member']);
        $member = User::factory()->create();
        ClubMembership::create(['club_id' => $fixture['club']->id, 'user_id' => $member->id, 'role_id' => $memberRole->id, 'status' => 'active']);

        Sanctum::actingAs($member);
        $this->getJson($this->endpoint($fixture))->assertForbidden();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson($this->endpoint($fixture))->assertForbidden();
    }

    public function test_unauthenticated_statement_request_is_denied(): void
    {
        $fixture = $this->fixture();

        $this->getJson($this->endpoint($fixture))->assertUnauthorized();
    }

    public function test_filter_contract_rejects_invalid_combinations_and_ranges(): void
    {
        $fixture = $this->fixture();
        Sanctum::actingAs($fixture['officer']);
        $base = "/api/v1/clubs/{$fixture['club']->id}/finance/account-statement";

        $this->getJson($base)->assertUnprocessable();
        $this->getJson($base.'?lions_year=2027-28&from=2027-07-01')->assertUnprocessable();
        $this->getJson($base.'?lions_year=2027-28&period_id='.$fixture['period']->id.'&from=2027-07-01&to=2027-07-02')->assertUnprocessable();
        $this->getJson($base.'?lions_year=2027-28&from=2027-06-30&to=2027-07-02')->assertUnprocessable();
        $this->getJson($base.'?lions_year=2027-28&unknown=1')->assertUnprocessable();
    }

    public function test_custom_range_is_inclusive_and_opening_is_not_double_counted(): void
    {
        $fixture = $this->fixture();
        Sanctum::actingAs($fixture['officer']);

        $response = $this->getJson($this->endpoint($fixture).'&from=2027-07-01&to=2027-07-15')->assertOk();

        $this->assertSame(2, $response->json('data.source.journal_count'));
        $this->assertSame('opening_balance', $response->json('data.traceability.journals.0.contribution'));
        $this->assertSame('period_movement', $response->json('data.traceability.journals.1.contribution'));
    }

    public function test_valid_period_filter_uses_owned_period_and_closed_state(): void
    {
        $fixture = $this->fixture();
        Sanctum::actingAs($fixture['officer']);

        $response = $this->getJson($this->endpoint($fixture).'&period_id='.$fixture['period']->id)->assertOk();

        $this->assertSame($fixture['period']->id, $response->json('data.range.period_id'));
        $this->assertSame('2027-07-01', $response->json('data.range.from'));
        $this->assertSame('2028-06-30', $response->json('data.range.to'));
        $this->assertFalse($response->json('data.range.is_closed'));
    }

    public function test_inactive_appointment_and_cross_club_period_are_denied(): void
    {
        $fixture = $this->fixture();
        ClubMembership::query()->where('club_id', $fixture['club']->id)->where('user_id', $fixture['officer']->id)->update(['status' => 'inactive']);
        Sanctum::actingAs($fixture['officer']);
        $this->getJson($this->endpoint($fixture))->assertForbidden();

        ClubMembership::query()->where('club_id', $fixture['club']->id)->where('user_id', $fixture['officer']->id)->update(['status' => 'active']);
        $otherClub = Club::create([
            'name' => 'Other Period Club', 'code' => 'OTHER-PERIOD',
            'timezone' => 'Asia/Kolkata', 'is_active' => true,
        ]);
        $otherPeriod = ClubAccountingPeriod::create([
            'club_id' => $otherClub->id, 'lions_year' => '2027-28', 'start_date' => '2027-07-01',
            'end_date' => '2028-06-30', 'status' => 'open', 'created_by_user_id' => $fixture['officer']->id,
        ]);
        $this->getJson($this->endpoint($fixture).'&period_id='.$otherPeriod->id)->assertUnprocessable();
    }

    public function test_opposite_direction_balance_is_returned_as_negative_natural_balance(): void
    {
        $fixture = $this->fixture();
        $expense = $this->account($fixture['club'], $fixture['officer'], 'EXPENSE-TEST', 'expense', 'debit', 'EXPENSES');
        $this->postJournal($fixture['club'], $fixture['period'], $fixture['officer'], '2027-07-20', 'expense', [
            new JournalLineInput($expense->id, 'debit', 1500, fundCode: 'GENERAL_CLUB_FUND'),
            new JournalLineInput($fixture['cash']->id, 'credit', 1500, fundCode: 'GENERAL_CLUB_FUND'),
        ]);
        Sanctum::actingAs($fixture['officer']);

        $response = $this->getJson($this->endpoint($fixture))->assertOk();
        $cash = collect($response->json('data.funds.0.classifications.0.accounts'))->firstWhere('account_code', 'CASH-ON-HAND');

        $this->assertSame(-300, $cash['closing_balance_paise']);
        $this->assertSame(1500, $response->json('data.overall_trial_balance.closing_debits_paise'));
        $this->assertSame(1500, $response->json('data.overall_trial_balance.closing_credits_paise'));
    }

    public function test_shared_cash_account_is_projected_separately_by_authoritative_fund(): void
    {
        $fixture = $this->fixture();
        $serviceIncome = $this->account($fixture['club'], $fixture['officer'], 'INCOME-SERVICE', 'income', 'credit', 'INCOME');
        $this->postJournal($fixture['club'], $fixture['period'], $fixture['officer'], '2027-07-20', 'service-income', [
            new JournalLineInput($fixture['cash']->id, 'debit', 300, fundCode: 'SERVICE_ACTIVITY_FUND'),
            new JournalLineInput($serviceIncome->id, 'credit', 300, fundCode: 'SERVICE_ACTIVITY_FUND'),
        ]);
        Sanctum::actingAs($fixture['officer']);

        $response = $this->getJson($this->endpoint($fixture))->assertOk();
        $generalCash = collect($response->json('data.funds.0.classifications.0.accounts'))->firstWhere('account_code', 'CASH-ON-HAND');
        $serviceCash = collect($response->json('data.funds.1.classifications.0.accounts'))->firstWhere('account_code', 'CASH-ON-HAND');

        $this->assertSame(1200, $generalCash['closing_balance_paise']);
        $this->assertSame(300, $serviceCash['closing_balance_paise']);
        $this->assertSame(300, $response->json('data.funds.1.trial_balance.period_debits_paise'));
        $this->assertSame(300, $response->json('data.funds.1.trial_balance.period_credits_paise'));
    }

    public function test_draft_and_later_journals_do_not_affect_selected_range(): void
    {
        $fixture = $this->fixture();
        DB::table('club_journal_entries')->insert([
            'public_id' => (string) Str::uuid(), 'club_id' => $fixture['club']->id,
            'accounting_period_id' => $fixture['period']->id, 'sequence_number' => 99,
            'reference' => 'DRAFT-99', 'effective_date' => '2027-07-10', 'posting_date' => '2027-07-10',
            'status' => 'draft', 'description' => 'Excluded draft', 'currency' => 'INR',
            'created_by_user_id' => $fixture['officer']->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        Sanctum::actingAs($fixture['officer']);

        $response = $this->getJson($this->endpoint($fixture).'&from=2027-07-01&to=2027-07-14')->assertOk();

        $this->assertSame(1, $response->json('data.source.journal_count'));
        $this->assertSame(0, $response->json('data.overall_trial_balance.period_debits_paise'));
    }

    public function test_missing_classification_or_fund_fails_closed(): void
    {
        $fixture = $this->fixture();
        Sanctum::actingAs($fixture['officer']);

        DB::table('ledger_accounts')->where('id', $fixture['cash']->id)->update(['statement_classification' => null]);
        $this->getJson($this->endpoint($fixture))
            ->assertConflict()->assertJsonPath('code', 'STATEMENT_INTEGRITY_FAILURE');

        DB::table('ledger_accounts')->where('id', $fixture['cash']->id)->update(['statement_classification' => 'CASH_AND_BANK']);
        DB::table('club_journal_lines')->where('ledger_account_id', $fixture['cash']->id)->update(['fund_code' => null]);
        $this->getJson($this->endpoint($fixture))
            ->assertConflict()->assertJsonPath('code', 'STATEMENT_INTEGRITY_FAILURE');
    }

    public function test_unactivated_accounting_returns_stable_conflict(): void
    {
        $fixture = $this->fixture();
        $club = Club::create([
            'name' => 'Unactivated Club', 'code' => 'UNACTIVATED',
            'timezone' => 'Asia/Kolkata', 'is_active' => true,
        ]);
        $membership = ClubMembership::query()->where('club_id', $fixture['club']->id)->where('user_id', $fixture['officer']->id)->firstOrFail();
        ClubMembership::create([
            'club_id' => $club->id, 'user_id' => $fixture['officer']->id,
            'role_id' => $membership->role_id, 'status' => 'active',
        ]);
        ClubAccountingPeriod::create([
            'club_id' => $club->id, 'lions_year' => '2027-28', 'start_date' => '2027-07-01',
            'end_date' => '2028-06-30', 'status' => 'open', 'created_by_user_id' => $fixture['officer']->id,
        ]);
        Sanctum::actingAs($fixture['officer']);

        $this->getJson("/api/v1/clubs/{$club->id}/finance/account-statement?lions_year=2027-28")
            ->assertConflict()->assertJsonPath('code', 'ACCOUNTING_NOT_ACTIVATED');
    }

    public function test_get_is_read_only_and_deterministic(): void
    {
        $fixture = $this->fixture();
        Sanctum::actingAs($fixture['officer']);
        $before = $this->tableCounts();

        $first = $this->getJson($this->endpoint($fixture))->assertOk()->json('data');
        $second = $this->getJson($this->endpoint($fixture))->assertOk()->json('data');

        $this->assertSame($first, $second);
        $this->assertSame($before, $this->tableCounts());
    }

    public function test_pre_cutover_posted_activity_is_excluded_and_zero_activity_range_is_valid(): void
    {
        $fixture = $this->fixture();
        $journalId = DB::table('club_journal_entries')->insertGetId([
            'public_id' => (string) Str::uuid(), 'club_id' => $fixture['club']->id,
            'accounting_period_id' => $fixture['period']->id, 'sequence_number' => 900,
            'reference' => 'PRE-CUTOVER', 'effective_date' => '2027-06-30', 'posting_date' => '2027-06-30',
            'posted_at' => now(), 'status' => 'posted', 'description' => 'Legacy activity', 'currency' => 'INR',
            'source_type' => 'legacy', 'source_id' => 'legacy-1', 'source_event' => 'posted',
            'idempotency_key' => (string) Str::uuid(), 'created_by_user_id' => $fixture['officer']->id,
            'posted_by_user_id' => $fixture['officer']->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([['debit', $fixture['cash']->id], ['credit', $fixture['income']->id]] as $index => [$direction, $accountId]) {
            DB::table('club_journal_lines')->insert([
                'public_id' => (string) Str::uuid(), 'club_id' => $fixture['club']->id,
                'journal_entry_id' => $journalId, 'ledger_account_id' => $accountId, 'line_number' => $index + 1,
                'direction' => $direction, 'amount_paise' => 9999, 'fund_code' => 'GENERAL_CLUB_FUND',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        Sanctum::actingAs($fixture['officer']);

        $full = $this->getJson($this->endpoint($fixture))->assertOk();
        $this->assertSame(2, $full->json('data.source.journal_count'));
        $empty = $this->getJson($this->endpoint($fixture).'&from=2027-07-16&to=2027-07-31')->assertOk();
        $this->assertSame(0, $empty->json('data.overall_trial_balance.period_debits_paise'));
        $this->assertSame(0, $empty->json('data.overall_trial_balance.period_credits_paise'));
    }

    public function test_reversal_is_applied_on_its_accounting_date_and_both_public_relationships_are_returned(): void
    {
        $fixture = $this->fixture();
        $reversal = app(JournalReversalService::class)->reverse(new JournalReversalRequest(
            $fixture['club']->id, $fixture['movement']->id, $fixture['period']->id, '2027-08-01',
            $fixture['officer']->id, 'Correction', 'statement_test_reversal', 'dues-reversal',
            'reversed', (string) Str::uuid(),
        ));
        Sanctum::actingAs($fixture['officer']);

        $before = $this->getJson($this->endpoint($fixture).'&from=2027-07-01&to=2027-07-31')->assertOk();
        $this->assertSame(200, $before->json('data.overall_trial_balance.period_debits_paise'));
        $original = collect($before->json('data.traceability.journals'))->firstWhere('journal_id', $fixture['movement']->public_id);
        $this->assertSame($reversal->public_id, $original['reversed_by_journal_id']);

        $after = $this->getJson($this->endpoint($fixture).'&from=2027-08-01&to=2027-08-31')->assertOk();
        $trace = collect($after->json('data.traceability.journals'))->firstWhere('journal_id', $reversal->public_id);
        $this->assertSame($fixture['movement']->public_id, $trace['reversal_of_journal_id']);
    }

    public function test_all_classifications_and_independently_reconciled_subtotals_are_returned(): void
    {
        $fixture = $this->fixture();
        Sanctum::actingAs($fixture['officer']);
        $response = $this->getJson($this->endpoint($fixture))->assertOk();
        $expected = ['CASH_AND_BANK', 'RECEIVABLES', 'OTHER_CURRENT_ASSETS', 'PROPERTY_AND_EQUIPMENT', 'LIABILITIES', 'FUND_BALANCE_EQUITY', 'INCOME', 'EXPENSES'];

        foreach ($response->json('data.funds') as $fund) {
            $this->assertSame($expected, array_column($fund['classifications'], 'classification'));
            foreach ($fund['classifications'] as $classification) {
                foreach (array_keys($classification['totals']) as $key) {
                    $this->assertSame(array_sum(array_column($classification['accounts'], $key)), $classification['totals'][$key]);
                }
            }
        }
        $this->assertTrue($response->json('data.integrity.classification_subtotals_reconcile'));
        $this->assertTrue($response->json('data.integrity.fund_subtotals_reconcile'));
    }

    public function test_duplicate_posted_activation_packages_fail_closed(): void
    {
        $fixture = $this->fixture();
        $duplicate = $fixture['package']->replicate();
        $duplicate->public_id = (string) Str::uuid();
        $duplicate->package_version = 2;
        $duplicate->opening_journal_entry_id = $fixture['movement']->id;
        DB::table('club_accounting_cutover_packages')->insert($duplicate->getAttributes());
        Sanctum::actingAs($fixture['officer']);

        $this->getJson($this->endpoint($fixture))
            ->assertConflict()->assertJsonPath('code', 'STATEMENT_INTEGRITY_FAILURE');
    }

    public function test_payment_trace_uses_public_identifiers_and_corruption_fails_closed(): void
    {
        $fixture = $this->fixture();
        $payment = $this->paymentTrace($fixture, false);
        Sanctum::actingAs($fixture['officer']);

        $response = $this->getJson($this->endpoint($fixture))->assertOk();
        $journal = collect($response->json('data.traceability.journals'))->firstWhere('journal_id', $payment['journal']->public_id);
        $trace = collect($journal['lines'])->flatMap(fn (array $line): array => $line['payment_allocations'])->first();
        $this->assertTrue(Str::isUuid($trace['allocation_id']));
        $this->assertSame($payment['payment_public_id'], $trace['payment_id']);
        $this->assertNotSame((string) $payment['allocation_id'], $trace['allocation_id']);

        DB::table('payment_allocation_journal_lines')
            ->where('payment_allocation_id', $payment['allocation_id'])
            ->where('line_role', 'asset_debit')
            ->update(['amount_paise' => 299]);
        $this->getJson($this->endpoint($fixture))
            ->assertConflict()->assertJsonPath('code', 'STATEMENT_INTEGRITY_FAILURE');
    }

    public function test_closed_period_projection_is_reproducible(): void
    {
        $fixture = $this->fixture();
        DB::table('club_accounting_periods')->where('id', $fixture['period']->id)->update([
            'status' => 'closed', 'closed_at' => now(), 'closed_by_user_id' => $fixture['officer']->id,
        ]);
        Sanctum::actingAs($fixture['officer']);
        $url = $this->endpoint($fixture).'&from=2027-07-01&to=2027-07-31';

        $first = $this->getJson($url)->assertOk()->assertJsonPath('data.range.is_closed', true)->json('data');
        $second = $this->getJson($url)->assertOk()->json('data');

        $this->assertSame($first, $second);
    }

    private function fixture(): array
    {
        $club = Club::create([
            'name' => 'Statement Test Club', 'code' => 'STATEMENT',
            'timezone' => 'Asia/Kolkata', 'is_active' => true,
        ]);
        $officer = User::factory()->create();
        $permission = Permission::create([
            'name' => 'View Club Account Statements', 'slug' => 'finance.club.statement.view',
            'group' => 'finance', 'description' => 'View statements.',
        ]);
        $role = Role::create(['name' => 'Finance Chair', 'slug' => 'finance-chair', 'description' => 'Finance Chair']);
        $role->permissions()->attach($permission);
        ClubMembership::create(['club_id' => $club->id, 'user_id' => $officer->id, 'role_id' => $role->id, 'status' => 'active']);
        $period = ClubAccountingPeriod::create([
            'club_id' => $club->id, 'lions_year' => '2027-28', 'start_date' => '2027-07-01',
            'end_date' => '2028-06-30', 'status' => 'open', 'created_by_user_id' => $officer->id,
        ]);
        $cash = $this->account($club, $officer, 'CASH-ON-HAND', 'asset', 'debit', 'CASH_AND_BANK');
        $equity = $this->account($club, $officer, 'OPENING-EQUITY', 'equity', 'credit', 'FUND_BALANCE_EQUITY');
        $income = $this->account($club, $officer, 'INCOME-DUES', 'income', 'credit', 'INCOME');
        $package = ClubAccountingCutoverPackage::create([
            'club_id' => $club->id, 'lions_year' => '2027-28', 'cutover_date' => '2027-07-01',
            'chart_version' => 'CLUB-COA-2026-01', 'package_version' => 1, 'status' => 'draft',
            'currency' => 'INR', 'canonical_checksum' => str_repeat('a', 64),
            'reconciliation_difference_paise' => 0, 'dr033_classification' => 'OPENING-BALANCE-EQUITY',
            'activation_ready' => false,
            'created_by_user_id' => $officer->id, 'updated_by_user_id' => $officer->id,
        ]);
        $cashRow = $this->openingRow($package, $cash, 'debit', 1000);
        $equityRow = $this->openingRow($package, $equity, 'credit', 1000);
        $opening = $this->postJournal($club, $period, $officer, '2027-07-01', 'opening', [
            new JournalLineInput($cash->id, 'debit', 1000, fundCode: 'GENERAL_CLUB_FUND', openingScheduleRowId: $cashRow->id),
            new JournalLineInput($equity->id, 'credit', 1000, fundCode: 'GENERAL_CLUB_FUND', openingScheduleRowId: $equityRow->id),
        ]);
        DB::table('club_accounting_cutover_packages')->where('id', $package->id)->update([
            'status' => 'activation_authorized', 'activation_ready' => true,
        ]);
        DB::table('club_accounting_cutover_packages')->where('id', $package->id)->update([
            'status' => 'posted', 'opening_journal_entry_id' => $opening->id,
            'posted_at' => now(), 'posted_by_user_id' => $officer->id,
        ]);
        $package = $package->fresh();
        $movement = $this->postJournal($club, $period, $officer, '2027-07-15', 'dues', [
            new JournalLineInput($cash->id, 'debit', 200, fundCode: 'GENERAL_CLUB_FUND'),
            new JournalLineInput($income->id, 'credit', 200, fundCode: 'GENERAL_CLUB_FUND'),
        ]);

        return compact('club', 'officer', 'period', 'cash', 'equity', 'income', 'opening', 'movement', 'package');
    }

    private function account(Club $club, User $actor, string $code, string $type, string $normal, string $classification): LedgerAccount
    {
        return LedgerAccount::create([
            'club_id' => $club->id, 'code' => $code, 'name' => $code, 'account_type' => $type,
            'normal_balance' => $normal, 'currency' => 'INR', 'is_active' => true,
            'created_by_user_id' => $actor->id, 'origin_kind' => 'custom',
            'origin_template_code' => null, 'statement_classification' => $classification,
        ]);
    }

    private function openingRow(ClubAccountingCutoverPackage $package, LedgerAccount $account, string $direction, int $amount): ClubAccountingCutoverScheduleRow
    {
        return ClubAccountingCutoverScheduleRow::create([
            'club_id' => $package->club_id, 'cutover_package_id' => $package->id,
            'schedule_type' => $account->account_type === 'equity' ? 'equity_preview' : 'cash_on_hand',
            'row_key' => 'OPENING-'.$account->id,
            'non_sensitive_label' => $account->name, 'ledger_account_id' => $account->id,
            'presentation_group' => 'GENERAL-CLUB-FUND', 'direction' => $direction,
            'amount_paise' => $amount, 'fund_code' => 'GENERAL_CLUB_FUND',
        ]);
    }

    private function postJournal(Club $club, ClubAccountingPeriod $period, User $actor, string $date, string $event, array $lines): ClubJournalEntry
    {
        return app(ClubJournalPostingService::class)->post(new JournalPostingRequest(
            clubId: $club->id, accountingPeriodId: $period->id, effectiveDate: $date,
            description: $event, currency: 'INR', sourceType: 'statement_test',
            sourceId: $event, sourceEvent: $event, idempotencyKey: (string) Str::uuid(),
            postingActorUserId: $actor->id, lines: $lines,
        ));
    }

    private function paymentTrace(array $fixture, bool $corrupt): array
    {
        $memberId = DB::table('members')->insertGetId([
            'club_id' => $fixture['club']->id, 'member_code' => 'TRACE-1', 'full_name' => 'Trace Member',
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $chargeId = DB::table('club_charges')->insertGetId([
            'public_id' => (string) Str::uuid(), 'club_id' => $fixture['club']->id, 'title' => 'Trace charge',
            'category' => 'membership_dues', 'fund_code' => 'GENERAL_CLUB_FUND', 'amount_paise' => 300,
            'due_date' => '2027-07-20', 'financial_year' => '2027-28', 'status' => 'issued',
            'assignment_member_ids' => json_encode([$memberId]), 'created_by_user_id' => $fixture['officer']->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $memberChargeId = DB::table('member_charges')->insertGetId([
            'club_id' => $fixture['club']->id, 'club_charge_id' => $chargeId, 'member_id' => $memberId,
            'original_amount_paise' => 300, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $paymentPublicId = (string) Str::uuid();
        $paymentId = DB::table('member_payments')->insertGetId([
            'public_id' => $paymentPublicId, 'club_id' => $fixture['club']->id, 'member_id' => $memberId,
            'amount_paise' => 300, 'payment_date' => '2027-07-20', 'payment_mode' => 'cash',
            'idempotency_key' => (string) Str::uuid(), 'request_hash' => str_repeat('b', 64),
            'status' => 'recorded', 'recorded_by_user_id' => $fixture['officer']->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $allocationId = DB::table('payment_allocations')->insertGetId([
            'club_id' => $fixture['club']->id, 'member_payment_id' => $paymentId,
            'member_charge_id' => $memberChargeId, 'amount_paise' => 300,
            'fund_code' => 'GENERAL_CLUB_FUND', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $receivable = $this->account($fixture['club'], $fixture['officer'], 'MEMBER-RECEIVABLE', 'asset', 'debit', 'RECEIVABLES');
        $journal = app(ClubJournalPostingService::class)->post(new JournalPostingRequest(
            clubId: $fixture['club']->id, accountingPeriodId: $fixture['period']->id, effectiveDate: '2027-07-20',
            description: 'payment', currency: 'INR', sourceType: 'member_payment', sourceId: $paymentPublicId,
            sourceEvent: 'committed', idempotencyKey: (string) Str::uuid(), postingActorUserId: $fixture['officer']->id,
            lines: [
                new JournalLineInput($fixture['cash']->id, 'debit', 300, fundCode: 'GENERAL_CLUB_FUND'),
                new JournalLineInput($receivable->id, 'credit', 300, fundCode: 'GENERAL_CLUB_FUND'),
            ],
        ));
        foreach ($journal->lines()->orderBy('line_number')->get() as $line) {
            DB::table('payment_allocation_journal_lines')->insert([
                'club_id' => $fixture['club']->id, 'payment_allocation_id' => $allocationId,
                'journal_line_id' => $line->id,
                'line_role' => $line->direction === 'debit' ? 'asset_debit' : 'receivables_credit',
                'amount_paise' => $corrupt ? 299 : 300, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return ['journal' => $journal, 'payment_public_id' => $paymentPublicId, 'allocation_id' => $allocationId];
    }

    private function endpoint(array $fixture): string
    {
        return "/api/v1/clubs/{$fixture['club']->id}/finance/account-statement?lions_year=2027-28";
    }

    private function tableCounts(): array
    {
        return collect([
            'club_journal_entries', 'club_journal_lines', 'club_accounting_periods',
            'club_accounting_cutover_packages', 'ledger_accounts', 'audit_logs', 'jobs',
        ])->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])->all();
    }
}
