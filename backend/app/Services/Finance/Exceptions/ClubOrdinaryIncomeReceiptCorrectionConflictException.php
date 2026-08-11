<?php

namespace App\Services\Finance\Exceptions;

class ClubOrdinaryIncomeReceiptCorrectionConflictException extends ClubOrdinaryIncomeReceiptCorrectionException
{
    public function __construct(string $message, string $errorCode = 'ORDINARY_INCOME_RECEIPT_CORRECTION_CONFLICT', ?\Throwable $previous = null)
    {
        parent::__construct($message, $errorCode, 409, $previous);
    }
}
