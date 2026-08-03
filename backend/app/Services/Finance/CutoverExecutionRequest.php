<?php

declare(strict_types=1);

namespace App\Services\Finance;

final readonly class CutoverExecutionRequest
{
    public function __construct(
        public int $clubId,
        public int $packageId,
        public int $expectedLockVersion,
        public string $canonicalChecksum,
        public string $executionReference,
        public string $idempotencyKey,
        public int $technicalOperatorUserId,
    ) {}
}
