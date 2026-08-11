<?php

declare(strict_types=1);

namespace App\Services\Finance;

final readonly class AccountingPeriodCloseRequest
{
    public function __construct(
        public int $clubId,
        public int $accountingPeriodId,
        public int $closingActorUserId,
        public string $expectedLionsYear,
        public string $reason,
    ) {}
}
