<?php

declare(strict_types=1);

namespace App\Services\Access;

use App\Models\Club;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\QueryException;
use InvalidArgumentException;
use PDOException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use WeakMap;

final class OrganisationTransactionGuard
{
    /** Issued capabilities, not an organisation activity cache; removed at callback exit. */
    private WeakMap $contexts;

    public function __construct(
        private readonly DatabaseManager $database,
        private readonly InstanceOrganisation $instance,
    ) {
        $this->contexts = new WeakMap;
    }

    /**
     * Own a new outer mutation boundary. Never adopt an unknown ambient transaction.
     * PostgreSQL transaction identity is required; SQLite is not a lock-proof substitute.
     */
    public function run(int|Club $organisation, Closure $operation, int $attempts = 1): mixed
    {
        if ($attempts < 1) {
            throw new InvalidArgumentException('Transaction attempts must be positive.');
        }

        $connection = $this->database->connection();
        if ($connection->transactionLevel() !== 0 || $connection->getPdo()->inTransaction()
            || $connection->getDriverName() !== 'pgsql') {
            throw OrganisationAccessRejected::unsupported();
        }

        try {
            return $connection->transaction(function () use ($connection, $organisation, $operation): mixed {
                $context = null;
                $attemptPdo = $connection->getPdo();
                try {
                    if ($this->database->connection() !== $connection
                        || $connection->selectOne("SELECT current_setting('transaction_read_only') AS read_only")->read_only !== 'off') {
                        throw OrganisationAccessRejected::unsupported();
                    }

                    // Resolve on the primary inside this new transaction, using P0.1's exact rules.
                    $assigned = $this->resolveInstance();
                    $this->assertTarget($connection, $organisation, $assigned->id);
                    $locked = Club::on($connection->getName())->useWritePdo()
                        ->whereKey($assigned->id)->lockForUpdate()->first();
                    if (! $locked || $locked->code !== $assigned->code) {
                        throw OrganisationAccessRejected::unavailable();
                    }
                    if (! $locked->is_active) {
                        throw OrganisationAccessRejected::suspended();
                    }

                    $context = new OrganisationTransactionContext($locked->id, $this);
                    $this->contexts[$context] = [
                        'connection' => $connection,
                        'pdo' => $connection->getPdo(),
                        'identity' => $this->identity($connection),
                        'code' => $locked->code,
                    ];
                    $result = $operation($context);
                    $context->assertUsable();
                    if ($connection->transactionLevel() !== 1) {
                        throw OrganisationAccessRejected::invalidContext();
                    }

                    return $result;
                } catch (DeadlockException $exception) {
                    // Laravel's nested wrapper casts a string SQLSTATE to code 0.
                    // Preserve the original serialization failure at our outer retry boundary.
                    $cause = $exception->getPrevious();
                    if ($cause instanceof PDOException && in_array($cause->getCode(), [40001, '40001'], true)) {
                        throw $cause;
                    }
                    throw $exception;
                } finally {
                    if ($context !== null) {
                        unset($this->contexts[$context]);
                    }
                    if ($connection->getRawPdo() !== $attemptPdo && $attemptPdo->inTransaction()) {
                        $attemptPdo->rollBack();
                    }
                }
            }, $attempts);
        } finally {
            // This method entered at depth zero. Never leave an unbalanced callback's
            // savepoints/root transaction open. This does not run on ambient rejection.
            if ($connection->transactionLevel() > 0) {
                $connection->rollBack(0);
            }
        }
    }

    /** Join an issued context without changing transaction ownership or retry policy. */
    public function within(OrganisationTransactionContext $context, int|Club $organisation, Closure $operation): mixed
    {
        $context->assertUsable($organisation);
        $result = $operation($context);
        $context->assertUsable($organisation);

        return $result;
    }

    /** Called by the context's issuing guard, including from a different nested service. */
    public function assertContext(OrganisationTransactionContext $context, int|Club|null $organisation = null): void
    {
        $proof = $this->contexts[$context] ?? null;
        if ($proof === null) {
            throw OrganisationAccessRejected::invalidContext();
        }
        $connection = $proof['connection'];
        if ($this->database->connection() !== $connection || $connection->transactionLevel() < 1
            || $connection->getPdo() !== $proof['pdo'] || ! $proof['pdo']->inTransaction()) {
            throw OrganisationAccessRejected::invalidContext();
        }
        try {
            if ($this->identity($connection) !== $proof['identity']) {
                throw OrganisationAccessRejected::invalidContext();
            }
        } catch (QueryException $exception) {
            throw OrganisationAccessRejected::invalidContext($exception);
        }

        $assigned = $this->resolveInstance();
        if ($assigned->id !== $context->organisationId || $assigned->code !== $proof['code']) {
            throw OrganisationAccessRejected::invalidContext();
        }
        $this->assertTarget($connection, $organisation ?? $context->organisationId, $context->organisationId);
        if (! $assigned->is_active) {
            throw OrganisationAccessRejected::suspended();
        }
    }

    private function identity(Connection $connection): array
    {
        $row = $connection->selectOne('SELECT pg_backend_pid() AS backend_pid, pg_current_xact_id()::text AS transaction_id');

        return [(string) $row->backend_pid, (string) $row->transaction_id];
    }

    private function resolveInstance(): Club
    {
        try {
            return $this->instance->resolve();
        } catch (HttpExceptionInterface $exception) {
            if ($exception->getStatusCode() === 503) {
                throw OrganisationAccessRejected::unavailable($exception);
            }
            throw $exception;
        }
    }

    private function assertTarget(Connection $connection, int|Club $organisation, int $assignedId): void
    {
        if ($organisation instanceof Club && $organisation->getConnection() !== $connection) {
            throw OrganisationAccessRejected::foreign();
        }
        $id = $organisation instanceof Club ? $organisation->getKey() : $organisation;
        if ((string) $id !== (string) $assignedId) {
            throw OrganisationAccessRejected::foreign();
        }
    }
}
