<?php

declare(strict_types=1);

namespace App\Services\Finance;

enum FundCode: string
{
    case GENERAL_CLUB_FUND = 'GENERAL_CLUB_FUND';
    case SERVICE_ACTIVITY_FUND = 'SERVICE_ACTIVITY_FUND';
    case FELLOWSHIP_MEMBER_ACTIVITY_FUND = 'FELLOWSHIP_MEMBER_ACTIVITY_FUND';
}
