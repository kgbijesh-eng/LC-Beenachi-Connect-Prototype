<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubMembership;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DefaultRolesAndPermissionsSeeder;
use Database\Seeders\LocalClubAdminBootstrapSeeder;
use Database\Seeders\LocalRoleTestUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalRoleTestUsersSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_local_role_test_users_with_expected_roles(): void
    {
        $this->seed([
            DefaultRolesAndPermissionsSeeder::class,
            LocalClubAdminBootstrapSeeder::class,
            LocalRoleTestUsersSeeder::class,
        ]);

        $club = Club::where('code', 'LCB')->firstOrFail();

        $expectedUsers = [
            'president@lcbeenachi.test' => 'president-admin',
            'secretary@lcbeenachi.test' => 'secretary',
            'treasurer@lcbeenachi.test' => 'treasurer',
            'local.member@lcbeenachi.test' => 'member',
        ];

        foreach ($expectedUsers as $email => $roleSlug) {
            $user = User::where('email', $email)->first();
            $role = Role::where('slug', $roleSlug)->firstOrFail();

            $this->assertNotNull($user, "Expected local test user {$email} to exist.");

            $this->assertDatabaseHas('club_memberships', [
                'club_id' => $club->id,
                'user_id' => $user->id,
                'role_id' => $role->id,
                'status' => 'active',
            ]);
        }

        $this->assertSame(4, User::whereIn('email', array_keys($expectedUsers))->count());

        $localUserIds = User::whereIn('email', array_keys($expectedUsers))->pluck('id');

        $this->assertSame(
            4,
            ClubMembership::where('club_id', $club->id)
                ->whereIn('user_id', $localUserIds)
                ->count()
        );
    }

    public function test_it_is_idempotent_and_does_not_duplicate_users_or_memberships(): void
    {
        $this->seed([
            DefaultRolesAndPermissionsSeeder::class,
            LocalClubAdminBootstrapSeeder::class,
            LocalRoleTestUsersSeeder::class,
            LocalRoleTestUsersSeeder::class,
        ]);

        $club = Club::where('code', 'LCB')->firstOrFail();

        $emails = [
            'president@lcbeenachi.test',
            'secretary@lcbeenachi.test',
            'treasurer@lcbeenachi.test',
            'local.member@lcbeenachi.test',
        ];

        $localUserIds = User::whereIn('email', $emails)->pluck('id');

        $this->assertSame(4, User::whereIn('email', $emails)->count());

        $this->assertSame(
            4,
            ClubMembership::where('club_id', $club->id)
                ->whereIn('user_id', $localUserIds)
                ->count()
        );
    }
}
