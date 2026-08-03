<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\ClubAccountingCutoverAuthorityAppointment;
use App\Models\ClubAccountingCutoverPackage;
use App\Models\ClubAccountingCutoverPermissionAssignment;
use App\Models\ClubMembership;
use App\Models\User;
use App\Services\Finance\Exceptions\CutoverAuthorizationException;

final class ClubCutoverAuthorityResolver
{
    public function permission(ClubAccountingCutoverPackage $package, User $actor, string $slug): ClubAccountingCutoverPermissionAssignment
    {
        $assignments = ClubAccountingCutoverPermissionAssignment::query()
            ->with(['permission', 'events'])->where('club_id', $package->club_id)
            ->where('cutover_package_id', $package->id)->where('user_id', $actor->id)
            ->whereHas('permission', fn ($query) => $query->where('slug', $slug))
            ->where('effective_at', '<=', now())->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->get()->reject(fn ($assignment) => $assignment->events->whereIn('event_type', ['revoked', 'invalidated'])->isNotEmpty());
        if ($assignments->count() !== 1) {
            throw new CutoverAuthorizationException('One current package-scoped cutover permission assignment is required.');
        }

        return $assignments->first();
    }

    public function appointment(ClubAccountingCutoverPackage $package, User $actor, string $type): ClubAccountingCutoverAuthorityAppointment
    {
        $appointments = ClubAccountingCutoverAuthorityAppointment::query()->with('events')
            ->where('club_id', $package->club_id)->where('cutover_package_id', $package->id)
            ->where('appointed_user_id', $actor->id)->where('authority_type', $type)
            ->where('effective_at', '<=', now())->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->get()->reject(fn ($appointment) => $appointment->events->whereIn('event_type', ['revoked', 'invalidated'])->isNotEmpty());
        if ($appointments->count() !== 1) {
            throw new CutoverAuthorizationException('One current package-scoped authority appointment is required.');
        }
        $conflicting = ClubAccountingCutoverAuthorityAppointment::query()->with('events')
            ->where('cutover_package_id', $package->id)->where('appointed_user_id', $actor->id)
            ->where('authority_type', '!=', $type)->get()
            ->contains(fn ($appointment) => $appointment->events->whereIn('event_type', ['revoked', 'invalidated'])->isEmpty());
        if ($conflicting || in_array($actor->id, array_filter([$package->prepared_by_user_id, $package->internally_verified_by_user_id]), true)) {
            throw new CutoverAuthorizationException('The actor holds a conflicting package role.');
        }

        return $appointments->first();
    }

    public function president(ClubAccountingCutoverPackage $package, User $actor, string $permission): ClubAccountingCutoverPermissionAssignment
    {
        $presidents = ClubMembership::query()->where('club_id', $package->club_id)->where('status', 'active')
            ->whereHas('role', fn ($query) => $query->where('slug', 'president-admin'))->get();
        if ($presidents->count() !== 1 || $presidents->first()->user_id !== $actor->id) {
            throw new CutoverAuthorizationException('The unique active same-club President is required.');
        }
        if (in_array($actor->id, array_filter([$package->prepared_by_user_id, $package->internally_verified_by_user_id]), true)) {
            throw new CutoverAuthorizationException('The President holds a conflicting package role.');
        }

        return $this->permission($package, $actor, $permission);
    }
}
