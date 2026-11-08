<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubJournalEntry;
use App\Models\User;
use App\Services\Access\OrganisationAccessRejected;
use App\Services\Access\OrganisationTransactionGuard;
use App\Services\Finance\ClubJournalPostingService;
use App\Services\Finance\ClubOrdinaryIncomeReceiptService;
use App\Services\Finance\JournalReversalService;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\FinancialOperationFixture as Fixture;
use Tests\Support\FinancialPostgresTestCase;

class FinancialTransactionRetryTest extends FinancialPostgresTestCase
{
    public static function aborts(): array
    {
        $cases = [];
        foreach (['post', 'post-nested', 'reverse', 'reverse-nested'] as $api) {
            foreach (['serialization', 'deadlock'] as $mode) {
                foreach ([false, true] as $exhaust) {
                    $cases[$api.'-'.$mode.'-'.(int) $exhaust] = [$api, $mode, $exhaust, 3];
                }
            }
        }
        foreach (['collections-issue', 'collections-payment', 'collections-cancel', 'collections-void'] as $api) {
            foreach (['serialization', 'deadlock'] as $mode) {
                $cases[$api.'-'.$mode] = [$api, $mode, true, 1];
            }
        }
        foreach (['expense', 'expense-reversal', 'transfer', 'income', 'correction', 'correction-replacement'] as $api) {
            foreach ([false, true] as $exhaust) {
                $cases[$api.'-serialization-'.(int) $exhaust] = [$api, 'serialization', $exhaust, 3];
            }
        }

        return $cases;
    }

    #[DataProvider('aborts')]
    public function test_genuine_postgres_abort_preserves_outer_budget_and_atomicity(string $api, string $mode, bool $exhaust, int $budget): void
    {
        $p = (new Fixture)->prepare(str_replace('-nested', '', $api));
        $before = Fixture::snapshot($p['club']);
        $journals = ClubJournalEntry::where('club_id', $p['club'])->count();
        $a = User::factory()->create();
        $b = User::factory()->create();
        $c = User::factory()->create();
        if ($mode === 'serialization') {
            Event::listen(TransactionBeginning::class, function (): void {
                if (DB::transactionLevel() === 1) {
                    DB::unprepared('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
                }
            });
        }
        $attempts = [];
        $codes = [];
        $workers = [];
        $contexts = [];
        ClubJournalEntry::created(function ($entry) use ($api, $mode, $exhaust, $p, $a, $b, $c, &$attempts, &$codes, &$workers): void {
            if ($api === 'correction-replacement' && $entry->source_type !== ClubOrdinaryIncomeReceiptService::SOURCE_TYPE) {
                return;
            }
            $attempts[] = DB::selectOne('SELECT pg_current_xact_id()::text AS xid')->xid;
            if (! $exhaust && count($attempts) > 1) {
                return;
            }
            $dir = sys_get_temp_dir().'/b3-real-abort-'.Str::uuid();
            mkdir($dir, 0700, true);
            $payload = ['a' => $a->id, 'b' => $b->id, 'c' => $c->id, 'pid' => DB::selectOne('SELECT pg_backend_pid() AS pid')->pid, 'peer_pid_path' => $dir.'/deadlock-b/pid'];
            if ($mode === 'deadlock') {
                DB::select('SELECT id FROM users WHERE id=? FOR UPDATE', [$a->id]);
            }
            $started = [];
            foreach ($mode === 'serialization' ? ['serialization'] : ['deadlock-b', 'deadlock-c'] as $part) {
                $workerDir = $dir.'/'.$part;
                mkdir($workerDir, 0700, true);
                file_put_contents($workerDir.'/payload.json', json_encode([...$payload, 'mode' => $part], JSON_THROW_ON_ERROR));
                $worker = new Process([PHP_BINARY, base_path('tests/Support/FinancialAbortWorker.php'), $workerDir.'/payload.json', $workerDir], base_path(), env: ['INSTANCE_ORGANISATION_CODE' => $p['code']], timeout: 35);
                $workers[] = [$worker, $workerDir];
                $started[] = [$worker, $workerDir];
                $worker->start();
            }
            if ($mode === 'serialization') {
                [$worker,$workerDir] = $started[0];
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
                $this->assertSame('committed', json_decode(file_get_contents($workerDir.'/result.json'), true)['outcome']);
            } else {
                foreach ($started as [$worker,$workerDir]) {
                    $end = microtime(true) + 20;
                    while (! is_file($workerDir.'/ready') && microtime(true) < $end) {
                        usleep(5000);
                    } $this->assertFileExists($workerDir.'/ready');
                }
            }
            try {
                if ($mode === 'serialization') {
                    DB::table('users')->where('id', $a->id)->update(['name' => 'Financial transaction update']);
                } else {
                    DB::select('SELECT id FROM users WHERE id=? FOR UPDATE', [$b->id]);
                }
                $this->fail('A genuine PostgreSQL abort must be observed.');
            } catch (QueryException $e) {
                $codes[] = (string) $e->errorInfo[0];
                throw $e;
            }
        });
        try {
            $failure = null;
            try {
                if (str_ends_with($api, '-nested')) {
                    app(OrganisationTransactionGuard::class)->run(Club::findOrFail($p['club']), function ($context) use ($api, $p, &$contexts) {
                        foreach ($contexts as $old) {
                            try {
                                $old->assertUsable();
                                $this->fail('A previous retry context must be invalid.');
                            } catch (OrganisationAccessRejected $e) {
                                $this->assertSame('ORGANISATION_TRANSACTION_CONTEXT_INVALID', $e->reason);
                            }
                        }
                        $contexts[] = $context;

                        return $api === 'post-nested' ? app(ClubJournalPostingService::class)->postWithin($context, Fixture::posting($p)) : app(JournalReversalService::class)->reverseWithin($context, Fixture::reversal($p));
                    }, $budget);
                } else {
                    Fixture::perform($p);
                }
            } catch (\Throwable $e) {
                $failure = $e;
            }
            if ($exhaust) {
                $this->assertNotNull($failure);
            } else {
                $this->assertNull($failure, $failure ? $failure::class.': '.$failure->getMessage() : '');
            }
            $this->assertCount($exhaust ? $budget : 2, array_unique($attempts));
            $this->assertSame(array_fill(0, $exhaust ? $budget : 1, $mode === 'serialization' ? '40001' : '40P01'), $codes);
            foreach ($attempts as $i => $xid) {
                $this->assertSame(! $exhaust && $i === count($attempts) - 1 ? 'committed' : 'aborted', DB::selectOne('SELECT pg_xact_status(CAST(? AS xid8)) AS state', [$xid])->state);
            }
            $this->assertSame($journals + ($exhaust ? 0 : Fixture::journalDelta(str_replace('-nested', '', $api))), ClubJournalEntry::where('club_id', $p['club'])->count());
            if ($exhaust) {
                $this->assertSame($before, Fixture::snapshot($p['club']));
            }
            foreach ($contexts as $context) {
                try {
                    $context->assertUsable();
                    $this->fail('Completed/aborted context must expire.');
                } catch (OrganisationAccessRejected $e) {
                    $this->assertSame('ORGANISATION_TRANSACTION_CONTEXT_INVALID', $e->reason);
                }
            }
            $this->assertSame(0, DB::transactionLevel());
            fwrite(STDOUT, "\nB3_REAL_ABORT_EVIDENCE ".json_encode(['api' => $api, 'mode' => $mode, 'budget' => $budget, 'exhaust' => $exhaust, 'transaction_ids' => $attempts, 'sqlstates' => $codes], JSON_THROW_ON_ERROR)."\n");
        } finally {
            foreach ($workers as [$worker,$workerDir]) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(),$worker->getErrorOutput());
                $this->assertSame('committed',json_decode(file_get_contents($workerDir.'/result.json'),true)['outcome']);
            }
        }
    }
}
