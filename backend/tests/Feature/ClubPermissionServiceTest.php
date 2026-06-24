<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubMembership;
use App\Models\Role;
use App\Models\User;
use App\Services\Access\ClubPermissionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubPermissionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_president_admin_has_expected_foundation_permissions(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::where('email', 'president@lcbeenachi.test')->firstOrFail();
        $club = Club::where('code', 'LCB')->firstOrFail();

        $service = new ClubPermissionService();

        $this->assertTrue($service->userCanAccessClub($user, $club->id));
        $this->assertTrue($service->userHasPermission($user, 'club.view', $club->id));
        $this->assertTrue($service->userHasPermission($user, 'club.manage', $club->id));
        $this->assertTrue($service->userHasPermission($user, 'users.manage', $club->id));
        $this->assertTrue($service->userHasPermission($user, 'members.view', $club->id));
        $this->assertTrue($service->userHasPermission($user, 'members.manage', $club->id));
        $this->assertTrue($service->userHasPermission($user, 'audit_logs.view', $club->id));
        $this->assertTrue($service->userHasPermission($user, 'events.view', $club->id));
        $this->assertTrue($service->userHasPermission($user, 'events.manage', $club->id));
        $this->assertTrue($service->userHasPermission($user, 'payments.record', $club->id));

        $this->assertSame([
            'audit_logs.view',
            'club.manage',
            'club.view',
            'events.manage',
            'events.view',
            'members.manage',
            'members.view',
            'memberships.manage',
            'memberships.view',
            'payments.record',
            'permissions.view',
            'projects.manage',
            'projects.view',
            'reminders.manage',
            'reminders.view',
            'roles.manage',
            'roles.view',
            'users.manage',
            'users.view',
        ], $service->userPermissionSlugs($user, $club->id));
    }

    public function test_normal_member_has_limited_foundation_permissions(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $memberRole = Role::where('slug', 'member')->firstOrFail();

        $user = User::factory()->create([
            'name' => 'Normal Member',
            'email' => 'member@lcbeenachi.test',
        ]);

        ClubMembership::create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'role_id' => $memberRole->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $service = new ClubPermissionService();

        $this->assertTrue($service->userCanAccessClub($user, $club->id));
        $this->assertTrue($service->userHasPermission($user, 'club.view', $club->id));
        $this->assertTrue($service->userHasPermission($user, 'members.view', $club->id));
        $this->assertTrue($service->userHasPermission($user, 'events.view', $club->id));
        $this->assertTrue($service->userHasPermission($user, 'projects.view', $club->id));
        $this->assertTrue($service->userHasPermission($user, 'reminders.view', $club->id));
        $this->assertFalse($service->userHasPermission($user, 'club.manage', $club->id));
        $this->assertFalse($service->userHasPermission($user, 'members.manage', $club->id));
        $this->assertFalse($service->userHasPermission($user, 'events.manage', $club->id));
        $this->assertFalse($service->userHasPermission($user, 'projects.manage', $club->id));
        $this->assertFalse($service->userHasPermission($user, 'reminders.manage', $club->id));
        $this->assertFalse($service->userHasPermission($user, 'users.manage', $club->id));
        $this->assertFalse($service->userHasPermission($user, 'audit_logs.view', $club->id));

        $this->assertSame([
            'club.view',
            'events.view',
            'members.view',
            'projects.view',
            'reminders.view',
        ], $service->userPermissionSlugs($user, $club->id));
    }

    public function test_inactive_membership_does_not_grant_club_access_or_permissions(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $role = Role::where('slug', 'president-admin')->firstOrFail();

        $user = User::factory()->create([
            'name' => 'Inactive User',
            'email' => 'inactive@lcbeenachi.test',
        ]);

        ClubMembership::create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'inactive',
            'joined_at' => now(),
        ]);

        $service = new ClubPermissionService();

        $this->assertFalse($service->userCanAccessClub($user, $club->id));
        $this->assertFalse($service->userHasPermission($user, 'club.view', $club->id));
        $this->assertSame([], $service->userPermissionSlugs($user, $club->id));
    }

    public function test_permissions_can_be_checked_across_all_active_club_memberships(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::where('email', 'president@lcbeenachi.test')->firstOrFail();

        $service = new ClubPermissionService();

        $this->assertTrue($service->userHasPermission($user, 'club.manage'));
        $this->assertTrue($service->userHasPermission($user, 'events.manage'));
        $this->assertContains('club.manage', $service->userPermissionSlugs($user));
        $this->assertContains('events.manage', $service->userPermissionSlugs($user));
    }
}
