<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubAccountingCutoverPackage;
use App\Models\ClubAccountingCutoverScheduleRow;
use App\Models\ClubAccountingPeriod;
use App\Models\ClubExpense;
use App\Models\LedgerAccount;
use App\Models\User;
use App\Services\Finance\ClubExpenseRecordingService;
use App\Services\Finance\Exceptions\ClubExpenseException;
use App\Services\Finance\ClubJournalPostingService;
use App\Services\Finance\JournalLineInput;
use App\Services\Finance\JournalPostingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use Throwable;

final class Milestone3iizvPostgresExpenseConcurrencyTest extends TestCase
{
    public function test_concurrent_exact_and_changed_replays_are_serialized_to_one_accounting_effect(): void
    {
        $this->requireDisposablePostgres();
        $exact = $this->fixture('EXACT'); $key = (string) Str::uuid(); $payload = $this->payload($exact, $key, 450000);
        $results = $this->race($exact, $payload, $payload);
        $this->assertSame(['success', 'success'], collect($results)->pluck('status')->sort()->values()->all());
        $this->assertSame($results[0]['expense'], $results[1]['expense']); $this->assertSame($results[0]['journal'], $results[1]['journal']);
        $this->assertEffectCounts($exact, 1, 1, 2);

        $changed = $this->fixture('CHANGED'); $changedKey = (string) Str::uuid();
        $results = $this->race($changed, $this->payload($changed, $changedKey, 10000), $this->payload($changed, $changedKey, 20000));
        $this->assertSame(['conflict', 'success'], collect($results)->pluck('status')->sort()->values()->all());
        $this->assertContains('EXPENSE_IDEMPOTENCY_CONFLICT', collect($results)->pluck('code')->filter()->all());
        $this->assertEffectCounts($changed, 1, 1, 2);
        $winner = ClubExpense::query()->where('club_id', $changed['club']->id)->firstOrFail();
        $this->assertContains($winner->amount_paise, [10000, 20000]);
    }

    public function test_tenant_key_and_account_isolation_and_atomic_failure_hold_on_postgres(): void
    {
        $this->requireDisposablePostgres(); $a = $this->fixture('TENANTA'); $b = $this->fixture('TENANTB'); $key = (string) Str::uuid();
        $aExpense = app(ClubExpenseRecordingService::class)->record($a['club'], $a['actor'], $this->payload($a, $key, 101));
        $bExpense = app(ClubExpenseRecordingService::class)->record($b['club'], $b['actor'], $this->payload($b, $key, 202));
        $this->assertNotSame($aExpense->public_id, $bExpense->public_id);
        $this->assertSame(1, ClubExpense::query()->where('club_id', $a['club']->id)->where('idempotency_key', $key)->count());
        $this->assertSame(1, ClubExpense::query()->where('club_id', $b['club']->id)->where('idempotency_key', $key)->count());
        foreach ([['expense_account' => $b['expense']->public_id], ['payment_account' => $b['bank']->public_id]] as $change) {
            try { app(ClubExpenseRecordingService::class)->record($a['club'], $a['actor'], [...$this->payload($a, (string) Str::uuid(), 303), ...$change]); $this->fail('Cross-club account selection must fail.'); }
            catch (ClubExpenseException) { $this->assertTrue(true); }
        }

        $atomic = $this->fixture('ATOMIC', catalogueExpense: true);
        try { app(ClubExpenseRecordingService::class)->record($atomic['club'], $atomic['actor'], $this->payload($atomic, (string) Str::uuid(), 404, 'GENERAL_CLUB_FUND')); $this->fail('Invalid catalogue fund must fail during journal posting.'); }
        catch (Throwable) { $this->assertSame(0, ClubExpense::query()->where('club_id', $atomic['club']->id)->count()); }
        $this->assertSame(0, DB::table('club_journal_entries')->where('club_id', $atomic['club']->id)->where('source_type', ClubExpenseRecordingService::SOURCE_TYPE)->count());
        $this->assertSame(0, DB::table('club_journal_lines')->join('club_journal_entries', 'club_journal_entries.id', '=', 'club_journal_lines.journal_entry_id')->where('club_journal_entries.club_id', $atomic['club']->id)->where('club_journal_entries.source_type', ClubExpenseRecordingService::SOURCE_TYPE)->count());
    }

    private function requireDisposablePostgres(): void
    {
        $this->assertSame('1', env('RUN_3IIZV_POSTGRES_CONCURRENCY'));
        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $this->assertSame('lcb_3iizv_expense_concurrency_test', DB::connection()->getDatabaseName());
        $this->assertNotSame('lc_beenachi_connect_reconstructed_20260727', DB::connection()->getDatabaseName());
    }

    private function race(array $fixture, array $first, array $second): array
    {
        $barrier = sys_get_temp_dir().DIRECTORY_SEPARATOR.'3iizv-expense-'.Str::lower(Str::random(10)); mkdir($barrier, 0700, true);
        $processes = [];
        foreach ([$first, $second] as $index => $payload) {
            $request = $barrier.DIRECTORY_SEPARATOR."request.{$index}.json";
            file_put_contents($request, json_encode(['club' => $fixture['club']->id, 'actor' => $fixture['actor']->id, 'payload' => $payload], JSON_THROW_ON_ERROR));
            $processes[$index] = new Process([PHP_BINARY, base_path('tests/Support/Milestone3iizvExpenseWorker.php'), $request, $barrier, (string) $index], base_path(), timeout: 40);
            $processes[$index]->start();
        }
        $this->await(fn () => file_exists($barrier.DIRECTORY_SEPARATOR.'ready.0') && file_exists($barrier.DIRECTORY_SEPARATOR.'ready.1'));
        file_put_contents($barrier.DIRECTORY_SEPARATOR.'go', '1');
        foreach ($processes as $process) { $process->wait(); $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput()); }
        return array_map(fn ($index) => json_decode((string) file_get_contents($barrier.DIRECTORY_SEPARATOR."result.{$index}.json"), true, flags: JSON_THROW_ON_ERROR), [0, 1]);
    }

    private function fixture(string $suffix, bool $catalogueExpense = false): array
    {
        $club = Club::create(['name' => "3IIZV {$suffix}", 'code' => "3IIZV{$suffix}", 'timezone' => 'Asia/Kolkata', 'is_active' => true]); $actor = User::factory()->create();
        $period = ClubAccountingPeriod::create(['club_id' => $club->id, 'lions_year' => '2027-28', 'start_date' => '2027-07-01', 'end_date' => '2028-06-30', 'status' => 'open', 'created_by_user_id' => $actor->id]);
        $bank = $this->account($club, $actor, "BANK-{$suffix}", 'asset', 'debit', 'CASH_AND_BANK'); $equity = $this->account($club, $actor, "EQUITY-{$suffix}", 'equity', 'credit', 'FUND_BALANCE_EQUITY');
        $expense = $this->account($club, $actor, $catalogueExpense ? 'EXPENSE-SERVICE-ACTIVITY' : "EXPENSE-{$suffix}", 'expense', 'debit', 'EXPENSES', $catalogueExpense ? 'catalogue' : 'custom', $catalogueExpense ? 'EXPENSE-SERVICE-ACTIVITY' : null);
        $package = ClubAccountingCutoverPackage::create(['club_id' => $club->id, 'lions_year' => '2027-28', 'cutover_date' => '2027-07-01', 'chart_version' => 'CLUB-COA-2026-01', 'package_version' => 1, 'status' => 'draft', 'currency' => 'INR', 'canonical_checksum' => str_repeat('d', 64), 'reconciliation_difference_paise' => 0, 'dr033_classification' => 'OPENING-BALANCE-EQUITY', 'activation_ready' => false, 'created_by_user_id' => $actor->id, 'updated_by_user_id' => $actor->id]);
        $rows = collect([[$bank, 'debit'], [$equity, 'credit']])->map(fn ($row) => ClubAccountingCutoverScheduleRow::create(['club_id' => $club->id, 'cutover_package_id' => $package->id, 'schedule_type' => $row[0]->account_type === 'equity' ? 'equity_preview' : 'cash_on_hand', 'row_key' => 'OPENING-'.$row[0]->id, 'non_sensitive_label' => $row[0]->name, 'ledger_account_id' => $row[0]->id, 'direction' => $row[1], 'amount_paise' => 1000, 'currency' => 'INR', 'fund_code' => 'GENERAL_CLUB_FUND']));
        $opening = app(ClubJournalPostingService::class)->post(new JournalPostingRequest($club->id, $period->id, '2027-07-01', 'Opening', 'INR', 'opening_balance_activation', $package->public_id, 'activated', (string) Str::uuid(), $actor->id, [new JournalLineInput($bank->id, 'debit', 1000, fundCode: 'GENERAL_CLUB_FUND', openingScheduleRowId: $rows[0]->id), new JournalLineInput($equity->id, 'credit', 1000, fundCode: 'GENERAL_CLUB_FUND', openingScheduleRowId: $rows[1]->id)]));
        DB::table('club_accounting_cutover_packages')->where('id', $package->id)->update(['status' => 'activation_authorized', 'activation_ready' => true]); DB::table('club_accounting_cutover_packages')->where('id', $package->id)->update(['status' => 'posted', 'opening_journal_entry_id' => $opening->id, 'posted_at' => now(), 'posted_by_user_id' => $actor->id]);
        return compact('club', 'actor', 'period', 'bank', 'expense');
    }

    private function account(Club $club, User $actor, string $code, string $type, string $normal, string $classification, string $origin = 'custom', ?string $template = null): LedgerAccount { return LedgerAccount::create(['club_id' => $club->id, 'code' => $code, 'name' => $code, 'account_type' => $type, 'normal_balance' => $normal, 'currency' => 'INR', 'is_active' => true, 'created_by_user_id' => $actor->id, 'origin_kind' => $origin, 'origin_template_code' => $template, 'statement_classification' => $classification]); }
    private function payload(array $f, string $key, int $amount, string $fund = 'SERVICE_ACTIVITY_FUND'): array { return ['accounting_date' => '2027-08-10', 'expense_account' => $f['expense']->public_id, 'payment_account' => $f['bank']->public_id, 'fund' => $fund, 'amount_paise' => $amount, 'payee' => 'Synthetic Payee', 'description' => 'Synthetic expense', 'external_reference' => 'SYNTHETIC', 'idempotency_key' => $key]; }
    private function assertEffectCounts(array $f, int $expenses, int $journals, int $lines): void { $this->assertSame($expenses, ClubExpense::query()->where('club_id', $f['club']->id)->count()); $journalIds = DB::table('club_journal_entries')->where('club_id', $f['club']->id)->where('source_type', ClubExpenseRecordingService::SOURCE_TYPE)->pluck('id'); $this->assertCount($journals, $journalIds); $this->assertSame($lines, DB::table('club_journal_lines')->whereIn('journal_entry_id', $journalIds)->count()); $actual = DB::table('club_journal_lines')->whereIn('journal_entry_id', $journalIds)->orderBy('line_number')->get(); $this->assertSame(['debit', 'credit'], $actual->pluck('direction')->all()); $this->assertSame(1, $actual->pluck('amount_paise')->unique()->count()); $this->assertSame(1, $actual->pluck('fund_code')->unique()->count()); }
    private function await(callable $condition): void { $deadline = microtime(true) + 20; while (! $condition()) { if (microtime(true) >= $deadline) throw new \RuntimeException('Barrier timeout.'); usleep(10000); } }
}
