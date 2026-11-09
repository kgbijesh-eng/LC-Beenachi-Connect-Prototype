<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Services\Access\OrganisationAccessRejected;
use Illuminate\Database\Connection;
use InvalidArgumentException;

final class UserCredentialLock
{
    public function acquire(Connection $connection, int $userId): void
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('A positive credential user ID is required.');
        }

        if ($connection->getDriverName() !== 'pgsql' || $connection->transactionLevel() !== 1
            || ! $connection->getPdo()->inTransaction()) {
            throw OrganisationAccessRejected::unsupported();
        }

        // Negative single-bigint keys are reserved for credentials.
        $connection->selectOne('SELECT pg_advisory_xact_lock(CAST(? AS bigint))', [-$userId], false);
    }
}
