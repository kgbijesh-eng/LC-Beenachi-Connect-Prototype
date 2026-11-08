<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ClubAccountingPeriod;
use App\Models\ClubJournalEntry;
use App\Models\ClubMembership;
use App\Models\Member;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\FinancialOperationFixture as Fixture;
use Tests\Support\FinancialPostgresTestCase;

class FinancialCrossServiceConcurrencyTest extends FinancialPostgresTestCase
{
    public static function closingRaces(): array
    {
        $cases = [];
        foreach (['post', 'reverse', 'expense', 'correction-replacement'] as $action) {
            foreach ([true, false] as $closeFirst) {
                $cases[$action.'-close-first-'.(int) $closeFirst] = [$action, $closeFirst];
            }
        }

        return $cases;
    }

    #[DataProvider('closingRaces')]
    public function test_existing_period_close_and_financial_root_share_club_first_order(string $action, bool $closeFirst): void
    {
        $p = (new Fixture)->prepare($action);
        $before = Fixture::snapshot($p['club']);
        $count = ClubJournalEntry::where('club_id', $p['club'])->count();
        [$dir,$one,$two] = $this->workers($p, $closeFirst ? 'close' : 'operation', $closeFirst ? 'operation' : 'close');
        try {
            $this->startHolder($dir, $one);
            $two->start();
            $this->await(fn () => is_file($dir.'/two.ready'));
            file_put_contents($dir.'/two.start', '1');
            $holder = json_decode(file_get_contents($dir.'/one.ready'), true)['pid'];
            $waiter = json_decode(file_get_contents($dir.'/two.ready'), true)['pid'];
            $this->await(function () use ($holder, $waiter) {
                DB::select('SELECT pg_stat_clear_snapshot()');

                return (int) DB::selectOne("SELECT count(*) AS n FROM pg_stat_activity WHERE pid=? AND wait_event_type='Lock' AND ?=ANY(pg_blocking_pids(pid))", [$waiter, $holder])->n === 1;
            });
            file_put_contents($dir.'/one.finish', '1');
            $results = $this->finish($dir, $one, $two);
            $this->assertSame('success', $results[0]['status']);
            $this->assertSame($closeFirst ? 'domain_rejected' : 'success', $results[1]['status']);
            if ($closeFirst) {
                $this->assertMatchesRegularExpression('/closed|open.*period|open.*Lions/i', $results[1]['message']);
            }
            $this->assertSame('closed', ClubAccountingPeriod::findOrFail($p['period'])->status);
            $this->assertSame($count + ($closeFirst ? 0 : Fixture::journalDelta($action)), ClubJournalEntry::where('club_id', $p['club'])->count());
            if ($closeFirst) {
                $after = Fixture::snapshot($p['club']);
                unset($before['audit_logs'],$after['audit_logs']);
                $this->assertSame($before, $after);
            }
            fwrite(STDOUT, "\nB3_PERIOD_LOCK_EDGE ".json_encode(['action' => $action, 'close_first' => $closeFirst, 'holder' => $holder, 'waiter' => $waiter, 'outcomes' => array_column($results, 'status')], JSON_THROW_ON_ERROR)."\n");
        } finally {
            file_put_contents($dir.'/one.finish', '1');
            file_put_contents($dir.'/two.finish', '1');
            foreach ([$one, $two] as $w) {
                if ($w->isRunning()) {
                    $w->stop(5);
                }
            }
        }
    }

    public static function unsynchronisedWriters(): array
    {
        return [['member-update', false], ['member-update', true], ['membership-update', false], ['membership-update', true]];
    }

    #[DataProvider('unsynchronisedWriters')]
    public function test_unmigrated_writers_are_characterised_without_claiming_synchronisation(string $writer, bool $afterAdmission): void
    {
        $p = (new Fixture)->prepare('legacy-payment');
        $before = Fixture::snapshot($p['club']);
        if ($writer === 'member-update') {
            $perm = Permission::firstOrCreate(['slug' => 'members.manage'], ['name' => 'Manage members', 'group' => 'members']);
            $role = Role::findOrFail(ClubMembership::where('club_id', $p['club'])->where('user_id', $p['actor'])->value('role_id'));
            $role->permissions()->syncWithoutDetaching([$perm->id]);
            $p['writer_token'] = User::findOrFail($p['actor'])->createToken('b3-synthetic-member-writer')->plainTextToken;
        }
        [$dir,$one,$two] = $this->workers($p, 'operation', $writer, $afterAdmission ? 'before-insert' : 'club');
        try {
            $this->startHolder($dir, $one);
            $two->start();
            $this->await(fn () => is_file($dir.'/two.ready'));
            file_put_contents($dir.'/two.start', '1');
            // The real member controller/model writer completes while the finance club lock is held.
            $this->await(fn () => is_file($dir.'/two.result.json'));
            $this->assertTrue($one->isRunning());
            file_put_contents($dir.'/one.finish', '1');
            $results = $this->finish($dir, $one, $two);
            $this->assertSame('success', $results[1]['status']);
            $this->assertSame($afterAdmission ? 'success' : 'domain_rejected', $results[0]['status']);
            if (! $afterAdmission) {
                $this->assertSame($before, Fixture::snapshot($p['club']));
            }
            $this->assertSame($afterAdmission ? 1 : 0, DB::table('payments')->where('club_id', $p['club'])->count());
            $this->assertSame($afterAdmission ? 1 : 0, DB::table('member_ledger_entries')->where('club_id', $p['club'])->count());
            if ($writer === 'member-update') {
                $this->assertSame('inactive', Member::findOrFail($p['member'])->status);
            } else {
                $this->assertSame('inactive', ClubMembership::where('club_id', $p['club'])->where('user_id', $p['actor'])->value('status'));
            }
            fwrite(STDOUT, "\nB3_UNSYNCHRONISED_WRITER ".json_encode(['writer' => $writer, 'after_admission' => $afterAdmission, 'synchronised' => false, 'outcome' => $results[0]['status'], 'scope' => 'B6 deferred; existing admission semantics retained'], JSON_THROW_ON_ERROR)."\n");
        } finally {
            file_put_contents($dir.'/one.finish', '1');
            file_put_contents($dir.'/two.finish', '1');
            foreach ([$one, $two] as $w) {
                if ($w->isRunning()) {
                    $w->stop(5);
                }
            }
        }
    }

    private function workers(array $p, string $first, string $second, string $pause = 'club'): array
    {
        $dir = sys_get_temp_dir().'/b3-cross-'.Str::uuid();
        mkdir($dir, 0700, true);
        $workers = [];
        foreach (['one' => $first, 'two' => $second] as $name => $mode) {
            file_put_contents($dir.'/'.$name.'.json', json_encode([...$p, 'mode' => $mode, 'hold' => $name === 'one', 'pause' => $pause], JSON_THROW_ON_ERROR));
            $workers[] = new Process([PHP_BINARY, base_path('tests/Support/FinancialSuspensionWorker.php'), $dir.'/'.$name.'.json', $dir, $name], base_path(), env: ['INSTANCE_ORGANISATION_CODE' => $p['code']], timeout: 40);
        }

        return [$dir, ...$workers];
    }

    private function startHolder(string $dir, Process $worker): void
    {
        $worker->start();
        $this->await(fn () => is_file($dir.'/one.ready'));
        file_put_contents($dir.'/one.start', '1');
        $this->await(fn () => is_file($dir.'/one.locked'));
    }

    private function finish(string $dir, Process $one, Process $two): array
    {
        foreach ([$one, $two] as $w) {
            $w->wait();
            $this->assertTrue($w->isSuccessful(), $w->getErrorOutput().$w->getOutput());
        }$r = array_map(fn ($n) => json_decode(file_get_contents($dir.'/'.$n.'.result.json'), true, flags: JSON_THROW_ON_ERROR), ['one', 'two']);
        $this->assertSame([0, 0], array_column($r, 'depth'));

        return $r;
    }

    private function await(callable $f): void
    {
        $end = microtime(true) + 20;
        while (! $f()) {
            if (microtime(true) >= $end) {
                $this->fail('Required cross-service barrier/lock edge was not observed.');
            }usleep(5000);
        }
    }
}
