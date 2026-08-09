<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Club;
use App\Models\ClubAccountingCutoverAuthorityAppointment;
use App\Models\ClubAccountingCutoverPackage;
use App\Models\ClubAccountingCutoverPermissionAssignment;
use App\Models\ClubMembership;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\ClubCutoverAuthorityAdministrationService;
use App\Services\Finance\ClubCutoverPackageBootstrapService;

trait InteractsWithCutoverGovernance
{
    protected function bootstrapCutoverPackage(Club $club, User $preparer, User $president, User $secretary, string $reference, int $version = 1): ClubAccountingCutoverPackage
    {
        $service = app(ClubCutoverPackageBootstrapService::class);
        $operation = $service->request($club, $preparer, $version, $reference, $secretary);
        $service->approve($operation, $president, $reference.'-P');
        $operation = $service->approve($operation, $secretary, $reference.'-S');

        return ClubAccountingCutoverPackage::findOrFail($operation->cutover_package_id);
    }

    protected function cutoverMember(Club $club, User $user, string $role, string $status = 'active'): void
    {
        ClubMembership::create(['club_id' => $club->id, 'user_id' => $user->id, 'role_id' => Role::where('slug', $role)->firstOrFail()->id, 'status' => $status]);
    }

    protected function dualAssign(ClubAccountingCutoverPackage $package, User $subject, string $slug, User $president, User $secretary, string $reference): ClubAccountingCutoverPermissionAssignment
    {
        $service = app(ClubCutoverAuthorityAdministrationService::class);
        $operation = $service->requestPermission($package, $subject, $slug, $reference, $secretary);
        $service->approve($operation, $president, $reference.'-P');
        $operation = $service->approve($operation, $secretary, $reference.'-S');

        return ClubAccountingCutoverPermissionAssignment::findOrFail($operation->target_record_id);
    }

    protected function dualAppoint(ClubAccountingCutoverPackage $package, User $subject, string $type, User $president, User $secretary, string $reference): ClubAccountingCutoverAuthorityAppointment
    {
        $service = app(ClubCutoverAuthorityAdministrationService::class);
        $operation = $service->requestAppointment($package, $subject, $type, $reference, $secretary);
        $service->approve($operation, $president, $reference.'-P');
        $operation = $service->approve($operation, $secretary, $reference.'-S');

        return ClubAccountingCutoverAuthorityAppointment::findOrFail($operation->target_record_id);
    }

    protected function dualAttestEligibility(ClubAccountingCutoverAuthorityAppointment $appointment, User $president, User $secretary, string $reference): void
    {
        $service = app(ClubCutoverAuthorityAdministrationService::class);
        $operation = $service->requestCaEligibility($appointment, [
            'professional_qualification' => 'QUALIFICATION-'.$appointment->id,
            'identity' => 'IDENTITY-'.$appointment->id,
            'professional_standing' => 'STANDING-'.$appointment->id,
            'engagement' => 'ENGAGEMENT-'.$appointment->id,
            'independence' => 'INDEPENDENCE-'.$appointment->id,
        ], '2027-07-01', 'confirmed', $reference, $secretary);
        $service->approve($operation, $president, $reference.'-P');
        $service->approve($operation, $secretary, $reference.'-S');
    }
}
