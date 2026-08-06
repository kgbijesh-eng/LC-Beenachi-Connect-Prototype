<?php

declare(strict_types=1);

namespace App\Services\Finance;

final readonly class JournalLineInput
{
    public function __construct(
        public int $accountId,
        public string $direction,
        public int $amountPaise,
        public ?string $description = null,
        public ?string $fundCode = null,
        public ?int $openingScheduleRowId = null,
        public ?int $reversalOfLineId = null,
    ) {}
}
