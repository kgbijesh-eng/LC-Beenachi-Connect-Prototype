<?php

declare(strict_types=1);

namespace App\Services\Finance;

enum FundUsageRule: string
{
    case ANY_APPROVED_FUND = 'ANY_APPROVED_FUND';
    case GENERAL_ONLY = 'GENERAL_ONLY';
    case SERVICE_ONLY = 'SERVICE_ONLY';
    case FELLOWSHIP_MEMBER_ONLY = 'FELLOWSHIP_MEMBER_ONLY';
}
