<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubMembership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubRolePermissionAuditFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_club_role_permission_membership_and_audit_foundation_can_be_created(): void
    {
        $club = Club::create([
            'name' => 'Lions Club Beenachi',
            'code' => 'LCB',
            'district' => '318E',
        ]);

        $club->refresh();

        $role = Role::create([
            'name' => 'President/Admin',
            'slug' => 'president-admin',
            'description' => 'Club-level president/admin role.',
        ]);

        $permission = Permission::create([
            'name' => 'Manage Club Foundation',
            'slug' => 'manage-club-foundation',
            'group' => 'foundation',
        ]);

        $role->permissions()->attach($permission);

        $user = User::factory()->create([
            'name' => 'Bijesh KG',
            'email' => 'bijesh@lcbeenachi.test',
        ]);

        $membership = ClubMembership::create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $auditLog = AuditLog::create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'action' => 'foundation.membership_created',
            'auditable_type' => ClubMembership::class,
            'auditable_id' => $membership->id,
            'metadata' => [
                'source' => 'test',
                'scope' => 'milestone_1c_foundation',
            ],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Laravel Test',
        ]);

        $this->assertTrue($club->is_active);
        $this->assertSame('Asia/Kolkata', $club->timezone);

        $this->assertTrue($role->permissions->contains($permission));
        $this->assertTrue($permission->roles->contains($role));

        $this->assertSame($club->id, $membership->club->id);
        $this->assertSame($user->id, $membership->user->id);
        $this->assertSame($role->id, $membership->role->id);

        $this->assertTrue($club->memberships->contains($membership));
        $this->assertTrue($user->clubMemberships->contains($membership));
        $this->assertTrue($user->clubs->contains($club));

        $this->assertSame('test', $auditLog->metadata['source']);
        $this->assertSame($club->id, $auditLog->club->id);
        $this->assertSame($user->id, $auditLog->user->id);
    }

    public function test_same_user_cannot_be_added_twice_to_same_club(): void
    {
        $club = Club::create([
            'name' => 'Lions Club Beenachi',
            'code' => 'LCB',
        ]);

        $role = Role::create([
            'name' => 'Secretary',
            'slug' => 'secretary',
        ]);

        $user = User::factory()->create();

        ClubMembership::create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        ClubMembership::create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);
    }
}
