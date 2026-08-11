<?php

declare(strict_types=1);

namespace App\Services\Finance;

final readonly class JournalPeriodMovementResult
{
    /** @param list<JournalAccountMovementSummary> $affectedAccounts */
    public function __construct(
        public int $clubId,
        public int $accountingPeriodId,
        public string $lionsYear,
        public string $periodStartDate,
        public string $periodEndDate,
        public string $periodStatus,
        public string $currency,
        public int $supportedEntryCount,
        public int $reversedOriginalCount,
        public int $postedReversalCount,
        public int $excludedDraftCount,
        public int $totalDebitPaise,
        public int $totalCreditPaise,
        public int $differencePaise,
        public array $affectedAccounts,
    ) {}
}
