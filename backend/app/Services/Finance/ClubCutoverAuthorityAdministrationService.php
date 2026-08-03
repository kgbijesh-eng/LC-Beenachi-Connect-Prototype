<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\ClubAccountingCutoverAuthorityAppointment;
use App\Models\ClubAccountingCutoverAuthorityEvent;
use App\Models\ClubAccountingCutoverPackage;
use App\Models\ClubAccountingCutoverPermissionAssignment;
use App\Models\ClubAccountingCutoverPermissionEvent;
use App\Models\Permission;
use App\Models\User;
use App\Services\Finance\Exceptions\CutoverAuthorizationException;
use Illuminate\Support\Facades\DB;

final class ClubCutoverAuthorityAdministrationService
{
    public function appoint(ClubAccountingCutoverPackage $package, User $actor, string $type, string $reference, User $appointedBy, ?string $expiresAt = null): ClubAccountingCutoverAuthorityAppointment
    {
        if (! in_array($type, [ClubAccountingCutoverAuthorityAppointment::TYPE_INDEPENDENT_CA, ClubAccountingCutoverAuthorityAppointment::TYPE_TECHNICAL_OPERATOR], true)) {
            throw new CutoverAuthorizationException('The cutover authority type is invalid.');
        }

        return ClubAccountingCutoverAuthorityAppointment::create(['club_id' => $package->club_id, 'cutover_package_id' => $package->id, 'appointed_user_id' => $actor->id, 'authority_type' => $type, 'appointment_reference' => $reference, 'effective_at' => now(), 'expires_at' => $expiresAt, 'appointed_by_user_id' => $appointedBy->id]);
    }

    public function assignPermission(ClubAccountingCutoverPackage $package, User $actor, string $slug, string $reference, User $assignedBy, ?string $expiresAt = null): ClubAccountingCutoverPermissionAssignment
    {
        $permission = Permission::query()->where('slug', $slug)->first();
        if (! $permission || ! str_starts_with($slug, 'finance.cutover.package.')) {
            throw new CutoverAuthorizationException('The exact cutover permission is undefined.');
        }

        return ClubAccountingCutoverPermissionAssignment::create(['club_id' => $package->club_id, 'cutover_package_id' => $package->id, 'user_id' => $actor->id, 'permission_id' => $permission->id, 'assignment_reference' => $reference, 'effective_at' => now(), 'expires_at' => $expiresAt, 'assigned_by_user_id' => $assignedBy->id]);
    }

    public function revokeAppointment(ClubAccountingCutoverAuthorityAppointment $appointment, User $actor, string $reference, string $reason): ClubAccountingCutoverAuthorityEvent
    {
        return DB::transaction(fn () => ClubAccountingCutoverAuthorityEvent::create(['club_id' => $appointment->club_id, 'cutover_package_id' => $appointment->cutover_package_id, 'authority_appointment_id' => $appointment->id, 'event_type' => 'revoked', 'event_reference' => $reference, 'reason' => $reason, 'recorded_by_user_id' => $actor->id, 'recorded_at' => now()]));
    }

    public function revokePermission(ClubAccountingCutoverPermissionAssignment $assignment, User $actor, string $reference, string $reason): ClubAccountingCutoverPermissionEvent
    {
        return DB::transaction(fn () => ClubAccountingCutoverPermissionEvent::create(['club_id' => $assignment->club_id, 'cutover_package_id' => $assignment->cutover_package_id, 'permission_assignment_id' => $assignment->id, 'event_type' => 'revoked', 'event_reference' => $reference, 'reason' => $reason, 'recorded_by_user_id' => $actor->id, 'recorded_at' => now()]));
    }
}
