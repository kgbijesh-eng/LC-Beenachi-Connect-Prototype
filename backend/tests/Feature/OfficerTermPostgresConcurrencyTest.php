<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubMembership;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\FinancialPostgresTestCase;

class OfficerTermPostgresConcurrencyTest extends FinancialPostgresTestCase
{
    public static function contenders(): iterable
    {
        yield 'office-first' => [true, false, true];
        yield 'office-reversed' => [true, true, true];
        yield 'holder-first' => [false, false, true];
        yield 'holder-reversed' => [false, true, true];
        yield 'office-rollback' => [true, false, false];
        yield 'holder-rollback' => [false, false, false];
    }

    #[DataProvider('contenders')]
    public function test_exclusion_waits_for_the_uncommitted_term(bool $sameOffice, bool $reverse, bool $commit): void
    {
        $club = Club::create(['name' => 'Synthetic club', 'code' => (string) Str::uuid(), 'is_active' => true]);
        $role = Role::create(['name' => 'Synthetic member', 'slug' => 'member']);
        $users = User::factory()->count(2)->create();
        $members = $users->map(fn ($user) => ClubMembership::create([
            'club_id' => $club->id, 'user_id' => $user->id, 'role_id' => $role->id, 'status' => 'active',
        ]));
        $common = [
            'club_id' => $club->id,
            'starts_at' => '2027-01-01 00:00:00+00',
            'ends_at' => '2027-02-01 00:00:00+00',
            'recorded_at' => '2027-01-01 00:00:00+00',
            'approved_by_user_id' => $users[0]->id,
            'confirmed_by_user_id' => $users[1]->id,
            'recorded_by_user_id' => $users[0]->id,
        ];
        $a = $common + ['membership_id' => $members[0]->id, 'office' => 'president', 'appointment_reference' => 'A'];
        $b = $common + ['membership_id' => $members[$sameOffice ? 1 : 0]->id,
            'office' => $sameOffice ? 'president' : 'secretary', 'appointment_reference' => 'B'];
        [$winner, $waiter] = $reverse ? [$b, $a] : [$a, $b];
        $this->assertFalse(DB::table('pg_extension')->where('extname', 'btree_gist')->exists());
        $constraints = DB::table('pg_constraint')->whereIn('conname', [
            'club_officer_terms_office_no_overlap', 'club_officer_terms_holder_no_overlap',
        ])->where('contype', 'x')->count();
        $this->assertSame(2, $constraints);

        $path = sys_get_temp_dir().'/officer-term-'.Str::uuid();
        file_put_contents($path, json_encode($waiter, JSON_THROW_ON_ERROR));
        $worker = new Process([PHP_BINARY, base_path('tests/Support/OfficerTermConstraintWorker.php'), $path, "$path.ready"], base_path(), timeout: 25);
        DB::beginTransaction();
        try {
            DB::table('club_officer_terms')->insert($winner);
            $holderPid = (int) DB::selectOne('SELECT pg_backend_pid() AS pid')->pid;
            $worker->start();
            $waiterPid = 0;
            $deadline = microtime(true) + 12;
            do {
                if (is_file("$path.ready")) {
                    $waiterPid = (int) file_get_contents("$path.ready");
                }
                DB::select('SELECT pg_stat_clear_snapshot()');
                $blocked = $waiterPid > 0 && (int) DB::selectOne(
                    "SELECT count(*) AS n FROM pg_stat_activity WHERE pid=? AND wait_event_type='Lock'
                     AND ?=ANY(pg_blocking_pids(pid)) AND query LIKE 'insert into \"club_officer_terms\"%'",
                    [$waiterPid, $holderPid],
                )->n === 1;
                if (! $blocked) {
                    usleep(5000);
                }
            } while (! $blocked && microtime(true) < $deadline && $worker->isRunning());
            $this->assertTrue($blocked, 'Expected the exclusion insert to wait on the holder transaction.');
            $commit ? DB::commit() : DB::rollBack();
            $this->assertSame(0, $worker->wait(), $worker->getErrorOutput());
            $this->assertSame($commit ? '23P01' : 'inserted', trim($worker->getOutput()));
            $this->assertSame($commit ? $winner['appointment_reference'] : $waiter['appointment_reference'],
                DB::table('club_officer_terms')->sole()->appointment_reference);
            fwrite(STDOUT, "\nOFFICER_TERM_EXCLUSION_EDGE ".json_encode([
                'scope' => $sameOffice ? 'office' : 'holder',
                'holder' => $holderPid, 'waiter' => $waiterPid,
                'commit' => $commit, 'outcome' => trim($worker->getOutput()),
            ], JSON_THROW_ON_ERROR)."\n");
        } finally {
            if (DB::transactionLevel() !== 0) {
                DB::rollBack();
            }
            if ($worker->isRunning()) {
                $worker->stop(2);
            }
            unlink($path);
            if (is_file("$path.ready")) {
                unlink("$path.ready");
            }
        }
    }
}
