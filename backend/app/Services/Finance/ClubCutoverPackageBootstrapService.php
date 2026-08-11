<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubAccountingCutoverBootstrapApproval;
use App\Models\ClubAccountingCutoverBootstrapOperation;
use App\Models\ClubAccountingCutoverPackage;
use App\Models\ClubAccountingCutoverPermissionAssignment;
use App\Models\ClubMembership;
use App\Models\Permission;
use App\Models\User;
use App\Services\Finance\Exceptions\CutoverAuthorizationException;
use Illuminate\Support\Facades\DB;

final class ClubCutoverPackageBootstrapService
{
    public function __construct(private readonly ClubCutoverAuthorityResolver $authority) {}

    public function request(Club $club, User $preparer, int $packageVersion, string $reference, User $requester): ClubAccountingCutoverBootstrapOperation
    {
        $reference = strtoupper(trim($reference));
        if ($packageVersion < 1 || ! preg_match('/\A[A-Z0-9][A-Z0-9._:-]{5,119}\z/', $reference)) {
            throw new CutoverAuthorizationException('The package bootstrap request is invalid.');
        }
        $this->authority->governanceOfficerRoleForClub($club->id, $requester);
        $this->assertPreparer($club->id, $preparer->id);
        $permission = Permission::query()->where('slug', 'finance.cutover.package.prepare')->firstOrFail();
        $binding = [
            'club_id' => $club->id, 'package_version' => $packageVersion,
            'lions_year' => ClubAccountingCutoverPackage::LIONS_YEAR,
            'cutover_date' => ClubAccountingCutoverPackage::CUTOVER_DATE,
            'chart_version' => ClubAccountingCutoverPackage::CHART_VERSION,
            'preparer_user_id' => $preparer->id, 'permission_id' => $permission->id,
        ];
        $digest = hash('sha256', json_encode($binding, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $existing = ClubAccountingCutoverBootstrapOperation::query()->where('operation_reference', $reference)->first();
        if ($existing) {
            if (! hash_equals($existing->request_digest, $digest)) {
                throw new CutoverAuthorizationException('The bootstrap reference was reused with changed semantics.');
            }

            return $existing;
        }

        return ClubAccountingCutoverBootstrapOperation::create([...$binding, 'operation_reference' => $reference, 'request_digest' => $digest, 'status' => 'pending', 'requested_by_user_id' => $requester->id, 'requested_at' => now()]);
    }

    public function approve(ClubAccountingCutoverBootstrapOperation $operation, User $actor, string $approvalReference): ClubAccountingCutoverBootstrapOperation
    {
        return DB::transaction(function () use ($operation, $actor, $approvalReference): ClubAccountingCutoverBootstrapOperation {
            $operation = ClubAccountingCutoverBootstrapOperation::query()->whereKey($operation->id)->lockForUpdate()->firstOrFail();
            if ($operation->status === 'applied') {
                return $operation;
            }
            Club::query()->whereKey($operation->club_id)->lockForUpdate()->firstOrFail();
            $role = $this->authority->governanceOfficerRoleForClub($operation->club_id, $actor);
            if ($operation->approvals()->where(fn ($query) => $query->where('approval_role', $role)->orWhere('approved_by_user_id', $actor->id))->exists()) {
                throw new CutoverAuthorizationException('Each governance role and actor may approve bootstrap only once.');
            }
            ClubAccountingCutoverBootstrapApproval::create([
                'club_id' => $operation->club_id, 'bootstrap_operation_id' => $operation->id,
                'approval_role' => $role, 'approved_by_user_id' => $actor->id,
                'approval_reference' => strtoupper(trim($approvalReference)),
                'operation_digest' => $operation->request_digest, 'approved_at' => now(),
            ]);
            if ($operation->approvals()->count() === 2) {
                $this->apply($operation);
            }

            return $operation->fresh('approvals');
        });
    }

    private function apply(ClubAccountingCutoverBootstrapOperation $operation): void
    {
        $this->assertPreparer($operation->club_id, $operation->preparer_user_id);
        foreach (['president' => 'president-admin', 'secretary' => 'secretary'] as $approvalRole => $membershipRole) {
            $approvedBy = (int) $operation->approvals()->where('approval_role', $approvalRole)->value('approved_by_user_id');
            if ($approvedBy < 1 || $this->authority->uniqueOfficerForClub($operation->club_id, $membershipRole)->id !== $approvedBy) {
                throw new CutoverAuthorizationException('Both bootstrap governance approvals must retain their current officer basis.');
            }
        }
        if (ClubAccountingCutoverPackage::query()->where('club_id', $operation->club_id)->where('cutover_date', $operation->cutover_date)->where('package_version', $operation->package_version)->exists()) {
            throw new CutoverAuthorizationException('The cutover package bootstrap boundary already exists.');
        }
        $secretaryId = $operation->approvals()->where('approval_role', 'secretary')->value('approved_by_user_id');
        $package = ClubAccountingCutoverPackage::create([
            'club_id' => $operation->club_id, 'lions_year' => $operation->lions_year,
            'cutover_date' => $operation->cutover_date->format('Y-m-d'), 'chart_version' => $operation->chart_version,
            'package_version' => $operation->package_version, 'status' => ClubAccountingCutoverPackage::STATUS_DRAFT,
            'currency' => ClubAccountingCutoverPackage::CURRENCY_INR, 'reconciliation_difference_paise' => 0,
            'activation_ready' => false, 'lock_version' => 1,
            'created_by_user_id' => $operation->preparer_user_id, 'updated_by_user_id' => $operation->preparer_user_id,
        ]);
        $assignment = ClubAccountingCutoverPermissionAssignment::create([
            'club_id' => $operation->club_id, 'cutover_package_id' => $package->id,
            'user_id' => $operation->preparer_user_id, 'permission_id' => $operation->permission_id,
            'assignment_reference' => $operation->operation_reference.'-PREPARE', 'effective_at' => now(),
            'assigned_by_user_id' => $secretaryId,
        ]);
        $operation->update(['status' => 'applied', 'cutover_package_id' => $package->id, 'permission_assignment_id' => $assignment->id, 'applied_at' => now()]);
        AuditLog::create(['club_id' => $package->club_id, 'user_id' => $operation->preparer_user_id, 'action' => 'finance.cutover_package.created', 'auditable_type' => ClubAccountingCutoverPackage::class, 'auditable_id' => $package->id, 'metadata' => ['package_version' => $package->package_version, 'bootstrap_operation_id' => $operation->id]]);
    }

    private function assertPreparer(int $clubId, int $userId): void
    {
        $count = ClubMembership::query()->where('club_id', $clubId)->where('user_id', $userId)->where('status', 'active')
            ->whereHas('role', fn ($role) => $role->where('slug', 'treasurer'))->count();
        if ($count !== 1) {
            throw new CutoverAuthorizationException('The unique active same-club Treasurer is required as package preparer.');
        }
    }
}
