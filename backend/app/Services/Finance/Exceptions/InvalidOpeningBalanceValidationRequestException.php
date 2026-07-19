<?php

declare(strict_types=1);

namespace App\Services\Finance\Exceptions;

use DomainException;

final class InvalidOpeningBalanceValidationRequestException extends DomainException {}
