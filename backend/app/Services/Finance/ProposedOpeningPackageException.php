<?php

declare(strict_types=1);

namespace App\Services\Finance;

final readonly class ProposedOpeningPackageException
{
    public function __construct(
        public string $exceptionId,
        public string $category,
        public string $status,
        public ?string $affectedAccountCode = null,
        public ?int $amountPaise = null,
        public ?string $resolutionReference = null,
    ) {}
}
