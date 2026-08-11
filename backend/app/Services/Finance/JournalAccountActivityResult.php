<?php

declare(strict_types=1);

namespace App\Services\Finance;

final readonly class JournalAccountActivityResult
{
    /**
     * @param  list<JournalAccountActivityRow>  $rows
     */
    public function __construct(
        public int $clubId,
        public string $clubCode,
        public int $accountId,
        public string $accountPublicId,
        public string $accountCode,
        public string $accountName,
        public string $accountType,
        public string $normalBalance,
        public string $currency,
        public string $fromEffectiveDate,
        public string $toEffectiveDate,
        public int $periodDebitPaise,
        public int $periodCreditPaise,
        public int $signedPeriodMovementPaise,
        public array $rows,
        public int $pageSize,
        public bool $hasMore,
        public ?string $nextCursor,
    ) {}
}
