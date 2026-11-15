<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\ClubMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait SeedsAnnouncementOfficerTerms
{
    private static function seedAnnouncementOfficerTerm(ClubMembership $membership, string $role, string $startsAt = '2000-01-01 00:00:00'): void
    {
        $office = match ($role) {
            'president-admin' => 'president',
            'secretary', 'treasurer' => $role,
            default => null,
        };
        if ($office === null || $membership->status !== 'active') {
            return;
        }

        DB::table('club_officer_terms')->insert([
            'club_id' => $membership->club_id,
            'membership_id' => $membership->id,
            'office' => $office,
            'starts_at' => $startsAt,
            'ends_at' => '2099-01-01 00:00:00',
            'recorded_at' => '2000-01-01 00:00:00',
            'appointment_reference' => (string) Str::uuid(),
            'approved_by_user_id' => $membership->user_id,
            'confirmed_by_user_id' => User::factory()->create()->id,
            'recorded_by_user_id' => $membership->user_id,
        ]);
    }
}
