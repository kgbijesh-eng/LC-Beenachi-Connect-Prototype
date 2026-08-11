<?php

declare(strict_types=1);

namespace App\Services\Finance;

final readonly class JournalPeriodMovementQuery
{
    public function __construct(public int $clubId, public int $accountingPeriodId) {}
}
