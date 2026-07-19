<?php

declare(strict_types=1);

namespace App\Services\Finance;

final readonly class ProposedUnverifiedLedgerAccountPlan
{
    /** @param list<ProposedUnverifiedLedgerAccountPlanItem> $items */
    public function __construct(
        public int $clubId,
        public int $actorUserId,
        public string $catalogueVersion,
        public string $planningPolicyVersion,
        public string $status,
        public array $items,
        public bool $isActive = false,
        public bool $provisioned = false,
        public bool $authorizedForCreation = false,
        public bool $authorizedForActivation = false,
    ) {}
}
