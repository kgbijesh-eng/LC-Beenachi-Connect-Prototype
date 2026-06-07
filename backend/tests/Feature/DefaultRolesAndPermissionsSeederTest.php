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
        $this->assertDatabaseCount('permissions', 12);
        $this->assertDatabaseCount('role_permissions', 25);

        $president = Role::where('slug', 'president-admin')->firstOrFail();
        $secretary = Role::where('slug', 'secretary')->firstOrFail();
        $treasurer = Role::where('slug', 'treasurer')->firstOrFail();
        $member = Role::where('slug', 'member')->firstOrFail();

        $this->assertSame('President/Admin', $president->name);
        $this->assertTrue($president->is_system);

        $this->assertSame(12, $president->permissions()->count());
        $this->assertSame(6, $secretary->permissions()->count());
        $this->assertSame(5, $treasurer->permissions()->count());
        $this->assertSame(2, $member->permissions()->count());

        $this->assertTrue($president->permissions->contains('slug', 'club.manage'));
        $this->assertTrue($president->permissions->contains('slug', 'roles.manage'));
        $this->assertTrue($president->permissions->contains('slug', 'audit_logs.view'));
        $this->assertTrue($president->permissions->contains('slug', 'members.view'));
        $this->assertTrue($president->permissions->contains('slug', 'members.manage'));

        $this->assertTrue($secretary->permissions->contains('slug', 'club.view'));
        $this->assertTrue($secretary->permissions->contains('slug', 'members.view'));
        $this->assertTrue($secretary->permissions->contains('slug', 'members.manage'));
        $this->assertFalse($secretary->permissions->contains('slug', 'club.manage'));

        $this->assertTrue($treasurer->permissions->contains('slug', 'memberships.view'));
        $this->assertTrue($treasurer->permissions->contains('slug', 'members.view'));
        $this->assertFalse($treasurer->permissions->contains('slug', 'members.manage'));
        $this->assertFalse($treasurer->permissions->contains('slug', 'users.manage'));

        $this->assertTrue($member->permissions->contains('slug', 'club.view'));
        $this->assertTrue($member->permissions->contains('slug', 'members.view'));
        $this->assertFalse($member->permissions->contains('slug', 'members.manage'));
        $this->assertFalse($member->permissions->contains('slug', 'audit_logs.view'));

        $this->assertNotNull(Permission::where('slug', 'permissions.view')->first());
        $this->assertNotNull(Permission::where('slug', 'members.view')->first());
        $this->assertNotNull(Permission::where('slug', 'members.manage')->first());
    }

    public function test_default_roles_and_permissions_seeder_is_idempotent(): void
    {
        $this->seed(DefaultRolesAndPermissionsSeeder::class);
        $this->seed(DefaultRolesAndPermissionsSeeder::class);

        $this->assertDatabaseCount('roles', 4);
        $this->assertDatabaseCount('permissions', 12);
        $this->assertDatabaseCount('role_permissions', 25);
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

        $this->assertDatabaseHas('permissions', [
            'slug' => 'club.view',
        ]);

        $this->assertDatabaseHas('permissions', [
            'slug' => 'members.view',
        ]);

        $this->assertDatabaseHas('permissions', [
            'slug' => 'members.manage',
        ]);
    }
}
