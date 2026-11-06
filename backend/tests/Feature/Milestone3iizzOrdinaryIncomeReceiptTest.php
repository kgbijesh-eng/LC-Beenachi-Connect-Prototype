<?php

declare(strict_types=1);
namespace Tests\Feature;

use App\Models\{Club, ClubAccountingCutoverPackage, ClubAccountingCutoverScheduleRow, ClubAccountingPeriod, ClubJournalEntry, ClubMembership, ClubOrdinaryIncomeReceipt, ClubOrdinaryIncomeReceiptCorrection, LedgerAccount, Permission, Role, User};
use App\Services\Finance\{ClubJournalPostingService, ClubOrdinaryIncomeReceiptService, JournalLineInput, JournalPostingRequest};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Support\RefreshesDatabaseWithoutTransactions;

final class Milestone3iizzOrdinaryIncomeReceiptTest extends TestCase
{
    use RefreshesDatabaseWithoutTransactions;

    public function test_receipt_posts_exact_same_fund_income_replays_and_changes_statement(): void
    {
        $f = $this->fixture('A'); $other = $this->fixture('H'); config(['instance.organisation_code' => $other['club']->code]); Sanctum::actingAs($other['actor']); $otherBefore = $this->getJson($this->statement($other))->assertOk()->json('data.snapshot_fingerprint'); config(['instance.organisation_code' => $f['club']->code]); Sanctum::actingAs($f['actor']); $payload = $this->payload($f, (string) Str::uuid());
        $before = $this->getJson($this->statement($f))->assertOk()->json('data.snapshot_fingerprint');
        $created = $this->postJson($this->endpoint($f), $payload)->assertCreated(); $id = $created->json('data.id');
        $this->assertSame($id, $this->postJson($this->endpoint($f), $payload)->assertCreated()->json('data.id'));
        $receipt = ClubOrdinaryIncomeReceipt::firstOrFail(); $lines = DB::table('club_journal_lines')->where('journal_entry_id', $receipt->journal_entry_id)->orderBy('line_number')->get();
        $this->assertSame([$f['bank']->id, $f['income']->id], $lines->pluck('ledger_account_id')->all()); $this->assertSame(['debit', 'credit'], $lines->pluck('direction')->all()); $this->assertSame([2500000, 2500000], $lines->pluck('amount_paise')->all()); $this->assertSame(['GENERAL_CLUB_FUND'], $lines->pluck('fund_code')->unique()->values()->all());
        $statement = $this->getJson($this->statement($f))->assertOk(); $this->assertNotSame($before, $statement->json('data.snapshot_fingerprint')); $classes = collect($statement->json('data.funds'))->flatMap(fn ($fund) => $fund['classifications']); $this->assertSame(2500000, $classes->firstWhere('classification', 'CASH_AND_BANK')['totals']['period_debit_paise']); $this->assertSame(2500000, $classes->firstWhere('classification', 'INCOME')['totals']['period_credit_paise']); $this->assertSame(0, $classes->firstWhere('classification', 'EXPENSES')['totals']['period_debit_paise']); $this->assertSame(0, $classes->firstWhere('classification', 'LIABILITIES')['totals']['period_credit_paise']); $this->assertSame(2500000, $statement->json('data.overall_trial_balance.period_debits_paise')); $this->assertSame(2500000, $statement->json('data.overall_trial_balance.period_credits_paise'));
        config(['instance.organisation_code' => $other['club']->code]); Sanctum::actingAs($other['actor']); $this->assertSame($otherBefore, $this->getJson($this->statement($other))->assertOk()->json('data.snapshot_fingerprint')); config(['instance.organisation_code' => $f['club']->code]); Sanctum::actingAs($f['actor']);
        $this->postJson($this->endpoint($f), [...$payload, 'amount_paise' => 2500001])->assertConflict()->assertJsonPath('code', 'ORDINARY_INCOME_RECEIPT_IDEMPOTENCY_CONFLICT');
        $this->getJson($this->endpoint($f))->assertOk()->assertJsonPath('data.0.id', $id); $this->getJson($this->endpoint($f)."/{$id}")->assertOk()->assertJsonMissingPath('data.club_id');
    }

    public function test_accounts_subtypes_amount_period_activation_and_tenant_scope_fail_closed(): void
    {
        $f = $this->fixture('B'); $other = $this->fixture('C'); config(['instance.organisation_code' => $f['club']->code]); Sanctum::actingAs($f['actor']);
        foreach ([['subtype' => 'donation'], ['receiving_account' => $f['income']->public_id], ['income_account' => $f['bank']->public_id], ['income_account' => $other['income']->public_id], ['amount_paise' => 0]] as $change) $this->postJson($this->endpoint($f), [...$this->payload($f, (string) Str::uuid()), ...$change])->assertUnprocessable();
        DB::table('ledger_accounts')->where('id', $f['income']->id)->update(['is_active' => false]); $this->postJson($this->endpoint($f), $this->payload($f, (string) Str::uuid()))->assertUnprocessable(); DB::table('ledger_accounts')->where('id', $f['income']->id)->update(['is_active' => true]);
        DB::table('club_accounting_periods')->where('id', $f['period']->id)->update(['status' => 'closed', 'closed_at' => now(), 'closed_by_user_id' => $f['actor']->id]); $this->postJson($this->endpoint($f), $this->payload($f, (string) Str::uuid()))->assertUnprocessable();
        $this->assertSame(0, ClubOrdinaryIncomeReceipt::count());
    }

    public function test_permission_matrix_and_tenant_scoped_uuid_isolation(): void
    {
        $president = $this->fixture('D', 'president-admin', true, true); $secretary = $this->fixture('E', 'secretary', false, true); $member = $this->fixture('F', 'member', false, false); $key = (string) Str::uuid();
        config(['instance.organisation_code' => $president['club']->code]); Sanctum::actingAs($president['actor']); $this->postJson($this->endpoint($president), $this->payload($president, $key))->assertCreated();
        config(['instance.organisation_code' => $secretary['club']->code]); Sanctum::actingAs($secretary['actor']); $this->postJson($this->endpoint($secretary), $this->payload($secretary, $key))->assertForbidden(); $this->getJson($this->endpoint($secretary))->assertOk();
        config(['instance.organisation_code' => $member['club']->code]); Sanctum::actingAs($member['actor']); $this->getJson($this->endpoint($member))->assertForbidden();
        config(['instance.organisation_code' => $secretary['club']->code]); Sanctum::actingAs($secretary['actor']); $this->postJson($this->endpoint($secretary), $this->payload($secretary, (string) Str::uuid()))->assertForbidden();
        config(['instance.organisation_code' => $president['club']->code]); Sanctum::actingAs($president['actor']); $this->postJson($this->endpoint($president), [...$this->payload($president, $key), 'idempotency_key' => $key])->assertCreated();
    }

    public function test_controlled_journal_failure_rolls_back_source_and_journal(): void
    {
        $f = $this->fixture('G'); $suffix = Str::lower(Str::random(12)); $trigger = "reject_ordinary_income_journal_{$suffix}"; $function = "reject_ordinary_income_journal_fn_{$suffix}"; $driver = DB::connection()->getDriverName(); $journalCount = DB::table('club_journal_entries')->where('club_id', $f['club']->id)->count(); $lineCount = DB::table('club_journal_lines')->where('club_id', $f['club']->id)->count();
        $thrown = null;
        try { if ($driver === 'pgsql') { DB::unprepared("CREATE FUNCTION {$function}() RETURNS trigger LANGUAGE plpgsql AS \$trigger\$ BEGIN IF NEW.source_type = 'club_ordinary_income_receipt' THEN RAISE EXCEPTION 'controlled ordinary income failure'; END IF; RETURN NEW; END; \$trigger\$"); DB::unprepared("CREATE TRIGGER {$trigger} BEFORE INSERT ON club_journal_entries FOR EACH ROW EXECUTE FUNCTION {$function}()"); } else { DB::unprepared("CREATE TRIGGER {$trigger} BEFORE INSERT ON club_journal_entries WHEN NEW.source_type = 'club_ordinary_income_receipt' BEGIN SELECT RAISE(ABORT, 'controlled ordinary income failure'); END"); } try { app(ClubOrdinaryIncomeReceiptService::class)->record($f['club'], $f['actor'], $this->payload($f, (string) Str::uuid())); } catch (\Throwable $exception) { $thrown = $exception; } }
        finally { if ($driver === 'pgsql') { DB::unprepared("DROP TRIGGER IF EXISTS {$trigger} ON club_journal_entries"); DB::unprepared("DROP FUNCTION IF EXISTS {$function}()"); } else { DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}"); } }
        $this->assertNotNull($thrown, 'Controlled journal failure must propagate.'); $this->assertSame(0, ClubOrdinaryIncomeReceipt::query()->where('club_id', $f['club']->id)->count()); $this->assertSame(0, DB::table('club_journal_entries')->where('club_id', $f['club']->id)->where('source_type', ClubOrdinaryIncomeReceiptService::SOURCE_TYPE)->count()); $this->assertSame($journalCount, DB::table('club_journal_entries')->where('club_id', $f['club']->id)->count()); $this->assertSame($lineCount, DB::table('club_journal_lines')->where('club_id', $f['club']->id)->count());
    }

    public function test_exact_replay_fails_closed_when_persisted_journal_provenance_is_corrupt(): void
    {
        $f = $this->fixture('I'); config(['instance.organisation_code' => $f['club']->code]); Sanctum::actingAs($f['actor']); $payload = $this->payload($f, (string) Str::uuid()); $this->postJson($this->endpoint($f), $payload)->assertCreated();
        $receipt = ClubOrdinaryIncomeReceipt::firstOrFail(); DB::table('club_journal_entries')->where('id', $receipt->journal_entry_id)->update(['source_id' => (string) Str::uuid()]);
        $this->postJson($this->endpoint($f), $payload)->assertConflict()->assertJsonPath('code', 'ORDINARY_INCOME_RECEIPT_IDEMPOTENCY_CONFLICT');
        $this->assertSame(1, ClubOrdinaryIncomeReceipt::count()); $this->assertSame(1, DB::table('club_journal_entries')->where('source_type', ClubOrdinaryIncomeReceiptService::SOURCE_TYPE)->count());
    }

    public function test_linked_correction_is_exact_idempotent_and_supports_atomic_replacement(): void
    {
        $f = $this->fixture('J'); $permission = Permission::firstOrCreate(['slug' => 'finance.club.ordinary_income_receipts.reverse'], ['name' => 'Reverse ordinary income', 'group' => 'finance', 'description' => 'Reverse']); ClubMembership::where('club_id', $f['club']->id)->where('user_id', $f['actor']->id)->firstOrFail()->role->permissions()->syncWithoutDetaching([$permission->id]); config(['instance.organisation_code' => $f['club']->code]); Sanctum::actingAs($f['actor']);
        $created = $this->postJson($this->endpoint($f), $this->payload($f, (string) Str::uuid()))->assertCreated()->json('data'); $original = ClubOrdinaryIncomeReceipt::firstOrFail(); $facts = $original->getAttributes(); $originalLines = DB::table('club_journal_lines')->where('journal_entry_id', $original->journal_entry_id)->orderBy('line_number')->get(); $key = (string) Str::uuid(); $payload = ['accounting_date' => '2027-08-12', 'reason' => 'Incorrect amount', 'idempotency_key' => $key];
        $first = $this->postJson($this->endpoint($f).'/'.$created['id'].'/correction', $payload)->assertOk()->assertJsonPath('data.status', 'reversed'); $replay = $this->postJson($this->endpoint($f).'/'.$created['id'].'/correction', $payload)->assertOk(); $this->assertSame($first->json('data.correction.id'), $replay->json('data.correction.id'));
        $correction = ClubOrdinaryIncomeReceiptCorrection::firstOrFail(); $reversal = ClubJournalEntry::with('lines')->findOrFail($correction->reversal_journal_entry_id); $this->assertSame(['credit', 'debit'], $reversal->lines->pluck('direction')->all()); $this->assertSame($originalLines->pluck('ledger_account_id')->all(), $reversal->lines->pluck('ledger_account_id')->all()); $this->assertSame($originalLines->pluck('amount_paise')->all(), $reversal->lines->pluck('amount_paise')->all()); $this->assertSame($originalLines->pluck('fund_code')->all(), $reversal->lines->pluck('fund_code')->all()); $this->assertSame($facts, ClubOrdinaryIncomeReceipt::findOrFail($original->id)->getAttributes()); $this->postJson($this->endpoint($f).'/'.$created['id'].'/correction', [...$payload, 'reason' => 'Changed'])->assertConflict();

        $g = $this->fixture('K'); ClubMembership::where('club_id', $g['club']->id)->where('user_id', $g['actor']->id)->firstOrFail()->role->permissions()->syncWithoutDetaching([$permission->id]); config(['instance.organisation_code' => $g['club']->code]); Sanctum::actingAs($g['actor']); $source = $this->postJson($this->endpoint($g), $this->payload($g, (string) Str::uuid()))->assertCreated()->json('data'); $replacement = $this->payload($g, (string) Str::uuid()); unset($replacement['idempotency_key']); $replacement['amount_paise'] = 2400000;
        $this->postJson($this->endpoint($g).'/'.$source['id'].'/correction', ['accounting_date' => '2027-08-12', 'reason' => 'Wrong amount', 'idempotency_key' => (string) Str::uuid(), 'replacement' => $replacement])->assertOk()->assertJsonPath('data.status', 'replaced'); $this->getJson($this->endpoint($g).'?status=replaced')->assertOk()->assertJsonPath('meta.total', 1);
    }

    private function fixture(string $suffix, string $roleSlug = 'treasurer', bool $create = true, bool $view = true): array
    {
        $club = Club::create(['name' => "Income {$suffix}", 'code' => "OI{$suffix}", 'timezone' => 'Asia/Kolkata', 'is_active' => true]); $actor = User::factory()->create(); $slugs = collect([$create ? 'finance.club.ordinary_income_receipts.create' : null, $view ? 'finance.club.ordinary_income_receipts.view' : null, 'finance.club.statement.view'])->filter(); $permissions = $slugs->map(fn ($slug) => Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'group' => 'finance', 'description' => $slug])); $role = Role::create(['name' => "Role {$suffix}", 'slug' => "{$roleSlug}-".strtolower($suffix), 'description' => $roleSlug]); $role->permissions()->attach($permissions->pluck('id')); ClubMembership::create(['club_id' => $club->id, 'user_id' => $actor->id, 'role_id' => $role->id, 'status' => 'active']);
        $period = ClubAccountingPeriod::create(['club_id' => $club->id, 'lions_year' => '2027-28', 'start_date' => '2027-07-01', 'end_date' => '2028-06-30', 'status' => 'open', 'created_by_user_id' => $actor->id]); $bank = $this->account($club, $actor, "BANK-{$suffix}", 'asset', 'debit', 'CASH_AND_BANK'); $income = $this->account($club, $actor, "INCOME-{$suffix}", 'income', 'credit', 'INCOME'); $equity = $this->account($club, $actor, "EQUITY-{$suffix}", 'equity', 'credit', 'FUND_BALANCE_EQUITY');
        $package = ClubAccountingCutoverPackage::create(['club_id' => $club->id, 'lions_year' => '2027-28', 'cutover_date' => '2027-07-01', 'chart_version' => 'CLUB-COA-2026-01', 'package_version' => 1, 'status' => 'draft', 'currency' => 'INR', 'canonical_checksum' => str_repeat('a', 64), 'reconciliation_difference_paise' => 0, 'dr033_classification' => 'OPENING-BALANCE-EQUITY', 'activation_ready' => false, 'created_by_user_id' => $actor->id, 'updated_by_user_id' => $actor->id]); $rows = collect([[$bank, 'debit'], [$equity, 'credit']])->map(fn ($row) => ClubAccountingCutoverScheduleRow::create(['club_id' => $club->id, 'cutover_package_id' => $package->id, 'schedule_type' => $row[0]->account_type === 'equity' ? 'equity_preview' : 'cash_on_hand', 'row_key' => 'OPENING-'.$row[0]->id, 'non_sensitive_label' => $row[0]->name, 'ledger_account_id' => $row[0]->id, 'direction' => $row[1], 'amount_paise' => 1000000, 'currency' => 'INR', 'fund_code' => 'GENERAL_CLUB_FUND'])); $opening = app(ClubJournalPostingService::class)->post(new JournalPostingRequest($club->id, $period->id, '2027-07-01', 'Opening', 'INR', 'opening_balance_activation', $package->public_id, 'activated', (string) Str::uuid(), $actor->id, [new JournalLineInput($bank->id, 'debit', 1000000, fundCode: 'GENERAL_CLUB_FUND', openingScheduleRowId: $rows[0]->id), new JournalLineInput($equity->id, 'credit', 1000000, fundCode: 'GENERAL_CLUB_FUND', openingScheduleRowId: $rows[1]->id)])); DB::table('club_accounting_cutover_packages')->where('id', $package->id)->update(['status' => 'activation_authorized', 'activation_ready' => true]); DB::table('club_accounting_cutover_packages')->where('id', $package->id)->update(['status' => 'posted', 'opening_journal_entry_id' => $opening->id, 'posted_at' => now(), 'posted_by_user_id' => $actor->id]); return compact('club', 'actor', 'period', 'bank', 'income');
    }
    private function account(Club $club, User $actor, string $code, string $type, string $normal, string $classification): LedgerAccount { return LedgerAccount::create(['club_id' => $club->id, 'code' => $code, 'name' => $code, 'account_type' => $type, 'normal_balance' => $normal, 'currency' => 'INR', 'is_active' => true, 'created_by_user_id' => $actor->id, 'origin_kind' => 'custom', 'statement_classification' => $classification]); }
    private function payload(array $f, string $key): array { return ['subtype' => 'sponsorship', 'accounting_date' => '2027-08-10', 'receiving_account' => $f['bank']->public_id, 'income_account' => $f['income']->public_id, 'fund' => 'GENERAL_CLUB_FUND', 'amount_paise' => 2500000, 'description' => 'Earned sponsorship income', 'payer_name' => 'Example Sponsor', 'external_reference' => 'AGR-2027-001', 'event_label' => 'Community Event', 'idempotency_key' => $key]; }
    private function endpoint(array $f): string { return "/api/v1/clubs/{$f['club']->id}/finance/ordinary-income-receipts"; }
    private function statement(array $f): string { return "/api/v1/clubs/{$f['club']->id}/finance/account-statement?lions_year=2027-28"; }
}
