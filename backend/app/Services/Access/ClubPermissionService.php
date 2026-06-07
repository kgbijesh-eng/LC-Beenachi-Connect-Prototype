<?php

namespace App\Services\Access;

use App\Models\User;

class ClubPermissionService
{
    public function userCanAccessClub(User $user, int $clubId): bool
    {
        return $user->clubMemberships()
            ->where('club_id', $clubId)
            ->where('status', 'active')
            ->exists();
    }

    public function userHasPermission(User $user, string $permissionSlug, ?int $clubId = null): bool
    {
        $memberships = $user->clubMemberships()
            ->with(['role.permissions'])
            ->where('status', 'active')
            ->when($clubId !== null, fn ($query) => $query->where('club_id', $clubId))
            ->get();

        return $memberships->contains(function ($membership) use ($permissionSlug): bool {
            return $membership->role
                ? $membership->role->permissions->contains('slug', $permissionSlug)
                : false;
        });
    }

    public function userPermissionSlugs(User $user, ?int $clubId = null): array
    {
        $memberships = $user->clubMemberships()
            ->with(['role.permissions'])
            ->where('status', 'active')
            ->when($clubId !== null, fn ($query) => $query->where('club_id', $clubId))
            ->get();

        return $memberships
            ->flatMap(fn ($membership) => $membership->role?->permissions->pluck('slug') ?? collect())
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
