<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AccountActivation;
use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubMembership;
use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use App\Services\Access\OrganisationAccessRejected;
use App\Services\Auth\AccountActivationService;
use Database\Seeders\DefaultRolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\FinancialPostgresTestCase;

class CredentialTransactionConcurrencyTest extends FinancialPostgresTestCase
{
    private const OLD_PASSWORD = 'Synthetic!Original123';

    private const NEW_PASSWORD = 'Synthetic!Replacement123';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DefaultRolesAndPermissionsSeeder::class);
        config(['account_activation.bootstrap_enabled' => true]);
    }

    public static function races(): iterable
    {
        foreach (['issue-complete', 'http-cli', 'login-complete', 'cross-club-login', 'cross-club-completion', 'logout-complete', 'email-claim', 'duplicate-completion'] as $race) {
            yield "$race-left-first" => [$race, false];
            yield "$race-right-first" => [$race, true];
        }
    }

    #[DataProvider('races')]
    public function test_credential_operations_serialize(string $scenario, bool $reverse): void
    {
        [$club, $user, $member] = $this->target('A');
        $email = 'updated@example.test';
        [$grant, $complete] = $this->completion($club, $member, $email);
        $expectedLock = 'club';
        $left = $complete;
        $right = $complete;
        $expected = [200, 422];
        $otherGrant = null;
        $otherUser = null;

        if (in_array($scenario, ['issue-complete', 'http-cli'], true)) {
            $actor = User::factory()->create();
            $this->membership($club, $actor, 'president-admin');
            $left = $this->request($club, "/api/v1/clubs/{$club->id}/members/account-activations", [
                'member_code' => $member->member_code, 'email' => $email,
            ]) + ['bearer' => $actor->createToken('synthetic-issuer')->plainTextToken];
            if ($scenario === 'http-cli') {
                $right = ['club_code' => $club->code, 'cli' => ['--club' => $club->code, '--member' => $member->member_code, '--email' => $email]];
                $expected = $reverse ? [0, 201] : [201, 0];
            } else {
                $expected = $reverse ? [200, 201] : [201, 422];
            }
        } elseif (in_array($scenario, ['login-complete', 'cross-club-login'], true)) {
            $loginClub = $club;
            if ($scenario === 'cross-club-login') {
                [$loginClub] = $this->target('B', $user);
                $expectedLock = 'user';
            }
            $left = $this->request($loginClub, '/api/v1/auth/login', ['email' => $user->email, 'password' => self::OLD_PASSWORD]);
            $expected = $reverse ? [200, 422] : [200, 200];
        } elseif ($scenario === 'logout-complete') {
            $left = $this->request($club, '/api/v1/auth/logout') + ['bearer' => $user->createToken('synthetic-logout')->plainTextToken];
            $expected = [200, 200];
            $expectedLock = 'user';
        } elseif ($scenario === 'cross-club-completion') {
            [$otherClub, , $otherMember] = $this->target('B', $user);
            [$otherGrant, $right] = $this->completion($otherClub, $otherMember, 'other-updated@example.test');
            $expected = [200, 200];
            $expectedLock = 'user';
        } elseif ($scenario === 'email-claim') {
            [$otherClub, $otherUser, $otherMember] = $this->target('B');
            [$otherGrant, $right] = $this->completion($otherClub, $otherMember, $email);
            $otherUser->createToken('synthetic-other-session');
            $expectedLock = 'email';
        }
        $user->createToken('synthetic-original-session');

        [$first, $second] = $reverse ? [$right, $left] : [$left, $right];
        $this->assertSame($expected, $this->race($first, $second, $expectedLock));

        $consumed = ! in_array($scenario, ['issue-complete', 'http-cli'], true) || ($scenario === 'issue-complete' && $reverse);
        if ($scenario === 'email-claim') {
            $winner = $reverse ? $otherUser : $user;
            $loser = $reverse ? $user : $otherUser;
            $this->assertSame($email, $winner->refresh()->email);
            $this->assertTrue(Hash::check(self::NEW_PASSWORD, $winner->password));
            $this->assertSame(0, $winner->tokens()->count());
            $this->assertTrue(Hash::check(self::OLD_PASSWORD, $loser->refresh()->password));
            $this->assertSame(1, $loser->tokens()->count());
            $this->assertNotNull(($reverse ? $otherGrant : $grant)->refresh()->consumed_at);
            $this->assertNull(($reverse ? $grant : $otherGrant)->refresh()->consumed_at);
        } else {
            $this->assertSame($consumed, $grant->refresh()->consumed_at !== null);
            $this->assertTrue(Hash::check($consumed ? self::NEW_PASSWORD : self::OLD_PASSWORD, $user->refresh()->password));
            $this->assertSame($consumed ? 0 : 1, $user->tokens()->count());
            if ($scenario === 'cross-club-completion') {
                $this->assertNotNull($otherGrant->refresh()->consumed_at);
                $this->assertSame($reverse ? $email : 'other-updated@example.test', $user->email);
            }
        }
        $this->assertSame((int) $consumed + (int) ($scenario === 'cross-club-completion'), AuditLog::where('action', 'account_activation.consumed')->count());
        if (in_array($scenario, ['http-cli', 'issue-complete'], true)) {
            $this->assertSame(1, AccountActivation::whereNull('revoked_at')->whereNull('consumed_at')->count());
            $this->assertSame($scenario === 'http-cli' ? 3 : 2, AuditLog::where('action', 'account_activation.issued')->count());
            $this->assertSame($scenario === 'http-cli' ? 2 : (int) ! $reverse, AuditLog::where('action', 'account_activation.superseded')->count());
        }
    }

    public function test_completion_audit_failure_rolls_back_credentials_grant_and_tokens(): void
    {
        [$club, $user, $member] = $this->target('A');
        [$grant, $request] = $this->completion($club, $member, 'updated@example.test');
        $token = $user->createToken('synthetic-original-session')->accessToken;
        $password = $user->password;
        AuditLog::creating(function (AuditLog $audit): void {
            if ($audit->action === 'account_activation.consumed') {
                throw new \LogicException('Synthetic credential audit failure');
            }
        });
        try {
            $p = $request['payload'];
            app(AccountActivationService::class)->complete($p['token'], $p['email'], $p['password']);
            $this->fail('Expected audit failure.');
        } catch (\LogicException $e) {
            $this->assertSame('Synthetic credential audit failure', $e->getMessage());
        } finally {
            AuditLog::flushEventListeners();
        }
        $this->assertSame($password, $user->refresh()->password);
        $this->assertSame('a@example.test', $user->email);
        $this->assertNull($grant->refresh()->consumed_at);
        $this->assertTrue($user->tokens()->whereKey($token->id)->exists());
        $this->assertSame(0, AuditLog::where('action', 'account_activation.consumed')->count());
        $this->assertSame(0, DB::transactionLevel());
    }

    public static function winnerOrders(): iterable
    {
        yield 'credential-first' => [true];
        yield 'membership-first' => [false];
    }

    #[DataProvider('winnerOrders')]
    public function test_completion_rechecks_membership_after_admission(bool $credentialFirst): void
    {
        [$club, $user, $member] = $this->target('A');
        [$grant, $complete] = $this->completion($club, $member, 'updated@example.test');
        $change = ['club_code' => $club->code, 'suspend_membership' => $user->id];
        $this->assertSame(
            $credentialFirst ? [200, 200] : [200, 422],
            $this->race(...($credentialFirst ? [$complete, $change, 'club'] : [$change, $complete, 'club'])),
        );
        $this->assertSame('inactive', ClubMembership::where('club_id', $club->id)->where('user_id', $user->id)->sole()->status);
        $this->assertSame($credentialFirst, $grant->refresh()->consumed_at !== null);
        $this->assertTrue(Hash::check($credentialFirst ? self::NEW_PASSWORD : self::OLD_PASSWORD, $user->refresh()->password));
        $this->assertSame((int) $credentialFirst, AuditLog::where('action', 'account_activation.consumed')->count());
    }

    public function test_foreign_and_revoked_grants_are_rejected_without_waiting_on_their_rows(): void
    {
        [$club, $user, $member] = $this->target('A');
        [$otherClub, , $otherMember] = $this->target('B');
        [$revoked, $local] = $this->completion($club, $member, 'updated@example.test');
        [$foreign, $remote] = $this->completion($otherClub, $otherMember, 'foreign-updated@example.test');
        $revoked->update(['revoked_at' => now()]);
        config(['instance.organisation_code' => $club->code]);
        $holder = DB::build([...DB::connection()->getConfig(), 'name' => 'credential_grant_holder']);
        $holder->beginTransaction();
        try {
            $holder->table('clubs')->where('id', $otherClub->id)->lockForUpdate()->first();
            $holder->table('account_activations')->whereIn('id', [$revoked->id, $foreign->id])->orderBy('id')->lockForUpdate()->get();
            DB::statement("SET lock_timeout = '1s'");
            foreach ([$local, $remote] as $request) {
                $this->postJson($request['route'], $request['payload'])->assertUnprocessable()
                    ->assertExactJson(['message' => 'Account activation could not be completed.']);
            }
        } finally {
            $holder->rollBack();
            $holder->disconnect();
            DB::statement('RESET lock_timeout');
        }
        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $user->refresh()->password));
        $this->assertSame(0, AuditLog::where('action', 'account_activation.consumed')->count());
    }

    public function test_completion_rejects_an_ambient_transaction_without_adopting_it(): void
    {
        [$club, , $member] = $this->target('A');
        [$grant, $request] = $this->completion($club, $member, 'updated@example.test');
        DB::beginTransaction();
        try {
            $p = $request['payload'];
            app(AccountActivationService::class)->complete($p['token'], $p['email'], $p['password']);
            $this->fail('Expected ambient transaction rejection.');
        } catch (OrganisationAccessRejected $e) {
            $this->assertSame('ORGANISATION_TRANSACTION_UNSUPPORTED', $e->reason);
            $this->assertSame(1, DB::transactionLevel());
        } finally {
            DB::rollBack();
        }
        $this->assertNull($grant->refresh()->consumed_at);
    }

    private function target(string $code, ?User $user = null): array
    {
        $club = Club::create(['name' => 'Synthetic '.$code, 'code' => $code, 'is_active' => true]);
        $user ??= User::factory()->create(['email' => strtolower($code).'@example.test', 'password' => self::OLD_PASSWORD]);
        $this->membership($club, $user, 'member');
        $member = Member::create(['club_id' => $club->id, 'user_id' => $user->id, 'member_code' => 'SYN', 'full_name' => 'Synthetic member', 'status' => 'active']);

        return [$club, $user, $member];
    }

    private function membership(Club $club, User $user, string $role): void
    {
        ClubMembership::create(['club_id' => $club->id, 'user_id' => $user->id, 'role_id' => Role::where('slug', $role)->sole()->id, 'status' => 'active']);
    }

    private function completion(Club $club, Member $member, string $email): array
    {
        config(['instance.organisation_code' => $club->code]);
        $issued = app(AccountActivationService::class)->issue($club, $member->member_code, $email, 'bootstrap', null);
        parse_str(parse_url($issued['link'], PHP_URL_FRAGMENT), $fragment);

        return [$issued['activation'], $this->request($club, '/api/v1/auth/activate', [
            'token' => $fragment['token'], 'email' => $email, 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD,
        ])];
    }

    private function request(Club $club, string $route, array $payload = []): array
    {
        return ['club_code' => $club->code, 'route' => $route, 'payload' => $payload];
    }

    private function race(array $first, array $second, string $lock): array
    {
        $dir = sys_get_temp_dir().'/credential-race-'.Str::uuid();
        mkdir($dir, 0700);
        $workers = [];
        try {
            foreach (['one' => $first, 'two' => $second] as $name => $data) {
                $path = "$dir/$name.json";
                file_put_contents($path, json_encode($data + ['hold' => $name === 'one' ? $lock : null], JSON_THROW_ON_ERROR));
                $workers[$name] = new Process([PHP_BINARY, base_path('tests/Support/CredentialTransactionWorker.php'), $path, $dir, $name], base_path(), env: [
                    'INSTANCE_ORGANISATION_CODE' => $data['club_code'],
                    'ACCOUNT_ACTIVATION_BOOTSTRAP_ENABLED' => 'true',
                ], timeout: 45);
                $workers[$name]->start();
                $this->await(fn () => is_file("$dir/$name.ready"));
                unlink($path);
            }
            file_put_contents("$dir/one.start", '1');
            $this->await(fn () => is_file("$dir/one.locked"));
            file_put_contents("$dir/two.start", '1');
            $holder = (int) file_get_contents("$dir/one.ready");
            $waiter = (int) file_get_contents("$dir/two.ready");
            $query = match ($lock) {
                'club' => '%"clubs"%for update%',
                'user' => '%pg_advisory_xact_lock%',
                'email' => 'update "users"%',
            };
            $this->await(fn () => (int) DB::selectOne(
                "SELECT count(*) AS n FROM pg_stat_activity WHERE pid=? AND wait_event_type='Lock' AND ?=ANY(pg_blocking_pids(pid)) AND query LIKE ?",
                [$waiter, $holder, $query],
            )->n === 1);
            file_put_contents("$dir/one.finish", '1');
            $statuses = [];
            foreach ($workers as $name => $worker) {
                $worker->wait();
                $raw = is_file("$dir/$name.result") ? file_get_contents("$dir/$name.result") : '{}';
                $this->assertTrue($worker->isSuccessful(), $raw);
                $result = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
                $this->assertSame(0, $result['depth']);
                $statuses[] = $result['status'];
            }
            fwrite(STDOUT, "\nB6B_CREDENTIAL_LOCK_EDGE ".json_encode([$lock, $holder, $waiter, $statuses], JSON_THROW_ON_ERROR)."\n");

            return $statuses;
        } finally {
            file_put_contents("$dir/one.finish", '1');
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop(5);
                }
            }
            foreach (glob("$dir/*") as $file) {
                unlink($file);
            }
            rmdir($dir);
        }
    }

    private function await(callable $condition): void
    {
        $deadline = microtime(true) + 18;
        while (! $condition()) {
            if (microtime(true) > $deadline) {
                $this->fail('The expected credential blocking edge was not observed.');
            }
            usleep(5000);
        }
    }
}
