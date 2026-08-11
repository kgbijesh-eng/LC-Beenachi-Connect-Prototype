<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubAccountingCutoverPackage;
use App\Models\ClubAccountingCutoverScheduleRow;
use App\Models\ClubAccountingPeriod;
use App\Models\ClubExpenseReversal;
use App\Models\LedgerAccount;
use App\Models\User;
use App\Services\Finance\ClubExpenseRecordingService;
use App\Services\Finance\ClubExpenseReversalService;
use App\Services\Finance\ClubJournalPostingService;
use App\Services\Finance\JournalLineInput;
use App\Services\Finance\JournalPostingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class Milestone3iizwPostgresExpenseReversalConcurrencyTest extends TestCase
{
    public function test_all_expense_reversal_races_serialize_to_one_linked_effect(): void
    {
        $this->requireDisposablePostgres();
        $exact = $this->fixture('RX'); $expense = $this->expense($exact); $key = (string) Str::uuid(); $payload = $this->payload($key);
        $results = $this->race($exact, $expense->public_id, $payload, $payload); $this->assertSame(['success', 'success'], collect($results)->pluck('status')->sort()->values()->all()); $this->assertSame($results[0]['reversal'], $results[1]['reversal']); $this->assertEffect($exact);

        $changed = $this->fixture('RC'); $expense = $this->expense($changed); $key = (string) Str::uuid();
        $results = $this->race($changed, $expense->public_id, $this->payload($key), $this->payload($key, 'Changed reason')); $this->assertSame(['conflict', 'success'], collect($results)->pluck('status')->sort()->values()->all()); $this->assertContains('EXPENSE_REVERSAL_CONFLICT', collect($results)->pluck('code')->filter()->all()); $this->assertEffect($changed);

        $different = $this->fixture('RD'); $expense = $this->expense($different);
        $results = $this->race($different, $expense->public_id, $this->payload((string) Str::uuid()), $this->payload((string) Str::uuid())); $this->assertSame(['conflict', 'success'], collect($results)->pluck('status')->sort()->values()->all()); $this->assertEffect($different);
    }

    private function requireDisposablePostgres(): void
    {
        $this->assertSame('1', env('RUN_3IIZW_POSTGRES_CONCURRENCY')); $this->assertSame('pgsql', DB::connection()->getDriverName());
        $this->assertSame('lcb_3iizw_expense_reversal_concurrency_test', DB::connection()->getDatabaseName()); $this->assertNotSame('lc_beenachi_connect_reconstructed_20260727', DB::connection()->getDatabaseName());
    }

    private function race(array $fixture, string $expense, array $first, array $second): array
    {
        $barrier = sys_get_temp_dir().DIRECTORY_SEPARATOR.'3iizw-reversal-'.Str::lower(Str::random(10)); mkdir($barrier, 0700, true); $processes = [];
        foreach ([$first, $second] as $index => $payload) { $request = $barrier.DIRECTORY_SEPARATOR."request.{$index}.json"; file_put_contents($request, json_encode(['club' => $fixture['club']->id, 'actor' => $fixture['actor']->id, 'expense' => $expense, 'payload' => $payload], JSON_THROW_ON_ERROR)); $processes[$index] = new Process([PHP_BINARY, base_path('tests/Support/Milestone3iizwExpenseReversalWorker.php'), $request, $barrier, (string) $index], base_path(), timeout: 40); $processes[$index]->start(); }
        $this->await(fn () => file_exists($barrier.DIRECTORY_SEPARATOR.'ready.0') && file_exists($barrier.DIRECTORY_SEPARATOR.'ready.1')); file_put_contents($barrier.DIRECTORY_SEPARATOR.'go', '1');
        foreach ($processes as $process) { $process->wait(); $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput()); }
        return array_map(fn ($index) => json_decode((string) file_get_contents($barrier.DIRECTORY_SEPARATOR."result.{$index}.json"), true, flags: JSON_THROW_ON_ERROR), [0, 1]);
    }

    private function fixture(string $suffix): array
    {
        $club = Club::create(['name' => "3IIZW {$suffix}", 'code' => "3IIZW{$suffix}", 'timezone' => 'Asia/Kolkata', 'is_active' => true]); $actor = User::factory()->create(); $period = ClubAccountingPeriod::create(['club_id' => $club->id, 'lions_year' => '2027-28', 'start_date' => '2027-07-01', 'end_date' => '2028-06-30', 'status' => 'open', 'created_by_user_id' => $actor->id]);
        $bank = $this->account($club, $actor, "BANK-{$suffix}", 'asset', 'debit', 'CASH_AND_BANK'); $equity = $this->account($club, $actor, "EQUITY-{$suffix}", 'equity', 'credit', 'FUND_BALANCE_EQUITY'); $expenseAccount = $this->account($club, $actor, "EXPENSE-{$suffix}", 'expense', 'debit', 'EXPENSES');
        $package = ClubAccountingCutoverPackage::create(['club_id' => $club->id, 'lions_year' => '2027-28', 'cutover_date' => '2027-07-01', 'chart_version' => 'CLUB-COA-2026-01', 'package_version' => 1, 'status' => 'draft', 'currency' => 'INR', 'canonical_checksum' => str_repeat('e', 64), 'reconciliation_difference_paise' => 0, 'dr033_classification' => 'OPENING-BALANCE-EQUITY', 'activation_ready' => false, 'created_by_user_id' => $actor->id, 'updated_by_user_id' => $actor->id]);
        $rows = collect([[$bank, 'debit'], [$equity, 'credit']])->map(fn ($row) => ClubAccountingCutoverScheduleRow::create(['club_id' => $club->id, 'cutover_package_id' => $package->id, 'schedule_type' => $row[0]->account_type === 'equity' ? 'equity_preview' : 'cash_on_hand', 'row_key' => 'OPENING-'.$row[0]->id, 'non_sensitive_label' => $row[0]->name, 'ledger_account_id' => $row[0]->id, 'direction' => $row[1], 'amount_paise' => 1000, 'currency' => 'INR', 'fund_code' => 'GENERAL_CLUB_FUND']));
        $opening = app(ClubJournalPostingService::class)->post(new JournalPostingRequest($club->id, $period->id, '2027-07-01', 'Opening', 'INR', 'opening_balance_activation', $package->public_id, 'activated', (string) Str::uuid(), $actor->id, [new JournalLineInput($bank->id, 'debit', 1000, fundCode: 'GENERAL_CLUB_FUND', openingScheduleRowId: $rows[0]->id), new JournalLineInput($equity->id, 'credit', 1000, fundCode: 'GENERAL_CLUB_FUND', openingScheduleRowId: $rows[1]->id)])); DB::table('club_accounting_cutover_packages')->where('id', $package->id)->update(['status' => 'activation_authorized', 'activation_ready' => true]); DB::table('club_accounting_cutover_packages')->where('id', $package->id)->update(['status' => 'posted', 'opening_journal_entry_id' => $opening->id, 'posted_at' => now(), 'posted_by_user_id' => $actor->id]);
        return compact('club', 'actor', 'period', 'bank', 'expenseAccount');
    }

    private function expense(array $f) { return app(ClubExpenseRecordingService::class)->record($f['club'], $f['actor'], ['accounting_date' => '2027-08-10', 'expense_account' => $f['expenseAccount']->public_id, 'payment_account' => $f['bank']->public_id, 'fund' => 'SERVICE_ACTIVITY_FUND', 'amount_paise' => 450000, 'payee' => 'Synthetic', 'description' => 'Synthetic expense', 'external_reference' => '3IIZW', 'idempotency_key' => (string) Str::uuid()]); }
    private function payload(string $key, string $reason = 'Incorrect amount'): array { return ['accounting_date' => '2027-08-12', 'reason' => $reason, 'idempotency_key' => $key]; }
    private function account(Club $club, User $actor, string $code, string $type, string $normal, string $classification): LedgerAccount { return LedgerAccount::create(['club_id' => $club->id, 'code' => $code, 'name' => $code, 'account_type' => $type, 'normal_balance' => $normal, 'currency' => 'INR', 'is_active' => true, 'created_by_user_id' => $actor->id, 'origin_kind' => 'custom', 'statement_classification' => $classification]); }
    private function assertEffect(array $f): void { $this->assertSame(1, ClubExpenseReversal::query()->where('club_id', $f['club']->id)->count()); $journalIds = DB::table('club_journal_entries')->where('club_id', $f['club']->id)->where('source_type', ClubExpenseReversalService::SOURCE_TYPE)->pluck('id'); $this->assertCount(1, $journalIds); $this->assertSame(2, DB::table('club_journal_lines')->whereIn('journal_entry_id', $journalIds)->count()); $this->assertSame(['credit', 'debit'], DB::table('club_journal_lines')->whereIn('journal_entry_id', $journalIds)->orderBy('line_number')->pluck('direction')->all()); }
    private function await(callable $condition): void { $deadline = microtime(true) + 20; while (! $condition()) { if (microtime(true) >= $deadline) throw new \RuntimeException('Barrier timeout.'); usleep(10000); } }
}
