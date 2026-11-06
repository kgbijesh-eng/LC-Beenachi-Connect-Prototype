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

class ProtectedMemberUpdateRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_president_admin_can_update_member_for_accessible_club(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $user = User::where('email', 'president@lcbeenachi.test')->firstOrFail();

        $member = Member::create([
            'club_id' => $club->id,
            'member_code' => 'LCB-200',
            'full_name' => 'Old Member Name',
            'phone' => '9999999200',
            'email' => 'old@lcbeenachi.test',
            'status' => 'active',
            'joined_at' => '2026-07-01',
            'blood_group' => 'A+',
            'blood_donor_consent' => false,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        config(['instance.organisation_code' => $club->code]);
        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson("/api/v1/clubs/{$club->id}/members/{$member->id}", [
                'member_code' => 'LCB-201',
                'full_name' => 'Updated Member Name',
                'phone' => '9999999201',
                'email' => 'updated@lcbeenachi.test',
                'status' => 'inactive',
                'joined_at' => '2026-08-01',
                'blood_group' => 'O+',
                'blood_donor_consent' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('member.club_id', $club->id)
            ->assertJsonPath('member.member_code', 'LCB-201')
            ->assertJsonPath('member.full_name', 'Updated Member Name')
            ->assertJsonPath('member.phone', '9999999201')
            ->assertJsonPath('member.email', 'updated@lcbeenachi.test')
            ->assertJsonPath('member.status', 'inactive')
            ->assertJsonPath('member.joined_at', '2026-08-01')
            ->assertJsonPath('member.blood_group', 'O+')
            ->assertJsonPath('member.blood_donor_consent', true);
    }

    public function test_secretary_can_update_member_for_accessible_club(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $secretaryRole = Role::where('slug', 'secretary')->firstOrFail();

        $user = User::factory()->create([
            'email' => 'secretary-update-member@lcbeenachi.test',
        ]);

        ClubMembership::create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'role_id' => $secretaryRole->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $member = Member::create([
            'club_id' => $club->id,
            'member_code' => 'LCB-202',
            'full_name' => 'Secretary Editable Member',
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        config(['instance.organisation_code' => $club->code]);
        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson("/api/v1/clubs/{$club->id}/members/{$member->id}", [
                'full_name' => 'Secretary Updated Member',
            ]);

        $response->assertOk()
            ->assertJsonPath('member.member_code', 'LCB-202')
            ->assertJsonPath('member.full_name', 'Secretary Updated Member');
    }

    public function test_treasurer_cannot_update_member(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $treasurerRole = Role::where('slug', 'treasurer')->firstOrFail();

        $user = User::factory()->create([
            'email' => 'treasurer-update-member@lcbeenachi.test',
        ]);

        ClubMembership::create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'role_id' => $treasurerRole->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $member = Member::create([
            'club_id' => $club->id,
            'member_code' => 'LCB-203',
            'full_name' => 'Treasurer Blocked Member',
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        config(['instance.organisation_code' => $club->code]);
        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson("/api/v1/clubs/{$club->id}/members/{$member->id}", [
                'full_name' => 'Treasurer Changed Name',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('members', [
            'id' => $member->id,
            'full_name' => 'Treasurer Blocked Member',
        ]);
    }

    public function test_normal_member_cannot_update_member(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $memberRole = Role::where('slug', 'member')->firstOrFail();

        $user = User::factory()->create([
            'email' => 'normal-update-member@lcbeenachi.test',
        ]);

        ClubMembership::create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'role_id' => $memberRole->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $member = Member::create([
            'club_id' => $club->id,
            'member_code' => 'LCB-204',
            'full_name' => 'Normal Blocked Member',
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        config(['instance.organisation_code' => $club->code]);
        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson("/api/v1/clubs/{$club->id}/members/{$member->id}", [
                'full_name' => 'Normal Changed Name',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('members', [
            'id' => $member->id,
            'full_name' => 'Normal Blocked Member',
        ]);
    }

    public function test_member_code_must_remain_unique_within_same_club_when_updating(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $user = User::where('email', 'president@lcbeenachi.test')->firstOrFail();

        Member::create([
            'club_id' => $club->id,
            'member_code' => 'LCB-205',
            'full_name' => 'Existing Member',
        ]);

        $member = Member::create([
            'club_id' => $club->id,
            'member_code' => 'LCB-206',
            'full_name' => 'Editable Member',
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        config(['instance.organisation_code' => $club->code]);
        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson("/api/v1/clubs/{$club->id}/members/{$member->id}", [
                'member_code' => 'LCB-205',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['member_code']);
    }

    public function test_member_can_keep_same_member_code_when_updating_other_fields(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $user = User::where('email', 'president@lcbeenachi.test')->firstOrFail();

        $member = Member::create([
            'club_id' => $club->id,
            'member_code' => 'LCB-207',
            'full_name' => 'Same Code Member',
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        config(['instance.organisation_code' => $club->code]);
        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson("/api/v1/clubs/{$club->id}/members/{$member->id}", [
                'member_code' => 'LCB-207',
                'full_name' => 'Same Code Updated Member',
            ]);

        $response->assertOk()
            ->assertJsonPath('member.member_code', 'LCB-207')
            ->assertJsonPath('member.full_name', 'Same Code Updated Member');
    }

    public function test_member_from_another_club_cannot_be_updated_through_wrong_club_route(): void
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
            'member_code' => 'OTHER-200',
            'full_name' => 'Other Club Member',
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        config(['instance.organisation_code' => $club->code]);
        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson("/api/v1/clubs/{$club->id}/members/{$member->id}", [
                'full_name' => 'Wrong Club Update Attempt',
            ]);

        $response->assertNotFound()
            ->assertJson([
                'message' => 'Member does not belong to this club.',
            ]);
    }

    public function test_update_member_rejects_invalid_email_and_status(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $user = User::where('email', 'president@lcbeenachi.test')->firstOrFail();

        $member = Member::create([
            'club_id' => $club->id,
            'member_code' => 'LCB-208',
            'full_name' => 'Validation Member',
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        config(['instance.organisation_code' => $club->code]);
        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson("/api/v1/clubs/{$club->id}/members/{$member->id}", [
                'email' => 'not-an-email',
                'status' => 'wrong-status',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
                'status',
            ]);
    }

    public function test_unauthenticated_user_cannot_update_member(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();

        $member = Member::create([
            'club_id' => $club->id,
            'member_code' => 'LCB-209',
            'full_name' => 'Unauthenticated Update Member',
        ]);

        config(['instance.organisation_code' => $club->code]);
        $response = $this->patchJson("/api/v1/clubs/{$club->id}/members/{$member->id}", [
            'full_name' => 'Unauthenticated Changed Name',
        ]);

        $response->assertUnauthorized();
    }
}
