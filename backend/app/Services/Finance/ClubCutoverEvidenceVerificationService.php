<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\ClubAccountingCutoverEvidenceReference;
use App\Models\ClubAccountingCutoverEvidenceVerification;
use App\Models\ClubAccountingCutoverPackage;
use App\Models\User;
use App\Services\Finance\Exceptions\CutoverAuthorizationException;
use Illuminate\Support\Facades\DB;

final class ClubCutoverEvidenceVerificationService
{
    public function __construct(private readonly ClubCutoverAuthorityResolver $authority) {}

    public function decide(ClubAccountingCutoverPackage $package, ClubAccountingCutoverEvidenceReference $evidence, User $actor, string $result, string $reference, ?string $safeNote, int $expectedLockVersion): ClubAccountingCutoverEvidenceVerification
    {
        if (! in_array($result, ['verified', 'rejected'], true) || $evidence->club_id !== $package->club_id || $evidence->cutover_package_id !== $package->id || ($safeNote !== null && (strlen($safeNote) > 500 || preg_match('~https?://|file:|password|secret|token|account\s*(number|no)~i', $safeNote)))) {
            throw new CutoverAuthorizationException('The evidence verification request is invalid.');
        }

        return DB::transaction(function () use ($package, $evidence, $actor, $result, $reference, $safeNote, $expectedLockVersion) {
            $locked = ClubAccountingCutoverPackage::whereKey($package->id)->lockForUpdate()->firstOrFail();
            $lockedEvidence = ClubAccountingCutoverEvidenceReference::query()->whereKey($evidence->id)
                ->where('club_id', $locked->club_id)->where('cutover_package_id', $locked->id)->first();
            if (! $lockedEvidence || $locked->club_id !== $package->club_id || $locked->package_version !== $package->package_version) {
                throw new CutoverAuthorizationException('The evidence verification package binding is stale.');
            }
            $this->authority->activeMember($locked, $actor, 'secretary');
            $this->authority->permission($locked, $actor, 'finance.cutover.package.verify');
            $bindingDigest = self::bindingDigest($lockedEvidence);
            $existing = ClubAccountingCutoverEvidenceVerification::where('verification_reference', $reference)->first();
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
            $verification = ClubAccountingCutoverEvidenceVerification::create(['club_id' => $locked->club_id, 'cutover_package_id' => $locked->id, 'evidence_reference_id' => $lockedEvidence->id, 'evidence_reference_public_id' => $lockedEvidence->public_id, 'evidence_binding_digest' => $bindingDigest, 'package_version' => $locked->package_version, 'package_lock_version' => $locked->lock_version, 'result' => $result, 'verification_reference' => $reference, 'safe_note' => $safeNote, 'verified_by_user_id' => $actor->id, 'verified_at' => now()]);
            $lockedEvidence->update(['verification_status' => $result, 'reviewer_note' => $safeNote]);
            $locked->update(['canonical_checksum' => null, 'last_validated_at' => null, 'activation_ready' => false, 'readiness_checked_at' => null, 'updated_by_user_id' => $actor->id, 'lock_version' => $locked->lock_version + 1]);

            return $verification;
        });
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
