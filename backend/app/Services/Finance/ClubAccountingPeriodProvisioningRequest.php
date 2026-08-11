<?php

declare(strict_types=1);

namespace App\Services\Finance;

final readonly class ClubAccountingPeriodProvisioningRequest
{
    public function __construct(
        public int $clubId,
        public string $lionsYear,
        public int $createdByUserId,
    ) {}
}
