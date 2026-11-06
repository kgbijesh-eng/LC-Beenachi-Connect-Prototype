<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubMembership;
use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProtectedMemberReadRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_list_members_for_accessible_club(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $user = User::where('email', 'president@lcbeenachi.test')->firstOrFail();

        Member::create([
            'club_id' => $club->id,
            'member_code' => 'LCB-002',
            'full_name' => 'Beta Member',
            'phone' => '9999999998',
            'status' => 'active',
            'blood_group' => 'A+',
            'blood_donor_consent' => true,
        ]);

        Member::create([
            'club_id' => $club->id,
            'member_code' => 'LCB-001',
            'full_name' => 'Alpha Member',
            'email' => 'alpha@lcbeenachi.test',
            'status' => 'active',
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        config(['instance.organisation_code' => $club->code]);
        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/clubs/{$club->id}/members");

        $response->assertOk()
            ->assertJsonPath('members.0.full_name', 'Alpha Member')
            ->assertJsonPath('members.0.member_code', 'LCB-001')
            ->assertJsonPath('members.1.full_name', 'Beta Member')
            ->assertJsonPath('members.1.blood_group', 'A+')
            ->assertJsonPath('members.1.blood_donor_consent', true);

        $this->assertCount(2, $response->json('members'));
    }

    public function test_authorized_user_can_view_single_member_in_accessible_club(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $user = User::where('email', 'president@lcbeenachi.test')->firstOrFail();

        $member = Member::create([
            'club_id' => $club->id,
            'member_code' => 'LCB-010',
            'full_name' => 'Single Member',
            'phone' => '9999999990',
            'email' => 'single@lcbeenachi.test',
            'status' => 'active',
            'joined_at' => '2026-07-01',
            'blood_group' => 'O+',
            'blood_donor_consent' => false,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        config(['instance.organisation_code' => $club->code]);
        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}");

        $response->assertOk()
            ->assertJsonPath('member.member_code', 'LCB-010')
            ->assertJsonPath('member.full_name', 'Single Member')
            ->assertJsonPath('member.phone', '9999999990')
            ->assertJsonPath('member.email', 'single@lcbeenachi.test')
            ->assertJsonPath('member.joined_at', '2026-07-01')
            ->assertJsonPath('member.blood_group', 'O+')
            ->assertJsonPath('member.blood_donor_consent', false);
    }

    public function test_normal_member_with_club_view_permission_can_read_member_list(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $memberRole = Role::where('slug', 'member')->firstOrFail();

        $user = User::factory()->create([
            'email' => 'normal-member-read@lcbeenachi.test',
        ]);

        ClubMembership::create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'role_id' => $memberRole->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        Member::create([
            'club_id' => $club->id,
            'member_code' => 'LCB-020',
            'full_name' => 'Readable Member',
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        config(['instance.organisation_code' => $club->code]);
        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/clubs/{$club->id}/members");

        $response->assertOk()
            ->assertJsonPath('members.0.full_name', 'Readable Member');
    }

    public function test_user_without_club_membership_cannot_read_members(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();

        $user = User::factory()->create([
            'email' => 'outsider-member-read@lcbeenachi.test',
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        config(['instance.organisation_code' => $club->code]);
        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/clubs/{$club->id}/members");

        $response->assertForbidden()
            ->assertJson([
                'message' => 'This action is unauthorized.',
            ]);
    }

    public function test_member_from_another_club_cannot_be_read_through_wrong_club_route(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $user = User::where('email', 'president@lcbeenachi.test')->firstOrFail();

        $otherClub = Club::create([
            'name' => 'Other Club',
            'code' => 'OTHER',
            'district' => '318E',
        ]);

        $member = Member::create([
            'club_id' => $otherClub->id,
            'member_code' => 'OTHER-001',
            'full_name' => 'Other Club Member',
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        config(['instance.organisation_code' => $club->code]);
        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}");

        $response->assertNotFound()
            ->assertJson([
                'message' => 'Member does not belong to this club.',
            ]);
    }

    public function test_unauthenticated_user_cannot_read_members(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();

        config(['instance.organisation_code' => $club->code]);
        $response = $this->getJson("/api/v1/clubs/{$club->id}/members");

        $response->assertUnauthorized();
    }
}
