<?php

declare(strict_types=1);

namespace App\Services\Finance;

final readonly class LedgerAccountProvisioningRequest
{
    public function __construct(
        public int $clubId,
        public string $code,
        public string $name,
        public string $accountType,
        public string $normalBalance,
        public string $currency,
        public int $createdByUserId,
    ) {}
}
