<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubAccountingCutoverPackage;
use App\Models\ClubAccountingCutoverScheduleRow;
use App\Models\ClubAccountingPeriod;
use App\Models\ClubExpense;
use App\Models\ClubExpenseReversal;
use App\Models\ClubJournalEntry;
use App\Models\ClubMembership;
use App\Models\LedgerAccount;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\ClubJournalPostingService;
use App\Services\Finance\JournalLineInput;
use App\Services\Finance\JournalPostingRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class Milestone3iizwExpenseHistoryCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_history_is_paginated_filterable_deterministic_and_tenant_scoped(): void
    {
        $a = $this->fixture('HA'); $b = $this->fixture('HB'); Sanctum::actingAs($a['officer']);
        $first = $this->record($a, ['payee' => 'Alpha Printer', 'external_reference' => 'INV-A']);
        $this->record($a, ['accounting_date' => '2027-08-11', 'payee' => 'Beta Hall', 'external_reference' => 'INV-B']);
        Sanctum::actingAs($b['officer']); $foreign = $this->record($b, ['payee' => 'Foreign']);
        Sanctum::actingAs($a['officer']);

        config(['instance.organisation_code' => $a['club']->code]);
        $this->getJson($this->endpoint($a).'?per_page=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2);
        $this->getJson($this->endpoint($a).'?search=Alpha&date_from=2027-08-10&date_to=2027-08-10&fund=SERVICE_ACTIVITY_FUND')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $first['id']);
        $this->getJson($this->endpoint($a).'?date_from=2027-01-01&date_to=2028-01-03')->assertUnprocessable()->assertJsonValidationErrors('date_to');
        $detail = $this->getJson($this->endpoint($a).'/'.$first['id'])->assertOk()->assertJsonPath('data.status', 'posted')->assertJsonPath('data.journal.lines.0.direction', 'debit');
        $this->assertArrayNotHasKey('club_id', $detail->json('data')); $this->assertArrayNotHasKey('journal_entry_id', $detail->json('data'));
        $this->getJson($this->endpoint($a).'/'.$foreign['id'])->assertNotFound();
        $this->postJson($this->endpoint($a).'/'.$foreign['id'].'/reversal', $this->reversalPayload())->assertNotFound();
    }

    public function test_full_reversal_is_exact_immutable_idempotent_and_changes_statement_fingerprint(): void
    {
        $f = $this->fixture('REV'); Sanctum::actingAs($f['officer']); $created = $this->record($f);
        config(['instance.organisation_code' => $f['club']->code]);
        $before = $this->getJson($this->statement($f))->assertOk()->json('data.snapshot_fingerprint'); $payload = $this->reversalPayload();
        $response = $this->postJson($this->endpoint($f).'/'.$created['id'].'/reversal', $payload)->assertOk()->assertJsonPath('data.status', 'reversed')->assertJsonPath('data.reversal.reason', 'Incorrect amount');
        $replay = $this->postJson($this->endpoint($f).'/'.$created['id'].'/reversal', $payload)->assertOk(); $this->assertSame($response->json('data.reversal.id'), $replay->json('data.reversal.id'));
        $this->assertDatabaseCount('club_expense_reversals', 1); $this->assertDatabaseCount('club_expenses', 1); $this->assertDatabaseCount('club_journal_entries', 3);
        $expense = ClubExpense::firstOrFail(); $original = $expense->journalEntry()->with('lines')->firstOrFail(); $reversal = ClubExpenseReversal::firstOrFail()->journalEntry()->with('lines')->firstOrFail();
        $this->assertSame(ClubJournalEntry::STATUS_REVERSED, $original->status); $this->assertSame($original->id, $reversal->reversal_of_entry_id);
        $this->assertSame([$f['expense']->id, $f['bank']->id], $original->lines->pluck('ledger_account_id')->all()); $this->assertSame(['debit', 'credit'], $original->lines->pluck('direction')->all());
        $this->assertSame([$f['expense']->id, $f['bank']->id], $reversal->lines->pluck('ledger_account_id')->all()); $this->assertSame(['credit', 'debit'], $reversal->lines->pluck('direction')->all());
        $this->assertSame([450000, 450000], $reversal->lines->pluck('amount_paise')->all()); $this->assertSame(['SERVICE_ACTIVITY_FUND'], $reversal->lines->pluck('fund_code')->unique()->values()->all());
        $after = $this->getJson($this->statement($f))->assertOk(); $this->assertNotSame($before, $after->json('data.snapshot_fingerprint')); $this->assertCount(3, $after->json('data.traceability.journals'));
        $replacement = $this->postJson($this->endpoint($f), $this->expensePayload($f, ['amount_paise' => 45000, 'corrected_from_expense' => $created['id'], 'idempotency_key' => (string) Str::uuid()]))->assertCreated();
        $this->assertNotSame($created['id'], $replacement->json('data.id')); $this->assertSame($created['id'], $replacement->json('data.corrected_from_expense')); $this->assertDatabaseCount('club_expenses', 2);
        $this->getJson($this->endpoint($f).'/'.$created['id'])->assertOk()->assertJsonPath('data.corrected_expense.id', $replacement->json('data.id'));
        $afterReplacement = $this->getJson($this->statement($f))->assertOk(); $this->assertNotSame($after->json('data.snapshot_fingerprint'), $afterReplacement->json('data.snapshot_fingerprint')); $this->assertCount(4, $afterReplacement->json('data.traceability.journals'));
        try { $expense->update(['description' => 'rewrite']); $this->fail('Expense mutation must fail.'); } catch (LogicException) { $this->addToAssertionCount(1); }
        config(['instance.organisation_code' => $f['club']->code]);
        $this->postJson($this->endpoint($f).'/'.$created['id'].'/reversal', [...$payload, 'reason' => 'Changed'])->assertConflict()->assertJsonPath('code', 'EXPENSE_REVERSAL_CONFLICT');
        $this->postJson($this->endpoint($f).'/'.$created['id'].'/reversal', [...$payload, 'idempotency_key' => (string) Str::uuid()])->assertConflict();
        DB::table('club_journal_entries')->where('id', $reversal->id)->update(['source_id' => 'corrupt-replay']);
        $this->postJson($this->endpoint($f).'/'.$created['id'].'/reversal', $payload)->assertUnprocessable()->assertJsonPath('code', 'EXPENSE_REVERSAL_REJECTED');
    }

    public function test_closed_period_requires_an_explicit_date_in_an_open_period_and_replacement_is_new(): void
    {
        $f = $this->fixture('CLOSE'); Sanctum::actingAs($f['officer']); $created = $this->record($f);
        DB::table('club_accounting_periods')->where('id', $f['period']->id)->update(['status' => 'closed', 'closed_at' => now(), 'closed_by_user_id' => $f['officer']->id]);
        config(['instance.organisation_code' => $f['club']->code]);
        $this->postJson($this->endpoint($f).'/'.$created['id'].'/reversal', $this->reversalPayload())->assertUnprocessable();
        $next = ClubAccountingPeriod::create(['club_id' => $f['club']->id, 'lions_year' => '2028-29', 'start_date' => '2028-07-01', 'end_date' => '2029-06-30', 'status' => 'open', 'created_by_user_id' => $f['officer']->id]);
        $payload = $this->reversalPayload(['accounting_date' => '2028-07-02', 'idempotency_key' => (string) Str::uuid()]);
        $this->postJson($this->endpoint($f).'/'.$created['id'].'/reversal', $payload)->assertOk()->assertJsonPath('data.reversal.accounting_date', '2028-07-02');
        $this->assertSame($next->id, ClubExpenseReversal::firstOrFail()->journalEntry->accounting_period_id);
    }

    public function test_permissions_inactive_membership_integrity_and_late_failure_all_fail_closed(): void
    {
        $f = $this->fixture('SAFE'); Sanctum::actingAs($f['officer']); $created = $this->record($f);
        DB::table('club_memberships')->where('club_id', $f['club']->id)->where('user_id', $f['officer']->id)->update(['status' => 'inactive']);
        config(['instance.organisation_code' => $f['club']->code]);
        $this->getJson($this->endpoint($f))->assertForbidden(); $this->postJson($this->endpoint($f).'/'.$created['id'].'/reversal', $this->reversalPayload())->assertForbidden();
        DB::table('club_memberships')->where('club_id', $f['club']->id)->where('user_id', $f['officer']->id)->update(['status' => 'active']);
        $member = User::factory()->create(); Sanctum::actingAs($member); $this->getJson($this->endpoint($f))->assertForbidden();
        Sanctum::actingAs($f['officer']); DB::table('club_journal_entries')->where('id', ClubExpense::firstOrFail()->journal_entry_id)->update(['source_id' => 'corrupt']);
        $this->postJson($this->endpoint($f).'/'.$created['id'].'/reversal', $this->reversalPayload())->assertUnprocessable(); $this->assertDatabaseCount('club_expense_reversals', 0);
        DB::table('club_journal_entries')->where('id', ClubExpense::firstOrFail()->journal_entry_id)->update(['source_id' => $created['id']]);
        DB::listen(function ($query): void { if (str_contains($query->sql, 'insert into "club_expense_reversals"')) throw new RuntimeException('Synthetic source bind failure.'); });
        try { app(\App\Services\Finance\ClubExpenseReversalService::class)->reverse($f['club'], $created['id'], $f['officer'], $this->reversalPayload()); $this->fail('Late bind failure should roll back.'); } catch (RuntimeException $exception) { $this->assertSame('Synthetic source bind failure.', $exception->getMessage()); }
        $this->assertDatabaseCount('club_expense_reversals', 0); $this->assertSame(ClubJournalEntry::STATUS_POSTED, ClubExpense::firstOrFail()->journalEntry->status); $this->assertDatabaseCount('club_journal_entries', 2);
    }

    private function fixture(string $suffix): array
    {
        $club = Club::create(['name' => "Expense Correction {$suffix}", 'code' => substr('CW'.$suffix, 0, 10), 'timezone' => 'Asia/Kolkata', 'is_active' => true]); $officer = User::factory()->create();
        $permissions = collect(['finance.club.expenses.create', 'finance.club.expenses.view', 'finance.club.expenses.reverse', 'finance.club.statement.view'])->map(fn ($slug) => Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'group' => 'finance', 'description' => $slug]));
        $role = Role::create(['name' => "Treasurer {$suffix}", 'slug' => 'correction-'.strtolower($suffix), 'description' => 'Treasurer']); $role->permissions()->attach($permissions->pluck('id')); ClubMembership::create(['club_id' => $club->id, 'user_id' => $officer->id, 'role_id' => $role->id, 'status' => 'active']);
        $period = ClubAccountingPeriod::create(['club_id' => $club->id, 'lions_year' => '2027-28', 'start_date' => '2027-07-01', 'end_date' => '2028-06-30', 'status' => 'open', 'created_by_user_id' => $officer->id]);
        $bank = $this->account($club, $officer, 'BANK-MAIN', 'asset', 'debit', 'CASH_AND_BANK'); $equity = $this->account($club, $officer, 'OPENING-EQUITY', 'equity', 'credit', 'FUND_BALANCE_EQUITY'); $expense = $this->account($club, $officer, 'PRINTING', 'expense', 'debit', 'EXPENSES');
        $package = ClubAccountingCutoverPackage::create(['club_id' => $club->id, 'lions_year' => '2027-28', 'cutover_date' => '2027-07-01', 'chart_version' => 'CLUB-COA-2026-01', 'package_version' => 1, 'status' => 'draft', 'currency' => 'INR', 'canonical_checksum' => str_repeat('a', 64), 'reconciliation_difference_paise' => 0, 'dr033_classification' => 'OPENING-BALANCE-EQUITY', 'activation_ready' => false, 'created_by_user_id' => $officer->id, 'updated_by_user_id' => $officer->id]);
        $rows = collect([[$bank, 'debit'], [$equity, 'credit']])->map(fn ($row) => ClubAccountingCutoverScheduleRow::create(['club_id' => $club->id, 'cutover_package_id' => $package->id, 'schedule_type' => $row[0]->account_type === 'equity' ? 'equity_preview' : 'cash_on_hand', 'row_key' => 'OPENING-'.$row[0]->id, 'non_sensitive_label' => $row[0]->name, 'ledger_account_id' => $row[0]->id, 'direction' => $row[1], 'amount_paise' => 1000, 'currency' => 'INR', 'fund_code' => 'GENERAL_CLUB_FUND']));
        $opening = app(ClubJournalPostingService::class)->post(new JournalPostingRequest($club->id, $period->id, '2027-07-01', 'Opening', 'INR', 'opening_balance_activation', $package->public_id, 'activated', (string) Str::uuid(), $officer->id, [new JournalLineInput($bank->id, 'debit', 1000, fundCode: 'GENERAL_CLUB_FUND', openingScheduleRowId: $rows[0]->id), new JournalLineInput($equity->id, 'credit', 1000, fundCode: 'GENERAL_CLUB_FUND', openingScheduleRowId: $rows[1]->id)]));
        DB::table('club_accounting_cutover_packages')->where('id', $package->id)->update(['status' => 'activation_authorized', 'activation_ready' => true]); DB::table('club_accounting_cutover_packages')->where('id', $package->id)->update(['status' => 'posted', 'opening_journal_entry_id' => $opening->id, 'posted_at' => now(), 'posted_by_user_id' => $officer->id]);
        return compact('club', 'officer', 'period', 'bank', 'expense');
    }

    private function account(Club $club, User $actor, string $code, string $type, string $normal, string $classification): LedgerAccount { return LedgerAccount::create(['club_id' => $club->id, 'code' => $code, 'name' => $code, 'account_type' => $type, 'normal_balance' => $normal, 'currency' => 'INR', 'is_active' => true, 'created_by_user_id' => $actor->id, 'origin_kind' => 'custom', 'statement_classification' => $classification]); }
    private function endpoint(array $f): string { return "/api/v1/clubs/{$f['club']->id}/finance/expenses"; }
    private function statement(array $f): string { return "/api/v1/clubs/{$f['club']->id}/finance/account-statement?lions_year=2027-28"; }
    private function record(array $f, array $changes = []): array { config(['instance.organisation_code' => $f['club']->code]); return $this->postJson($this->endpoint($f), $this->expensePayload($f, $changes))->assertCreated()->json('data'); }
    private function expensePayload(array $f, array $changes = []): array { return [...['accounting_date' => '2027-08-10', 'expense_account' => $f['expense']->public_id, 'payment_account' => $f['bank']->public_id, 'fund' => 'SERVICE_ACTIVITY_FUND', 'amount_paise' => 450000, 'payee' => 'Tenant Printer', 'description' => 'Programme printing', 'external_reference' => 'INV-42', 'idempotency_key' => (string) Str::uuid()], ...$changes]; }
    private function reversalPayload(array $changes = []): array { return [...['accounting_date' => '2027-08-12', 'reason' => 'Incorrect amount', 'idempotency_key' => (string) Str::uuid()], ...$changes]; }
}
