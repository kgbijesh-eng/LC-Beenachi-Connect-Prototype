<?php

declare(strict_types=1);

namespace App\Services\Finance;

final readonly class ProposedMemberReceivablesReconciliation
{
    public function __construct(
        public int $controlTotalPaise,
        public int $privateSubledgerTotalPaise,
        public ?string $reconciliationReference,
        public ?string $mappingReference,
    ) {}
}
