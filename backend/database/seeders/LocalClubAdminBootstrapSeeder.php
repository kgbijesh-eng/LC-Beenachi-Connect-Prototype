<?php

namespace Database\Seeders;

use App\Models\Club;
use App\Models\ClubMembership;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class LocalClubAdminBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $club = Club::updateOrCreate(
            ['code' => 'LCB'],
            [
                'name' => 'Lions Club Beenachi',
                'district' => '318E',
                'timezone' => 'Asia/Kolkata',
                'is_active' => true,
            ]
        );

        $presidentRole = Role::where('slug', 'president-admin')->firstOrFail();

        $user = User::updateOrCreate(
            ['email' => 'president@lcbeenachi.test'],
            [
                'name' => 'LC Beenachi President',
                'password' => Hash::make(env('LC_BEENACHI_BOOTSTRAP_PASSWORD', 'password')),
            ]
        );

        ClubMembership::updateOrCreate(
            [
                'club_id' => $club->id,
                'user_id' => $user->id,
            ],
            [
                'role_id' => $presidentRole->id,
                'status' => 'active',
                'joined_at' => now(),
            ]
        );
    }
}
