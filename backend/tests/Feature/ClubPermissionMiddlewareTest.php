<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubMembership;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ClubPermissionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:sanctum', 'club.permission:club.manage,club'])
            ->get('/api/test/clubs/{club}/manage', fn () => response()->json([
                'message' => 'Permission granted.',
            ]));
    }

    public function test_user_with_required_permission_can_access_protected_route(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $user = User::where('email', 'president@lcbeenachi.test')->firstOrFail();

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/test/clubs/{$club->id}/manage");

        $response->assertOk()
            ->assertJson([
                'message' => 'Permission granted.',
            ]);
    }

    public function test_user_without_required_permission_is_forbidden(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $memberRole = Role::where('slug', 'member')->firstOrFail();

        $user = User::factory()->create([
            'email' => 'member-middleware@lcbeenachi.test',
        ]);

        ClubMembership::create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'role_id' => $memberRole->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/test/clubs/{$club->id}/manage");

        $response->assertForbidden()
            ->assertJson([
                'message' => 'This action is unauthorized.',
            ]);
    }

    public function test_unauthenticated_user_cannot_access_protected_route(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();

        $response = $this->getJson("/api/test/clubs/{$club->id}/manage");

        $response->assertUnauthorized();
    }

    public function test_missing_or_invalid_club_context_is_forbidden(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::where('email', 'president@lcbeenachi.test')->firstOrFail();

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/test/clubs/not-a-valid-club/manage');

        $response->assertForbidden()
            ->assertJson([
                'message' => 'A valid club context is required.',
            ]);
    }
}
