<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubAccountingPeriod;
use App\Models\LedgerAccount;
use App\Models\Member;
use App\Models\User;
use App\Services\Access\OrganisationAccessRejected;
use App\Services\Access\OrganisationTransactionContext;
use App\Services\Access\OrganisationTransactionGuard;
use App\Services\Finance\ClubJournalPostingService;
use App\Services\Finance\JournalLineInput;
use App\Services\Finance\JournalPostingRequest;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class OrganisationTransactionGuardTest extends TestCase
{
    private Club $club;
    private Club $foreign;
    private OrganisationTransactionGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        // Check exact engine/target/role safety BEFORE any migration or fixture writes.
        $this->assertSame('1', env('RUN_P02B1_POSTGRES_TESTS'));
        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $this->assertSame('lcb_p02b1_transaction_guard_test', DB::connection()->getDatabaseName());
        $safe = DB::selectOne("SELECT current_user = pg_get_userbyid(d.datdba) AND NOT r.rolsuper AND NOT r.rolcreatedb AND NOT r.rolcreaterole AND NOT r.rolreplication AND NOT r.rolbypassrls AS safe FROM pg_database d JOIN pg_roles r ON r.rolname = current_user WHERE d.datname = current_database()");
        $this->assertTrue(in_array($safe->safe, [true, 1, '1', 't'], true));
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        $this->foreign = Club::create(['name' => 'Foreign synthetic', 'code' => 'FOREIGN', 'is_active' => true]);
        $this->club = Club::create(['name' => 'Assigned synthetic', 'code' => 'ASSIGNED', 'is_active' => true]);
        config(['instance.organisation_code' => 'ASSIGNED']);
        $this->guard = app(OrganisationTransactionGuard::class);
    }

    public function test_assigned_operation_locks_before_callback_and_commits_once(): void
    {
        $result = $this->guard->run($this->club, function (OrganisationTransactionContext $context) {
            $this->assertSame(1, DB::transactionLevel());
            $this->assertSame($this->club->id, $context->organisationId);
            $context->assertUsable($this->club->id);
            return $this->member('committed')->member_code;
        });
        $this->assertSame('committed', $result);
        $this->assertDatabaseCount('members', 1);
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_stale_active_model_cannot_admit_suspended_database_state(): void
    {
        Club::whereKey($this->club->id)->update(['is_active' => false]);
        $this->assertTrue($this->club->is_active);
        $this->deniedRun($this->club, 'ORGANISATION_SUSPENDED');
    }

    public function test_activity_is_reloaded_under_lock_after_initial_identity_resolution(): void
    {
        $other = $this->otherSession();
        $changed = false;
        DB::listen(function ($query) use ($other, &$changed) {
            if (! $changed && str_contains($query->sql, '"code" = ?') && $query->bindings === ['ASSIGNED']) {
                $changed = true;
                $other->exec('UPDATE clubs SET is_active=false WHERE id='.(int) $this->club->id);
            }
        });
        $this->deniedRun($this->club, 'ORGANISATION_SUSPENDED');
        $this->assertTrue($changed);
    }

    #[DataProvider('assignedActivity')]
    public function test_foreign_target_stays_404_before_suspension(bool $active): void
    {
        Club::whereKey($this->club->id)->update(['is_active' => $active]);
        $this->deniedRun($this->foreign, 'ORGANISATION_FOREIGN');
    }

    public static function assignedActivity(): array
    {
        return [[true], [false]];
    }

    #[DataProvider('invalidConfiguration')]
    public function test_misconfigured_identity_fails_closed(mixed $code): void
    {
        config(['instance.organisation_code' => $code]);
        $this->deniedRun($this->club, 'ORGANISATION_CONFIGURATION_UNAVAILABLE');
    }

    public static function invalidConfiguration(): array
    {
        return [[null], [''], ['UNKNOWN'], [' ASSIGNED '], [['ASSIGNED']], [2]];
    }

    public function test_deleted_configured_organisation_fails_closed(): void
    {
        $this->club->delete();
        $this->deniedRun($this->club, 'ORGANISATION_CONFIGURATION_UNAVAILABLE');
    }

    public function test_callback_failure_rolls_back_and_expires_context(): void
    {
        $escaped = null;
        try {
            $this->guard->run($this->club, function ($context) use (&$escaped) {
                $escaped = $context;
                $this->member('rollback');
                throw new RuntimeException('Synthetic failure');
            });
            $this->fail('Failure must propagate.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic failure', $exception->getMessage());
        }
        $this->assertDatabaseCount('members', 0);
        $this->assertSame(0, DB::transactionLevel());
        $this->rejected(fn () => $escaped->assertUsable(), 'ORGANISATION_TRANSACTION_CONTEXT_INVALID');
    }

    public function test_committed_context_cannot_be_reused_on_same_pdo_in_another_transaction(): void
    {
        $pdo = DB::connection()->getPdo();
        $escaped = $this->guard->run($this->club, fn ($context) => $context);
        $this->rejected(fn () => $escaped->assertUsable(), 'ORGANISATION_TRANSACTION_CONTEXT_INVALID');
        $this->guard->run($this->club, function ($current) use ($escaped, $pdo) {
            $this->assertSame($pdo, DB::connection()->getPdo());
            $this->rejected(fn () => $escaped->assertUsable(), 'ORGANISATION_TRANSACTION_CONTEXT_INVALID');
            $current->assertUsable();
        });
    }

    public function test_real_postgres_serialization_retry_issues_new_context_and_no_duplicate_effect(): void
    {
        $first = null;
        $calls = 0;
        $this->guard->run($this->club, function ($context) use (&$first, &$calls) {
            $calls++;
            if ($calls === 1) {
                $first = $context;
                $this->member('retry');
                $this->serializationFailure();
            }
            $this->assertNotSame($first, $context);
            $this->rejected(fn () => $first->assertUsable(), 'ORGANISATION_TRANSACTION_CONTEXT_INVALID');
            $this->assertDatabaseCount('members', 0);
            $this->member('retry');
        }, 2);
        $this->assertSame(2, $calls);
        $this->assertDatabaseCount('members', 1);
    }

    public function test_retry_rechecks_activity_before_reentering_business_callback(): void
    {
        $calls = 0;
        $changed = false;
        app('events')->listen(TransactionRolledBack::class, function ($event) use (&$changed) {
            if (! $changed && $event->connection->transactionLevel() === 0) {
                $changed = true;
                Club::whereKey($this->club->id)->update(['is_active' => false]);
            }
        });
        $this->rejected(function () use (&$calls) {
            $this->guard->run($this->club, function () use (&$calls) {
                $calls++;
                $this->serializationFailure();
            }, 2);
        }, 'ORGANISATION_SUSPENDED');
        $this->assertSame(1, $calls);
        $this->assertFalse($this->club->fresh()->is_active);
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_nested_framework_failure_retries_only_the_complete_outer_attempt(): void
    {
        $outerCalls = 0;
        $innerCalls = 0;
        $this->guard->run($this->club, function ($context) use (&$outerCalls, &$innerCalls) {
            $outerCalls++;
            DB::transaction(function () use ($context, &$innerCalls) {
                $innerCalls++;
                $context->assertUsable();
                $this->member('nested-retry');
                if ($innerCalls === 1) {
                    $this->serializationFailure();
                }
            }, 3);
        }, 2);
        $this->assertSame(2, $outerCalls);
        $this->assertSame(2, $innerCalls);
        $this->assertDatabaseCount('members', 1);
    }

    public function test_unknown_laravel_ambient_transaction_is_not_adopted_or_rolled_back(): void
    {
        DB::transaction(function () {
            $this->member('caller-owned');
            $this->deniedRun($this->club, 'ORGANISATION_TRANSACTION_UNSUPPORTED', 1);
            $this->assertDatabaseCount('members', 1);
        });
        $this->assertDatabaseCount('members', 1);
    }

    public function test_native_pdo_ambient_transaction_is_rejected_at_laravel_depth_zero(): void
    {
        $pdo = DB::connection()->getPdo();
        $pdo->beginTransaction();
        try {
            $this->deniedRun($this->club, 'ORGANISATION_TRANSACTION_UNSUPPORTED');
            $this->assertTrue($pdo->inTransaction());
        } finally {
            $pdo->rollBack();
        }
    }

    public function test_nested_context_works_across_guard_instances_without_new_transaction(): void
    {
        $other = app(OrganisationTransactionGuard::class);
        $this->guard->run($this->club, function ($context) use ($other) {
            $other->within($context, $this->club->id, function ($nested) use ($context) {
                $this->assertSame($context, $nested);
                $this->assertSame(1, DB::transactionLevel());
                $this->member('nested');
            });
            $this->rejected(fn () => $other->run($this->club, fn () => $this->fail('No ambient adoption')), 'ORGANISATION_TRANSACTION_UNSUPPORTED');
            $context->assertUsable();
        });
        $this->assertDatabaseCount('members', 1);
    }

    public function test_nested_foreign_target_cannot_use_a_valid_context(): void
    {
        $this->guard->run($this->club, function ($context) {
            $this->rejected(fn () => $this->guard->within($context, $this->foreign, fn () => $this->fail('No foreign work')), 'ORGANISATION_FOREIGN');
            $context->assertUsable();
        });
    }

    public function test_constructed_and_cloned_contexts_are_not_authority(): void
    {
        $fake = new OrganisationTransactionContext($this->club->id, $this->guard);
        $this->rejected(fn () => $fake->assertUsable(), 'ORGANISATION_TRANSACTION_CONTEXT_INVALID');
        $this->guard->run($this->club, function ($context) {
            $clone = clone $context;
            $this->rejected(fn () => $clone->assertUsable(), 'ORGANISATION_TRANSACTION_CONTEXT_INVALID');
            $context->assertUsable();
        });
    }

    public function test_context_cannot_be_serialized(): void
    {
        $this->expectException(\LogicException::class);
        $this->guard->run($this->club, fn ($context) => serialize($context));
    }

    public function test_callback_cannot_return_success_after_changing_activity(): void
    {
        $calls = 0;
        $this->rejected(function () use (&$calls) {
            $this->guard->run($this->club, function () use (&$calls) {
                $calls++;
                $this->member('must-rollback');
                Club::whereKey($this->club->id)->update(['is_active' => false]);
            }, 3);
        }, 'ORGANISATION_SUSPENDED');
        $this->assertSame(1, $calls);
        $this->assertDatabaseCount('members', 0);
        $this->assertTrue($this->club->fresh()->is_active);
    }

    public function test_configuration_cannot_retarget_an_issued_context(): void
    {
        try {
            $this->rejected(fn () => $this->guard->run($this->club, function ($context) {
                config(['instance.organisation_code' => 'FOREIGN']);
                $context->assertUsable();
            }), 'ORGANISATION_TRANSACTION_CONTEXT_INVALID');
        } finally {
            config(['instance.organisation_code' => 'ASSIGNED']);
        }
    }

    #[DataProvider('nativeEndings')]
    public function test_replaced_root_transaction_at_same_depth_and_pdo_invalidates_context(string $ending): void
    {
        $this->rejected(fn () => $this->guard->run($this->club, function ($context) use ($ending) {
            $pdo = DB::connection()->getPdo();
            $pdo->$ending();
            $pdo->beginTransaction();
            $this->assertSame(1, DB::transactionLevel());
            $context->assertUsable();
        }), 'ORGANISATION_TRANSACTION_CONTEXT_INVALID');
        $this->assertSame(0, DB::transactionLevel());
    }

    public static function nativeEndings(): array
    {
        return [['rollBack'], ['commit']];
    }

    public function test_different_default_connection_cannot_borrow_context(): void
    {
        $original = DB::getDefaultConnection();
        config(['database.connections.b1_alias' => DB::connection()->getConfig()]);
        try {
            $this->guard->run($this->club, function ($context) use ($original) {
                DB::setDefaultConnection('b1_alias');
                try {
                    $this->rejected(fn () => $context->assertUsable(), 'ORGANISATION_TRANSACTION_CONTEXT_INVALID');
                } finally {
                    DB::setDefaultConnection($original);
                }
                $context->assertUsable();
            });
        } finally {
            DB::setDefaultConnection($original);
            DB::purge('b1_alias');
        }
    }

    public function test_connection_replacement_is_rejected_and_original_pdo_is_rolled_back(): void
    {
        $original = DB::connection()->getPdo();
        $this->rejected(fn () => $this->guard->run($this->club, function ($context) {
            $this->member('must-rollback');
            DB::connection()->disconnect();
            $context->assertUsable();
        }), 'ORGANISATION_TRANSACTION_CONTEXT_INVALID');
        $this->assertFalse($original->inTransaction());
        $this->assertSame(0, DB::transactionLevel());
        $this->assertDatabaseCount('members', 0);
    }

    public function test_unbalanced_savepoint_cannot_leave_a_transaction_open(): void
    {
        $this->rejected(fn () => $this->guard->run($this->club, function () {
            $this->member('must-rollback');
            DB::beginTransaction();
        }), 'ORGANISATION_TRANSACTION_CONTEXT_INVALID');
        $this->assertSame(0, DB::transactionLevel());
        $this->assertDatabaseCount('members', 0);
    }

    public function test_nested_framework_savepoint_commit_and_rollback_preserve_root_lock_context(): void
    {
        $this->guard->run($this->club, function ($context) {
            DB::transaction(function () use ($context) {
                $this->assertSame(2, DB::transactionLevel());
                $context->assertUsable();
                $this->member('nested-commit');
            });
            try {
                DB::transaction(function () use ($context) {
                    $context->assertUsable();
                    $this->member('nested-rollback');
                    throw new RuntimeException('Rollback child');
                });
            } catch (RuntimeException $exception) {
                $this->assertSame('Rollback child', $exception->getMessage());
            }
            $context->assertUsable();
        });
        $this->assertDatabaseCount('members', 1);
    }

    public function test_read_only_ambient_snapshot_is_rejected_without_altering_its_contract(): void
    {
        DB::beginTransaction();
        try {
            DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
            $this->deniedRun($this->club, 'ORGANISATION_TRANSACTION_UNSUPPORTED', 1);
            $this->assertSame('on', DB::selectOne("SELECT current_setting('transaction_read_only') AS value")->value);
            $this->assertSame('repeatable read', DB::selectOne("SELECT current_setting('transaction_isolation') AS value")->value);
        } finally {
            DB::rollBack();
        }
    }

    public function test_non_elevated_second_session_cannot_suspend_while_guard_holds_club_lock(): void
    {
        $other = $this->otherSession();
        $other->exec("SET lock_timeout = '100ms'");
        $this->guard->run($this->club, function ($context) use ($other) {
            try {
                $other->exec('UPDATE clubs SET is_active=false WHERE id='.(int) $this->club->id);
                $this->fail('The club lock must block suspension.');
            } catch (\PDOException $exception) {
                $this->assertSame('55P03', $exception->getCode());
            }
            $context->assertUsable();
            $this->assertTrue($this->club->fresh()->is_active);
        });
        $other->exec('UPDATE clubs SET is_active=false WHERE id='.(int) $this->club->id);
        $this->deniedRun($this->club, 'ORGANISATION_SUSPENDED');
    }

    public function test_existing_journal_api_nested_transactions_and_replay_remain_compatible(): void
    {
        $actor = User::factory()->create();
        $period = ClubAccountingPeriod::create(['club_id' => $this->club->id, 'lions_year' => '2027-28', 'start_date' => '2027-07-01', 'end_date' => '2028-06-30', 'status' => 'open', 'created_by_user_id' => $actor->id]);
        $accounts = [];
        foreach ([['BANK-SYN', 'asset', 'debit', 'CASH_AND_BANK'], ['INCOME-SYN', 'income', 'credit', 'INCOME']] as [$code, $type, $balance, $classification]) {
            $accounts[] = LedgerAccount::create(['club_id' => $this->club->id, 'code' => $code, 'name' => $code, 'account_type' => $type, 'normal_balance' => $balance, 'currency' => 'INR', 'is_active' => true, 'created_by_user_id' => $actor->id, 'origin_kind' => 'custom', 'statement_classification' => $classification]);
        }
        $request = new JournalPostingRequest($this->club->id, $period->id, '2027-08-01', 'Synthetic journal', 'INR', 'b1_synthetic', 'synthetic', 'posted', (string) Str::uuid(), $actor->id, [new JournalLineInput($accounts[0]->id, 'debit', 100, fundCode: 'GENERAL_CLUB_FUND'), new JournalLineInput($accounts[1]->id, 'credit', 100, fundCode: 'GENERAL_CLUB_FUND')]);
        $this->guard->run($this->club, function ($context) use ($request) {
            $first = app(ClubJournalPostingService::class)->postWithin($context, $request);
            $replay = app(ClubJournalPostingService::class)->postWithin($context, $request);
            $this->assertSame($first->id, $replay->id);
            $context->assertUsable();
            $this->assertSame(1, DB::transactionLevel());
        });
        $this->assertDatabaseCount('club_journal_entries', 1);
        $this->assertDatabaseCount('club_journal_lines', 2);
    }

    private function member(string $code): Member
    {
        return Member::create(['club_id' => $this->club->id, 'member_code' => $code, 'full_name' => 'Synthetic member']);
    }

    private function otherSession(): PDO
    {
        $cfg = DB::connection()->getConfig();

        return new PDO("pgsql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']}", $cfg['username'], $cfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    private function serializationFailure(): never
    {
        DB::unprepared("DO \$\$ BEGIN RAISE EXCEPTION USING ERRCODE = '40001', MESSAGE = 'Synthetic serialization retry'; END \$\$");
        throw new RuntimeException('PostgreSQL must raise a serialization failure.');
    }

    private function deniedRun(int|Club $club, string $reason, int $depth = 0): void
    {
        $before = Member::count();
        $this->rejected(fn () => $this->guard->run($club, fn () => $this->fail('Denied callback must not run')), $reason);
        $this->assertSame($depth, DB::transactionLevel());
        $this->assertDatabaseCount('members', $before);
    }

    private function rejected(callable $operation, string $reason): void
    {
        try {
            $operation();
            $this->fail('Expected typed organisation rejection.');
        } catch (OrganisationAccessRejected $exception) {
            $this->assertSame($reason, $exception->reason);
            $this->assertSame(match ($reason) {
                'ORGANISATION_FOREIGN' => 404,
                'ORGANISATION_SUSPENDED' => 403,
                'ORGANISATION_CONFIGURATION_UNAVAILABLE' => 503,
                default => 409,
            }, $exception->status);
        }
    }
}
