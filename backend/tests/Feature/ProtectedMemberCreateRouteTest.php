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

class ProtectedMemberCreateRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_president_admin_can_create_member_for_accessible_club(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $user = User::where('email', 'president@lcbeenachi.test')->firstOrFail();

        $token = $user->createToken('test-token')->plainTextToken;

        config(['instance.organisation_code' => $club->code]);
        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/clubs/{$club->id}/members", [
                'member_code' => 'LCB-100',
                'full_name' => 'Created Member',
                'phone' => '9999999100',
                'email' => 'created@lcbeenachi.test',
                'joined_at' => '2026-07-01',
                'blood_group' => 'B+',
                'blood_donor_consent' => true,
            ]);

        $response->assertCreated()
            ->assertJsonPath('member.club_id', $club->id)
            ->assertJsonPath('member.member_code', 'LCB-100')
            ->assertJsonPath('member.full_name', 'Created Member')
            ->assertJsonPath('member.phone', '9999999100')
            ->assertJsonPath('member.email', 'created@lcbeenachi.test')
            ->assertJsonPath('member.status', 'active')
            ->assertJsonPath('member.joined_at', '2026-07-01')
            ->assertJsonPath('member.blood_group', 'B+')
            ->assertJsonPath('member.blood_donor_consent', true);

        $this->assertDatabaseHas('members', [
            'club_id' => $club->id,
            'member_code' => 'LCB-100',
            'full_name' => 'Created Member',
            'email' => 'created@lcbeenachi.test',
        ]);
    }

    public function test_secretary_can_create_member_for_accessible_club(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $secretaryRole = Role::where('slug', 'secretary')->firstOrFail();

        $user = User::factory()->create([
            'email' => 'secretary-create-member@lcbeenachi.test',
        ]);

        ClubMembership::create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'role_id' => $secretaryRole->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        config(['instance.organisation_code' => $club->code]);
        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/clubs/{$club->id}/members", [
                'member_code' => 'LCB-101',
                'full_name' => 'Secretary Created Member',
            ]);

        $response->assertCreated()
            ->assertJsonPath('member.member_code', 'LCB-101')
            ->assertJsonPath('member.full_name', 'Secretary Created Member')
            ->assertJsonPath('member.status', 'active')
            ->assertJsonPath('member.blood_donor_consent', false);
    }

    public function test_treasurer_cannot_create_member(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $treasurerRole = Role::where('slug', 'treasurer')->firstOrFail();

        $user = User::factory()->create([
            'email' => 'treasurer-create-member@lcbeenachi.test',
        ]);

        ClubMembership::create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'role_id' => $treasurerRole->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        config(['instance.organisation_code' => $club->code]);
        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/clubs/{$club->id}/members", [
                'member_code' => 'LCB-102',
                'full_name' => 'Blocked Treasurer Member',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('members', [
            'club_id' => $club->id,
            'member_code' => 'LCB-102',
        ]);
    }

    public function test_normal_member_cannot_create_member(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $memberRole = Role::where('slug', 'member')->firstOrFail();

        $user = User::factory()->create([
            'email' => 'normal-create-member@lcbeenachi.test',
        ]);

        ClubMembership::create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'role_id' => $memberRole->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        config(['instance.organisation_code' => $club->code]);
        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/clubs/{$club->id}/members", [
                'member_code' => 'LCB-103',
                'full_name' => 'Blocked Normal Member',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('members', [
            'club_id' => $club->id,
            'member_code' => 'LCB-103',
        ]);
    }

    public function test_member_code_must_be_unique_within_same_club(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $user = User::where('email', 'president@lcbeenachi.test')->firstOrFail();

        Member::create([
            'club_id' => $club->id,
            'member_code' => 'LCB-104',
            'full_name' => 'Existing Member',
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        config(['instance.organisation_code' => $club->code]);
        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/clubs/{$club->id}/members", [
                'member_code' => 'LCB-104',
                'full_name' => 'Duplicate Member',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['member_code']);
    }

    public function test_create_member_requires_minimum_required_fields(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $user = User::where('email', 'president@lcbeenachi.test')->firstOrFail();

        $token = $user->createToken('test-token')->plainTextToken;

        config(['instance.organisation_code' => $club->code]);
        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/clubs/{$club->id}/members", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'member_code',
                'full_name',
            ]);
    }

    public function test_unauthenticated_user_cannot_create_member(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();

        config(['instance.organisation_code' => $club->code]);
        $response = $this->postJson("/api/v1/clubs/{$club->id}/members", [
            'member_code' => 'LCB-105',
            'full_name' => 'Unauthenticated Member',
        ]);

        $response->assertUnauthorized();
    }
}
