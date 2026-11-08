<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ClubAccountingCutoverExecutionEvent as Audit;
use App\Models\ClubAccountingCutoverExecutionSuccess as Success;
use App\Models\ClubAccountingCutoverAuthorityConsumption as Consumption;
use App\Models\ClubJournalEntry;
use App\Models\User;
use App\Services\Finance\ClubOpeningJournalActivationService;
use App\Services\Finance\CutoverExecutionRequest;
use App\Services\Finance\Exceptions\CutoverExecutionConflictException;
use Database\Seeders\DefaultRolesAndPermissionsSeeder;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\BuildsOpeningExecutionFixture;
use Tests\Support\CutoverPostgresTestCase;
use Tests\Support\InteractsWithCutoverGovernance;

class CutoverOpeningTransactionOutcomeTest extends CutoverPostgresTestCase
{
    use BuildsOpeningExecutionFixture, InteractsWithCutoverGovernance;

    public static function abortCases(): array
    {
        return [['serialization', false], ['serialization', true], ['deadlock', false], ['deadlock', true]];
    }

    #[DataProvider('abortCases')]
    public function test_real_postgres_abort_outcomes_preserve_retry_budget(string $mode, bool $exhaust): void
    {
        $this->seed(DefaultRolesAndPermissionsSeeder::class);
        $f = $this->authorizedFixture('outcome', 1);
        $a = User::factory()->create(); $b = User::factory()->create(); $c = User::factory()->create();
        $request = new CutoverExecutionRequest($f['club']->id, $f['package']->id, $f['package']->lock_version, $f['package']->canonical_checksum, 'F1-REAL-ABORT', (string) Str::uuid(), $f['operator']->id);
        if ($mode === 'serialization') {
            Event::listen(TransactionBeginning::class, function (): void {
                if (DB::transactionLevel() === 1) { DB::unprepared('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE'); }
            });
        }
        $attempts = []; $codes = []; $workers = [];
        Success::creating(function () use ($a, $b, $c, $f, $mode, $exhaust, &$attempts, &$codes, &$workers): void {
            $attempts[] = DB::selectOne('SELECT pg_current_xact_id()::text AS xid')->xid;
            if (! $exhaust && count($attempts) > 1) { return; }
            $dir = sys_get_temp_dir().'/f1-outcome-'.Str::uuid(); mkdir($dir, 0700, true);
            $payload = ['a' => $a->id, 'b' => $b->id, 'c' => $c->id, 'pid' => DB::selectOne('SELECT pg_backend_pid() AS pid')->pid, 'peer_pid_path' => $dir.'/deadlock-b/pid'];
            if ($mode === 'deadlock') { DB::select('SELECT id FROM users WHERE id=? FOR UPDATE', [$a->id]); }
            $started = [];
            foreach ($mode === 'serialization' ? ['serialization'] : ['deadlock-b', 'deadlock-c'] as $part) {
                $workerDir = $dir.'/'.$part; mkdir($workerDir, 0700, true);
                file_put_contents($workerDir.'/payload.json', json_encode([...$payload, 'mode' => $part], JSON_THROW_ON_ERROR));
                $worker = new Process([PHP_BINARY, base_path('tests/Support/OpeningOutcomeConcurrencyWorker.php'), $workerDir.'/payload.json', $workerDir], base_path(), env: ['INSTANCE_ORGANISATION_CODE' => $f['club']->code], timeout: 35);
                $workers[] = [$worker, $workerDir]; $started[] = [$worker, $workerDir]; $worker->start();
            }
            if ($mode === 'serialization') {
                [$worker, $workerDir] = $started[0];
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
                $this->assertSame('committed', json_decode(file_get_contents($workerDir.'/result.json'), true)['outcome']);
            } else {
                foreach ($started as [$worker, $workerDir]) {
                    $deadline = microtime(true) + 20;
                    while (! is_file($workerDir.'/ready') && microtime(true) < $deadline) { usleep(5_000); }
                    $this->assertFileExists($workerDir.'/ready');
                }
            }
            try {
                if ($mode === 'serialization') {
                    DB::table('users')->where('id', $a->id)->update(['name' => 'Financial transaction update']);
                } else {
                    DB::select('SELECT id FROM users WHERE id=? FOR UPDATE', [$b->id]);
                }
                $this->fail('Expected a genuine concurrent PostgreSQL abort.');
            } catch (QueryException $e) {
                $codes[] = $e->errorInfo[0];
                throw $e;
            }
        });
        try {
            try { app(ClubOpeningJournalActivationService::class)->execute($request); $this->assertFalse($exhaust); }
            catch (CutoverExecutionConflictException) { $this->assertTrue($exhaust); }
            $this->assertCount($exhaust ? 3 : 2, array_unique($attempts));
            $this->assertSame(array_fill(0, $exhaust ? 3 : 1, $mode === 'serialization' ? '40001' : '40P01'), $codes);
            $states = [];
            foreach ($attempts as $i => $xid) {
                $state = DB::selectOne('SELECT pg_xact_status(CAST(? AS xid8)) AS state', [$xid])->state;
                $states[$xid] = $state;
                $this->assertSame(! $exhaust && $i === count($attempts) - 1 ? 'committed' : 'aborted', $state);
            }
            $this->assertSame(1, Audit::where('event_type', 'execution_attempt')->count());
            $this->assertSame(1, Audit::where('event_type', $exhaust ? 'execution_conflict' : 'execution_success')->count());
            $this->assertSame($exhaust ? 1 : 0, Audit::where('result', 'not_posted')->count());
            $this->assertSame(0, Audit::where('result', 'outcome_unknown')->count());
            $this->assertSame($exhaust ? 0 : 1, Success::count());
            $this->assertSame($exhaust ? 0 : 1, Consumption::count());
            $this->assertSame($exhaust ? 0 : 1, ClubJournalEntry::count());
            fwrite(STDOUT, "\nF1_OUTCOME_EVIDENCE ".json_encode(['mode' => $mode, 'exhaust' => $exhaust, 'transaction_states' => $states, 'sqlstates' => $codes, 'successes' => Success::count(), 'consumptions' => Consumption::count(), 'journals' => ClubJournalEntry::count()], JSON_THROW_ON_ERROR)."\n");
        } finally {
            foreach ($workers as [$worker, $dir]) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
                $this->assertSame('committed', json_decode(file_get_contents($dir.'/result.json'), true)['outcome']);
            }
        }
    }
}
