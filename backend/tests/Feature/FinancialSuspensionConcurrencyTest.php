<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubJournalEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\FinancialOperationFixture as Fixture;
use Tests\Support\FinancialPostgresTestCase;

class FinancialSuspensionConcurrencyTest extends FinancialPostgresTestCase
{
    public static function races(): array
    {
        $cases = [];
        foreach (Fixture::actions() as $action) {
            foreach (['suspension-first', 'mutation-first'] as $order) {
                $cases[$action.'-'.$order] = [$action, $order];
            }
        }

        return $cases;
    }

    #[DataProvider('races')]
    public function test_real_club_lock_linearises_suspension_mutation_and_replay(string $action, string $order): void
    {
        $p = (new Fixture)->prepare($action);
        $before = Fixture::snapshot($p['club']);
        $journals = ClubJournalEntry::where('club_id', $p['club'])->count();
        $dir = sys_get_temp_dir().'/b3-suspension-'.Str::uuid();
        mkdir($dir, 0700, true);
        $suspensionFirst = $order === 'suspension-first';
        $processes = [];
        foreach (['one' => $suspensionFirst ? 'suspend' : 'operation', 'two' => $suspensionFirst ? 'operation' : 'suspend'] as $name => $mode) {
            file_put_contents($dir.'/'.$name.'.json', json_encode([...$p, 'mode' => $mode, 'hold' => $name === 'one'], JSON_THROW_ON_ERROR));
            $processes[$name] = new Process([PHP_BINARY, base_path('tests/Support/FinancialSuspensionWorker.php'), $dir.'/'.$name.'.json', $dir, $name], base_path(), env: ['INSTANCE_ORGANISATION_CODE' => $p['code']], timeout: 40);
        }
        try {
            $processes['one']->start();
            $this->await(fn () => is_file($dir.'/one.ready'));
            file_put_contents($dir.'/one.start', '1');
            $this->await(fn () => is_file($dir.'/one.locked'));
            $processes['two']->start();
            $this->await(fn () => is_file($dir.'/two.ready'));
            $holder = json_decode(file_get_contents($dir.'/one.ready'), true)['pid'];
            $waiter = json_decode(file_get_contents($dir.'/two.ready'), true)['pid'];
            file_put_contents($dir.'/two.start', '1');
            $this->await(function () use ($holder, $waiter): bool {
                DB::select('SELECT pg_stat_clear_snapshot()');

                return (int) DB::selectOne("SELECT count(*) AS n FROM pg_stat_activity WHERE pid=? AND wait_event_type='Lock' AND ?=ANY(pg_blocking_pids(pid))", [$waiter, $holder])->n === 1;
            });
            file_put_contents($dir.'/one.finish', '1');
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
            }
            $one = json_decode(file_get_contents($dir.'/one.result.json'), true, flags: JSON_THROW_ON_ERROR);
            $two = json_decode(file_get_contents($dir.'/two.result.json'), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('success', $one['status']);
            $this->assertSame($suspensionFirst ? 'rejected' : 'success', $two['status']);
            if ($suspensionFirst) {
                $this->assertSame('ORGANISATION_SUSPENDED', $two['reason']);
            }
            $this->assertSame([0, 0], [$one['depth'], $two['depth']]);
            $this->assertFalse(Club::findOrFail($p['club'])->is_active);
            $this->assertSame($journals + ($suspensionFirst ? 0 : Fixture::journalDelta($action)), ClubJournalEntry::where('club_id', $p['club'])->count());
            if ($suspensionFirst || str_ends_with($action, '-replay')) {
                $this->assertSame($before, Fixture::snapshot($p['club']));
            }
            $this->assertSame([], DB::select("SELECT journal_entry_id FROM club_journal_lines WHERE club_id=? GROUP BY journal_entry_id,fund_code HAVING sum(CASE WHEN direction='debit' THEN amount_paise ELSE -amount_paise END)<>0", [$p['club']]));
            fwrite(STDOUT, "\nB3_SUSPENSION_LOCK_EDGE ".json_encode(['action' => $action, 'order' => $order, 'holder' => $holder, 'waiter' => $waiter, 'edge_observed' => true, 'outcomes' => [$one['status'], $two['status']]], JSON_THROW_ON_ERROR)."\n");
        } finally {
            foreach (['one', 'two'] as $name) {
                file_put_contents($dir.'/'.$name.'.finish', '1');
            }
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(5);
                }
            }
        }
    }

    private function await(callable $condition): void
    {
        $end = microtime(true) + 20;
        while (! $condition()) {
            if (microtime(true) >= $end) {
                $this->fail('Required PostgreSQL blocking edge/barrier was not observed.');
            } usleep(5000);
        }
    }
}
