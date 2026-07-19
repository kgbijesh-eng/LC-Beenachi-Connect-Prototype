<?php

declare(strict_types=1);

namespace App\Services\Finance;

final readonly class InactiveLedgerAccountProvisioningPlanRequest
{
    /** @param list<InactiveLedgerAccountProvisioningPlanSelection> $selections */
    public function __construct(
        public int $clubId,
        public int $actorUserId,
        public string $catalogueVersion,
        public string $planningPolicyVersion,
        public array $selections,
    ) {}
}
