<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\DefaultRolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefaultRolesAndPermissionsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_roles_and_permissions_are_seeded_with_expected_matrix(): void
    {
        $this->seed(DefaultRolesAndPermissionsSeeder::class);

        $this->assertDatabaseCount('roles', 4);
        $this->assertDatabaseCount('permissions', 16);
        $this->assertDatabaseCount('role_permissions', 37);

        $president = Role::where('slug', 'president-admin')->firstOrFail();
        $secretary = Role::where('slug', 'secretary')->firstOrFail();
        $treasurer = Role::where('slug', 'treasurer')->firstOrFail();
        $member = Role::where('slug', 'member')->firstOrFail();

        $this->assertSame('President/Admin', $president->name);
        $this->assertTrue($president->is_system);

        $this->assertSame(16, $president->permissions()->count());
        $this->assertSame(10, $secretary->permissions()->count());
        $this->assertSame(7, $treasurer->permissions()->count());
        $this->assertSame(4, $member->permissions()->count());

        $this->assertTrue($president->permissions->contains('slug', 'club.manage'));
        $this->assertTrue($president->permissions->contains('slug', 'roles.manage'));
        $this->assertTrue($president->permissions->contains('slug', 'audit_logs.view'));
        $this->assertTrue($president->permissions->contains('slug', 'members.view'));
        $this->assertTrue($president->permissions->contains('slug', 'members.manage'));
        $this->assertTrue($president->permissions->contains('slug', 'events.view'));
        $this->assertTrue($president->permissions->contains('slug', 'events.manage'));
        $this->assertTrue($president->permissions->contains('slug', 'projects.view'));
        $this->assertTrue($president->permissions->contains('slug', 'projects.manage'));

        $this->assertTrue($secretary->permissions->contains('slug', 'club.view'));
        $this->assertTrue($secretary->permissions->contains('slug', 'members.view'));
        $this->assertTrue($secretary->permissions->contains('slug', 'members.manage'));
        $this->assertTrue($secretary->permissions->contains('slug', 'events.view'));
        $this->assertTrue($secretary->permissions->contains('slug', 'events.manage'));
        $this->assertTrue($secretary->permissions->contains('slug', 'projects.view'));
        $this->assertTrue($secretary->permissions->contains('slug', 'projects.manage'));
        $this->assertFalse($secretary->permissions->contains('slug', 'club.manage'));

        $this->assertTrue($treasurer->permissions->contains('slug', 'memberships.view'));
        $this->assertTrue($treasurer->permissions->contains('slug', 'members.view'));
        $this->assertTrue($treasurer->permissions->contains('slug', 'events.view'));
        $this->assertTrue($treasurer->permissions->contains('slug', 'projects.view'));
        $this->assertFalse($treasurer->permissions->contains('slug', 'members.manage'));
        $this->assertFalse($treasurer->permissions->contains('slug', 'events.manage'));
        $this->assertFalse($treasurer->permissions->contains('slug', 'projects.manage'));
        $this->assertFalse($treasurer->permissions->contains('slug', 'users.manage'));

        $this->assertTrue($member->permissions->contains('slug', 'club.view'));
        $this->assertTrue($member->permissions->contains('slug', 'members.view'));
        $this->assertTrue($member->permissions->contains('slug', 'events.view'));
        $this->assertTrue($member->permissions->contains('slug', 'projects.view'));
        $this->assertFalse($member->permissions->contains('slug', 'members.manage'));
        $this->assertFalse($member->permissions->contains('slug', 'events.manage'));
        $this->assertFalse($member->permissions->contains('slug', 'projects.manage'));
        $this->assertFalse($member->permissions->contains('slug', 'audit_logs.view'));

        $this->assertNotNull(Permission::where('slug', 'permissions.view')->first());
        $this->assertNotNull(Permission::where('slug', 'members.view')->first());
        $this->assertNotNull(Permission::where('slug', 'members.manage')->first());
        $this->assertNotNull(Permission::where('slug', 'events.view')->first());
        $this->assertNotNull(Permission::where('slug', 'events.manage')->first());
        $this->assertNotNull(Permission::where('slug', 'projects.view')->first());
        $this->assertNotNull(Permission::where('slug', 'projects.manage')->first());
    }

    public function test_default_roles_and_permissions_seeder_is_idempotent(): void
    {
        $this->seed(DefaultRolesAndPermissionsSeeder::class);
        $this->seed(DefaultRolesAndPermissionsSeeder::class);

        $this->assertDatabaseCount('roles', 4);
        $this->assertDatabaseCount('permissions', 16);
        $this->assertDatabaseCount('role_permissions', 37);
    }

    public function test_database_seeder_calls_default_roles_and_permissions_seeder(): void
    {
        $this->seed();

        $this->assertDatabaseHas('roles', [
            'slug' => 'president-admin',
            'name' => 'President/Admin',
        ]);

        $this->assertDatabaseHas('roles', [
            'slug' => 'member',
            'name' => 'Member',
        ]);

        foreach ([
            'club.view',
            'members.view',
            'members.manage',
            'events.view',
            'events.manage',
            'projects.view',
            'projects.manage',
        ] as $permission) {
            $this->assertDatabaseHas('permissions', [
                'slug' => $permission,
            ]);
        }
    }
}