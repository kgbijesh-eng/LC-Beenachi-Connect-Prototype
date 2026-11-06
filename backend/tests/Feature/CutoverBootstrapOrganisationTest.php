<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubAccountingCutoverBootstrapOperation;
use App\Models\ClubAccountingCutoverPackage;
use App\Models\ClubMembership;
use App\Models\Role;
use App\Models\User;
use App\Services\Access\OrganisationAccessRejected;
use App\Services\Finance\ClubCutoverPackageBootstrapService;
use Database\Seeders\DefaultRolesAndPermissionsSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\Support\CutoverPostgresTestCase;

class CutoverBootstrapOrganisationTest extends CutoverPostgresTestCase
{
    private Club $club;
    private User $president;
    private User $secretary;
    private User $preparer;
    private ClubCutoverPackageBootstrapService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DefaultRolesAndPermissionsSeeder::class);
        $this->club = Club::create(['name' => 'Synthetic assigned', 'code' => 'BOOT', 'is_active' => true]);
        config(['instance.organisation_code' => 'BOOT']);
        foreach (['president' => 'president-admin', 'secretary' => 'secretary', 'preparer' => 'treasurer'] as $key => $slug) {
            $this->$key = User::factory()->create();
            ClubMembership::create(['club_id' => $this->club->id, 'user_id' => $this->$key->id, 'role_id' => Role::where('slug', $slug)->firstOrFail()->id, 'status' => 'active']);
        }
        $this->service = app(ClubCutoverPackageBootstrapService::class);
    }

    private function request(): ClubAccountingCutoverBootstrapOperation
    {
        return $this->service->request($this->club, $this->preparer, 1, 'BOOTSTRAP-TEST', $this->secretary);
    }

    public function test_active_request_and_approval_replay_lock_club_before_operation(): void
    {
        $locks = [];
        DB::listen(function (QueryExecuted $event) use (&$locks) {
            if (str_contains(strtolower($event->sql), 'for update')) {
                $locks[] = ['sql' => $event->sql, 'depth' => DB::transactionLevel()];
            }
        });
        $operation = $this->request();
        $this->assertStringContainsString('"clubs"', $locks[0]['sql']);
        $this->assertSame($operation->id, $this->request()->id);
        $locks = [];
        $this->service->approve($operation, $this->president, 'BOOTSTRAP-P');
        $this->assertStringContainsString('"clubs"', $locks[0]['sql']);
        $this->assertStringContainsString('"club_accounting_cutover_bootstrap_operations"', $locks[1]['sql']);
        $this->assertSame([1], array_values(array_unique(array_column($locks, 'depth'))));
        $applied = $this->service->approve($operation, $this->secretary, 'BOOTSTRAP-S');
        $locks = [];
        $this->assertSame($applied->id, $this->service->approve($applied, $this->secretary, 'REPLAY-ONLY')->id);
        $this->assertStringContainsString('"clubs"', $locks[0]['sql']);
        $this->assertDatabaseCount('club_accounting_cutover_packages', 1);
        $this->assertDatabaseCount('club_accounting_cutover_permission_assignments', 1);
        $this->assertDatabaseCount('club_accounting_cutover_bootstrap_approvals', 2);
        $this->assertDatabaseCount('club_journal_entries', 0);
        fwrite(STDOUT, "\nBOOTSTRAP_LOCK_EVIDENCE ".json_encode($locks, JSON_THROW_ON_ERROR)."\n");
    }

    public function test_suspended_stale_models_cannot_create_request_or_replay(): void
    {
        $operation = $this->request();
        Club::whereKey($this->club->id)->update(['is_active' => false]);
        $this->denied(fn () => $this->request(), 'ORGANISATION_SUSPENDED');
        $this->denied(fn () => $this->service->request($this->club, $this->preparer, 2, 'ANOTHER-REQUEST', $this->secretary), 'ORGANISATION_SUSPENDED');
        $this->denied(fn () => $this->service->approve($operation, $this->president, 'DENIED-P'), 'ORGANISATION_SUSPENDED');
        $this->assertDatabaseCount('club_accounting_cutover_bootstrap_operations', 1);
        $this->assertDatabaseCount('club_accounting_cutover_bootstrap_approvals', 0);
        $this->assertDatabaseCount('club_accounting_cutover_packages', 0);
    }

    public function test_applied_replay_is_denied_after_suspension_and_reactivation_does_not_duplicate(): void
    {
        $operation = $this->request();
        $this->service->approve($operation, $this->president, 'APPLIED-P');
        $applied = $this->service->approve($operation, $this->secretary, 'APPLIED-S');
        Club::whereKey($this->club->id)->update(['is_active' => false]);
        $this->denied(fn () => $this->service->approve($applied, $this->secretary, 'DENIED-REPLAY'), 'ORGANISATION_SUSPENDED');
        Club::whereKey($this->club->id)->update(['is_active' => true]);
        $this->assertSame($applied->id, $this->service->approve($applied, $this->secretary, 'ACTIVE-REPLAY')->id);
        $this->assertDatabaseCount('club_accounting_cutover_packages', 1);
        $this->assertDatabaseCount('club_accounting_cutover_permission_assignments', 1);
    }

    public function test_foreign_request_and_approval_stay_404_before_activity_disclosure(): void
    {
        $operation = $this->request();
        $other = Club::create(['name' => 'Foreign', 'code' => 'FOREIGN', 'is_active' => false]);
        $this->denied(fn () => $this->service->request($other, $this->preparer, 1, 'FOREIGN-REQUEST', $this->secretary), 'ORGANISATION_FOREIGN');
        $operation->club_id = $other->id;
        $this->denied(fn () => $this->service->approve($operation, $this->president, 'FOREIGN-P'), 'ORGANISATION_FOREIGN');
        $this->assertDatabaseCount('club_accounting_cutover_bootstrap_approvals', 0);
    }

    #[DataProvider('invalidConfiguration')]
    public function test_invalid_configuration_blocks_request_and_approval(mixed $code): void
    {
        $operation = $this->request();
        config(['instance.organisation_code' => $code]);
        $this->denied(fn () => $this->request(), 'ORGANISATION_CONFIGURATION_UNAVAILABLE');
        $this->denied(fn () => $this->service->approve($operation, $this->president, 'CONFIG-P'), 'ORGANISATION_CONFIGURATION_UNAVAILABLE');
        $this->assertDatabaseCount('club_accounting_cutover_bootstrap_approvals', 0);
    }

    public static function invalidConfiguration(): array
    {
        return [[null], [''], ['UNKNOWN'], [' BOOT'], [[]]];
    }

    public function test_mutated_operation_scope_cannot_lock_a_foreign_record(): void
    {
        $operation = $this->request();
        $foreign = Club::create(['name' => 'Other assigned synthetic', 'code' => 'OTHER', 'is_active' => true]);
        config(['instance.organisation_code' => 'OTHER']);
        $operation->club_id = $foreign->id;
        try {
            $this->service->approve($operation, $this->president, 'FORGED-SCOPE');
            $this->fail('Persisted scope must be revalidated.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            $this->assertDatabaseCount('club_accounting_cutover_bootstrap_approvals', 0);
        }
    }

    public function test_unsupported_ambient_transaction_is_preserved_without_business_writes(): void
    {
        $operation = $this->request();
        DB::beginTransaction();
        try {
            $this->denied(fn () => $this->request(), 'ORGANISATION_TRANSACTION_UNSUPPORTED', 1);
            $this->denied(fn () => $this->service->approve($operation, $this->president, 'AMBIENT-P'), 'ORGANISATION_TRANSACTION_UNSUPPORTED', 1);
            $this->assertDatabaseCount('club_accounting_cutover_bootstrap_approvals', 0);
        } finally {
            DB::rollBack();
        }
    }

    public function test_serialization_failure_is_not_retried_and_apply_rolls_back_atomically(): void
    {
        $operation = $this->request();
        $this->service->approve($operation, $this->president, 'ATOMIC-P');
        $calls = 0;
        ClubAccountingCutoverPackage::created(function () use (&$calls) {
            $calls++;
            DB::unprepared("DO \$\$ BEGIN RAISE EXCEPTION 'Synthetic serialization failure' USING ERRCODE = '40001'; END \$\$");
        });
        try {
            $this->service->approve($operation, $this->secretary, 'ATOMIC-S');
            $this->fail('Failure must escape the one-attempt root.');
        } catch (\Illuminate\Database\QueryException $exception) {
            $this->assertSame('40001', (string) $exception->getCode());
        }
        $this->assertSame(1, $calls);
        $this->assertSame('pending', $operation->fresh()->status);
        $this->assertDatabaseCount('club_accounting_cutover_bootstrap_approvals', 1);
        $this->assertDatabaseCount('club_accounting_cutover_packages', 0);
        $this->assertDatabaseCount('club_accounting_cutover_permission_assignments', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_suspension_first_blocks_approval_before_operation_lock(): void
    {
        $operation = $this->request();
        $this->service->approve($operation, $this->president, 'RACE-P');
        $pdo = $this->otherSession();
        $pdo->beginTransaction();
        try {
            $pdo->exec('UPDATE clubs SET is_active=false WHERE id='.(int) $this->club->id);
            [$process, $directory] = $this->worker($operation, 'approve');
            $process->start();
            $this->awaitFile($directory.'/started');
            $this->awaitBlocked($pdo);
            $pdo->commit();
            $process->wait();
            $result = json_decode(file_get_contents($directory.'/result.json'), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('ORGANISATION_SUSPENDED', $result['reason']);
            $this->assertSame(1, count($result['locks']));
            $this->assertStringContainsString('"clubs"', $result['locks'][0]);
            $this->assertDatabaseCount('club_accounting_cutover_packages', 0);
            $this->assertDatabaseCount('club_accounting_cutover_bootstrap_approvals', 1);
        } finally {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            if (isset($process) && $process->isRunning()) { $process->stop(); }
        }
    }

    public function test_bootstrap_first_holds_club_until_atomic_apply_and_then_suspension_commits(): void
    {
        $operation = $this->request();
        $this->service->approve($operation, $this->president, 'RACE-P');
        [$bootstrap, $directory] = $this->worker($operation, 'approve', true);
        [$suspend, $suspendDirectory] = $this->worker($operation, 'suspend');
        try {
            $bootstrap->start();
            $this->awaitFile($directory.'/locked');
            $suspend->start();
            $this->awaitFile($suspendDirectory.'/started');
            $this->awaitBlocked($this->otherSession());
            $this->assertDatabaseCount('club_accounting_cutover_packages', 0);
            file_put_contents($directory.'/release', '1');
            $bootstrap->wait();
            $suspend->wait();
            $result = json_decode(file_get_contents($directory.'/result.json'), true, flags: JSON_THROW_ON_ERROR);
            $suspended = json_decode(file_get_contents($suspendDirectory.'/result.json'), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('applied', $result['outcome']);
            $this->assertSame('suspended', $suspended['outcome']);
            $this->assertStringContainsString('"clubs"', $result['locks'][0]);
            $this->assertStringContainsString('"club_accounting_cutover_bootstrap_operations"', $result['locks'][1]);
            $this->assertFalse($this->club->fresh()->is_active);
            $this->assertDatabaseCount('club_accounting_cutover_packages', 1);
            $this->assertDatabaseCount('club_accounting_cutover_permission_assignments', 1);
            $this->denied(fn () => $this->service->approve($operation->fresh(), $this->secretary, 'SUSPENDED-REPLAY'), 'ORGANISATION_SUSPENDED');
            fwrite(STDOUT, "\nBOOTSTRAP_RACE_EVIDENCE ".json_encode($result, JSON_THROW_ON_ERROR)."\n");
        } finally {
            file_put_contents($directory.'/release', '1');
            if ($bootstrap->isRunning()) { $bootstrap->stop(); }
            if ($suspend->isRunning()) { $suspend->stop(); }
        }
    }

    private function denied(callable $operation, string $reason, int $depth = 0): void
    {
        try { $operation(); $this->fail('Eligibility must reject.'); }
        catch (OrganisationAccessRejected $exception) { $this->assertSame($reason, $exception->reason); }
        $this->assertSame($depth, DB::transactionLevel());
    }

    private function otherSession(): PDO
    {
        $c = DB::connection()->getConfig();
        return new PDO("pgsql:host={$c['host']};port={$c['port']};dbname={$c['database']}", $c['username'], $c['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    private function worker(ClubAccountingCutoverBootstrapOperation $operation, string $mode, bool $hold = false): array
    {
        $directory = sys_get_temp_dir().'/p02b2-bootstrap-'.bin2hex(random_bytes(8));
        mkdir($directory, 0700, true);
        file_put_contents($directory.'/request.json', json_encode(['operation_id' => $operation->id, 'club_id' => $this->club->id, 'actor_id' => $this->secretary->id, 'mode' => $mode, 'hold' => $hold], JSON_THROW_ON_ERROR));
        return [new Process([PHP_BINARY, base_path('tests/Support/CutoverBootstrapRaceWorker.php'), $directory], base_path(), env: ['INSTANCE_ORGANISATION_CODE' => 'BOOT'], timeout: 40), $directory];
    }

    private function awaitFile(string $path): void
    {
        $deadline = microtime(true) + 15;
        while (! is_file($path)) {
            if (microtime(true) > $deadline) { throw new RuntimeException('Worker barrier timed out: '.$path); }
            usleep(5_000);
        }
    }

    private function awaitBlocked(PDO $pdo): void
    {
        $deadline = microtime(true) + 15;
        do {
            // The suspension-first observer is itself in a transaction: refresh
            // PostgreSQL's statistics snapshot before polling the worker's wait.
            $pdo->query('SELECT pg_stat_clear_snapshot()')->fetchColumn();
            if ((int) $pdo->query("SELECT count(*) FROM pg_stat_activity WHERE datname=current_database() AND application_name LIKE 'p02b2-race-%' AND wait_event_type='Lock' AND cardinality(pg_blocking_pids(pid))>0")->fetchColumn() > 0) {
                $this->assertTrue(true, 'Independent worker is blocked on a PostgreSQL lock.');
                return;
            }
            usleep(5_000);
        } while (microtime(true) < $deadline);
        throw new RuntimeException('Expected PostgreSQL lock wait was not observed.');
    }
}
