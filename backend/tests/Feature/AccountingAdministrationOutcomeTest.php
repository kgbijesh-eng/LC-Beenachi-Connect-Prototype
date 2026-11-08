<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubAccountingPeriod;
use App\Models\LedgerAccount;
use App\Models\User;
use App\Services\Access\OrganisationAccessRejected;
use App\Services\Access\OrganisationTransactionGuard;
use App\Services\Finance\Exceptions\AccountingPeriodProvisioningConflictException;
use App\Services\Finance\Exceptions\InvalidProvisioningActorException;
use App\Services\Finance\Exceptions\LedgerAccountProvisioningConflictException;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\AccountingAdministrationFixture as Fixture;
use Tests\Support\FinancialPostgresTestCase;

class AccountingAdministrationOutcomeTest extends FinancialPostgresTestCase
{
    public static function recoveries(): array
    {
        $out = [];
        foreach (['account', 'period'] as $api) {
            foreach (['exact', 'conflict', 'absent', 'actor', 'context', 'suspended', 'rollback-failure', 'release-failure'] as $mode) {
                $out[$api.'-'.$mode] = [$api, $mode];
            }
        }

return $out;
    }

    #[DataProvider('recoveries')]
    public function test_recovery_requires_explicit_owned_savepoint_rollback(string $api, string $mode): void
    {
        $p = Fixture::prepare($api.($mode === 'absent' ? '' : '-replay'));
        $before = Fixture::snapshot($p['club']);
        if ($mode === 'actor') {
            $p['actor'] = PHP_INT_MAX;
        }
        if ($mode === 'conflict') {
            $p['actor'] = User::factory()->create()->id;
        }
        $table = $api === 'account' ? 'ledger_accounts' : 'club_accounting_periods';
        $armed = false;
        $fired = false;
        $original = null;
        $queries = [];
        $rollback = false;
        DB::connection()->beforeExecuting(function ($sql) use ($mode, &$armed, &$original): void {
            if ($armed && $mode === 'rollback-failure' && str_starts_with($sql, 'ROLLBACK TO SAVEPOINT b5_')) {
                throw new RuntimeException('controlled rollback uncertainty');
            }
            if ($armed && $mode === 'release-failure' && str_starts_with($sql, 'RELEASE SAVEPOINT b5_')) {
                $original = new QueryException(DB::getDefaultConnection(), $sql, [], new PDOException('controlled release failure'));
                throw $original;
            }
        });
        DB::listen(function ($q) use ($table, $mode, &$armed, &$fired, &$original, &$queries, &$rollback, $p): void {
            $queries[] = $q->sql;
            if (str_starts_with($q->sql, 'SAVEPOINT b5_')) {
                $armed = true;
            }
            if (str_starts_with($q->sql, 'ROLLBACK TO SAVEPOINT b5_')) {
                $rollback = true;
                if ($mode === 'context') {
                    config(['instance.organisation_code' => null]);
                }
                if ($mode === 'suspended') {
                    Club::whereKey($p['club'])->update(['is_active' => false]);
                }
            }
            $target = $mode === 'actor' ? 'users' : $table;
            if ($armed && ! $fired && $mode !== 'release-failure' && str_starts_with($q->sql, 'select') && str_contains($q->sql, '"'.$target.'"')) {
                $fired = true;
                try {
                    DB::statement('SELECT 1/0');
                } catch (QueryException $e) {
                    $original = $e;
                    throw $e;
                }
            }
        });
        $result = null;
        $failure = null;
        try {
            $result = Fixture::perform($p);
        } catch (\Throwable $e) {
            $failure = $e;
        }
        if ($mode === 'exact') {
            $this->assertNull($failure);
            $this->assertNotNull($result);
            $this->assertTrue($rollback);
        } elseif (in_array($mode, ['absent', 'rollback-failure', 'release-failure'], true)) {
            $this->assertSame($original, $failure);
        } elseif ($mode === 'actor') {
            $this->assertInstanceOf(InvalidProvisioningActorException::class, $failure);
        } elseif (in_array($mode, ['context', 'suspended'], true)) {
            $this->assertInstanceOf(OrganisationAccessRejected::class, $failure);
            $this->assertSame($mode === 'context' ? 'ORGANISATION_CONFIGURATION_UNAVAILABLE' : 'ORGANISATION_SUSPENDED', $failure->reason);
        } else {
            $this->assertInstanceOf($api === 'account' ? LedgerAccountProvisioningConflictException::class : AccountingPeriodProvisioningConflictException::class, $failure);
        }
        $this->assertSame(0, DB::transactionLevel());
        $this->assertSame($before, Fixture::snapshot($p['club']));
        if ($mode === 'exact') {
            $rb = array_search(true, array_map(fn ($sql) => str_starts_with($sql, 'ROLLBACK TO SAVEPOINT b5_'), $queries), true);
            $this->assertIsInt($rb);
            $after = array_slice($queries, $rb + 1);
            $this->assertTrue(count(array_filter($after, fn ($sql) => str_contains($sql, '"users"'))) >= 1);
            $this->assertTrue(count(array_filter($after, fn ($sql) => str_contains($sql, '"'.$table.'"') && str_contains($sql, 'for update'))) >= 1);
        }
        fwrite(STDOUT, "\nB5_SAVEPOINT_OUTCOME ".json_encode(['api' => $api, 'mode' => $mode, 'rollback_observed' => $rollback, 'success' => $result !== null, 'exception' => $failure?->getMessage(), 'depth' => DB::transactionLevel()], JSON_THROW_ON_ERROR)."\n");
    }

    public static function completions(): array
    {
        $out = [];
        foreach (['account', 'period', 'close'] as $api) {
            foreach (['savepoint', 'commit-40001', 'commit-40P01', 'lost-ack'] as $mode) {
                $out[$api.'-'.$mode] = [$api, $mode];
            }
        }

return $out;
    }

    #[DataProvider('completions')]
    public function test_completion_failure_never_becomes_fallback_success(string $api, string $mode): void
    {
        $p = Fixture::prepare($api);
        $before = Fixture::snapshot($p['club']);
        $original = null;
        $fired = false;
        Event::listen(TransactionCommitted::class, function () use ($mode, &$original, &$fired): void {
            $depth = DB::transactionLevel();
            if ($fired || ($mode === 'savepoint' ? $depth !== 1 : $depth !== 0)) {
                return;
            }
            $fired = true;
            if ($mode === 'lost-ack') {
                $original = new RuntimeException('lost acknowledgement');
            } else {
                $pdo = new PDOException('completion error');
                (new \ReflectionProperty(\Exception::class, 'code'))->setValue($pdo, $mode === 'commit-40001' ? '40001' : ($mode === 'commit-40P01' ? '40P01' : 'XX000'));
                $pdo->errorInfo = [$pdo->getCode(), 0, 'completion error'];
                $original = new QueryException(DB::getDefaultConnection(), 'post-completion', [], $pdo);
            }
            throw $original;
        });
        try {
            if ($mode === 'savepoint') {
                app(OrganisationTransactionGuard::class)->run($p['club'], fn ($ctx) => Fixture::perform($p, $ctx), 3);
            } else {
                Fixture::perform($p);
            }$this->fail('Completion failure must escape.');
        } catch (\Throwable $e) {
            $this->assertSame($original, $e);
        }
        $this->assertTrue($fired);
        $this->assertSame(0, DB::transactionLevel());
        if ($mode === 'savepoint') {
            $this->assertSame($before, Fixture::snapshot($p['club']));
        } else {
            $after = Fixture::snapshot($p['club']);
            $one = Fixture::perform($p);
            $this->assertNotNull($one);
            $this->assertSame($after, Fixture::snapshot($p['club']));
            if ($api === 'close') {
                $this->assertSame(1, AuditLog::where('club_id', $p['club'])->where('action', 'finance.accounting_period.closed')->count());
            } else {
                $this->assertSame(1, ($api === 'account' ? LedgerAccount::query()->where('code', 'B5-CASH') : ClubAccountingPeriod::query()->where('lions_year', '2028-29'))->where('club_id', $p['club'])->count());
            }
        }
    }

    public static function mutations(): array
    {
        return [['account'], ['period'], ['close']];
    }

    #[DataProvider('mutations')]
    public function test_parent_rollback_undoes_supported_nested_mutation(string $api): void
    {
        $p = Fixture::prepare($api);
        $before = Fixture::snapshot($p['club']);
        try {
            app(OrganisationTransactionGuard::class)->run($p['club'], function ($ctx) use ($p): void {
                Fixture::perform($p, $ctx);
                throw new RuntimeException('parent rollback');
            }, 3);
        } catch (RuntimeException $e) {
            $this->assertSame('parent rollback', $e->getMessage());
        }
        $this->assertSame($before, Fixture::snapshot($p['club']));
        $this->assertSame(0, DB::transactionLevel());
    }

    public static function closingFailures(): array
    {
        return [['period'], ['audit']];
    }

    #[DataProvider('closingFailures')]
    public function test_close_and_audit_are_atomic(string $point): void
    {
        $p = Fixture::prepare('close');
        $before = Fixture::snapshot($p['club']);
        if ($point === 'audit') {
            AuditLog::created(fn () => throw new RuntimeException('controlled close failure'));
        } else {
            ClubAccountingPeriod::updated(fn () => throw new RuntimeException('controlled close failure'));
        }
        try {
            Fixture::perform($p);
            $this->fail('Failure must escape');
        } catch (RuntimeException $e) {
            $this->assertSame('controlled close failure',$e->getMessage());
        }
        $this->assertSame($before,Fixture::snapshot($p['club']));
    }
}
