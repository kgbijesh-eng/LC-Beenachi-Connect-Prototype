<?php

declare(strict_types=1);

namespace App\Services\Finance;

final readonly class ClubAccountStatementQuery
{
    public function __construct(
        public int $clubId,
        public string $lionsYear,
        public ?int $periodId = null,
        public ?string $from = null,
        public ?string $to = null,
    ) {}
}
