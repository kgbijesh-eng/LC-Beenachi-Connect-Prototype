<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubAccountingCutoverPackage;
use App\Models\ClubAccountingCutoverScheduleRow;
use App\Models\ClubAccountingPeriod;
use App\Models\LedgerAccount;
use App\Models\User;
use App\Services\Finance\ClubAccountStatementProjectionService;
use App\Services\Finance\ClubAccountStatementQuery;
use App\Services\Finance\ClubJournalPostingService;
use App\Services\Finance\JournalLineInput;
use App\Services\Finance\JournalPostingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\RefreshesDatabaseWithoutTransactions;
use Tests\TestCase;

final class Milestone3iiztPostgresStatementSnapshotConcurrencyTest extends TestCase
{
    use RefreshesDatabaseWithoutTransactions;

    public function test_posting_committed_during_projection_cannot_create_a_mixed_snapshot(): void
    {
        $this->requireExplicitDisposablePostgres();
        $fixture = $this->fixture();
        $query = new ClubAccountStatementQuery($fixture['club']->id, '2027-28', null, null, null);
        $before = app(ClubAccountStatementProjectionService::class)->project($query);
        $barrier = sys_get_temp_dir().DIRECTORY_SEPARATOR.'3iizt-statement-'.Str::lower(Str::random(8));
        mkdir($barrier, 0700, true);
        $requestPath = $barrier.DIRECTORY_SEPARATOR.'request.json';
        file_put_contents($requestPath, json_encode([
            'clubId' => $fixture['club']->id, 'lionsYear' => '2027-28',
            'periodId' => null, 'from' => null, 'to' => null,
        ], JSON_THROW_ON_ERROR));
        $process = new Process([
            PHP_BINARY, base_path('tests/Support/Milestone3iiztPostgresStatementWorker.php'), $requestPath, $barrier,
        ], base_path(), env: ['INSTANCE_ORGANISATION_CODE' => $fixture['club']->code], timeout: 40);
        $process->start();
        $this->awaitFile($barrier.DIRECTORY_SEPARATOR.'snapshot.ready');

        $this->postJournal($fixture, '2027-07-20', 'concurrent-snapshot-change', 125);
        file_put_contents($barrier.DIRECTORY_SEPARATOR.'snapshot.release', '1');
        $process->wait();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());

        $during = json_decode((string) file_get_contents($barrier.DIRECTORY_SEPARATOR.'result.json'), true, flags: JSON_THROW_ON_ERROR);
        $after = app(ClubAccountStatementProjectionService::class)->project($query);
        $this->assertSame($before['snapshot_fingerprint'], $during['snapshot_fingerprint']);
        $this->assertSame($before['funds'], $during['funds']);
        $this->assertSame($before['overall_trial_balance'], $during['overall_trial_balance']);
        $this->assertNotSame($during['snapshot_fingerprint'], $after['snapshot_fingerprint']);
        $this->assertSame($during['overall_trial_balance']['period_debits_paise'] + 125, $after['overall_trial_balance']['period_debits_paise']);
        $this->assertSame($during['overall_trial_balance']['period_credits_paise'] + 125, $after['overall_trial_balance']['period_credits_paise']);
    }

    private function requireExplicitDisposablePostgres(): void
    {
        if (env('RUN_3IIZT_POSTGRES_CONCURRENCY') !== '1') {
            $this->markTestSkipped('Set RUN_3IIZT_POSTGRES_CONCURRENCY=1 on an explicitly disposable PostgreSQL database.');
        }
        $this->assertSame('pgsql', DB::connection()->getDriverName(), 'This test never falls back to SQLite.');
        $database = (string) DB::connection()->getDatabaseName();
        $this->assertStringStartsWith('lcb_', $database);
        $this->assertNotSame('lc_beenachi_connect_reconstructed_20260727', $database);
    }

    private function fixture(): array
    {
        $club = Club::create(['name' => '3IIZT Snapshot Club', 'code' => 'STMT3IIZT', 'timezone' => 'Asia/Kolkata', 'is_active' => true]);
        config(['instance.organisation_code' => $club->code]);
        $officer = User::factory()->create();
        $period = ClubAccountingPeriod::create([
            'club_id' => $club->id, 'lions_year' => '2027-28', 'start_date' => '2027-07-01',
            'end_date' => '2028-06-30', 'status' => 'open', 'created_by_user_id' => $officer->id,
        ]);
        $cash = $this->account($club, $officer, 'CASH-3IIZT', 'asset', 'debit', 'CASH_AND_BANK');
        $equity = $this->account($club, $officer, 'EQUITY-3IIZT', 'equity', 'credit', 'FUND_BALANCE_EQUITY');
        $income = $this->account($club, $officer, 'INCOME-3IIZT', 'income', 'credit', 'INCOME');
        $package = ClubAccountingCutoverPackage::create([
            'club_id' => $club->id, 'lions_year' => '2027-28', 'cutover_date' => '2027-07-01',
            'chart_version' => 'CLUB-COA-2026-01', 'package_version' => 1, 'status' => 'draft',
            'currency' => 'INR', 'canonical_checksum' => str_repeat('c', 64),
            'reconciliation_difference_paise' => 0, 'dr033_classification' => 'OPENING-BALANCE-EQUITY',
            'activation_ready' => false, 'created_by_user_id' => $officer->id, 'updated_by_user_id' => $officer->id,
        ]);
        $cashRow = $this->openingRow($package, $cash, 'debit');
        $equityRow = $this->openingRow($package, $equity, 'credit');
        $opening = $this->postJournal($this->compactFixture($club, $period, $officer, $cash, $income), '2027-07-01', 'opening-3iizt', 1000, $equity, $cashRow, $equityRow);
        DB::table('club_accounting_cutover_packages')->where('id', $package->id)->update([
            'status' => 'activation_authorized', 'activation_ready' => true,
        ]);
        DB::table('club_accounting_cutover_packages')->where('id', $package->id)->update([
            'status' => 'posted', 'opening_journal_entry_id' => $opening->id,
            'posted_at' => now(), 'posted_by_user_id' => $officer->id,
        ]);
        $fixture = $this->compactFixture($club, $period, $officer, $cash, $income);
        $this->postJournal($fixture, '2027-07-15', 'movement-3iizt', 200);

        return $fixture;
    }

    private function compactFixture(Club $club, ClubAccountingPeriod $period, User $officer, LedgerAccount $cash, LedgerAccount $income): array
    {
        return compact('club', 'period', 'officer', 'cash', 'income');
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

    private function openingRow(ClubAccountingCutoverPackage $package, LedgerAccount $account, string $direction): ClubAccountingCutoverScheduleRow
    {
        return ClubAccountingCutoverScheduleRow::create([
            'club_id' => $package->club_id, 'cutover_package_id' => $package->id,
            'schedule_type' => $account->account_type === 'equity' ? 'equity_preview' : 'cash_on_hand',
            'row_key' => 'OPENING-'.$account->code, 'non_sensitive_label' => $account->name,
            'ledger_account_id' => $account->id, 'presentation_group' => 'GENERAL-CLUB-FUND',
            'direction' => $direction, 'amount_paise' => 1000, 'fund_code' => 'GENERAL_CLUB_FUND',
        ]);
    }

    private function postJournal(array $fixture, string $date, string $event, int $amount, ?LedgerAccount $credit = null, ?ClubAccountingCutoverScheduleRow $debitRow = null, ?ClubAccountingCutoverScheduleRow $creditRow = null): object
    {
        return app(ClubJournalPostingService::class)->post(new JournalPostingRequest(
            clubId: $fixture['club']->id, accountingPeriodId: $fixture['period']->id,
            effectiveDate: $date, description: $event, currency: 'INR', sourceType: 'statement_snapshot_test',
            sourceId: $event, sourceEvent: $event, idempotencyKey: (string) Str::uuid(),
            postingActorUserId: $fixture['officer']->id,
            lines: [
                new JournalLineInput($fixture['cash']->id, 'debit', $amount, fundCode: 'GENERAL_CLUB_FUND', openingScheduleRowId: $debitRow?->id),
                new JournalLineInput(($credit ?? $fixture['income'])->id, 'credit', $amount, fundCode: 'GENERAL_CLUB_FUND', openingScheduleRowId: $creditRow?->id),
            ],
        ));
    }

    private function awaitFile(string $path): void
    {
        $deadline = microtime(true) + 20;
        while (! is_file($path) && microtime(true) < $deadline) {
            usleep(5_000);
        }
        $this->assertFileExists($path);
    }
}
