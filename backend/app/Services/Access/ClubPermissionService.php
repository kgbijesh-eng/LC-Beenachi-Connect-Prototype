<?php

namespace App\Services\Access;

use App\Models\ClubMembership;
use App\Models\ClubOfficerTerm;
use App\Models\User;
use DateTimeInterface;

class ClubPermissionService
{
    private const ORDINARY = [
        'club.view', 'members.view', 'events.view', 'projects.view', 'reminders.view',
        'announcements.view', 'finance.self.view', 'profile_updates.self.submit', 'profile_updates.self.view',
    ];

    private const OFFICER = [
        'users.view', 'memberships.view', 'audit_logs.view', 'reports.view', 'finance.club.view',
        'finance.club.statement.view', 'finance.club.expenses.create', 'finance.club.expenses.view',
        'finance.club.ordinary_income_receipts.view', 'finance.manage',
    ];

    private const PRESIDENT_AND_SECRETARY = [
        'members.manage', 'members.account_activation.issue', 'events.manage', 'projects.manage', 'reminders.manage',
        'announcements.management.view', 'announcements.drafts.manage', 'announcements.replace',
        'announcements.approve', 'announcements.publish', 'announcements.withdraw', 'announcements.archive',
        'profile_updates.review', 'profile_updates.approve',
    ];

    private const PRESIDENT_AND_TREASURER = [
        'finance.club.expenses.reverse', 'finance.club.transfers.create', 'finance.club.ordinary_income_receipts.create',
        'finance.club.ordinary_income_receipts.reverse', 'finance.void',
    ];

    private const PRESIDENT = ['club.manage', 'users.manage', 'memberships.manage', 'roles.view', 'roles.manage', 'permissions.view'];

    public function __construct(private readonly OfficerTermResolver $terms) {}

    public function userCanAccessClub(User $user, int $clubId): bool
    {
        return $user->clubMemberships()->where('club_id', $clubId)->where('status', 'active')->exists();
    }

    public function userHasPermission(User $user, string $permissionSlug, ?int $clubId = null, ?DateTimeInterface $at = null): bool
    {
        return in_array($permissionSlug, $this->userPermissionSlugs($user, $clubId, $at), true);
    }

    public function userPermissionSlugs(User $user, ?int $clubId = null, ?DateTimeInterface $at = null): array
    {
        $at ??= now('UTC');

        return $user->clubMemberships()->with('role.permissions')->where('status', 'active')
            ->when($clubId !== null, fn ($query) => $query->where('club_id', $clubId))
            ->get()->flatMap(fn (ClubMembership $membership) => $this->membershipAuthority($membership, $at)['permissions'])
            ->unique()->sort()->values()->all();
    }

    public function membershipAuthority(ClubMembership $membership, DateTimeInterface $at): array
    {
        if ($membership->status !== 'active' || ! $membership->role) {
            return ['permissions' => [], 'term' => null];
        }

        $grants = $membership->role->permissions->pluck('slug')->all();
        $ordinary = in_array($membership->role->slug, ['member', ...array_values(ClubOfficerTerm::LEGACY_ROLES)], true)
            ? self::ORDINARY : array_intersect(self::ORDINARY, $grants);
        $term = $this->terms->forMembership((int) $membership->club_id, (int) $membership->id, $at);
        $office = $term?->office;
        $permissions = $ordinary;
        if ($office !== null) {
            $permissions = [...$permissions, ...self::OFFICER];
            if (in_array('payments.record', $grants, true)) {
                $permissions[] = 'payments.record';
            }
        }
        if (in_array($office, ['president', 'secretary'], true)) {
            $permissions = [...$permissions, ...self::PRESIDENT_AND_SECRETARY];
        }
        if (in_array($office, ['president', 'treasurer'], true)) {
            $permissions = [...$permissions, ...self::PRESIDENT_AND_TREASURER];
            if (in_array('finance.accounting.integration.configure', $grants, true)) {
                $permissions[] = 'finance.accounting.integration.configure';
            }
        }
        if ($office === 'president') {
            $permissions = [...$permissions, ...self::PRESIDENT];
        }
        sort($permissions);

        return ['permissions' => $permissions, 'term' => $term];
    }
    public function effectiveOffice(User $user, int $clubId, ?DateTimeInterface $at = null): ?string
    {
        $memberships = $user->clubMemberships()->where('club_id', $clubId)->where('status', 'active')->limit(2)->get();
        if ($memberships->count() !== 1) {
            return null;
        }

        $term = $this->terms->forMembership($clubId, (int) $memberships->sole()->id, $at ?? now('UTC'));

        return $term?->office;
    }

    public function officePayload(?ClubOfficerTerm $term): ?array
    {
        if ($term === null) {
            return null;
        }

        $end = $term->ended_at !== null && $term->ended_at < $term->ends_at
            ? $term->ended_at : $term->ends_at;

        return ['office' => $term->office, 'effective_until' => $end->toISOString()];
    }

}
