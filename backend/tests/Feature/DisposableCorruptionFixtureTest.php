<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Club;
use App\Models\User;
use App\Services\Access\OrganisationAccessRejected;
use App\Services\Access\OrganisationTransactionGuard;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Support\DisposableCorruptionFixture;
use Tests\Support\FinancialPostgresTestCase;

class DisposableCorruptionFixtureTest extends FinancialPostgresTestCase
{
    public function test_owned_corruption_commits_only_after_trigger_restoration(): void
    {
        [$club, $one, $two] = $this->fixture();
        $helper = new DisposableCorruptionFixture;
        $locks = [];
        DB::listen(function ($q) use (&$locks) {
            if (str_contains($q->sql, 'for update') || str_starts_with($q->sql, 'ALTER TABLE')) {
                $locks[] = ['sql' => $q->sql, 'depth' => DB::transactionLevel()];
            }
        });
        $helper->corrupt($club, [$this->deletion($one)]);
        $helper->assertRestored();
        $this->assertStringContainsString('"clubs"', $locks[0]['sql']);
        $this->assertSame(1, $locks[0]['depth']);
        $this->assertSame(0, DB::transactionLevel());
        $this->assertSame(0, DB::table('club_accounting_source_coverage_decisions')->where('id', $one)->count());
        $this->assertSame(1, DB::table('club_accounting_source_coverage_decisions')->where('id', $two)->count());
        $this->expectException(QueryException::class);
        DB::table('club_accounting_source_coverage_decisions')->where('id', $two)->delete();
    }

    public function test_check_failure_rolls_back_all_rows_and_restores_triggers(): void
    {
        [$club, $one, $two] = $this->fixture();
        $helper = new DisposableCorruptionFixture;
        try {
            $helper->corrupt($club, [$this->deletion($one), ['table' => 'club_accounting_source_coverage_decisions', 'id' => $two, 'values' => ['decision_reason' => 'not-a-valid-reason']]]);
            $this->fail('CHECK constraints must remain enabled.');
        } catch (QueryException $e) {
            $this->assertSame('23514', $e->errorInfo[0]);
        }
        $helper->assertRestored();
        $this->assertSame(2, DB::table('club_accounting_source_coverage_decisions')->count());
        $this->assertSame(0, DB::transactionLevel());
        $this->assertFileDoesNotExist(getenv('B3_FIXTURE_POISON_FILE'));
    }

    public function test_exception_after_enable_rolls_back_and_proves_restoration(): void
    {
        [$club, $one] = $this->fixture();
        $helper = new DisposableCorruptionFixture;
        $fired = false;
        DB::listen(function ($q) use (&$fired): void {
            if (! $fired && str_contains($q->sql, ' ENABLE TRIGGER ')) {
                $fired = true;
                throw new RuntimeException('controlled enable acknowledgement loss');
            }
        });
        try {
            $helper->corrupt($club, [$this->deletion($one)]);
            $this->fail('Injected failure must propagate.');
        } catch (RuntimeException $e) {
            $this->assertSame('controlled enable acknowledgement loss', $e->getMessage());
        }
        $this->assertTrue($fired);
        $helper->assertRestored();
        $this->assertSame(2, DB::table('club_accounting_source_coverage_decisions')->count());
        $this->assertFileDoesNotExist(getenv('B3_FIXTURE_POISON_FILE'));
    }

    public function test_wrong_cluster_attestation_fails_before_any_ddl(): void
    {
        [$club, $one] = $this->fixture();
        $prior = getenv('B3_FIXTURE_STARTED');
        $ddl = [];
        DB::listen(function ($q) use (&$ddl) {
            if (str_starts_with($q->sql, 'ALTER TABLE')) {
                $ddl[] = $q->sql;
            }
        });
        putenv('B3_FIXTURE_STARTED=1');
        try {
            (new DisposableCorruptionFixture)->corrupt($club, [$this->deletion($one)]);
            $this->fail('Wrong cluster must fail closed.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('rejected', $e->getMessage());
        } finally {
            putenv('B3_FIXTURE_STARTED='.$prior);
        }
        $this->assertSame([], $ddl);
        (new DisposableCorruptionFixture)->assertRestored();
    }

    public function test_unapproved_tables_foreign_rows_and_ambient_transactions_are_rejected(): void
    {
        [$club, $one] = $this->fixture();
        $helper = new DisposableCorruptionFixture;
        foreach ([['table' => 'club_journal_entries', 'id' => 1, 'values' => ['status' => 'posted']], $this->deletion(PHP_INT_MAX)] as $plan) {
            try {
                $helper->corrupt($club, [$plan]);
                $this->fail('Unsafe plan must fail closed.');
            } catch (RuntimeException $e) {
                $this->assertTrue(str_contains($e->getMessage(), 'allowlisted') || str_contains($e->getMessage(), 'owned'));
            }
        }
        $foreign = Club::create(['name' => 'Foreign synthetic', 'code' => 'OTHER']);
        try {
            $helper->corrupt($foreign, [$this->deletion($one)]);
            $this->fail('Foreign target must be rejected.');
        } catch (OrganisationAccessRejected $e) {
            $this->assertSame('ORGANISATION_FOREIGN', $e->reason);
        }
        DB::transaction(function () use ($helper, $club, $one) {
            try {
                $helper->corrupt($club, [$this->deletion($one)]);
                $this->fail('No ambient adoption.');
            } catch (RuntimeException $e) {
                $this->assertSame('Fixture refuses ambient transactions.', $e->getMessage());
            }
            $this->assertSame(1, DB::transactionLevel());
        });
        $helper->assertRestored();
        $this->assertSame(2, DB::table('club_accounting_source_coverage_decisions')->count());
    }

    public function test_another_database_client_rejects_corruption(): void
    {
        [$club, $one] = $this->fixture();
        config(['database.connections.b3_other_client' => config('database.connections.'.DB::getDefaultConnection())]);
        DB::connection('b3_other_client')->selectOne('SELECT 1');
        try {
            (new DisposableCorruptionFixture)->corrupt($club, [$this->deletion($one)]);
            $this->fail('Concurrent clients must fail closed.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('isolation rejected', $e->getMessage());
        } finally {
            DB::purge('b3_other_client');
            gc_collect_cycles();
        }
        $deadline = microtime(true) + 2;
        while (DB::selectOne("SELECT count(*) AS n FROM pg_stat_activity WHERE datname=current_database() AND pid<>pg_backend_pid() AND backend_type='client backend'")->n > 0 && microtime(true) < $deadline) {
            usleep(10000);
        }
        (new DisposableCorruptionFixture)->assertRestored();
        $this->assertSame(2, DB::table('club_accounting_source_coverage_decisions')->count());
    }

    public function test_disabled_state_is_rejected_and_restored_before_outer_commit(): void
    {
        [$club] = $this->fixture();
        $helper = new DisposableCorruptionFixture;
        app(OrganisationTransactionGuard::class)->run($club, function ($context) use ($helper): void {
            DB::unprepared('ALTER TABLE ONLY public."club_accounting_source_coverage_decisions" DISABLE TRIGGER "club_accounting_source_coverage_decisions_immutable"');
            try {
                $helper->assertRestored();
                $this->fail('Disabled immutable trigger must fail closed.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('enabled state rejected', $e->getMessage());
            } finally {
                DB::unprepared('ALTER TABLE ONLY public."club_accounting_source_coverage_decisions" ENABLE TRIGGER "club_accounting_source_coverage_decisions_immutable"');
            }
            $helper->assertRestored();
            $context->assertUsable();
        });
        $helper->assertRestored();
        $this->assertSame(0, DB::transactionLevel());
    }

    private function fixture(): array
    {
        $club = Club::create(['name' => 'Disposable corruption', 'code' => 'CORRUPT', 'is_active' => true]);
        config(['instance.organisation_code' => $club->code]);
        $actor = User::factory()->create();
        $row = fn () => DB::table('club_accounting_source_coverage_decisions')->insertGetId(['public_id' => (string) Str::uuid(), 'club_id' => $club->id, 'source_type' => 'member_charge', 'source_identifier' => (string) Str::uuid(), 'accounting_date' => '2027-07-05', 'integration_required' => false, 'integration_activation_id' => null, 'terminal_activation_event_id' => null, 'decision_reason' => 'not_activated', 'decided_by_user_id' => $actor->id, 'decided_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        return [$club, $row(), $row()];
    }

    private function deletion(int $id): array
    {
        return ['table' => 'club_accounting_source_coverage_decisions', 'id' => $id, 'delete' => true];
    }
}
