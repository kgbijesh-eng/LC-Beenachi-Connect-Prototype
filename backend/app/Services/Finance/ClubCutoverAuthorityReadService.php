<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\ClubAccountingCutoverAdminOperation;
use App\Models\ClubAccountingCutoverAuthorityAppointment;
use App\Models\ClubAccountingCutoverBootstrapOperation;
use App\Models\ClubAccountingCutoverCaEligibility;
use App\Models\ClubAccountingCutoverPackage;
use App\Models\ClubAccountingCutoverPermissionAssignment;
use App\Models\ClubMembership;
use App\Models\User;
use App\Services\Finance\Exceptions\CutoverAuthorizationException;

final class ClubCutoverAuthorityReadService
{
    public function __construct(private readonly ClubCutoverAuthorityResolver $authority) {}

    public function metadata(ClubAccountingCutoverPackage $package, User $actor): array
    {
        $membership = ClubMembership::with('role')->where('club_id', $package->club_id)->where('user_id', $actor->id)->where('status', 'active')->first();
        $role = $membership?->role?->slug;
        $appointments = ClubAccountingCutoverAuthorityAppointment::with('events')->where('cutover_package_id', $package->id);
        $permissions = ClubAccountingCutoverPermissionAssignment::with(['events', 'permission'])->where('cutover_package_id', $package->id);
        if (in_array($role, ['president-admin', 'secretary'], true)) {
            if ($this->authority->uniqueOfficer($package, $role)->id !== $actor->id) {
                throw new CutoverAuthorizationException('Unique active governance officer resolution is required.');
            }

            return [
                'bootstrap_operations' => ClubAccountingCutoverBootstrapOperation::with('approvals')->where('club_id', $package->club_id)
                    ->where(fn ($query) => $query->where('cutover_package_id', $package->id)->orWhere(fn ($boundary) => $boundary->whereNull('cutover_package_id')->where('package_version', $package->package_version)->where('cutover_date', $package->cutover_date)))->get(),
                'operations' => ClubAccountingCutoverAdminOperation::with('approvals')->where('cutover_package_id', $package->id)->get(),
                'appointments' => $appointments->get(), 'permissions' => $permissions->get(),
                'ca_eligibilities' => ClubAccountingCutoverCaEligibility::where('cutover_package_id', $package->id)->get(),
            ];
        }
        if ($role === 'treasurer' && $package->prepared_by_user_id === $actor->id) {
            return ['readiness' => $package->only(['status', 'package_version', 'lock_version', 'activation_ready']), 'appointments' => collect(), 'permissions' => $permissions->where('user_id', $actor->id)->get()];
        }
        $ownAppointments = $appointments->where('appointed_user_id', $actor->id)->get();
        if ($ownAppointments->isNotEmpty()) {
            return ['appointments' => $ownAppointments, 'permissions' => $permissions->where('user_id', $actor->id)->get(), 'package' => $package->only(['status', 'package_version', 'canonical_checksum'])];
        }
        throw new CutoverAuthorizationException('Authority administration metadata access is denied.');
    }
}
