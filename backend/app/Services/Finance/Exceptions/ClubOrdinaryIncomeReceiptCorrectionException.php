<?php

namespace App\Services\Finance\Exceptions;

use RuntimeException;

class ClubOrdinaryIncomeReceiptCorrectionException extends RuntimeException
{
    public function __construct(string $message, public readonly string $errorCode = 'ORDINARY_INCOME_RECEIPT_CORRECTION_REJECTED', public readonly int $httpStatus = 422, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
