<?php

declare(strict_types=1);

namespace App\Services\Finance;

final readonly class ProposedUnverifiedLedgerAccountPlanItem
{
    /** @param list<string> $referenceIdentifiers */
    public function __construct(
        public string $status,
        public string $templateCode,
        public string $generatedAccountCode,
        public string $name,
        public string $accountType,
        public string $normalBalance,
        public string $currency,
        public string $eligibilityClassification,
        public array $referenceIdentifiers,
        public bool $isActive = false,
        public bool $provisioned = false,
        public bool $authorizedForCreation = false,
        public bool $authorizedForActivation = false,
    ) {}
}
