<?php

namespace Database\Seeders;

use App\Models\Club;
use App\Models\ClubMembership;
use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class LocalLinkedMemberIdentitiesSeeder extends Seeder
{
    private const IDENTITIES = [
        [
            'name' => 'Ranjith MR',
            'email' => 'ranjith.treasurer@lcbeenachi.test',
            'role_slug' => 'treasurer',
            'member_code' => '26766860',
            'member_name' => 'Ranjith MR',
        ],
        [
            'name' => 'Sajith K',
            'email' => 'sajith.member@lcbeenachi.test',
            'role_slug' => 'member',
            'member_code' => '4794369',
            'member_name' => 'Sajith K',
        ],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $club = Club::where('code', 'LCB')->firstOrFail();

        foreach (self::IDENTITIES as $identity) {
            $matches = Member::query()
                ->where('club_id', $club->id)
                ->where('member_code', $identity['member_code'])
                ->get();

            if ($matches->count() !== 1) {
                throw new RuntimeException(sprintf(
                    'Linked development identity [%s] requires exactly one LCB member with code [%s]; found %d.',
                    $identity['member_name'],
                    $identity['member_code'],
                    $matches->count()
                ));
            }

            $member = $matches->firstOrFail();
            if ($member->full_name !== $identity['member_name']) {
                throw new RuntimeException(sprintf(
                    'Linked development identity code [%s] resolved to [%s], expected [%s].',
                    $identity['member_code'],
                    $member->full_name,
                    $identity['member_name']
                ));
            }

            $role = Role::where('slug', $identity['role_slug'])->firstOrFail();
            $user = User::updateOrCreate(
                ['email' => $identity['email']],
                [
                    'name' => $identity['name'],
                    'password' => Hash::make(env('LC_BEENACHI_BOOTSTRAP_PASSWORD', 'password')),
                ]
            );

            ClubMembership::updateOrCreate(
                ['club_id' => $club->id, 'user_id' => $user->id],
                ['role_id' => $role->id, 'status' => 'active', 'joined_at' => now()]
            );

            $otherLinks = Member::query()
                ->where('club_id', $club->id)
                ->where('user_id', $user->id)
                ->where('id', '!=', $member->id)
                ->count();

            if ($otherLinks !== 0) {
                throw new RuntimeException(sprintf(
                    'Linked development account [%s] already has %d other LCB member link(s).',
                    $identity['email'],
                    $otherLinks
                ));
            }

            $member->update(['user_id' => $user->id]);
        }
    }
}
