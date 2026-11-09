<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ClubAccountingCutoverAuthorityConsumption;
use App\Models\ClubAccountingCutoverExecutionEvent;
use App\Models\ClubAccountingCutoverExecutionSuccess;
use App\Models\ClubAccountingPeriod;
use App\Models\ClubJournalEntry;
use App\Services\Finance\ClubOpeningJournalActivationService;
use App\Services\Finance\CutoverExecutionRequest;
use App\Services\Finance\Exceptions\CutoverExecutionException;
use Database\Seeders\DefaultRolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\BuildsOpeningExecutionFixture;
use Tests\Support\CutoverPostgresTestCase;
use Tests\Support\InteractsWithCutoverGovernance;

class AccountingAdministrationOpeningConcurrencyTest extends CutoverPostgresTestCase
{
    use BuildsOpeningExecutionFixture, InteractsWithCutoverGovernance;

    public static function orders(): array
    {
        return [[true], [false]];
    }

    #[DataProvider('orders')]
    public function test_period_close_and_opening_preserve_winner_audit_and_consumption_contract(bool $openingFirst): void
    {
        $this->seed(DefaultRolesAndPermissionsSeeder::class);
        $f = $this->authorizedFixture('b3-peer', 1);
        $p = ['club' => $f['club']->id, 'actor' => $f['operator']->id, 'package' => $f['package']->id, 'version' => $f['package']->lock_version, 'checksum' => $f['package']->canonical_checksum, 'period' => ClubAccountingPeriod::where('club_id', $f['club']->id)->value('id'), 'opening_key' => (string) Str::uuid(), 'close_actor' => $f['president']->id];
        $dir = sys_get_temp_dir().'/b3-opening-peer-'.Str::uuid();
        mkdir($dir, 0700, true);
        $workers = [];
        foreach (['one' => $openingFirst ? 'opening' : 'close', 'two' => $openingFirst ? 'close' : 'opening'] as $name => $mode) {
            file_put_contents($dir.'/'.$name.'.json', json_encode([...$p, 'mode' => $mode, 'hold' => $name === 'one'], JSON_THROW_ON_ERROR));
            $workers[$name] = new Process([PHP_BINARY, base_path('tests/Support/AccountingAdministrationOpeningWorker.php'), $dir.'/'.$name.'.json', $dir, $name], base_path(), env: ['INSTANCE_ORGANISATION_CODE' => $f['club']->code], timeout: 40);
        }
        try {
            $workers['one']->start();
            $this->await(fn () => is_file($dir.'/one.ready'));
            file_put_contents($dir.'/one.start', '1');
            $this->await(fn () => is_file($dir.'/one.locked'));
            $workers['two']->start();
            $this->await(fn () => is_file($dir.'/two.ready'));
            file_put_contents($dir.'/two.start', '1');
            $holder = json_decode(file_get_contents($dir.'/one.ready'), true)['pid'];
            $waiter = json_decode(file_get_contents($dir.'/two.ready'), true)['pid'];
            $this->await(function () use ($holder, $waiter) {
                DB::select('SELECT pg_stat_clear_snapshot()');

                return (int) DB::selectOne("SELECT count(*) AS n FROM pg_stat_activity WHERE pid=? AND wait_event_type='Lock' AND ?=ANY(pg_blocking_pids(pid))", [$waiter, $holder])->n === 1;
            });
            file_put_contents($dir.'/one.finish', '1');
            foreach ($workers as $w) {
                $w->wait();
                $this->assertTrue($w->isSuccessful(), $w->getErrorOutput().$w->getOutput());
            }
            foreach (['one', 'two'] as $name) {
                $r = json_decode(file_get_contents($dir.'/'.$name.'.result.json'), true);
                $this->assertSame(! $openingFirst && $name === 'two' ? 'domain_rejected' : 'success', $r['status']);
                if ($r['status'] === 'domain_rejected') {
                    $this->assertSame(CutoverExecutionException::class, $r['class']);
                    $this->assertSame('The cutover accounting period is unavailable.', $r['message']);
                }
                $this->assertSame(0, $r['depth']);
            }
            $this->assertSame($openingFirst ? 1 : 0, ClubJournalEntry::count());
            $this->assertSame('closed', ClubAccountingPeriod::findOrFail($p['period'])->status);
            $this->assertSame(1, AuditLog::where('action', 'finance.accounting_period.closed')->count());
            $this->assertSame($openingFirst ? [1] : [], ClubJournalEntry::orderBy('sequence_number')->pluck('sequence_number')->all());
            $this->assertSame($openingFirst ? 1 : 0, ClubAccountingCutoverExecutionSuccess::count());
            $this->assertSame($openingFirst ? 1 : 0, ClubAccountingCutoverAuthorityConsumption::count());
            $this->assertSame(1, ClubAccountingCutoverExecutionEvent::where('event_type', 'execution_attempt')->count());
            $this->assertSame($openingFirst ? 1 : 0, ClubAccountingCutoverExecutionEvent::where('event_type', 'execution_success')->count());
            $this->assertSame($openingFirst ? 0 : 1, ClubAccountingCutoverExecutionEvent::where('event_type', 'execution_failure')->where('result', 'not_posted')->count());
            $this->assertSame([], DB::select("SELECT journal_entry_id FROM club_journal_lines GROUP BY journal_entry_id,fund_code HAVING sum(CASE WHEN direction='debit' THEN amount_paise ELSE -amount_paise END)<>0"));
            if ($openingFirst) {
                $success = app(ClubOpeningJournalActivationService::class)->execute(new CutoverExecutionRequest($p['club'], $p['package'], $p['version'], $p['checksum'], 'B5-OPENING-CLOSE', $p['opening_key'], $p['actor']));
                $this->assertSame(ClubAccountingCutoverExecutionSuccess::sole()->id, $success->id);
                $this->assertSame(1, ClubJournalEntry::count());
                $this->assertSame(1, ClubAccountingCutoverAuthorityConsumption::count());
                $this->assertSame(1, ClubAccountingCutoverExecutionEvent::where('event_type', 'execution_replay')->count());
            }
            fwrite(STDOUT, "\nB5_OPENING_CLOSE_LOCK_EDGE ".json_encode(['opening_first' => $openingFirst, 'holder' => $holder, 'waiter' => $waiter, 'edge_observed' => true, 'journals' => ClubJournalEntry::count(), 'successes' => ClubAccountingCutoverExecutionSuccess::count(), 'consumptions' => ClubAccountingCutoverAuthorityConsumption::count()], JSON_THROW_ON_ERROR)."\n");
        } finally {
            foreach (['one', 'two'] as $n) {
                file_put_contents($dir.'/'.$n.'.finish', '1');
            }foreach ($workers as $w) {
                if ($w->isRunning()) {
                    $w->stop(5);
                }
            }
        }
    }

    private function await(callable $f): void
    {
        $end = microtime(true) + 20;
        while (! $f()) {
            if (microtime(true) >= $end) {
                $this->fail('Required opening peer lock edge/barrier missing.');
            }usleep(5000);
        }
    }
}
