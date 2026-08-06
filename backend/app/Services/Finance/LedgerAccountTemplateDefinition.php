<?php

declare(strict_types=1);

namespace App\Services\Finance;

final readonly class LedgerAccountTemplateDefinition
{
    public function __construct(
        public string $templateCode,
        public string $name,
        public string $accountType,
        public string $normalBalance,
        public string $statementClassification,
        public string $fundUsageRule,
        public string $currency,
        public string $purpose,
        public string $initialActivation,
        public bool $parameterized,
        public bool $activeByDefault,
        public string $provisioningStatus,
        public bool $manualPostingAllowed,
        public bool $selectedByTemplate,
        public string $qualification,
    ) {}
}
