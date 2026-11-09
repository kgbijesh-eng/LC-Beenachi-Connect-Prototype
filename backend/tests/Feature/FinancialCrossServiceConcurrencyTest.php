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
        return [['member-update', false], ['member-update', true], ['membership-update', false], ['membership-update', true], ['member-import', false], ['member-import', true], ['member-link', false], ['member-link', true]];
    }

    #[DataProvider('unsynchronisedWriters')]
    public function test_unmigrated_writers_are_characterised_without_claiming_synchronisation(string $writer, bool $afterAdmission): void
    {
        $p = (new Fixture)->prepare('legacy-payment');
        $before = Fixture::snapshot($p['club']);
        if ($writer !== 'membership-update') {
            $perm = Permission::firstOrCreate(['slug' => 'members.manage'], ['name' => 'Manage members', 'group' => 'members']);
            $role = Role::findOrFail(ClubMembership::where('club_id', $p['club'])->where('user_id', $p['actor'])->value('role_id'));
            $role->permissions()->syncWithoutDetaching([$perm->id]);
            if ($writer === 'member-import') {
                $role->update(['slug' => 'treasurer']);
                $view = Permission::firstOrCreate(['slug' => 'club.view'], ['name' => 'View club', 'group' => 'club']);
                $role->permissions()->syncWithoutDetaching([$view->id]);
            }
            $p['writer_token'] = User::findOrFail($p['actor'])->createToken('b3-synthetic-member-writer')->plainTextToken;
        }
        $synchronised = $writer !== 'membership-update';
        $financeSucceeds = $afterAdmission || $writer === 'member-link';
        $writerFirst = $synchronised && ! $afterAdmission;
        [$dir,$one,$two] = $this->workers($p, $writerFirst ? $writer : 'operation', $writerFirst ? 'operation' : $writer, ! $synchronised && $afterAdmission ? 'before-insert' : 'club');
        try {
            $this->startHolder($dir, $one);
            $two->start();
            $this->await(fn () => is_file($dir.'/two.ready'));
            file_put_contents($dir.'/two.start', '1');
            if ($synchronised) {
                $holder = json_decode(file_get_contents($dir.'/one.ready'), true)['pid'];
                $waiter = json_decode(file_get_contents($dir.'/two.ready'), true)['pid'];
                $this->await(fn () => (int) DB::selectOne('SELECT count(*) AS n FROM pg_stat_activity WHERE pid=? AND ?=ANY(pg_blocking_pids(pid))', [$waiter, $holder])->n === 1);
            } else {
                $this->await(fn () => is_file($dir.'/two.result.json'));
            }
            $this->assertTrue($one->isRunning());
            file_put_contents($dir.'/one.finish', '1');
            $results = $this->finish($dir, $one, $two);
            $this->assertSame('success', $results[$writerFirst ? 0 : 1]['status']);
            $this->assertSame($financeSucceeds ? 'success' : 'domain_rejected', $results[$writerFirst ? 1 : 0]['status']);
            if (! $financeSucceeds) {
                $this->assertSame($before, Fixture::snapshot($p['club']));
            }
            $this->assertSame($financeSucceeds ? 1 : 0, DB::table('payments')->where('club_id', $p['club'])->count());
            $this->assertSame($financeSucceeds ? 1 : 0, DB::table('member_ledger_entries')->where('club_id', $p['club'])->count());
            if ($writer === 'member-link') {
                $this->assertSame($p['actor'], Member::findOrFail($p['member'])->user_id);
                $this->assertSame('active', Member::findOrFail($p['member'])->status);
            } elseif ($synchronised) {
                $this->assertSame('inactive', Member::findOrFail($p['member'])->status);
            } else {
                $this->assertSame('inactive', ClubMembership::where('club_id', $p['club'])->where('user_id', $p['actor'])->value('status'));
            }
            fwrite(STDOUT, "\nB3_UNSYNCHRONISED_WRITER ".json_encode(['writer' => $writer, 'after_admission' => $afterAdmission, 'synchronised' => $synchronised, 'outcome' => $results[$writerFirst ? 1 : 0]['status'], 'scope' => $synchronised ? 'B6-A member writer' : 'Membership administration deferred'], JSON_THROW_ON_ERROR)."\n");
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

    #[\PHPUnit\Framework\Attributes\Group('historical-profile-diagnostic')]
    public function test_historical_profile_lock_inversion_reports_40p01(): void
    {
        if (env('RUN_B6A_PROFILE_DIAGNOSTIC') !== '1') {
            $this->markTestSkipped('Run separately from the corrected acceptance selection.');
        }
        $p = (new Fixture)->prepare('legacy-payment');
        config(['instance.organisation_code' => $p['code']]);
        $club = \App\Models\Club::findOrFail($p['club']);
        $user = User::findOrFail($p['actor']);
        Member::findOrFail($p['member'])->update(['user_id' => $user->id]);
        $p['profile_request'] = app(\App\Services\ProfileUpdates\ProfileUpdateWriteService::class)
            ->submit($club, $user, ['changes' => ['occupation' => 'Proposed']])->id;
        [$dir, $one, $two] = $this->workers($p, 'historical-profile', 'historical-member');
        try {
            $this->startHolder($dir, $one);
            $two->start();
            $this->await(fn () => is_file($dir.'/two.ready'));
            file_put_contents($dir.'/two.start', '1');
            $holder = json_decode(file_get_contents($dir.'/one.ready'), true)['pid'];
            $waiter = json_decode(file_get_contents($dir.'/two.ready'), true)['pid'];
            $this->await(fn () => (int) DB::selectOne(
                "SELECT count(*) AS n FROM pg_stat_activity WHERE pid=? AND wait_event_type='Lock' AND ?=ANY(pg_blocking_pids(pid))",
                [$waiter, $holder],
            )->n === 1);
            file_put_contents($dir.'/one.finish', '1');
            $statuses = array_column($this->finish($dir, $one, $two), 'status');
            sort($statuses);
            $this->assertSame(['40P01', 'success'], $statuses);
            fwrite(STDOUT, "\nB6A_HISTORICAL_PROFILE_LOCK_INVERSION 40P01\n");
        } finally {
            file_put_contents($dir.'/one.finish', '1');
            foreach ([$one, $two] as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop(5);
                }
            }
        }
    }

    public static function profileRaces(): iterable
    {
        foreach ([
            ['approve', 'member-update'],
            ['submit', 'member-link'],
            ['submit', 'member-import'],
            ['approve', 'member-import'],
            ['reject', 'member-import'],
            ['withdraw', 'member-import'],
            ['submit', 'profile-submit'],
            ['approve', 'profile-approve'],
            ['reject', 'profile-reject'],
            ['withdraw', 'profile-withdraw'],
            ['approve', 'profile-reject'],
            ['approve', 'profile-withdraw'],
            ['reject', 'profile-withdraw'],
        ] as [$profile, $peer]) {
            foreach ([true, false] as $profileFirst) {
                yield "$profile-$peer-".($profileFirst ? 'profile-first' : 'peer-first') => [$profile, $peer, $profileFirst];
            }
        }
    }

    #[DataProvider('profileRaces')]
    public function test_profile_writers_share_the_club_lock(string $operation, string $peer, bool $profileFirst): void
    {
        $p = (new Fixture)->prepare('legacy-payment');
        config(['instance.organisation_code' => $p['code']]);
        $club = \App\Models\Club::findOrFail($p['club']);
        $actor = User::findOrFail($p['actor']);
        $user = User::factory()->create();
        Member::findOrFail($p['member'])->update(['user_id' => $user->id]);
        $p['profile_user'] = $user->id;

        $role = Role::findOrFail(ClubMembership::where('club_id', $club->id)->where('user_id', $actor->id)->value('role_id'));
        foreach (['club.view', 'members.manage'] as $slug) {
            $role->permissions()->syncWithoutDetaching([Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'group' => 'members'])->id]);
        }
        if ($peer === 'member-import') {
            $role->update(['slug' => 'treasurer']);
        }
        $p['writer_token'] = $actor->createToken('synthetic-profile-peer')->plainTextToken;

        if ($operation !== 'submit') {
            $p['profile_request'] = app(\App\Services\ProfileUpdates\ProfileUpdateWriteService::class)
                ->submit($club, $user, ['changes' => ['occupation' => 'Proposed']])->id;
        }

        $profile = 'profile-'.$operation;
        [$dir, $one, $two] = $this->workers($p, $profileFirst ? $profile : $peer, $profileFirst ? $peer : $profile);
        try {
            $this->startHolder($dir, $one);
            $two->start();
            $this->await(fn () => is_file($dir.'/two.ready'));
            file_put_contents($dir.'/two.start', '1');
            $holder = json_decode(file_get_contents($dir.'/one.ready'), true)['pid'];
            $waiter = json_decode(file_get_contents($dir.'/two.ready'), true)['pid'];
            $this->await(fn () => (int) DB::selectOne(
                "SELECT count(*) AS n FROM pg_stat_activity WHERE pid=? AND wait_event_type='Lock' AND ?=ANY(pg_blocking_pids(pid))",
                [$waiter, $holder],
            )->n === 1);
            file_put_contents($dir.'/one.finish', '1');
            $results = $this->finish($dir, $one, $two);
            $this->assertSame('success', $results[0]['status']);
            $expected = match (true) {
                str_starts_with($peer, 'profile-') => 'http_409',
                ! $profileFirst && $peer === 'member-link' => 'http_403',
                ! $profileFirst && $operation === 'approve' => 'http_409',
                default => 'success',
            };
            $this->assertSame($expected, $results[1]['status'], json_encode($results, JSON_THROW_ON_ERROR));
            fwrite(STDOUT, "\nB6A_PROFILE_LOCK_EDGE ".json_encode([
                'operation' => $operation,
                'peer' => $peer,
                'profile_first' => $profileFirst,
                'holder' => $holder,
                'waiter' => $waiter,
                'outcomes' => array_column($results, 'status'),
            ], JSON_THROW_ON_ERROR)."\n");
            $requests = \App\Models\ProfileUpdateRequest::where('club_id', $club->id)->get();
            $this->assertCount($expected === 'http_403' ? 0 : 1, $requests);
            if ($operation !== 'submit') {
                $winner = $profileFirst || ! str_starts_with($peer, 'profile-') ? $operation : substr($peer, strlen('profile-'));
                $status = match ($winner) {
                    'approve' => $expected === 'http_409' && ! $profileFirst && ! str_starts_with($peer, 'profile-') ? 'pending' : 'approved',
                    'reject' => 'rejected',
                    'withdraw' => 'withdrawn',
                };
                $this->assertSame($status, $requests->sole()->status);
                $this->assertSame($status === 'pending' ? 1 : 2, $requests->sole()->lock_version);
                $this->assertSame($status === 'pending' ? 0 : 1, \App\Models\AuditLog::where('club_id', $club->id)
                    ->whereIn('action', ['profile_updates.approved', 'profile_updates.rejected', 'profile_updates.withdrawn'])->count());
            }
        } finally {
            file_put_contents($dir.'/one.finish', '1');
            foreach ([$one, $two] as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop(5);
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
