<?php

namespace Database\Seeders;

use App\Models\Club;
use App\Models\ClubMembership;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class LocalRoleTestUsersSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $club = Club::where('code', 'LCB')->firstOrFail();

        $localUsers = [
            [
                'name' => 'LC Beenachi President',
                'email' => 'president@lcbeenachi.test',
                'role_slug' => 'president-admin',
            ],
            [
                'name' => 'LC Beenachi Secretary',
                'email' => 'secretary@lcbeenachi.test',
                'role_slug' => 'secretary',
            ],
            [
                'name' => 'LC Beenachi Treasurer',
                'email' => 'treasurer@lcbeenachi.test',
                'role_slug' => 'treasurer',
            ],
            [
                'name' => 'LC Beenachi Member',
                'email' => 'local.member@lcbeenachi.test',
                'role_slug' => 'member',
            ],
        ];

        foreach ($localUsers as $localUser) {
            $role = Role::where('slug', $localUser['role_slug'])->firstOrFail();

            $user = User::updateOrCreate(
                ['email' => $localUser['email']],
                [
                    'name' => $localUser['name'],
                    'password' => Hash::make(env('LC_BEENACHI_BOOTSTRAP_PASSWORD', 'password')),
                ]
            );

            ClubMembership::updateOrCreate(
                [
                    'club_id' => $club->id,
                    'user_id' => $user->id,
                ],
                [
                    'role_id' => $role->id,
                    'status' => 'active',
                    'joined_at' => now(),
                ]
            );
        }
    }
}
