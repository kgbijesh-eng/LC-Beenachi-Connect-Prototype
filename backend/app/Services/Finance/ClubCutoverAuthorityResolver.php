<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\ClubAccountingCutoverAuthorityAppointment;
use App\Models\ClubAccountingCutoverCaEligibility;
use App\Models\ClubAccountingCutoverPackage;
use App\Models\ClubAccountingCutoverPermissionAssignment;
use App\Models\ClubMembership;
use App\Models\User;
use App\Services\Access\ClubPermissionService;
use App\Services\Access\OfficerTermResolver;
use DateTimeInterface;
use App\Services\Finance\Exceptions\CutoverAuthorizationException;

final class ClubCutoverAuthorityResolver
{
    public function activeMember(ClubAccountingCutoverPackage $package, User $actor, ?string $roleSlug = null, ?DateTimeInterface $at = null): ClubMembership
    {
        $query = ClubMembership::query()->with('role')->where('club_id', $package->club_id)
            ->where('user_id', $actor->id)->where('status', 'active');
        $memberships = $query->get();
        if ($memberships->count() !== 1 || ($roleSlug !== null
            && $this->uniqueOfficerForClub((int) $package->club_id, $roleSlug, $at)->id !== $actor->id)) {
            throw new CutoverAuthorizationException('One active same-club membership with the required role is required.');
        }

        return $memberships->first();
    }

    public function uniqueOfficer(ClubAccountingCutoverPackage $package, string $roleSlug, ?DateTimeInterface $at = null): User
    {
        return $this->uniqueOfficerForClub($package->club_id, $roleSlug, $at);
    }

    public function uniqueOfficerForClub(int $clubId, string $roleSlug, ?DateTimeInterface $at = null): User
    {
        $office = $roleSlug === 'president-admin' ? 'president' : $roleSlug;
        $term = app(OfficerTermResolver::class)->forOffice($clubId, $office, $at ?? now('UTC'));
        $user = $term === null ? null : ClubMembership::query()->where('club_id', $clubId)
            ->whereKey($term->membership_id)->first()?->user;
        if ($user === null) {
            throw new CutoverAuthorizationException("The unique active same-club {$roleSlug} officer is required.");
        }

        return $user;
    }

    public function governanceOfficerRole(ClubAccountingCutoverPackage $package, User $actor, ?DateTimeInterface $at = null): string
    {
        return $this->governanceOfficerRoleForClub($package->club_id, $actor, $at);
    }

    public function governanceOfficerRoleForClub(int $clubId, User $actor, ?DateTimeInterface $at = null): string
    {
        $office = app(ClubPermissionService::class)->effectiveOffice($actor, $clubId, $at);
        if (in_array($office, ['president', 'secretary'], true)) {
            return $office;
        }
        throw new CutoverAuthorizationException('The active same-club President or Secretary is required.');
    }

    public function permission(ClubAccountingCutoverPackage $package, User $actor, string $slug, ?DateTimeInterface $at = null): ClubAccountingCutoverPermissionAssignment
    {
        $at ??= now('UTC')->toImmutable();
        $databaseAt = \DateTimeImmutable::createFromInterface($at)->setTimezone(new \DateTimeZone(config('app.timezone')));
        $isExternalAppointee = ClubAccountingCutoverAuthorityAppointment::query()->with('events')
            ->where('club_id', $package->club_id)->where('cutover_package_id', $package->id)
            ->where('appointed_user_id', $actor->id)->get()
            ->contains(fn ($appointment) => $appointment->events->whereIn('event_type', ['revoked', 'invalidated'])->isEmpty()
                && ($appointment->expires_at === null || $appointment->expires_at > $at));
        if (! $isExternalAppointee) {
            $this->activeMember($package, $actor);
        }
        $assignments = ClubAccountingCutoverPermissionAssignment::query()
            ->with(['permission', 'events'])->where('club_id', $package->club_id)
            ->where('cutover_package_id', $package->id)->where('user_id', $actor->id)
            ->whereHas('permission', fn ($query) => $query->where('slug', $slug))
            ->where('effective_at', '<=', $databaseAt)->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', $databaseAt))
            ->get()->reject(fn ($assignment) => $assignment->events->whereIn('event_type', ['revoked', 'invalidated'])->isNotEmpty());
        if ($assignments->count() !== 1) {
            throw new CutoverAuthorizationException('One current package-scoped cutover permission assignment is required.');
        }

        $this->assertPermissionRoleBasis($package, $actor, $slug, $at);

        return $assignments->first();
    }

    private function assertPermissionRoleBasis(ClubAccountingCutoverPackage $package, User $actor, string $slug, DateTimeInterface $at): void
    {
        $roles = match ($slug) {
            'finance.cutover.package.prepare' => ['treasurer'],
            'finance.cutover.package.validate' => ['treasurer', 'secretary'],
            'finance.cutover.package.verify' => ['secretary'],
            'finance.cutover.package.business-approve' => [],
            default => [],
        };
        foreach ($roles as $role) {
            try {
                $this->activeMember($package, $actor, $role, $at);

                return;
            } catch (CutoverAuthorizationException) {
                // Try the next explicitly permitted role basis.
            }
        }
        if ($slug === 'finance.cutover.package.accounting-review') {
            $this->appointment($package, $actor, ClubAccountingCutoverAuthorityAppointment::TYPE_INDEPENDENT_CA, $at);

            return;
        }
        if ($slug === 'finance.cutover.package.business-approve') {
            if ($this->uniqueOfficer($package, 'president-admin', $at)->id !== $actor->id) {
                throw new CutoverAuthorizationException('The unique active same-club President is required.');
            }

            return;
        }
        if ($slug === 'finance.cutover.package.execute') {
            $this->appointment($package, $actor, ClubAccountingCutoverAuthorityAppointment::TYPE_TECHNICAL_OPERATOR, $at);

            return;
        }
        if ($slug === 'finance.cutover.package.view-restricted-manifest') {
            try {
                $this->activeMember($package, $actor, 'secretary', $at);

                return;
            } catch (CutoverAuthorizationException) {
                $this->appointment($package, $actor, ClubAccountingCutoverAuthorityAppointment::TYPE_INDEPENDENT_CA, $at);

                return;
            }
        }
        if (in_array($slug, ['finance.cutover.package.dry-run-accept', 'finance.cutover.package.activate-authorize', 'finance.cutover.package.rollback-authorize'], true)) {
            try {
                if ($this->uniqueOfficer($package, 'president-admin', $at)->id !== $actor->id) {
                    throw new CutoverAuthorizationException('The unique active same-club President is required.');
                }

                return;
            } catch (CutoverAuthorizationException) {
                $this->appointment($package, $actor, ClubAccountingCutoverAuthorityAppointment::TYPE_INDEPENDENT_CA, $at);

                return;
            }
        }
        if ($roles !== []) {
            throw new CutoverAuthorizationException('The current role basis for this package permission is required.');
        }
        throw new CutoverAuthorizationException('The package permission has no recognized runtime role basis.');
    }

    public function appointment(ClubAccountingCutoverPackage $package, User $actor, string $type, ?DateTimeInterface $at = null): ClubAccountingCutoverAuthorityAppointment
    {
        $at ??= now('UTC')->toImmutable();
        $databaseAt = \DateTimeImmutable::createFromInterface($at)->setTimezone(new \DateTimeZone(config('app.timezone')));
        $appointments = ClubAccountingCutoverAuthorityAppointment::query()->with('events')
            ->where('club_id', $package->club_id)->where('cutover_package_id', $package->id)
            ->where('appointed_user_id', $actor->id)->where('authority_type', $type)
            ->where('effective_at', '<=', $databaseAt)->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', $databaseAt))
            ->get()->reject(fn ($appointment) => $appointment->events->whereIn('event_type', ['revoked', 'invalidated'])->isNotEmpty());
        if ($appointments->count() !== 1) {
            throw new CutoverAuthorizationException('One current package-scoped authority appointment is required.');
        }
        $conflicting = ClubAccountingCutoverAuthorityAppointment::query()->with('events')
            ->where('cutover_package_id', $package->id)->where('appointed_user_id', $actor->id)
            ->where('authority_type', '!=', $type)->where('effective_at', '<=', $databaseAt)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', $databaseAt))->get()
            ->contains(fn ($appointment) => $appointment->events->whereIn('event_type', ['revoked', 'invalidated'])->isEmpty());
        $office = app(ClubPermissionService::class)->effectiveOffice($actor, (int) $package->club_id, $at);
        if ($conflicting || in_array($office, ['president', 'secretary'], true) || in_array($actor->id, array_filter([$package->prepared_by_user_id, $package->internally_verified_by_user_id]), true)) {
            throw new CutoverAuthorizationException('The actor holds a conflicting package role.');
        }
        if ($type === ClubAccountingCutoverAuthorityAppointment::TYPE_INDEPENDENT_CA) {
            $eligible = ClubAccountingCutoverCaEligibility::query()->where('authority_appointment_id', $appointments->first()->id)
                ->where('independence_state', 'confirmed')->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', $databaseAt))->exists();
            if (! $eligible) {
                throw new CutoverAuthorizationException('The appointed Independent CA lacks current dual-attested eligibility metadata.');
            }
        }

        return $appointments->first();
    }

    public function president(ClubAccountingCutoverPackage $package, User $actor, string $permission, ?DateTimeInterface $at = null): ClubAccountingCutoverPermissionAssignment
    {
        $at ??= now('UTC')->toImmutable();
        if ($this->uniqueOfficer($package, 'president-admin', $at)->id !== $actor->id) {
            throw new CutoverAuthorizationException('The unique active same-club President is required.');
        }
        if (in_array($actor->id, array_filter([$package->prepared_by_user_id, $package->internally_verified_by_user_id]), true)) {
            throw new CutoverAuthorizationException('The President holds a conflicting package role.');
        }

        return $this->permission($package, $actor, $permission, $at);
    }
}
