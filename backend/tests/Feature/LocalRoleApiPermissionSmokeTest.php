<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Member;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalRoleApiPermissionSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_president_admin_can_login_read_create_and_update_members(): void
    {
        $this->assertRoleCanManageMembers(
            'president@lcbeenachi.test',
            'LCB-2D-PRES',
            'President Created Smoke Member',
            'President Updated Smoke Member'
        );
    }

    public function test_seeded_secretary_can_login_read_create_and_update_members(): void
    {
        $this->assertRoleCanManageMembers(
            'secretary@lcbeenachi.test',
            'LCB-2D-SEC',
            'Secretary Created Smoke Member',
            'Secretary Updated Smoke Member'
        );
    }

    public function test_seeded_treasurer_can_login_and_read_but_cannot_create_or_update_members(): void
    {
        $this->assertRoleCannotManageMembers(
            'treasurer@lcbeenachi.test',
            'LCB-2D-TREAS',
            'Treasurer Blocked Smoke Member',
            'Treasurer Blocked Update Smoke Member'
        );
    }

    public function test_seeded_normal_member_can_login_and_read_but_cannot_create_or_update_members(): void
    {
        $this->assertRoleCannotManageMembers(
            'local.member@lcbeenachi.test',
            'LCB-2D-MEM',
            'Member Blocked Smoke Member',
            'Member Blocked Update Smoke Member'
        );
    }

    private function assertRoleCanManageMembers(
        string $email,
        string $createdMemberCode,
        string $createdMemberName,
        string $updatedMemberName
    ): void {
        [$club, $existingMember, $token] = $this->seedClubMemberAndLogin($email);

        $this->assertCanReadMembers($club, $token, $email);

        $createResponse = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/clubs/{$club->id}/members", [
                'member_code' => $createdMemberCode,
                'full_name' => $createdMemberName,
            ]);

        $createResponse->assertCreated()
            ->assertJsonPath('member.member_code', $createdMemberCode)
            ->assertJsonPath('member.full_name', $createdMemberName);

        $this->assertDatabaseHas('members', [
            'club_id' => $club->id,
            'member_code' => $createdMemberCode,
            'full_name' => $createdMemberName,
        ]);

        $updateResponse = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson("/api/v1/clubs/{$club->id}/members/{$existingMember->id}", [
                'full_name' => $updatedMemberName,
            ]);

        $updateResponse->assertOk()
            ->assertJsonPath('member.full_name', $updatedMemberName);

        $this->assertDatabaseHas('members', [
            'id' => $existingMember->id,
            'full_name' => $updatedMemberName,
        ]);
    }

    private function assertRoleCannotManageMembers(
        string $email,
        string $createdMemberCode,
        string $createdMemberName,
        string $updatedMemberName
    ): void {
        [$club, $existingMember, $token] = $this->seedClubMemberAndLogin($email);

        $this->assertCanReadMembers($club, $token, $email);

        $createResponse = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/clubs/{$club->id}/members", [
                'member_code' => $createdMemberCode,
                'full_name' => $createdMemberName,
            ]);

        $createResponse->assertForbidden();

        $this->assertDatabaseMissing('members', [
            'club_id' => $club->id,
            'member_code' => $createdMemberCode,
        ]);

        $updateResponse = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson("/api/v1/clubs/{$club->id}/members/{$existingMember->id}", [
                'full_name' => $updatedMemberName,
            ]);

        $updateResponse->assertForbidden();

        $this->assertDatabaseHas('members', [
            'id' => $existingMember->id,
            'full_name' => 'Permission Smoke Member',
        ]);

        $this->assertDatabaseMissing('members', [
            'id' => $existingMember->id,
            'full_name' => $updatedMemberName,
        ]);
    }

    private function assertCanReadMembers(Club $club, string $token, string $email): void
    {
        $listResponse = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/clubs/{$club->id}/members");

        $listResponse->assertOk();

        $this->assertTrue(
            collect($listResponse->json('members'))
                ->contains(fn (array $member): bool => $member['member_code'] === 'LCB-2D-001'),
            "Expected {$email} to read the seeded member list."
        );
    }

    /**
     * @return array{0: Club, 1: Member, 2: string}
     */
    private function seedClubMemberAndLogin(string $email): array
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();

        $existingMember = Member::create([
            'club_id' => $club->id,
            'member_code' => 'LCB-2D-001',
            'full_name' => 'Permission Smoke Member',
            'status' => 'active',
        ]);

        $token = $this->loginTokenFor($email);

        return [$club, $existingMember, $token];
    }

    private function loginTokenFor(string $email): string
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.email', $email)
            ->assertJsonStructure([
                'message',
                'token',
                'user' => [
                    'id',
                    'name',
                    'email',
                ],
            ]);

        return $response->json('token');
    }
}
