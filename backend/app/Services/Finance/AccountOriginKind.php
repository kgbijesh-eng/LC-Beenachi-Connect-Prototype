<?php

declare(strict_types=1);

namespace App\Services\Finance;

enum AccountOriginKind: string
{
    case CATALOGUE = 'catalogue';
    case CUSTOM = 'custom';
}
