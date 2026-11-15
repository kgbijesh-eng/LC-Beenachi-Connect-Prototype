<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\ClubAccountingCutoverEvidenceReference;
use App\Models\ClubAccountingCutoverEvidenceVerification;
use App\Models\ClubAccountingCutoverPackage;
use App\Models\User;
use App\Services\Access\OrganisationTransactionContext;
use App\Services\Access\OrganisationTransactionGuard;
use App\Services\Finance\Exceptions\CutoverAuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;

final class ClubCutoverEvidenceVerificationService
{
    public function __construct(private readonly ClubCutoverAuthorityResolver $authority, private readonly OrganisationTransactionGuard $organisations) {}

    public function decide(ClubAccountingCutoverPackage $package, ClubAccountingCutoverEvidenceReference $evidence, User $actor, string $result, string $reference, ?string $safeNote, int $expectedLockVersion): ClubAccountingCutoverEvidenceVerification
    {
        if (! in_array($result, ['verified', 'rejected'], true) || $evidence->club_id !== $package->club_id || $evidence->cutover_package_id !== $package->id || ($safeNote !== null && (strlen($safeNote) > 500 || preg_match('~https?://|file:|password|secret|token|account\s*(number|no)~i', $safeNote)))) {
            throw new CutoverAuthorizationException('The evidence verification request is invalid.');
        }

        return $this->organisations->run($package->club_id, function (OrganisationTransactionContext $context) use ($package, $evidence, $actor, $result, $reference, $safeNote, $expectedLockVersion) {
            $at = now('UTC')->toImmutable();
            $scoped = ClubAccountingCutoverPackage::whereKey($package->id)->where('club_id', $context->organisationId);
            $snapshot = $scoped->firstOrFail();
            $this->boundEvidence($snapshot, $package, $evidence);
            $this->authority->activeMember($snapshot, $actor, 'secretary', $at);
            $this->authority->permission($snapshot, $actor, 'finance.cutover.package.verify', $at);

            $locked = $scoped->lockForUpdate()->firstOrFail();
            $lockedEvidence = $this->boundEvidence($locked, $package, $evidence);
            $bindingDigest = self::bindingDigest($lockedEvidence);
            $existing = ClubAccountingCutoverEvidenceVerification::where('club_id', $context->organisationId)->where('verification_reference', $reference)->first();
            if ($existing) {
                if ($existing->club_id !== $locked->club_id || $existing->cutover_package_id !== $locked->id
                    || $existing->package_version !== $locked->package_version || $existing->evidence_reference_id !== $lockedEvidence->id
                    || $existing->evidence_reference_public_id !== $lockedEvidence->public_id
                    || ! hash_equals($existing->evidence_binding_digest, $bindingDigest)
                    || $existing->result !== $result || $existing->verified_by_user_id !== $actor->id
                    || $existing->safe_note !== $safeNote) {
                    throw new CutoverAuthorizationException('The verification reference was reused with changed semantics.');
                }

                return $existing;
            }
            if ($locked->lock_version !== $expectedLockVersion) {
                throw new CutoverAuthorizationException('The evidence verification package binding is stale.');
            }
            $verification = $this->createVerification(['club_id' => $locked->club_id, 'cutover_package_id' => $locked->id, 'evidence_reference_id' => $lockedEvidence->id, 'evidence_reference_public_id' => $lockedEvidence->public_id, 'evidence_binding_digest' => $bindingDigest, 'package_version' => $locked->package_version, 'package_lock_version' => $locked->lock_version, 'result' => $result, 'verification_reference' => $reference, 'safe_note' => $safeNote, 'verified_by_user_id' => $actor->id, 'verified_at' => now()]);
            $lockedEvidence->update(['verification_status' => $result, 'reviewer_note' => $safeNote]);
            $locked->update(['canonical_checksum' => null, 'last_validated_at' => null, 'activation_ready' => false, 'readiness_checked_at' => null, 'updated_by_user_id' => $actor->id, 'lock_version' => $locked->lock_version + 1]);

            return $verification;
        }, attempts: 1);
    }

    private function boundEvidence(ClubAccountingCutoverPackage $current, ClubAccountingCutoverPackage $expected, ClubAccountingCutoverEvidenceReference $evidence): ClubAccountingCutoverEvidenceReference
    {
        $bound = ClubAccountingCutoverEvidenceReference::query()->whereKey($evidence->id)
            ->where('club_id', $current->club_id)->where('cutover_package_id', $current->id)->first();
        if (! $bound || $current->club_id !== $expected->club_id || $current->package_version !== $expected->package_version) {
            throw new CutoverAuthorizationException('The evidence verification package binding is stale.');
        }

        return $bound;
    }

    private function createVerification(array $attributes): ClubAccountingCutoverEvidenceVerification
    {
        try {
            return ClubAccountingCutoverEvidenceVerification::create($attributes);
        } catch (UniqueConstraintViolationException $exception) {
            throw new CutoverAuthorizationException('The verification reference was reused with changed semantics.', previous: $exception);
        }
    }

    public static function bindingDigest(ClubAccountingCutoverEvidenceReference $evidence): string
    {
        return hash('sha256', json_encode([
            'public_id' => $evidence->public_id, 'evidence_type' => $evidence->evidence_type,
            'opaque_reference' => $evidence->opaque_reference, 'evidence_date' => $evidence->evidence_date->format('Y-m-d'),
            'evidence_version' => $evidence->evidence_version, 'digest_sha256' => $evidence->digest_sha256,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
