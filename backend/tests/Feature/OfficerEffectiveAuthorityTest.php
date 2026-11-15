<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\Access\ClubPermissionService;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Support\OfficerTermTestCase;

final class OfficerEffectiveAuthorityTest extends OfficerTermTestCase
{
    public function test_legacy_role_keeps_ordinary_permissions_without_officer_authority(): void
    {
        $club = $this->club();
        $membership = $this->membership($club, 'president-admin');
        $user = User::findOrFail($membership->user_id);
        $permissions = app(ClubPermissionService::class);

        $this->assertTrue($permissions->userHasPermission($user, 'finance.self.view', $club->id));
        $this->assertFalse($permissions->userHasPermission($user, 'finance.manage', $club->id));
        $this->assertFalse($permissions->userHasPermission($user, 'payments.record', $club->id));
    }

    public function test_term_on_ordinary_role_grants_same_club_authority_until_exact_end(): void
    {
        $club = $this->club();
        $foreign = $this->club();
        $membership = $this->membership($club);
        $user = User::findOrFail($membership->user_id);
        DB::table('club_officer_terms')->insert($this->term($membership));
        $permissions = app(ClubPermissionService::class);
        $start = new DateTimeImmutable('2027-01-01T00:00:00Z');
        $end = new DateTimeImmutable('2027-02-01T00:00:00Z');

        $this->assertFalse($permissions->userHasPermission($user, 'finance.manage', $club->id, $start->modify('-1 second')));
        $this->assertTrue($permissions->userHasPermission($user, 'finance.manage', $club->id, $start));
        $this->assertFalse($permissions->userHasPermission($user, 'payments.record', $club->id, $start));
        $this->assertFalse($permissions->userHasPermission($user, 'finance.manage', $foreign->id, $start));
        $this->assertFalse($permissions->userHasPermission($user, 'finance.manage', $club->id, $end));
        $this->assertTrue($permissions->userHasPermission($user, 'finance.self.view', $club->id, $end));
    }
}
