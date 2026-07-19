<?php

declare(strict_types=1);

namespace App\Services\Finance;

final readonly class JournalAccountActivityRow
{
    public function __construct(
        public string $journalEntryPublicId,
        public string $journalLinePublicId,
        public string $journalReference,
        public string $lionsYear,
        public string $effectiveDate,
        public string $postingDate,
        public string $postedAt,
        public int $sequenceNumber,
        public string $lifecycleStatus,
        public ?string $sourceType,
        public ?string $sourceId,
        public ?string $sourceEvent,
        public ?string $reversalOfEntryPublicId,
        public ?string $reversedByEntryPublicId,
        public int $lineNumber,
        public string $direction,
        public int $amountPaise,
        public string $journalDescription,
        public ?string $lineDescription,
    ) {}
}
