<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureInstanceOrganisation;
use App\Models\Club;
use App\Models\ClubMembership;
use App\Models\Member;
use App\Models\MemberProfileDocument;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\AccountActivationService;
use Database\Seeders\DefaultRolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InstanceOrganisationTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;
    private Club $foreign;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->seed(DefaultRolesAndPermissionsSeeder::class);
        // LCB deliberately comes first; it must never be selected by preference or order.
        $this->foreign = Club::create(['name' => 'Foreign', 'code' => 'LCB', 'is_active' => true]);
        $this->club = Club::create(['name' => 'Assigned', 'code' => 'PILOT-B', 'is_active' => true]);
        config(['instance.organisation_code' => 'PILOT-B']);
        $this->user = User::factory()->create(['password' => 'Synthetic!Password123']);
        foreach ([$this->foreign, $this->club] as $club) {
            ClubMembership::create(['club_id' => $club->id, 'user_id' => $this->user->id,
                'role_id' => Role::where('slug', 'president-admin')->sole()->id, 'status' => 'active']);
        }
    }

    public function test_session_exposes_only_assigned_active_membership_and_member(): void
    {
        foreach ([$this->foreign, $this->club] as $club) {
            Member::create(['club_id' => $club->id, 'user_id' => $this->user->id,
                'member_code' => 'SYN-1', 'full_name' => $club->name, 'status' => 'active']);
        }
        $token = $this->postJson('/api/v1/auth/login', ['email' => $this->user->email,
            'password' => 'Synthetic!Password123'])->assertOk()->json('token');
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk()
            ->assertJsonPath('instance_organisation.id', $this->club->id)
            ->assertJsonPath('instance_organisation.code', 'PILOT-B')
            ->assertJsonCount(1, 'club_memberships')
            ->assertJsonPath('club_memberships.0.club.id', $this->club->id)
            ->assertJsonPath('club_memberships.0.status', 'active')
            ->assertJsonPath('club_memberships.0.member.full_name', 'Assigned')
            ->assertJsonMissing(['code' => 'LCB']);
    }

    public static function invalidConfigurations(): array
    {
        return ['missing' => [null], 'blank' => [''], 'unknown' => ['UNKNOWN'],
            'whitespace' => [' PILOT-B '], 'array' => [['PILOT-B']], 'numeric' => [2]];
    }

    #[DataProvider('invalidConfigurations')]
    public function test_invalid_configuration_fails_closed_without_token_or_member_disclosure(mixed $value): void
    {
        config(['instance.organisation_code' => $value]);
        $this->postJson('/api/v1/auth/login', ['email' => $this->user->email, 'password' => 'Synthetic!Password123'])
            ->assertStatus(503)->assertJsonMissingPath('token');
        $this->assertDatabaseCount('personal_access_tokens', 0);
        Sanctum::actingAs($this->user);
        $this->getJson('/api/v1/auth/me')->assertStatus(503)->assertJsonMissingPath('user');
        $this->getJson("/api/v1/clubs/{$this->club->id}/members")->assertStatus(503)->assertJsonMissingPath('members');
        $this->getJson('/api/v1/health')->assertOk();
    }

    public function test_missing_or_inactive_matching_membership_denies_login_and_existing_session(): void
    {
        ClubMembership::where('club_id', $this->club->id)->update(['status' => 'inactive']);
        $this->postJson('/api/v1/auth/login', ['email' => $this->user->email, 'password' => 'Synthetic!Password123'])
            ->assertForbidden()->assertJsonMissingPath('token');
        Sanctum::actingAs($this->user);
        $this->getJson('/api/v1/auth/me')->assertForbidden()->assertJsonMissingPath('club_memberships');
        $this->assertDatabaseCount('personal_access_tokens', 0);
        ClubMembership::where('club_id', $this->club->id)->delete();
        $this->getJson('/api/v1/auth/me')->assertForbidden();
    }

    public function test_all_club_routes_have_instance_guard_before_bindings(): void
    {
        $router = app('router');
        $count = 0;
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/clubs/{club}')) continue;
            $middleware = $router->gatherRouteMiddleware($route);
            $this->assertContains(EnsureInstanceOrganisation::class, $middleware, $route->uri());
            $this->assertLessThan(array_search(\Illuminate\Routing\Middleware\SubstituteBindings::class, $middleware),
                array_search(EnsureInstanceOrganisation::class, $middleware), $route->uri());
            $count++;
        }
        $this->assertGreaterThan(80, $count);
    }

    public function test_foreign_routes_are_rejected_before_controllers_even_with_membership_and_permission(): void
    {
        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/clubs/{$this->club->id}/members")->assertOk();
        $club = $this->foreign->id;
        foreach (["/clubs/$club/members", "/clubs/$club/members/me", "/clubs/$club/reports",
            "/clubs/$club/members/999/profile-documents", "/clubs/$club/members/999/payments/999/receipt",
            "/clubs/$club/finance/account-statement", "/clubs/$club/events", "/clubs/$club/projects",
            "/clubs/$club/reminders", "/clubs/$club/announcements"] as $path) {
            $this->getJson('/api/v1'.$path)->assertNotFound()->assertJsonMissingPath('data');
        }
        $this->postJson("/api/v1/clubs/$club/members", ['full_name' => 'Forbidden'])->assertNotFound();
        $this->postJson("/api/v1/clubs/$club/finance/ordinary-income-receipts", [])->assertNotFound();
        $this->assertDatabaseCount('members', 0);
        $this->assertDatabaseCount('club_journal_entries', 0);
    }

    public function test_matching_organisation_preserves_permission_and_resource_ownership_checks(): void
    {
        Sanctum::actingAs($this->user);
        $member = Member::create(['club_id' => $this->foreign->id, 'member_code' => 'FOREIGN', 'full_name' => 'Hidden']);
        $document = MemberProfileDocument::create(['club_id' => $this->foreign->id, 'member_id' => $member->id,
            'document_type' => 'identity', 'status' => 'active']);
        $this->getJson("/api/v1/clubs/{$this->club->id}/members/{$member->id}")->assertNotFound();
        $this->getJson("/api/v1/clubs/{$this->club->id}/members/{$member->id}/profile-documents/{$document->id}")->assertNotFound();
        ClubMembership::where('club_id', $this->club->id)->update(['role_id' => Role::where('slug', 'member')->sole()->id]);
        $this->postJson("/api/v1/clubs/{$this->club->id}/members", ['full_name' => 'Forbidden'])->assertForbidden();
        $this->assertDatabaseCount('members', 1);
    }

    public function test_logout_remains_available_when_instance_configuration_is_broken(): void
    {
        $token = $this->user->createToken('synthetic')->plainTextToken;
        config(['instance.organisation_code' => null]);
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_request_headers_and_body_cannot_override_deployment_identity(): void
    {
        Sanctum::actingAs($this->user);
        $this->withHeaders(['X-Organisation-Code' => 'LCB', 'X-Club-Id' => (string) $this->foreign->id])
            ->getJson('/api/v1/auth/me?organisation_code=LCB&club_id='.$this->foreign->id)
            ->assertOk()->assertJsonPath('instance_organisation.id', $this->club->id);
    }

    public function test_activation_cannot_cross_the_instance_boundary_or_mutate_credentials(): void
    {
        $member = Member::create(['club_id' => $this->foreign->id, 'user_id' => $this->user->id,
            'member_code' => 'FOREIGN', 'full_name' => 'Foreign', 'status' => 'active']);
        // Simulate an activation issued by the foreign deployment, then return to this deployment.
        config(['instance.organisation_code' => 'LCB']);
        $issued = app(AccountActivationService::class)->issue($this->foreign, $member->member_code, $this->user->email, 'bootstrap', null);
        parse_str(parse_url($issued['link'], PHP_URL_FRAGMENT), $fragment);
        config(['instance.organisation_code' => 'PILOT-B']);
        $password = $this->user->password;
        $this->postJson('/api/v1/auth/activate', ['token' => $fragment['token'], 'email' => $this->user->email,
            'password' => 'Synthetic!Changed123', 'password_confirmation' => 'Synthetic!Changed123'])->assertNotFound();
        $this->assertSame($password, $this->user->fresh()->password);
        $this->assertNull($issued['activation']->fresh()->consumed_at);
        $this->assertDatabaseCount('account_activations', 1);
    }
}
