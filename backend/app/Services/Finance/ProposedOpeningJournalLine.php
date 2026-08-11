<?php

declare(strict_types=1);

namespace App\Services\Finance;

final readonly class ProposedOpeningJournalLine
{
    public function __construct(
        public string $accountCode,
        public string $stableAccountReference,
        public ?int $debitPaise,
        public ?int $creditPaise,
        public ?string $evidenceReference,
    ) {}
}
