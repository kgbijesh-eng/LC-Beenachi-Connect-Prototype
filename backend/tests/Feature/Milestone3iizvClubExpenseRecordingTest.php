<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubAccountingCutoverPackage;
use App\Models\ClubAccountingCutoverScheduleRow;
use App\Models\ClubAccountingPeriod;
use App\Models\ClubMembership;
use App\Models\ClubExpense;
use App\Models\LedgerAccount;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\ClubJournalPostingService;
use App\Services\Finance\JournalLineInput;
use App\Services\Finance\JournalPostingRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;

class Milestone3iizvClubExpenseRecordingTest extends TestCase
{
    use RefreshDatabase;

    public function test_expense_posts_once_and_flows_into_the_canonical_statement(): void
    {
        $f = $this->fixture('A'); Sanctum::actingAs($f['officer']);
        config(['instance.organisation_code' => $f['club']->code]);
        $before = $this->getJson($this->statement($f))->assertOk()->json('data.snapshot_fingerprint');
        $payload = $this->payload($f);

        $created = $this->postJson($this->endpoint($f), $payload)->assertCreated()
            ->assertJsonPath('data.amount_paise', 450000)->assertJsonPath('data.fund', 'SERVICE_ACTIVITY_FUND')
            ->assertJsonPath('data.expense_account.code', 'PRINTING')->assertJsonPath('data.payment_account.code', 'BANK-MAIN');
        $replay = $this->postJson($this->endpoint($f), $payload)->assertCreated();
        $this->assertSame($created->json('data.id'), $replay->json('data.id'));
        $this->assertDatabaseCount('club_expenses', 1); $this->assertDatabaseCount('club_journal_entries', 2);
        $journal = DB::table('club_journal_entries')->where('source_type', 'club_expense')->first();
        $lines = DB::table('club_journal_lines')->where('journal_entry_id', $journal->id)->orderBy('line_number')->get();
        $this->assertSame([['debit', $f['expense']->id, 450000], ['credit', $f['bank']->id, 450000]], $lines->map(fn ($l) => [$l->direction, $l->ledger_account_id, $l->amount_paise])->all());
        $this->assertSame(['SERVICE_ACTIVITY_FUND'], $lines->pluck('fund_code')->unique()->values()->all());

        $statement = $this->getJson($this->statement($f))->assertOk();
        $this->assertNotSame($before, $statement->json('data.snapshot_fingerprint'));
        $service = collect($statement->json('data.funds'))->firstWhere('fund_code', 'SERVICE_ACTIVITY_FUND');
        $expense = collect($service['classifications'])->firstWhere('classification', 'EXPENSES');
        $cash = collect($service['classifications'])->firstWhere('classification', 'CASH_AND_BANK');
        $this->assertSame(450000, $expense['totals']['period_debit_paise']);
        $this->assertSame(-450000, $cash['totals']['closing_balance_paise']);
        $this->assertSame(1000, $statement->json('data.overall_trial_balance.opening_debits_paise'));
        $source = ClubExpense::query()->firstOrFail();
        try { $source->update(['amount_paise' => 1]); $this->fail('Posted expense mutation must fail.'); } catch (LogicException) { $this->assertSame(450000, $source->fresh()->amount_paise); }
        try { $source->delete(); $this->fail('Posted expense deletion must fail.'); } catch (LogicException) { $this->assertDatabaseCount('club_expenses', 1); }
    }

    public function test_changed_replay_and_cross_club_accounts_fail_closed_with_tenant_scoped_idempotency(): void
    {
        $a = $this->fixture('A'); $b = $this->fixture('B'); $sharedKey = (string) Str::uuid(); Sanctum::actingAs($a['officer']);
        $payload = [...$this->payload($a), 'idempotency_key' => $sharedKey]; config(['instance.organisation_code' => $a['club']->code]); $this->postJson($this->endpoint($a), $payload)->assertCreated();
        $this->postJson($this->endpoint($a), [...$payload, 'amount_paise' => 1])->assertConflict()->assertJsonPath('code', 'EXPENSE_IDEMPOTENCY_CONFLICT');
        $this->postJson($this->endpoint($a), [...$this->payload($a), 'idempotency_key' => (string) Str::uuid(), 'expense_account' => $b['expense']->public_id])->assertUnprocessable();
        $this->postJson($this->endpoint($a), [...$this->payload($a), 'idempotency_key' => (string) Str::uuid(), 'payment_account' => $b['bank']->public_id])->assertUnprocessable();
        Sanctum::actingAs($b['officer']); config(['instance.organisation_code' => $b['club']->code]); $this->postJson($this->endpoint($b), [...$this->payload($b), 'idempotency_key' => $sharedKey])->assertCreated();
        $this->assertDatabaseCount('club_expenses', 2);
    }

    public function test_exact_replay_rejects_material_persisted_expense_provenance_corruption(): void
    {
        $f = $this->fixture('C'); Sanctum::actingAs($f['officer']); $payload = $this->payload($f);
        config(['instance.organisation_code' => $f['club']->code]);
        $this->postJson($this->endpoint($f), $payload)->assertCreated();
        $expense = ClubExpense::query()->where('club_id', $f['club']->id)->firstOrFail();
        $journal = DB::table('club_journal_entries')->where('id', $expense->journal_entry_id)->first();
        $debit = DB::table('club_journal_lines')->where('journal_entry_id', $expense->journal_entry_id)->where('direction', 'debit')->first();
        $cases = [
            [['journal_entry_id' => null], ['journal_entry_id' => $expense->journal_entry_id], 'club_expenses'],
            [['source_type' => 'corrupt'], ['source_type' => $journal->source_type], 'club_journal_entries'],
            [['source_id' => (string) Str::uuid()], ['source_id' => $journal->source_id], 'club_journal_entries'],
            [['source_event' => 'corrupt'], ['source_event' => $journal->source_event], 'club_journal_entries'],
            [['idempotency_key' => (string) Str::uuid()], ['idempotency_key' => $journal->idempotency_key], 'club_journal_entries'],
            [['ledger_account_id' => $expense->payment_account_id], ['ledger_account_id' => $debit->ledger_account_id], 'club_journal_lines'],
            [['direction' => 'credit'], ['direction' => $debit->direction], 'club_journal_lines'],
            [['amount_paise' => $expense->amount_paise + 1], ['amount_paise' => $debit->amount_paise], 'club_journal_lines'],
            [['fund_code' => 'GENERAL_CLUB_FUND'], ['fund_code' => $debit->fund_code], 'club_journal_lines'],
        ];
        foreach ($cases as [$corrupt, $restore, $table]) {
            $id = $table === 'club_expenses' ? $expense->id : ($table === 'club_journal_entries' ? $expense->journal_entry_id : $debit->id);
            DB::table($table)->where('id', $id)->update($corrupt);
            config(['instance.organisation_code' => $f['club']->code]);
            $this->postJson($this->endpoint($f), $payload)->assertConflict()->assertJsonPath('code', 'EXPENSE_IDEMPOTENCY_CONFLICT');
            DB::table($table)->where('id', $id)->update($restore);
        }
    }

    public function test_permission_membership_account_period_amount_and_options_contracts_fail_closed(): void
    {
        $f = $this->fixture('A'); Sanctum::actingAs($f['officer']);
        config(['instance.organisation_code' => $f['club']->code]);
        $this->getJson($this->endpoint($f).'/options')->assertOk()->assertJsonCount(1, 'data.expense_accounts')->assertJsonCount(1, 'data.payment_accounts');
        foreach ([['amount_paise' => 0], ['expense_account' => $f['bank']->public_id], ['payment_account' => $f['expense']->public_id]] as $change) {
            config(['instance.organisation_code' => $f['club']->code]);
            $this->postJson($this->endpoint($f), [...$this->payload($f), ...$change, 'idempotency_key' => (string) Str::uuid()])->assertUnprocessable();
        }
        config(['instance.organisation_code' => $f['club']->code]);
        $this->postJson($this->endpoint($f), [...$this->payload($f), 'fund' => 'NOT_A_FUND', 'idempotency_key' => (string) Str::uuid()])->assertUnprocessable();
        DB::table('ledger_accounts')->where('id', $f['expense']->id)->update(['is_active' => false]);
        $this->postJson($this->endpoint($f), [...$this->payload($f), 'idempotency_key' => (string) Str::uuid()])->assertUnprocessable();
        DB::table('ledger_accounts')->where('id', $f['expense']->id)->update(['is_active' => true]);
        try { DB::table('ledger_accounts')->where('id', $f['expense']->id)->update(['currency' => 'USD']); $this->fail('Ledger currency guard must reject mismatches.'); }
        catch (QueryException) { $this->assertSame('INR', DB::table('ledger_accounts')->where('id', $f['expense']->id)->value('currency')); }
        DB::table('ledger_accounts')->where('id', $f['bank']->id)->update(['is_active' => false]);
        config(['instance.organisation_code' => $f['club']->code]);
        $this->postJson($this->endpoint($f), [...$this->payload($f), 'idempotency_key' => (string) Str::uuid()])->assertUnprocessable();
        DB::table('ledger_accounts')->where('id', $f['bank']->id)->update(['is_active' => true]);
        DB::table('club_accounting_periods')->where('id', $f['period']->id)->update(['status' => 'closed', 'closed_at' => now(), 'closed_by_user_id' => $f['officer']->id]);
        $this->postJson($this->endpoint($f), [...$this->payload($f), 'idempotency_key' => (string) Str::uuid()])->assertUnprocessable();
        DB::table('club_memberships')->where('club_id', $f['club']->id)->where('user_id', $f['officer']->id)->update(['status' => 'inactive']);
        $this->postJson($this->endpoint($f), $this->payload($f))->assertForbidden();
        Sanctum::actingAs(User::factory()->create()); $this->postJson($this->endpoint($f), $this->payload($f))->assertForbidden();
    }

    private function fixture(string $suffix): array
    {
        $club = Club::create(['name' => "Expense Club {$suffix}", 'code' => "EXP{$suffix}", 'timezone' => 'Asia/Kolkata', 'is_active' => true]);
        $officer = User::factory()->create();
        $permissions = collect(['finance.club.expenses.create', 'finance.club.statement.view'])->map(fn ($slug) => Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'group' => 'finance', 'description' => $slug]));
        $role = Role::create(['name' => "Treasurer {$suffix}", 'slug' => 'treasurer-'.strtolower($suffix), 'description' => 'Treasurer']); $role->permissions()->attach($permissions->pluck('id'));
        ClubMembership::create(['club_id' => $club->id, 'user_id' => $officer->id, 'role_id' => $role->id, 'status' => 'active']);
        $period = ClubAccountingPeriod::create(['club_id' => $club->id, 'lions_year' => '2027-28', 'start_date' => '2027-07-01', 'end_date' => '2028-06-30', 'status' => 'open', 'created_by_user_id' => $officer->id]);
        $bank = $this->account($club, $officer, 'BANK-MAIN', 'asset', 'debit', 'CASH_AND_BANK');
        $equity = $this->account($club, $officer, 'OPENING-EQUITY', 'equity', 'credit', 'FUND_BALANCE_EQUITY');
        $expense = $this->account($club, $officer, 'PRINTING', 'expense', 'debit', 'EXPENSES');
        $package = ClubAccountingCutoverPackage::create(['club_id' => $club->id, 'lions_year' => '2027-28', 'cutover_date' => '2027-07-01', 'chart_version' => 'CLUB-COA-2026-01', 'package_version' => 1, 'status' => 'draft', 'currency' => 'INR', 'canonical_checksum' => str_repeat('a', 64), 'reconciliation_difference_paise' => 0, 'dr033_classification' => 'OPENING-BALANCE-EQUITY', 'activation_ready' => false, 'created_by_user_id' => $officer->id, 'updated_by_user_id' => $officer->id]);
        $rows = collect([[$bank, 'debit'], [$equity, 'credit']])->map(fn ($row) => ClubAccountingCutoverScheduleRow::create(['club_id' => $club->id, 'cutover_package_id' => $package->id, 'schedule_type' => $row[0]->account_type === 'equity' ? 'equity_preview' : 'cash_on_hand', 'row_key' => 'OPENING-'.$row[0]->id, 'non_sensitive_label' => $row[0]->name, 'ledger_account_id' => $row[0]->id, 'direction' => $row[1], 'amount_paise' => 1000, 'currency' => 'INR', 'fund_code' => 'GENERAL_CLUB_FUND']));
        $opening = app(ClubJournalPostingService::class)->post(new JournalPostingRequest($club->id, $period->id, '2027-07-01', 'Opening', 'INR', 'opening_balance_activation', $package->public_id, 'activated', (string) Str::uuid(), $officer->id, [new JournalLineInput($bank->id, 'debit', 1000, fundCode: 'GENERAL_CLUB_FUND', openingScheduleRowId: $rows[0]->id), new JournalLineInput($equity->id, 'credit', 1000, fundCode: 'GENERAL_CLUB_FUND', openingScheduleRowId: $rows[1]->id)]));
        DB::table('club_accounting_cutover_packages')->where('id', $package->id)->update(['status' => 'activation_authorized', 'activation_ready' => true]);
        DB::table('club_accounting_cutover_packages')->where('id', $package->id)->update(['status' => 'posted', 'opening_journal_entry_id' => $opening->id, 'posted_at' => now(), 'posted_by_user_id' => $officer->id]);
        return compact('club', 'officer', 'period', 'bank', 'expense');
    }

    private function account(Club $club, User $actor, string $code, string $type, string $normal, string $classification): LedgerAccount { return LedgerAccount::create(['club_id' => $club->id, 'code' => $code, 'name' => $code, 'account_type' => $type, 'normal_balance' => $normal, 'currency' => 'INR', 'is_active' => true, 'created_by_user_id' => $actor->id, 'origin_kind' => 'custom', 'statement_classification' => $classification]); }
    private function endpoint(array $f): string { return "/api/v1/clubs/{$f['club']->id}/finance/expenses"; }
    private function statement(array $f): string { return "/api/v1/clubs/{$f['club']->id}/finance/account-statement?lions_year=2027-28"; }
    private function payload(array $f): array { return ['accounting_date' => '2027-08-10', 'expense_account' => $f['expense']->public_id, 'payment_account' => $f['bank']->public_id, 'fund' => 'SERVICE_ACTIVITY_FUND', 'amount_paise' => 450000, 'payee' => 'Tenant Printer', 'description' => 'Programme printing', 'external_reference' => 'INV-42', 'idempotency_key' => (string) Str::uuid()]; }
}
