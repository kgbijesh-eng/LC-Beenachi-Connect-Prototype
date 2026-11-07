<?php

declare(strict_types=1);

namespace App\Services\Finance\Exceptions;

use Throwable;

final class CutoverExecutionAuditException extends CutoverExecutionException
{
    public function __construct(public readonly string $phase, public readonly Throwable $auditFailure, public readonly ?Throwable $businessFailure=null)
    {
        parent::__construct('Opening execution audit could not be persisted; reconciliation is required.',previous:$businessFailure??$auditFailure);
    }
}
