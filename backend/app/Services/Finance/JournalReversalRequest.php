<?php

declare(strict_types=1);

namespace App\Services\Finance;

final readonly class JournalReversalRequest
{
    public function __construct(
        public int $clubId,
        public int $originalEntryId,
        public int $reversalAccountingPeriodId,
        public string $effectiveDate,
        public int $reversalActorUserId,
        public string $reason,
        public string $sourceType,
        public string $sourceId,
        public string $sourceEvent,
        public string $idempotencyKey,
    ) {}
}
