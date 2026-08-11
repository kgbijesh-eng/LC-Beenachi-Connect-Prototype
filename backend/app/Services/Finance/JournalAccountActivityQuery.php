<?php

declare(strict_types=1);

namespace App\Services\Finance;

final readonly class JournalAccountActivityQuery
{
    public function __construct(
        public int $clubId,
        public int $ledgerAccountId,
        public string $fromEffectiveDate,
        public string $toEffectiveDate,
        public int $pageSize,
        public ?string $cursor = null,
    ) {}
}
