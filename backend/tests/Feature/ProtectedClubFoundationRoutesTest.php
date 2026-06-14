<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubMembership;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProtectedClubFoundationRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_president_can_view_club_foundation_details(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $user = User::where('email', 'president@lcbeenachi.test')->firstOrFail();

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/clubs/{$club->id}");

        $response->assertOk()
            ->assertJsonPath('club.name', 'Lions Club Beenachi')
            ->assertJsonPath('club.code', 'LCB')
            ->assertJsonPath('club.district', '318E')
            ->assertJsonPath('club.timezone', 'Asia/Kolkata')
            ->assertJsonPath('club.is_active', true);
    }

    public function test_authenticated_president_can_view_own_club_access_context(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $user = User::where('email', 'president@lcbeenachi.test')->firstOrFail();

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/clubs/{$club->id}/me");

        $response->assertOk()
            ->assertJsonPath('club.code', 'LCB')
            ->assertJsonPath('membership.status', 'active')
            ->assertJsonPath('membership.role.slug', 'president-admin')
            ->assertJsonFragment([
                'club.manage',
            ])
            ->assertJsonFragment([
                'users.manage',
            ])
            ->assertJsonFragment([
                'members.view',
            ])
            ->assertJsonFragment([
                'members.manage',
            ])
            ->assertJsonFragment([
                'payments.record',
            ]);

        $this->assertCount(13, $response->json('permissions'));
    }

    public function test_normal_member_can_view_club_foundation_but_only_gets_limited_permissions(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $memberRole = Role::where('slug', 'member')->firstOrFail();

        $user = User::factory()->create([
            'email' => 'normal-member-foundation@lcbeenachi.test',
        ]);

        ClubMembership::create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'role_id' => $memberRole->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $clubResponse = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/clubs/{$club->id}");

        $clubResponse->assertOk()
            ->assertJsonPath('club.code', 'LCB');

        $meResponse = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/clubs/{$club->id}/me");

        $meResponse->assertOk()
            ->assertJsonPath('membership.role.slug', 'member');

        $this->assertSame(['club.view', 'members.view'], $meResponse->json('permissions'));
    }

    public function test_user_without_club_membership_cannot_view_club_foundation_details(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();

        $user = User::factory()->create([
            'email' => 'outsider-foundation@lcbeenachi.test',
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/clubs/{$club->id}");

        $response->assertForbidden()
            ->assertJson([
                'message' => 'This action is unauthorized.',
            ]);
    }

    public function test_unauthenticated_user_cannot_view_club_foundation_details(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();

        $response = $this->getJson("/api/v1/clubs/{$club->id}");

        $response->assertUnauthorized();
    }
}
