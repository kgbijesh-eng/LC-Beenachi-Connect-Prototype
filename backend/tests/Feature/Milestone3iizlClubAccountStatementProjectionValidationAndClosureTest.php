<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Api\V1\Finance\ClubAccountStatementController;
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
use App\Services\Finance\ClubAccountStatementProjectionService;
use App\Services\Finance\ClubJournalPostingService;
use App\Services\Finance\JournalLineInput;
use App\Services\Finance\JournalPostingRequest;
use App\Services\Finance\JournalReversalRequest;
use App\Services\Finance\JournalReversalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Support\RefreshesDatabaseWithoutTransactions;

class Milestone3iizlClubAccountStatementProjectionValidationAndClosureTest extends TestCase
{
    use RefreshesDatabaseWithoutTransactions;

    public function test_stable_get_route_controller_and_contract_are_active(): void
    {
        $route = collect(Route::getRoutes())->first(fn ($route) => $route->uri() === 'api/v1/clubs/{club}/finance/account-statement');

        $this->assertNotNull($route);
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        $this->assertSame(ClubAccountStatementController::class.'@show', $route->getActionName());
        $this->assertSame('CLUB-ACCOUNT-STATEMENT-2026-01', ClubAccountStatementProjectionService::CONTRACT_VERSION);

        $fixture = $this->fixture();
        config(['instance.organisation_code' => $fixture['club']->code]); Sanctum::actingAs($fixture['officer']);
        $this->getJson($this->endpoint($fixture))->assertOk()
            ->assertJsonPath('data.contract_version', 'CLUB-ACCOUNT-STATEMENT-2026-01');
    }

    public function test_successful_get_is_read_only_and_deterministic(): void
    {
        $this->assertSame(0, DB::transactionLevel());
        $fixture = $this->fixture();
        config(['instance.organisation_code' => $fixture['club']->code]); Sanctum::actingAs($fixture['officer']);
        $before = $this->accountingCounts();

        $first = $this->getJson($this->endpoint($fixture))->assertOk()->json('data');
        $second = $this->getJson($this->endpoint($fixture))->assertOk()->json('data');

        $this->assertSame($first, $second);
        $this->assertSame($before, $this->accountingCounts());
    }

    public function test_authorization_boundaries_remain_closed(): void
    {
        $fixture = $this->fixture();
        $memberRole = Role::create(['name' => 'Member', 'slug' => 'member', 'description' => 'Member']);
        $member = User::factory()->create();
        ClubMembership::create(['club_id' => $fixture['club']->id, 'user_id' => $member->id, 'role_id' => $memberRole->id, 'status' => 'active']);

        config(['instance.organisation_code' => $fixture['club']->code]); Sanctum::actingAs($member);
        $this->getJson($this->endpoint($fixture))->assertForbidden();

        ClubMembership::where('club_id', $fixture['club']->id)->where('user_id', $fixture['officer']->id)->update(['status' => 'inactive']);
        config(['instance.organisation_code' => $fixture['club']->code]); Sanctum::actingAs($fixture['officer']);
        $this->getJson($this->endpoint($fixture))->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->getJson($this->endpoint($fixture))->assertUnauthorized();

        $otherClub = Club::create(['name' => 'Other Club', 'code' => '3IIZL-OTHER', 'timezone' => 'Asia/Kolkata', 'is_active' => true]);
        ClubMembership::where('club_id', $fixture['club']->id)->where('user_id', $fixture['officer']->id)->update(['status' => 'active']);
        config(['instance.organisation_code' => $otherClub->code]); Sanctum::actingAs($fixture['officer']);
        $this->getJson("/api/v1/clubs/{$otherClub->id}/finance/account-statement?lions_year=2027-28")->assertForbidden();
    }

    public function test_activation_gate_returns_stable_conflict(): void
    {
        $fixture = $this->fixture();
        $club = Club::create(['name' => 'Inactive Accounting Club', 'code' => '3IIZL-NOACT', 'timezone' => 'Asia/Kolkata', 'is_active' => true]);
        $membership = ClubMembership::where('club_id', $fixture['club']->id)->where('user_id', $fixture['officer']->id)->firstOrFail();
        ClubMembership::create(['club_id' => $club->id, 'user_id' => $fixture['officer']->id, 'role_id' => $membership->role_id, 'status' => 'active']);
        ClubAccountingPeriod::create(['club_id' => $club->id, 'lions_year' => '2027-28', 'start_date' => '2027-07-01', 'end_date' => '2028-06-30', 'status' => 'open', 'created_by_user_id' => $fixture['officer']->id]);
        config(['instance.organisation_code' => $club->code]); Sanctum::actingAs($fixture['officer']);

        $this->getJson("/api/v1/clubs/{$club->id}/finance/account-statement?lions_year=2027-28")
            ->assertConflict()->assertJsonPath('code', 'ACCOUNTING_NOT_ACTIVATED');
    }

    public function test_projection_scope_excludes_draft_pre_cutover_and_out_of_range_activity(): void
    {
        $fixture = $this->fixture();
        $this->rawJournal($fixture, '2027-06-30', 'posted', 9000);
        $this->rawJournal($fixture, '2027-07-10', 'draft', 8000);
        $this->postJournal($fixture, '2027-09-01', 'future', 7000);
        config(['instance.organisation_code' => $fixture['club']->code]); Sanctum::actingAs($fixture['officer']);

        $response = $this->getJson($this->endpoint($fixture).'&from=2027-07-01&to=2027-07-31')->assertOk();
        $this->assertSame(2, $response->json('data.source.journal_count'));
        $this->assertSame(200, $response->json('data.overall_trial_balance.period_debits_paise'));

        $zero = $this->getJson($this->endpoint($fixture).'&from=2027-07-16&to=2027-07-31')->assertOk();
        $this->assertSame(0, $zero->json('data.overall_trial_balance.period_debits_paise'));
        $this->assertSame(0, $zero->json('data.overall_trial_balance.period_credits_paise'));
    }

    public function test_opening_fund_classification_balance_and_public_trace_contract_reconcile(): void
    {
        $fixture = $this->fixture();
        $serviceIncome = $this->account($fixture, 'SERVICE-INCOME', 'income', 'credit', 'INCOME');
        $this->postJournal($fixture, '2027-07-20', 'service', 300, 'SERVICE_ACTIVITY_FUND', $serviceIncome);
        $expense = $this->account($fixture, 'GENERAL-EXPENSE', 'expense', 'debit', 'EXPENSES');
        $this->postBalanced($fixture, '2027-07-21', 'expense', [
            new JournalLineInput($expense->id, 'debit', 1500, fundCode: 'GENERAL_CLUB_FUND'),
            new JournalLineInput($fixture['cash']->id, 'credit', 1500, fundCode: 'GENERAL_CLUB_FUND'),
        ]);
        config(['instance.organisation_code' => $fixture['club']->code]); Sanctum::actingAs($fixture['officer']);

        $response = $this->getJson($this->endpoint($fixture))->assertOk();
        $journals = $response->json('data.traceability.journals');
        $this->assertSame('opening_balance', $journals[0]['contribution']);
        $this->assertSame('period_movement', $journals[1]['contribution']);
        $this->assertTrue(Str::isUuid($journals[0]['lines'][0]['journal_line_id']));
        $this->assertTrue(Str::isUuid($journals[0]['lines'][0]['opening_schedule_row_id']));
        $this->assertNotEmpty($journals[0]['reference']);

        $generalCash = collect($response->json('data.funds.0.classifications.0.accounts'))->firstWhere('account_code', 'CASH');
        $serviceCash = collect($response->json('data.funds.1.classifications.0.accounts'))->firstWhere('account_code', 'CASH');
        $this->assertSame(-300, $generalCash['closing_balance_paise']);
        $this->assertSame(300, $serviceCash['closing_balance_paise']);
        $this->assertSame('INCOME', $response->json('data.funds.1.classifications.6.classification'));
        $this->assertSame(
            $response->json('data.overall_trial_balance.opening_debits_paise'),
            $response->json('data.overall_trial_balance.opening_credits_paise'),
        );
        $this->assertSame($response->json('data.overall_trial_balance.period_debits_paise'), $response->json('data.overall_trial_balance.period_credits_paise'));
        $this->assertSame($response->json('data.overall_trial_balance.closing_debits_paise'), $response->json('data.overall_trial_balance.closing_credits_paise'));
        $this->assertTrue($response->json('data.integrity.fund_balances_reconcile'));
        $this->assertTrue($response->json('data.integrity.classification_subtotals_reconcile'));
        $this->assertTrue($response->json('data.integrity.fund_subtotals_reconcile'));
    }

    public function test_reversal_relationships_remain_public_and_traceable(): void
    {
        $fixture = $this->fixture();
        $reversal = app(JournalReversalService::class)->reverse(new JournalReversalRequest(
            $fixture['club']->id, $fixture['movement']->id, $fixture['period']->id, '2027-08-01',
            $fixture['officer']->id, 'Closure validation', '3iizl_reversal', 'movement-reversal',
            'reversed', (string) Str::uuid(),
        ));
        config(['instance.organisation_code' => $fixture['club']->code]); Sanctum::actingAs($fixture['officer']);

        $response = $this->getJson($this->endpoint($fixture))->assertOk();
        $original = collect($response->json('data.traceability.journals'))->firstWhere('journal_id', $fixture['movement']->public_id);
        $reversalTrace = collect($response->json('data.traceability.journals'))->firstWhere('journal_id', $reversal->public_id);
        $this->assertSame($reversal->public_id, $original['reversed_by_journal_id']);
        $this->assertSame($fixture['movement']->public_id, $reversalTrace['reversal_of_journal_id']);
    }

    public function test_integrity_and_query_defects_fail_closed(): void
    {
        $fixture = $this->fixture();
        config(['instance.organisation_code' => $fixture['club']->code]); Sanctum::actingAs($fixture['officer']);
        $base = "/api/v1/clubs/{$fixture['club']->id}/finance/account-statement";

        foreach ([
            '?lions_year=invalid',
            '?lions_year=2027-28&from=2027-07-02',
            '?lions_year=2027-28&from=2027-08-01&to=2027-07-01',
            '?lions_year=2027-28&period_id='.$fixture['period']->id.'&from=2027-07-01&to=2027-07-31',
            '?lions_year=2027-28&unknown=1',
        ] as $query) {
            $this->getJson($base.$query)->assertUnprocessable();
        }

        $otherClub = Club::create(['name' => 'Period Owner', 'code' => '3IIZL-PERIOD', 'timezone' => 'Asia/Kolkata', 'is_active' => true]);
        $otherPeriod = ClubAccountingPeriod::create(['club_id' => $otherClub->id, 'lions_year' => '2027-28', 'start_date' => '2027-07-01', 'end_date' => '2028-06-30', 'status' => 'open', 'created_by_user_id' => $fixture['officer']->id]);
        $this->getJson($this->endpoint($fixture).'&period_id='.$otherPeriod->id)->assertUnprocessable();

        DB::table('ledger_accounts')->where('id', $fixture['cash']->id)->update(['statement_classification' => null]);
        $this->getJson($this->endpoint($fixture))->assertConflict()->assertJsonPath('code', 'STATEMENT_INTEGRITY_FAILURE');
        DB::table('ledger_accounts')->where('id', $fixture['cash']->id)->update(['statement_classification' => 'CASH_AND_BANK']);
        DB::table('club_journal_lines')->where('ledger_account_id', $fixture['cash']->id)->update(['fund_code' => null]);
        $this->getJson($this->endpoint($fixture))->assertConflict()->assertJsonPath('code', 'STATEMENT_INTEGRITY_FAILURE');
    }

    private function fixture(): array
    {
        $club = Club::create(['name' => '3IIZL Club', 'code' => '3IIZL', 'timezone' => 'Asia/Kolkata', 'is_active' => true]);
        $officer = User::factory()->create();
        $permission = Permission::create(['name' => 'View Club Account Statements', 'slug' => 'finance.club.statement.view', 'group' => 'finance', 'description' => 'View statements.']);
        $role = Role::create(['name' => 'Treasurer', 'slug' => 'treasurer', 'description' => 'Treasurer']);
        $role->permissions()->attach($permission);
        ClubMembership::create(['club_id' => $club->id, 'user_id' => $officer->id, 'role_id' => $role->id, 'status' => 'active']);
        $period = ClubAccountingPeriod::create(['club_id' => $club->id, 'lions_year' => '2027-28', 'start_date' => '2027-07-01', 'end_date' => '2028-06-30', 'status' => 'open', 'created_by_user_id' => $officer->id]);
        $fixture = compact('club', 'officer', 'period');
        $cash = $this->account($fixture, 'CASH', 'asset', 'debit', 'CASH_AND_BANK');
        $equity = $this->account($fixture, 'OPENING-EQUITY', 'equity', 'credit', 'FUND_BALANCE_EQUITY');
        $income = $this->account($fixture, 'DUES-INCOME', 'income', 'credit', 'INCOME');
        $package = ClubAccountingCutoverPackage::create([
            'club_id' => $club->id, 'lions_year' => '2027-28', 'cutover_date' => '2027-07-01',
            'chart_version' => 'CLUB-COA-2026-01', 'package_version' => 1, 'status' => 'draft',
            'currency' => 'INR', 'canonical_checksum' => str_repeat('a', 64), 'reconciliation_difference_paise' => 0,
            'dr033_classification' => 'OPENING-BALANCE-EQUITY', 'activation_ready' => false,
            'created_by_user_id' => $officer->id, 'updated_by_user_id' => $officer->id,
        ]);
        $cashRow = $this->openingRow($package, $cash, 'debit');
        $equityRow = $this->openingRow($package, $equity, 'credit');
        $fixture += compact('cash', 'equity', 'income');
        $opening = $this->postBalanced($fixture, '2027-07-01', 'opening', [
            new JournalLineInput($cash->id, 'debit', 1000, fundCode: 'GENERAL_CLUB_FUND', openingScheduleRowId: $cashRow->id),
            new JournalLineInput($equity->id, 'credit', 1000, fundCode: 'GENERAL_CLUB_FUND', openingScheduleRowId: $equityRow->id),
        ]);
        DB::table('club_accounting_cutover_packages')->where('id', $package->id)->update(['status' => 'activation_authorized', 'activation_ready' => true]);
        DB::table('club_accounting_cutover_packages')->where('id', $package->id)->update(['status' => 'posted', 'opening_journal_entry_id' => $opening->id, 'posted_at' => now(), 'posted_by_user_id' => $officer->id]);
        $movement = $this->postJournal($fixture, '2027-07-15', 'dues', 200);

        return $fixture + compact('opening', 'movement', 'package');
    }

    private function account(array $fixture, string $code, string $type, string $normal, string $classification): LedgerAccount
    {
        return LedgerAccount::create([
            'club_id' => $fixture['club']->id, 'code' => $code, 'name' => $code, 'account_type' => $type,
            'normal_balance' => $normal, 'currency' => 'INR', 'is_active' => true,
            'created_by_user_id' => $fixture['officer']->id, 'origin_kind' => 'custom',
            'statement_classification' => $classification,
        ]);
    }

    private function openingRow(ClubAccountingCutoverPackage $package, LedgerAccount $account, string $direction): ClubAccountingCutoverScheduleRow
    {
        return ClubAccountingCutoverScheduleRow::create([
            'club_id' => $package->club_id, 'cutover_package_id' => $package->id,
            'schedule_type' => $account->account_type === 'equity' ? 'equity_preview' : 'cash_on_hand',
            'row_key' => 'OPENING-'.$account->id, 'non_sensitive_label' => $account->name,
            'ledger_account_id' => $account->id, 'presentation_group' => 'GENERAL-CLUB-FUND',
            'direction' => $direction, 'amount_paise' => 1000, 'fund_code' => 'GENERAL_CLUB_FUND',
        ]);
    }

    private function postJournal(array $fixture, string $date, string $event, int $amount, string $fund = 'GENERAL_CLUB_FUND', ?LedgerAccount $credit = null): ClubJournalEntry
    {
        return $this->postBalanced($fixture, $date, $event, [
            new JournalLineInput($fixture['cash']->id, 'debit', $amount, fundCode: $fund),
            new JournalLineInput(($credit ?? $fixture['income'])->id, 'credit', $amount, fundCode: $fund),
        ]);
    }

    private function postBalanced(array $fixture, string $date, string $event, array $lines): ClubJournalEntry
    {
        return app(ClubJournalPostingService::class)->post(new JournalPostingRequest(
            clubId: $fixture['club']->id, accountingPeriodId: $fixture['period']->id, effectiveDate: $date,
            description: $event, currency: 'INR', sourceType: '3iizl_validation', sourceId: $event,
            sourceEvent: $event, idempotencyKey: (string) Str::uuid(), postingActorUserId: $fixture['officer']->id,
            lines: $lines,
        ));
    }

    private function rawJournal(array $fixture, string $date, string $status, int $amount): void
    {
        $id = DB::table('club_journal_entries')->insertGetId([
            'public_id' => (string) Str::uuid(), 'club_id' => $fixture['club']->id, 'accounting_period_id' => $fixture['period']->id,
            'sequence_number' => random_int(100, 999), 'reference' => strtoupper($status).'-'.$date.'-'.$amount,
            'effective_date' => $date, 'posting_date' => $date, 'posted_at' => $status === 'posted' ? now() : null,
            'status' => $status, 'description' => $status, 'currency' => 'INR', 'source_type' => '3iizl_raw',
            'source_id' => $date.'-'.$status, 'source_event' => $status, 'idempotency_key' => (string) Str::uuid(),
            'created_by_user_id' => $fixture['officer']->id, 'posted_by_user_id' => $status === 'posted' ? $fixture['officer']->id : null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([['debit', $fixture['cash']->id], ['credit', $fixture['income']->id]] as $index => [$direction, $account]) {
            DB::table('club_journal_lines')->insert([
                'public_id' => (string) Str::uuid(), 'club_id' => $fixture['club']->id, 'journal_entry_id' => $id,
                'ledger_account_id' => $account, 'line_number' => $index + 1, 'direction' => $direction,
                'amount_paise' => $amount, 'fund_code' => 'GENERAL_CLUB_FUND', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function endpoint(array $fixture): string
    {
        return "/api/v1/clubs/{$fixture['club']->id}/finance/account-statement?lions_year=2027-28";
    }

    private function accountingCounts(): array
    {
        return collect(['club_journal_entries', 'club_journal_lines', 'club_accounting_periods', 'club_accounting_cutover_packages', 'ledger_accounts'])
            ->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])->all();
    }
}
