<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\ClubAccountingCutoverApproval;
use App\Models\ClubAccountingCutoverAuthorityAppointment;
use App\Models\ClubAccountingCutoverDecision;
use App\Models\ClubAccountingCutoverDecisionEvent;
use App\Models\ClubAccountingCutoverPackage;
use App\Models\ClubAccountingCutoverReview;
use App\Models\LedgerAccount;
use App\Models\User;
use App\Services\Finance\Exceptions\CutoverPackageException;
use Illuminate\Support\Facades\DB;

final class ClubCutoverLifecycleService
{
    public function __construct(private ClubCutoverAuthorityResolver $authority, private ClubAccountingCutoverPackageService $packages) {}

    public function prepare(ClubAccountingCutoverPackage $package, User $actor, int $expectedLockVersion): ClubAccountingCutoverPackage
    {
        return DB::transaction(function () use ($package, $actor, $expectedLockVersion): ClubAccountingCutoverPackage {
            $locked = $this->lock($package, $expectedLockVersion, ClubAccountingCutoverPackage::STATUS_DRAFT);
            $this->authority->permission($locked, $actor, 'finance.cutover.package.prepare');
            $validation = $this->packages->validate($locked);
            if (! $validation['valid']) {
                throw new CutoverPackageException('The package must validate before preparation.');
            }
            $checksum = $this->packages->checksum($locked);
            $locked->update(['status' => ClubAccountingCutoverPackage::STATUS_PREPARED, 'prepared_by_user_id' => $actor->id, 'prepared_at' => now(), 'canonical_checksum' => $checksum, 'lock_version' => $locked->lock_version + 1, 'updated_by_user_id' => $actor->id]);

            return $locked->fresh();
        });
    }

    public function verify(ClubAccountingCutoverPackage $package, User $actor, int $expectedLockVersion): ClubAccountingCutoverPackage
    {
        return DB::transaction(function () use ($package, $actor, $expectedLockVersion): ClubAccountingCutoverPackage {
            $locked = $this->lock($package, $expectedLockVersion, ClubAccountingCutoverPackage::STATUS_PREPARED);
            $this->authority->permission($locked, $actor, 'finance.cutover.package.verify');
            if ($locked->prepared_by_user_id === $actor->id) {
                throw new CutoverPackageException('The preparer cannot verify the same package.');
            }
            if (! $this->packages->validate($locked)['valid'] || ! hash_equals((string) $locked->canonical_checksum, $this->packages->checksum($locked))) {
                throw new CutoverPackageException('The prepared package binding is stale.');
            }
            $locked->update(['status' => ClubAccountingCutoverPackage::STATUS_INTERNALLY_VERIFIED, 'internally_verified_by_user_id' => $actor->id, 'internally_verified_at' => now(), 'lock_version' => $locked->lock_version + 1, 'updated_by_user_id' => $actor->id]);

            return $locked->fresh();
        });
    }

    public function review(ClubAccountingCutoverPackage $package, User $actor, int $expectedLockVersion, string $classification, string $reference, string $reviewDate, string $rationale): ClubAccountingCutoverReview
    {
        return DB::transaction(function () use ($package, $actor, $expectedLockVersion, $classification, $reference, $reviewDate, $rationale): ClubAccountingCutoverReview {
            $locked = $this->lock($package, $expectedLockVersion, ClubAccountingCutoverPackage::STATUS_INTERNALLY_VERIFIED);
            if ($locked->dr033_classification !== null || ! in_array($classification, [ClubAccountingCutoverPackage::CLASSIFICATION_OPENING_EQUITY, ClubAccountingCutoverPackage::CLASSIFICATION_ACCUMULATED_SURPLUS], true)) {
                throw new CutoverPackageException('DR-033 may be selected exactly once by the accounting review.');
            }
            $appointment = $this->authority->appointment($locked, $actor, ClubAccountingCutoverAuthorityAppointment::TYPE_INDEPENDENT_CA);
            $permission = $this->authority->permission($locked, $actor, 'finance.cutover.package.accounting-review');
            if (! LedgerAccount::query()->where('club_id', $locked->club_id)->where('code', $classification)->where('account_type', LedgerAccount::TYPE_EQUITY)->where('currency', 'INR')->where('is_active', true)->exists()) {
                throw new CutoverPackageException('The selected DR-033 account is unavailable.');
            }
            $locked->status = ClubAccountingCutoverPackage::STATUS_ACCOUNTING_REVIEWED;
            $locked->dr033_classification = $classification;
            $locked->canonical_checksum = null;
            $locked->lock_version++;
            $locked->updated_by_user_id = $actor->id;
            $locked->save();
            $checksum = $this->packages->checksum($locked->fresh());
            DB::table('club_accounting_cutover_packages')->where('id', $locked->id)->update(['canonical_checksum' => $checksum]);
            $review = ClubAccountingCutoverReview::create(['club_id' => $locked->club_id, 'cutover_package_id' => $locked->id, 'reviewer_identifier' => $reference, 'review_date' => $reviewDate, 'selected_classification' => $classification, 'written_rationale' => $rationale, 'package_version' => $locked->package_version, 'chart_version' => $locked->chart_version, 'package_checksum' => $checksum, 'status' => 'current', 'recorded_by_user_id' => $actor->id, 'authority_appointment_id' => $appointment->id, 'permission_assignment_id' => $permission->id, 'authority_basis' => 'appointed_independent_ca', 'recorded_at' => now()]);

            return $review->fresh();
        });
    }

    public function approve(ClubAccountingCutoverPackage $package, User $actor, int $expectedLockVersion, string $approvalDate, string $reference): ClubAccountingCutoverApproval
    {
        return DB::transaction(function () use ($package, $actor, $expectedLockVersion, $approvalDate, $reference): ClubAccountingCutoverApproval {
            $locked = $this->lock($package, $expectedLockVersion, ClubAccountingCutoverPackage::STATUS_ACCOUNTING_REVIEWED);
            $permission = $this->authority->president($locked, $actor, 'finance.cutover.package.business-approve');
            $review = $this->trustedReview($locked);
            if ($review->recorded_by_user_id === $actor->id) {
                throw new CutoverPackageException('The President and accounting reviewer must be different actors.');
            }
            $approval = ClubAccountingCutoverApproval::create(['club_id' => $locked->club_id, 'cutover_package_id' => $locked->id, 'approval_authority' => 'President', 'approval_date' => $approvalDate, 'approval_reference' => $reference, 'package_version' => $locked->package_version, 'chart_version' => $locked->chart_version, 'package_checksum' => $locked->canonical_checksum, 'status' => 'current', 'recorded_by_user_id' => $actor->id, 'permission_assignment_id' => $permission->id, 'authority_basis' => 'unique_active_president', 'recorded_at' => now()]);
            $locked->update(['status' => ClubAccountingCutoverPackage::STATUS_BUSINESS_APPROVED, 'lock_version' => $locked->lock_version + 1, 'updated_by_user_id' => $actor->id]);

            return $approval->fresh();
        });
    }

    public function decide(ClubAccountingCutoverPackage $package, User $actor, int $expectedLockVersion, string $stage, string $authorityType, string $reference, string $reason): ClubAccountingCutoverDecision
    {
        return DB::transaction(function () use ($package, $actor, $expectedLockVersion, $stage, $authorityType, $reference, $reason): ClubAccountingCutoverDecision {
            $requiredState = $stage === ClubAccountingCutoverDecision::STAGE_DRY_RUN ? ClubAccountingCutoverPackage::STATUS_BUSINESS_APPROVED : ClubAccountingCutoverPackage::STATUS_DRY_RUN_ACCEPTED;
            $locked = $this->lock($package, $expectedLockVersion, $requiredState);
            $permissionSlug = $stage === ClubAccountingCutoverDecision::STAGE_DRY_RUN ? 'finance.cutover.package.dry-run-accept' : 'finance.cutover.package.activate-authorize';
            $appointment = null;
            if ($authorityType === ClubAccountingCutoverDecision::ACTOR_PRESIDENT) {
                $permission = $this->authority->president($locked, $actor, $permissionSlug);
            } elseif ($authorityType === ClubAccountingCutoverDecision::ACTOR_INDEPENDENT_CA) {
                $appointment = $this->authority->appointment($locked, $actor, ClubAccountingCutoverAuthorityAppointment::TYPE_INDEPENDENT_CA);
                $permission = $this->authority->permission($locked, $actor, $permissionSlug);
            } else {
                throw new CutoverPackageException('The decision authority is invalid.');
            }
            $review = $this->trustedReview($locked);
            $approval = $this->trustedApproval($locked);
            if (in_array($actor->id, [$review->recorded_by_user_id, $approval->recorded_by_user_id], true) === false && $authorityType === ClubAccountingCutoverDecision::ACTOR_INDEPENDENT_CA) {
                throw new CutoverPackageException('The appointed accounting reviewer must make the CA decision.');
            }
            $decision = ClubAccountingCutoverDecision::create(['club_id' => $locked->club_id, 'cutover_package_id' => $locked->id, 'decision_stage' => $stage, 'actor_authority' => $authorityType, 'decision_result' => 'accepted', 'decision_reference' => $reference, 'package_version' => $locked->package_version, 'canonical_checksum' => $locked->canonical_checksum, 'chart_version' => $locked->chart_version, 'lions_year' => $locked->lions_year, 'cutover_date' => $locked->cutover_date, 'dr033_classification' => $locked->dr033_classification, 'accounting_review_id' => $review->id, 'president_approval_id' => $approval->id, 'authority_appointment_id' => $appointment?->id, 'permission_assignment_id' => $permission->id, 'recorded_by_user_id' => $actor->id, 'reason' => $reason, 'recorded_at' => now()]);
            $current = $this->currentDecisions($locked, $stage);
            if ($current->where('actor_authority', ClubAccountingCutoverDecision::ACTOR_PRESIDENT)->count() === 1 && $current->where('actor_authority', ClubAccountingCutoverDecision::ACTOR_INDEPENDENT_CA)->count() === 1) {
                $locked->update(['status' => $stage === ClubAccountingCutoverDecision::STAGE_DRY_RUN ? ClubAccountingCutoverPackage::STATUS_DRY_RUN_ACCEPTED : ClubAccountingCutoverPackage::STATUS_ACTIVATION_AUTHORIZED, 'lock_version' => $locked->lock_version + 1, 'updated_by_user_id' => $actor->id]);
            } else {
                $locked->update(['lock_version' => $locked->lock_version + 1, 'updated_by_user_id' => $actor->id]);
            }

            return $decision->fresh();
        });
    }

    public function revoke(ClubAccountingCutoverDecision $decision, User $actor, string $reference, string $reason): ClubAccountingCutoverDecisionEvent
    {
        return DB::transaction(function () use ($decision, $actor, $reference, $reason): ClubAccountingCutoverDecisionEvent {
            $decision = ClubAccountingCutoverDecision::query()->with('events')->lockForUpdate()->findOrFail($decision->id);
            if ($decision->recorded_by_user_id !== $actor->id || $decision->events->isNotEmpty()) {
                throw new CutoverPackageException('Only the original actor may revoke a current decision.');
            }
            $package = ClubAccountingCutoverPackage::query()->findOrFail($decision->cutover_package_id);
            $permission = $this->authority->permission($package, $actor, 'finance.cutover.package.rollback-authorize');

            return ClubAccountingCutoverDecisionEvent::create(['club_id' => $decision->club_id, 'cutover_package_id' => $decision->cutover_package_id, 'cutover_decision_id' => $decision->id, 'event_type' => 'revoked', 'event_reference' => $reference, 'reason' => $reason, 'recorded_by_user_id' => $actor->id, 'permission_assignment_id' => $permission->id, 'recorded_at' => now()]);
        });
    }

    private function lock(ClubAccountingCutoverPackage $package, int $version, string $state): ClubAccountingCutoverPackage
    {
        $locked = ClubAccountingCutoverPackage::query()->whereKey($package->id)->where('club_id', $package->club_id)->lockForUpdate()->first();
        if (! $locked || $locked->lock_version !== $version || $locked->status !== $state) {
            throw new CutoverPackageException('The cutover lifecycle state or lock version is stale.');
        }

        return $locked;
    }

    private function trustedReview(ClubAccountingCutoverPackage $package): ClubAccountingCutoverReview
    {
        $review = ClubAccountingCutoverReview::query()->where('cutover_package_id', $package->id)->where('status', 'current')->whereNotNull('authority_basis')->whereDoesntHave('events', fn ($query) => $query->whereIn('event_type', ['revoked', 'invalidated']))->latest('id')->first();
        if (! $review || $review->package_version !== $package->package_version || ! hash_equals($review->package_checksum, (string) $package->canonical_checksum) || $review->selected_classification !== $package->dr033_classification) {
            throw new CutoverPackageException('A trusted matching accounting review is required.');
        }

        return $review;
    }

    private function trustedApproval(ClubAccountingCutoverPackage $package): ClubAccountingCutoverApproval
    {
        $approval = ClubAccountingCutoverApproval::query()->where('cutover_package_id', $package->id)->where('status', 'current')->whereNotNull('authority_basis')->whereDoesntHave('events', fn ($query) => $query->whereIn('event_type', ['revoked', 'invalidated']))->latest('id')->first();
        if (! $approval || $approval->package_version !== $package->package_version || ! hash_equals($approval->package_checksum, (string) $package->canonical_checksum)) {
            throw new CutoverPackageException('A trusted matching President approval is required.');
        }

        return $approval;
    }

    private function currentDecisions(ClubAccountingCutoverPackage $package, string $stage)
    {
        return ClubAccountingCutoverDecision::query()->with('events')->where('cutover_package_id', $package->id)->where('decision_stage', $stage)->get()->reject(fn ($decision) => $decision->events->isNotEmpty());
    }
}
