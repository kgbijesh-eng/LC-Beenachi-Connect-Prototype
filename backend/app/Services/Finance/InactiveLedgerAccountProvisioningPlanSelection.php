<?php

declare(strict_types=1);

namespace App\Services\Finance;

final readonly class InactiveLedgerAccountProvisioningPlanSelection
{
    /**
     * @param  list<string>  $eligibilityClassifications
     * @param  list<string>  $referenceIdentifiers
     */
    public function __construct(
        public string $templateCode,
        public array $eligibilityClassifications,
        public array $referenceIdentifiers,
        public ?string $bankShortAlias = null,
    ) {}
}
