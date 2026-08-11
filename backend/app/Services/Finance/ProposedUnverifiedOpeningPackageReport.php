<?php

declare(strict_types=1);

namespace App\Services\Finance;

final readonly class ProposedUnverifiedOpeningPackageReport
{
    /**
     * @param  list<array<string, mixed>>  $positions
     * @param  list<array<string, mixed>>  $journalLines
     * @param  list<OpeningPackageActivationBlocker>  $blockers
     */
    public function __construct(
        public string $packageStructureVersion,
        public string $validationPolicyVersion,
        public string $catalogueVersion,
        public string $provisioningPlanPolicyVersion,
        public string $packageId,
        public string $sourceId,
        public string $status,
        public string $activationStatus,
        public array $positions,
        public array $journalLines,
        public array $blockers,
        public string $dryRunFingerprint,
        public bool $authoritative = false,
        public bool $evidenceVerified = false,
        public bool $accountingReviewCompleted = false,
        public bool $authorizedForPosting = false,
        public bool $authorizedForActivation = false,
    ) {}
}
