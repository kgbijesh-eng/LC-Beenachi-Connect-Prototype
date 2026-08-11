<?php

namespace Tests\Feature;

use App\Models\AccountActivation;
use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubMembership;
use App\Models\Member;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\AccountActivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_backend_tests_are_forced_to_isolated_in_memory_sqlite(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    public function test_authenticated_authorized_issuance_stores_only_hash_and_revokes_replacement(): void
    {
        [$club, $target, $member] = $this->linkedTarget();
        $actor = $this->authorizedActor($club);
        Sanctum::actingAs($actor);

        $first = $this->postJson("/api/v1/clubs/{$club->id}/members/account-activations", [
            'member_code' => $member->member_code,
            'email' => ' Operational.Member@Example.test ',
        ])->assertCreated()->assertJsonStructure(['activation_url', 'expires_at']);
        $firstToken = $this->fragmentToken($first->json('activation_url'));

        $this->assertDatabaseMissing('account_activations', ['token_hash' => $firstToken]);
        $this->assertSame(AccountActivationService::tokenHash($firstToken), AccountActivation::first()->token_hash);
        $this->assertSame('operational.member@example.test', AccountActivation::first()->intended_email);
        $this->assertSame('authenticated', AccountActivation::first()->issuance_method);
        $this->assertNotNull(AccountActivation::first()->expires_at);

        $this->postJson("/api/v1/clubs/{$club->id}/members/account-activations", [
            'member_code' => $member->member_code,
            'email' => 'operational.member@example.test',
        ])->assertCreated();

        $this->assertNotNull(AccountActivation::query()->oldest('id')->first()->revoked_at);
        $this->assertDatabaseCount('account_activations', 2);
        $this->assertSafeAudit();
        $this->assertSame($target->id, AccountActivation::query()->latest('id')->first()->user_id);
    }

    public function test_issuance_requires_authentication_and_explicit_permission(): void
    {
        [$club, , $member] = $this->linkedTarget();
        $payload = ['member_code' => $member->member_code, 'email' => 'new@example.test'];

        $this->postJson("/api/v1/clubs/{$club->id}/members/account-activations", $payload)->assertUnauthorized();

        Sanctum::actingAs($this->actorWithoutPermission($club));
        $this->postJson("/api/v1/clubs/{$club->id}/members/account-activations", $payload)->assertForbidden();
    }

    public function test_inactive_and_duplicate_email_targets_are_rejected(): void
    {
        [$club, , $member, $membership] = $this->linkedTarget();
        Sanctum::actingAs($this->authorizedActor($club));
        User::factory()->create(['email' => 'owned@example.test']);

        $this->postJson("/api/v1/clubs/{$club->id}/members/account-activations", [
            'member_code' => $member->member_code, 'email' => 'owned@example.test',
        ])->assertUnprocessable();

        $membership->update(['status' => 'inactive']);
        $this->postJson("/api/v1/clubs/{$club->id}/members/account-activations", [
            'member_code' => $member->member_code, 'email' => 'free@example.test',
        ])->assertUnprocessable();
    }

    public function test_valid_completion_updates_credentials_consumes_activation_and_revokes_tokens(): void
    {
        [$club, $target, $member] = $this->linkedTarget();
        $target->createToken('synthetic-existing-session');
        $issued = app(AccountActivationService::class)->issue(
            $club, $member->member_code, 'NEW.LOGIN@EXAMPLE.TEST', 'bootstrap', null
        );
        $token = $this->fragmentToken($issued['link']);

        $response = $this->postJson('/api/v1/auth/activate', [
            'token' => $token,
            'email' => 'new.login@example.test',
            'password' => 'Synthetic!Password123',
            'password_confirmation' => 'Synthetic!Password123',
        ])->assertOk()->assertJsonMissingPath('token');

        $target->refresh();
        $this->assertSame('new.login@example.test', $target->email);
        $this->assertTrue(Hash::check('Synthetic!Password123', $target->password));
        $this->assertNotNull($issued['activation']->fresh()->consumed_at);
        $this->assertCount(0, $target->tokens);
        $this->assertSame('Account activation completed. Sign in with your operational credentials.', $response->json('message'));
        $this->assertSafeAudit();
    }

    public function test_completion_rejects_replay_expiry_mismatch_and_unknown_tokens_generically(): void
    {
        [$club, , $member] = $this->linkedTarget();
        $issued = app(AccountActivationService::class)->issue($club, $member->member_code, 'new@example.test', 'bootstrap', null);
        $token = $this->fragmentToken($issued['link']);
        $payload = [
            'token' => $token, 'email' => 'new@example.test',
            'password' => 'Synthetic!Password123', 'password_confirmation' => 'Synthetic!Password123',
        ];
        $this->postJson('/api/v1/auth/activate', $payload)->assertOk();
        $replay = $this->postJson('/api/v1/auth/activate', $payload)->assertUnprocessable();
        $unknown = $this->postJson('/api/v1/auth/activate', array_merge($payload, [
            'token' => str_repeat('x', 64),
        ]))->assertUnprocessable();
        $this->assertSame($replay->json('message'), $unknown->json('message'));
    }

    public function test_weak_password_and_missing_confirmation_are_rejected_without_consumption(): void
    {
        [$club, , $member] = $this->linkedTarget();
        $issued = app(AccountActivationService::class)->issue($club, $member->member_code, 'new@example.test', 'bootstrap', null);

        $this->postJson('/api/v1/auth/activate', [
            'token' => $this->fragmentToken($issued['link']),
            'email' => 'new@example.test',
            'password' => 'weak',
        ])->assertUnprocessable();
        $this->assertNull($issued['activation']->fresh()->consumed_at);
    }

    public function test_bootstrap_command_is_disabled_by_default_and_never_accepts_password(): void
    {
        $this->artisan('account:activation-issue', [
            '--club' => 'TEST',
            '--member' => 'MEM-001',
            '--email' => 'new@example.test',
        ])->assertFailed();

        $this->assertFalse(Artisan::all()['account:activation-issue']->getDefinition()->hasOption('password'));
    }

    public function test_enabled_bootstrap_requires_confirmation_and_uses_synthetic_linkage(): void
    {
        config(['account_activation.bootstrap_enabled' => true]);
        [$club, , $member] = $this->linkedTarget();

        $this->artisan('account:activation-issue', [
            '--club' => $club->code,
            '--member' => $member->member_code,
            '--email' => 'bootstrap@example.test',
        ])->expectsConfirmation('Issue a single-use activation for this exact target?', 'no')->assertFailed();
        $this->assertDatabaseCount('account_activations', 0);

        $this->artisan('account:activation-issue', [
            '--club' => $club->code,
            '--member' => $member->member_code,
            '--email' => 'bootstrap@example.test',
        ])->expectsConfirmation('Issue a single-use activation for this exact target?', 'yes')
            ->expectsOutputToContain('/activate#token=')
            ->assertSuccessful();
        $this->assertSame('bootstrap', AccountActivation::first()->issuance_method);
        $this->assertNull(AccountActivation::first()->issuing_actor_user_id);
        $this->assertSafeAudit();
    }

    public function test_login_and_activation_routes_are_rate_limited_generically(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'nobody@example.test', 'password' => 'Synthetic!Wrong123',
            ])->assertUnprocessable();
        }
        $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.test', 'password' => 'Synthetic!Wrong123',
        ])->assertStatus(429)->assertJsonMissingPath('email');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/activate', [
                'token' => str_repeat((string) $attempt, 64),
                'email' => 'nobody@example.test',
                'password' => 'Synthetic!Password123',
                'password_confirmation' => 'Synthetic!Password123',
            ])->assertUnprocessable();
        }
        $this->postJson('/api/v1/auth/activate', [
            'token' => str_repeat('z', 64),
            'email' => 'nobody@example.test',
            'password' => 'Synthetic!Password123',
            'password_confirmation' => 'Synthetic!Password123',
        ])->assertStatus(429)->assertJson(['message' => 'Account activation could not be completed.']);
    }

    private function linkedTarget(): array
    {
        $club = Club::query()->create(['name' => 'Synthetic Club', 'code' => 'SYN', 'is_active' => true]);
        $role = Role::query()->create(['name' => 'Member', 'slug' => 'member', 'is_system' => true]);
        $user = User::factory()->create([
            'email' => 'development@example.test',
            'password' => 'Synthetic!Existing123',
        ]);
        $membership = ClubMembership::query()->create([
            'club_id' => $club->id, 'user_id' => $user->id, 'role_id' => $role->id, 'status' => 'active',
        ]);
        $member = Member::query()->create([
            'club_id' => $club->id, 'user_id' => $user->id, 'member_code' => 'MEM-001',
            'full_name' => 'Synthetic Member', 'email' => 'member@example.test', 'status' => 'active',
        ]);

        return [$club, $user, $member, $membership];
    }

    private function authorizedActor(Club $club): User
    {
        $permission = Permission::query()->create([
            'name' => 'Issue activations', 'slug' => AccountActivationService::PERMISSION_ISSUE, 'group' => 'members',
        ]);
        $role = Role::query()->create(['name' => 'Officer', 'slug' => 'officer', 'is_system' => false]);
        $role->permissions()->attach($permission);
        $actor = User::factory()->create([
            'email' => 'actor@example.test',
            'password' => 'Synthetic!Actor123',
        ]);
        ClubMembership::query()->create([
            'club_id' => $club->id, 'user_id' => $actor->id, 'role_id' => $role->id, 'status' => 'active',
        ]);

        return $actor;
    }

    private function actorWithoutPermission(Club $club): User
    {
        $role = Role::query()->create(['name' => 'Observer', 'slug' => 'observer', 'is_system' => false]);
        $actor = User::factory()->create([
            'email' => 'observer@example.test',
            'password' => 'Synthetic!Observer123',
        ]);
        ClubMembership::query()->create([
            'club_id' => $club->id, 'user_id' => $actor->id, 'role_id' => $role->id, 'status' => 'active',
        ]);

        return $actor;
    }

    private function fragmentToken(string $link): string
    {
        parse_str(parse_url($link, PHP_URL_FRAGMENT), $fragment);

        return $fragment['token'];
    }

    private function assertSafeAudit(): void
    {
        $serialized = AuditLog::query()->get()->toJson();
        $this->assertStringNotContainsString('token', strtolower($serialized));
        $this->assertStringNotContainsString('password', strtolower($serialized));
        $this->assertStringNotContainsString('/activate#', strtolower($serialized));
    }
}
