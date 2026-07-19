<?php

declare(strict_types=1);

namespace App\Services\Finance;

final readonly class OpeningPackageActivationBlocker
{
    public function __construct(public string $code, public ?string $field = null) {}
}
