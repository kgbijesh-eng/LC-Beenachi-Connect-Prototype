<?php

declare(strict_types=1);
namespace Tests\Feature;

use App\Models\{ClubJournalEntry, ClubOrdinaryIncomeReceipt, ClubOrdinaryIncomeReceiptCorrection};
use App\Services\Finance\ClubOrdinaryIncomeReceiptCorrectionService;
use App\Services\Finance\ClubOrdinaryIncomeReceiptService;
use App\Services\Finance\Exceptions\{ClubOrdinaryIncomeReceiptCorrectionException, JournalIdempotencyConflictException};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\BuildsMilestone3ijaeFixture;
use Tests\TestCase;

final class Milestone3ijaePostgresOrdinaryIncomeReceiptCorrectionConcurrencyTest extends TestCase
{
    use BuildsMilestone3ijaeFixture;

    public function test_all_correction_races_preserve_one_complete_canonical_effect(): void
    {
        $this->requireDisposablePostgres();
        $exact = $this->fixture3ijae('PGEX'); $receipt = app(ClubOrdinaryIncomeReceiptService::class)->record($exact['club'], $exact['actor'], $this->receiptPayload3ijae($exact)); $key = (string) Str::uuid(); $payload = $this->correctionPayload3ijae($key); $results = $this->race($exact, $receipt->public_id, $payload, $payload); $this->assertSame(['success', 'success'], collect($results)->pluck('status')->sort()->values()->all()); $this->assertSame($results[0]['correction'], $results[1]['correction']); $this->assertEffect($exact, false);
        $different = $this->fixture3ijae('PGDI'); $receipt = app(ClubOrdinaryIncomeReceiptService::class)->record($different['club'], $different['actor'], $this->receiptPayload3ijae($different)); $results = $this->race($different, $receipt->public_id, $this->correctionPayload3ijae(), $this->correctionPayload3ijae()); $this->assertSame(['conflict', 'success'], collect($results)->pluck('status')->sort()->values()->all()); $this->assertEffect($different, false);
        $replacement = $this->fixture3ijae('PGRE'); $receipt = app(ClubOrdinaryIncomeReceiptService::class)->record($replacement['club'], $replacement['actor'], $this->receiptPayload3ijae($replacement)); $new = $this->receiptPayload3ijae($replacement); unset($new['idempotency_key']); $key = (string) Str::uuid(); $payload = $this->correctionPayload3ijae($key, $new); $results = $this->race($replacement, $receipt->public_id, $payload, $payload); $this->assertSame(['success', 'success'], collect($results)->pluck('status')->sort()->values()->all()); $this->assertSame($results[0]['replacement'], $results[1]['replacement']); $this->assertEffect($replacement, true);
        $collision = $this->fixture3ijae('PGID'); $receiptKey = (string) Str::uuid(); $receipt = app(ClubOrdinaryIncomeReceiptService::class)->record($collision['club'], $collision['actor'], $this->receiptPayload3ijae($collision, $receiptKey)); $originalJournalId = $receipt->journal_entry_id; try { app(ClubOrdinaryIncomeReceiptCorrectionService::class)->correct($collision['club'], $receipt->public_id, $collision['actor'], $this->correctionPayload3ijae($receiptKey)); $this->fail('Cross-source idempotency identity must not impersonate a correction.'); } catch (ClubOrdinaryIncomeReceiptCorrectionException $exception) { $this->assertSame('ORDINARY_INCOME_RECEIPT_CORRECTION_POSTING_FAILED', $exception->errorCode); $this->assertInstanceOf(JournalIdempotencyConflictException::class, $exception->getPrevious()); } $this->assertSame(0, ClubOrdinaryIncomeReceiptCorrection::where('club_id', $collision['club']->id)->count()); $this->assertSame(1, ClubOrdinaryIncomeReceipt::where('club_id', $collision['club']->id)->count()); $this->assertSame(0, ClubOrdinaryIncomeReceipt::where('club_id', $collision['club']->id)->whereNotNull('corrected_from_receipt_id')->count()); $this->assertSame(0, ClubJournalEntry::where('club_id', $collision['club']->id)->where('source_type', ClubOrdinaryIncomeReceiptCorrectionService::SOURCE_TYPE)->count()); $this->assertSame(ClubJournalEntry::STATUS_POSTED, ClubJournalEntry::findOrFail($originalJournalId)->status); $this->assertSame($receiptKey, strtolower(ClubJournalEntry::findOrFail($originalJournalId)->idempotency_key));
    }

    private function requireDisposablePostgres(): void { $database = DB::connection()->getDatabaseName(); $this->assertSame('1', env('RUN_3IJAE_POSTGRES_CONCURRENCY')); $this->assertSame('pgsql', DB::connection()->getDriverName()); $this->assertStringStartsWith('lcb_3ijae_', $database); $this->assertNotSame('lc_beenachi_connect_reconstructed_20260727', $database); }
    private function race(array $fixture, string $receipt, array $first, array $second): array
    {
        $barrier = sys_get_temp_dir().DIRECTORY_SEPARATOR.'3ijae-correction-'.Str::lower(Str::random(10)); mkdir($barrier, 0700, true); $processes = [];
        foreach ([$first, $second] as $index => $payload) { $request = $barrier.DIRECTORY_SEPARATOR."request.{$index}.json"; file_put_contents($request, json_encode(['club' => $fixture['club']->id, 'actor' => $fixture['actor']->id, 'receipt' => $receipt, 'payload' => $payload], JSON_THROW_ON_ERROR)); $processes[$index] = new Process([PHP_BINARY, base_path('tests/Support/Milestone3ijaeOrdinaryIncomeReceiptCorrectionWorker.php'), $request, $barrier, (string) $index], base_path(), timeout: 40); $processes[$index]->start(); }
        $this->await(fn () => file_exists($barrier.DIRECTORY_SEPARATOR.'ready.0') && file_exists($barrier.DIRECTORY_SEPARATOR.'ready.1')); file_put_contents($barrier.DIRECTORY_SEPARATOR.'go', '1'); foreach ($processes as $process) { $process->wait(); $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput()); }
        return array_map(fn ($index) => json_decode((string) file_get_contents($barrier.DIRECTORY_SEPARATOR."result.{$index}.json"), true, flags: JSON_THROW_ON_ERROR), [0, 1]);
    }
    private function assertEffect(array $fixture, bool $replacement): void { $correction = ClubOrdinaryIncomeReceiptCorrection::where('club_id', $fixture['club']->id)->firstOrFail(); $this->assertSame(1, ClubOrdinaryIncomeReceiptCorrection::where('club_id', $fixture['club']->id)->count()); $this->assertSame(1, DB::table('club_journal_entries')->where('club_id', $fixture['club']->id)->where('source_type', 'club_ordinary_income_receipt_correction')->count()); $this->assertSame(2, DB::table('club_journal_lines')->where('journal_entry_id', $correction->reversal_journal_entry_id)->count()); $this->assertSame($replacement, $correction->replacement_receipt_id !== null); $this->assertSame($replacement ? 1 : 0, DB::table('club_ordinary_income_receipts')->where('corrected_from_receipt_id', $correction->original_receipt_id)->count()); }
    private function await(callable $condition): void { $deadline = microtime(true) + 20; while (! $condition()) { if (microtime(true) >= $deadline) throw new \RuntimeException('Barrier timeout.'); usleep(10000); } }
}
