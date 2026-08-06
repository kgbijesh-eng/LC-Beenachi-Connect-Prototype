<?php

declare(strict_types=1);

namespace App\Services\Finance;

enum PaymentJournalLineRole: string
{
    case ASSET_DEBIT = 'asset_debit';
    case RECEIVABLES_CREDIT = 'receivables_credit';
}
