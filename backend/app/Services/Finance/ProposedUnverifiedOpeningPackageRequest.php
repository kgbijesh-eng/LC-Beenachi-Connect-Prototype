<?php

declare(strict_types=1);

namespace App\Services\Finance;

final readonly class ProposedUnverifiedOpeningPackageRequest
{
    /**
     * @param  list<ProposedOpeningPosition>  $positions
     * @param  list<ProposedOpeningJournalLine>  $journalLines
     * @param  list<ProposedOpeningPackageException>  $exceptions
     */
    public function __construct(
        public string $packageStructureVersion,
        public string $validationPolicyVersion,
        public string $catalogueVersion,
        public string $provisioningPlanPolicyVersion,
        public int $clubId,
        public int $tenantId,
        public string $packageId,
        public string $packageVersion,
        public string $lionsYear,
        public string $cutoverDate,
        public ?string $futureCutoverDecisionReference,
        public string $sourceType,
        public string $sourceId,
        public string $sourceEvent,
        public string $idempotencyCandidate,
        public ProposedUnverifiedLedgerAccountPlan $accountPlan,
        public array $positions,
        public ?ProposedMemberReceivablesReconciliation $memberReceivables,
        public array $journalLines,
        public array $exceptions,
        public ProposedOpeningPrivacyAssertions $privacyAssertions,
    ) {}
}
