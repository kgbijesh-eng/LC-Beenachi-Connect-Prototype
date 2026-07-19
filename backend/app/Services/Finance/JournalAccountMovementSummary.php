<?php

declare(strict_types=1);

namespace App\Services\Finance;

final readonly class JournalAccountMovementSummary
{
    public function __construct(
        public int $accountId,
        public string $accountPublicId,
        public string $accountCode,
        public string $accountName,
        public string $accountType,
        public string $normalBalance,
        public bool $isActive,
        public int $debitMovementPaise,
        public int $creditMovementPaise,
        public int $signedPeriodMovementPaise,
        public int $includedLineCount,
    ) {}
}
