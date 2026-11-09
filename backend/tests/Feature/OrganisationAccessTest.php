<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureInstanceOrganisation;
use App\Models\Club;
use App\Models\ClubMembership;
use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use App\Services\Access\OrganisationAccess;
use App\Services\Auth\AccountActivationService;
use Database\Seeders\DefaultRolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FinancialPostgresTestCase;

class OrganisationAccessTest extends FinancialPostgresTestCase
{
    private Club $club;
    private Club $foreign;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DefaultRolesAndPermissionsSeeder::class);
        $this->foreign = Club::create(['name' => 'Foreign secret', 'code' => 'FOREIGN', 'is_active' => true]);
        $this->club = Club::create(['name' => 'Assigned secret', 'code' => 'ASSIGNED', 'is_active' => true]);
        config(['instance.organisation_code' => 'ASSIGNED']);
        $this->user = User::factory()->create(['password' => 'Synthetic!Password123']);
        foreach ([$this->foreign, $this->club] as $club) {
            ClubMembership::create(['club_id' => $club->id, 'user_id' => $this->user->id,
                'role_id' => Role::where('slug', 'president-admin')->sole()->id, 'status' => 'active']);
        }
    }

    public function test_active_organisation_preserves_login_session_and_protected_reads(): void
    {
        $token = $this->login();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk()
            ->assertJsonPath('instance_organisation.id', $this->club->id)
            ->assertJsonCount(1, 'club_memberships')->assertJsonMissing(['code' => 'FOREIGN']);
        $this->getJson("/api/v1/clubs/{$this->club->id}/members")->assertOk();
    }

    public function test_existing_token_is_denied_on_every_club_route_after_database_suspension(): void
    {
        $token = $this->login();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk();
        // Retain stale active model and reuse the same service object deliberately.
        $access = app(OrganisationAccess::class);
        $this->assertTrue($access->resolve()->is_active);
        Club::whereKey($this->club->id)->update(['is_active' => false]);
        $this->assertTrue($this->club->is_active);
        app()->instance(OrganisationAccess::class, $access);
        $this->assertSuspended($this->getJson('/api/v1/auth/me'));

        $count = 0;
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/clubs/{club}')) continue;
            $uri = str_replace('{club}', (string) $this->club->id, $route->uri());
            $uri = preg_replace('/\{[^}]+\}/', '00000000-0000-4000-8000-000000000001', $uri);
            foreach ($route->methods() as $method) {
                Auth::forgetGuards();
                $response = $this->withToken($token)->json($method, '/'.$uri);
                if ($method === 'HEAD') {
                    $response->assertForbidden();
                } else {
                    $this->assertSuspended($response);
                }
                $count++;
            }
        }
        $this->assertGreaterThan(110, $count);
        foreach (['members', 'payments', 'receipts', 'member_payments', 'payment_allocations',
            'club_charges', 'club_expenses', 'club_internal_transfers', 'club_ordinary_income_receipts',
            'club_journal_entries', 'club_journal_lines', 'audit_logs', 'account_activations'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        // P0.2A denies HTTP access; it does not revoke credentials.
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_suspension_blocks_login_and_activation_without_credential_mutation(): void
    {
        $member = Member::create(['club_id' => $this->club->id, 'user_id' => $this->user->id,
            'member_code' => 'SYN-1', 'full_name' => 'Synthetic member', 'status' => 'active']);
        config(['account_activation.bootstrap_enabled' => true]);
        $issued = app(AccountActivationService::class)->issue($this->club, $member->member_code,
            $this->user->email, 'bootstrap', null);
        parse_str(parse_url($issued['link'], PHP_URL_FRAGMENT), $fragment);
        $password = $this->user->password;
        $token = $this->login();
        Club::whereKey($this->club->id)->update(['is_active' => false]);
        $this->assertSuspended($this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email, 'password' => 'Synthetic!Password123']));
        $this->assertSuspended($this->postJson('/api/v1/auth/activate', [
            'token' => $fragment['token'], 'email' => $this->user->email,
            'password' => 'Synthetic!Changed123', 'password_confirmation' => 'Synthetic!Changed123']));
        $this->assertSuspended($this->withToken($token)->postJson(
            "/api/v1/clubs/{$this->club->id}/members/account-activations",
            ['member_code' => $member->member_code, 'email' => $this->user->email]));
        $this->assertSame($password, $this->user->fresh()->password);
        $this->assertNull($issued['activation']->fresh()->consumed_at);
        $this->assertDatabaseCount('account_activations', 1);
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_foreign_club_stays_404_even_when_assigned_club_is_suspended(): void
    {
        $token = $this->login();
        Club::whereKey($this->club->id)->update(['is_active' => false]);
        $this->withToken($token)->getJson("/api/v1/clubs/{$this->foreign->id}/members")
            ->assertNotFound()->assertJsonMissingPath('code')->assertJsonMissingPath('data');
        $this->postJson("/api/v1/clubs/{$this->foreign->id}/members", [])
            ->assertNotFound()->assertJsonMissingPath('code');
        $this->assertDatabaseCount('members', 0);
    }

    public static function invalidConfigurations(): array
    {
        return ['missing' => [null], 'blank' => [''], 'unknown' => ['UNKNOWN'],
            'whitespace' => [' ASSIGNED '], 'array' => [['ASSIGNED']], 'numeric' => [2]];
    }

    #[DataProvider('invalidConfigurations')]
    public function test_invalid_configuration_keeps_503_on_authentication_activation_and_protected_routes(mixed $value): void
    {
        $token = $this->login();
        Club::whereKey($this->club->id)->update(['is_active' => false]);
        config(['instance.organisation_code' => $value]);
        foreach (['/api/v1/auth/login', '/api/v1/auth/activate'] as $uri) {
            $this->postJson($uri)->assertStatus(503)->assertJsonMissingPath('token')->assertJsonMissingPath('code');
        }
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertStatus(503)->assertJsonMissingPath('user');
        $this->getJson("/api/v1/clubs/{$this->club->id}/members")->assertStatus(503)->assertJsonMissingPath('data');
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_deleted_configured_organisation_fails_closed(): void
    {
        // No membership/finance cascade assumptions: select a separate empty organisation.
        $missing = Club::create(['name' => 'Removed', 'code' => 'REMOVED', 'is_active' => true]);
        config(['instance.organisation_code' => 'REMOVED']);
        $access = app(OrganisationAccess::class);
        $this->assertSame($missing->id, $access->resolve()->id);
        $missing->delete();
        $this->postJson('/api/v1/auth/login')->assertStatus(503)->assertJsonMissingPath('token');
    }

    public function test_state_is_rechecked_without_revocation_and_current_permissions_still_apply(): void
    {
        $token = $this->login();
        Club::whereKey($this->club->id)->update(['is_active' => false]);
        $this->assertSuspended($this->withToken($token)->getJson('/api/v1/auth/me'));
        Club::whereKey($this->club->id)->update(['is_active' => true]);
        Auth::forgetGuards();
        // Deliberate P0.2A limitation: no credential revocation is implemented.
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk();
        ClubMembership::where('club_id', $this->club->id)->update(['status' => 'inactive']);
        Auth::forgetGuards();
        $this->getJson('/api/v1/auth/me')->assertForbidden()->assertJsonMissingPath('club_memberships');
        $this->getJson("/api/v1/clubs/{$this->club->id}/members")->assertForbidden();
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_health_and_authenticated_logout_work_while_suspended_or_misconfigured(): void
    {
        foreach (['ASSIGNED', null] as $code) {
            $token = $this->user->createToken('exception-test')->plainTextToken;
            Club::whereKey($this->club->id)->update(['is_active' => false]);
            config(['instance.organisation_code' => $code]);
            $this->getJson('/api/v1/health')->assertOk()->assertJsonMissingPath('instance_organisation');
            $this->head('/api/v1/health')->assertOk();
            $this->get('/up')->assertOk();
            Auth::forgetGuards();
            $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
            $this->assertDatabaseCount('personal_access_tokens', 0);
        }
        Auth::forgetGuards();
        $this->withHeader('Authorization', '')->postJson('/api/v1/auth/logout')->assertUnauthorized();
    }

    public function test_new_non_club_api_routes_are_protected_and_exceptions_are_method_specific(): void
    {
        foreach ([['get', 'api/v1/eligibility-probe'], ['post', 'api/v1/health'], ['get', 'api/v1/auth/logout']] as [$method, $uri]) {
            Route::$method($uri, fn () => response()->json(['protected' => 'must not execute']))->middleware('api');
        }
        Club::whereKey($this->club->id)->update(['is_active' => false]);
        $this->assertSuspended($this->getJson('/api/v1/eligibility-probe'));
        $this->assertSuspended($this->postJson('/api/v1/health'));
        $this->assertSuspended($this->getJson('/api/v1/auth/logout'));
    }

    public function test_all_business_api_routes_have_guard_before_model_binding(): void
    {
        $router = app('router');
        $count = 0;
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/')) continue;
            $middleware = $router->gatherRouteMiddleware($route);
            $this->assertContains(EnsureInstanceOrganisation::class, $middleware, $route->uri());
            $this->assertLessThan(array_search(\Illuminate\Routing\Middleware\SubstituteBindings::class, $middleware),
                array_search(EnsureInstanceOrganisation::class, $middleware), $route->uri());
            if ($route->uri() === 'api/v1/auth/logout') $this->assertContains('Illuminate\Auth\Middleware\Authenticate:sanctum', $middleware);
            $count++;
        }
        $this->assertSame(115, $count);
    }

    private function login(): string
    {
        return $this->postJson('/api/v1/auth/login', ['email' => $this->user->email,
            'password' => 'Synthetic!Password123'])->assertOk()->json('token');
    }

    private function assertSuspended(\Illuminate\Testing\TestResponse $response): void
    {
        $response->assertForbidden()->assertExactJson([
            'message' => 'Organisation access is suspended.', 'code' => 'ORGANISATION_SUSPENDED',
        ]);
    }
}
