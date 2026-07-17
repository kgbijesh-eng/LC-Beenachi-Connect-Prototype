<?php

declare(strict_types=1);

namespace App\Services\Finance;

final readonly class JournalPostingRequest
{
    /**
     * @param  list<JournalLineInput>  $lines
     */
    public function __construct(
        public int $clubId,
        public int $accountingPeriodId,
        public string $effectiveDate,
        public string $description,
        public string $currency,
        public string $sourceType,
        public string $sourceId,
        public string $sourceEvent,
        public string $idempotencyKey,
        public int $postingActorUserId,
        public array $lines,
    ) {}
}
