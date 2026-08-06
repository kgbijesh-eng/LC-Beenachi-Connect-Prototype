<?php

declare(strict_types=1);

namespace App\Services\Finance;

enum StatementClassification: string
{
    case CASH_AND_BANK = 'CASH_AND_BANK';
    case RECEIVABLES = 'RECEIVABLES';
    case OTHER_CURRENT_ASSETS = 'OTHER_CURRENT_ASSETS';
    case PROPERTY_AND_EQUIPMENT = 'PROPERTY_AND_EQUIPMENT';
    case LIABILITIES = 'LIABILITIES';
    case FUND_BALANCE_EQUITY = 'FUND_BALANCE_EQUITY';
    case INCOME = 'INCOME';
    case EXPENSES = 'EXPENSES';
}
