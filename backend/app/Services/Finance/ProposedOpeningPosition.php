<?php

declare(strict_types=1);

namespace App\Services\Finance;

final readonly class ProposedOpeningPosition
{
    public function __construct(
        public string $section,
        public string $accountCode,
        public int $amountPaise,
        public ?string $evidenceReference,
        public ?string $classification = null,
    ) {}
}
