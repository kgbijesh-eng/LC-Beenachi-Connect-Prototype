<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubMembership;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DefaultRolesAndPermissionsSeeder;
use Database\Seeders\LocalClubAdminBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LocalClubAdminBootstrapSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_club_admin_bootstrap_seeder_creates_club_admin_and_membership(): void
    {
        $this->seed(DefaultRolesAndPermissionsSeeder::class);
        $this->seed(LocalClubAdminBootstrapSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $user = User::where('email', 'president@lcbeenachi.test')->firstOrFail();
        $role = Role::where('slug', 'president-admin')->firstOrFail();

        $this->assertSame('Lions Club Beenachi', $club->name);
        $this->assertSame('318E', $club->district);
        $this->assertSame('Asia/Kolkata', $club->timezone);
        $this->assertTrue($club->is_active);

        $this->assertSame('LC Beenachi President', $user->name);
        $this->assertTrue(Hash::check('password', $user->password));

        $this->assertDatabaseHas('club_memberships', [
            'club_id' => $club->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $membership = ClubMembership::where('club_id', $club->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->assertSame($role->id, $membership->role->id);
    }

    public function test_local_club_admin_bootstrap_seeder_is_idempotent(): void
    {
        $this->seed(DefaultRolesAndPermissionsSeeder::class);
        $this->seed(LocalClubAdminBootstrapSeeder::class);
        $this->seed(LocalClubAdminBootstrapSeeder::class);

        $this->assertDatabaseCount('clubs', 1);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('club_memberships', 1);
    }

    public function test_database_seeder_includes_local_club_admin_bootstrap_for_local_environment(): void
    {
        $this->seed();

        $this->assertDatabaseHas('clubs', [
            'code' => 'LCB',
            'name' => 'Lions Club Beenachi',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'president@lcbeenachi.test',
            'name' => 'LC Beenachi President',
        ]);
    }
}
