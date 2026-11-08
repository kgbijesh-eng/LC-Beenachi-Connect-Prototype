<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Real roots only; no RefreshDatabase transaction and no eligibility bypass. */
abstract class FinancialPostgresTestCase extends TestCase
{
    protected function setUp(): void
    {
        if (getenv('B3_FIXTURE_POISON_FILE') && is_file(getenv('B3_FIXTURE_POISON_FILE'))) {
            throw new \RuntimeException('Disposable fixture cluster discarded; no further database operations.');
        }
        parent::setUp();
        $name = DB::connection()->getDatabaseName();
        $b3 = env('RUN_P02B3_FINANCE_POSTGRES') === '1' && $name === 'lcb_p02b3_finance_test';
        $historical = env('RUN_3IJAE_POSTGRES_CONCURRENCY') === '1' && $name === 'lcb_3ijae_finance_p02b2ab';
        if (! $b3 && ! $historical) {
            $this->markTestSkipped('Requires the isolated B3 or canonical finance PostgreSQL runner.');
        }
        $this->assertSame('pgsql', DB::getDriverName());
        $this->assertSame('lc_beenachi_app', DB::connection()->getConfig('username'));
        $this->assertSame(0, DB::transactionLevel());
        $allowed = $b3
            ? "'lcb_p02b3_finance_test','postgres','template0','template1'"
            : "'lcb_3ijae_finance_p02b2ab','lcb_3iizt_statement_snapshot_concurrency_test','postgres','template0','template1'";
        $safe = DB::selectOne("SELECT current_user='lc_beenachi_app' AND current_user=pg_get_userbyid(d.datdba) AND NOT r.rolsuper AND NOT r.rolcreatedb AND NOT r.rolcreaterole AND NOT r.rolreplication AND NOT r.rolbypassrls AND NOT r.rolinherit AND NOT EXISTS(SELECT 1 FROM pg_auth_members WHERE member=r.oid) AND NOT EXISTS(SELECT 1 FROM pg_database WHERE datname NOT IN ($allowed)) AS safe FROM pg_database d JOIN pg_roles r ON r.rolname=current_user WHERE d.datname=current_database()");
        $this->assertTrue(in_array($safe->safe, [true, 1, '1', 't'], true));
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        config(['instance.organisation_code' => null]);
    }
    protected function tearDown(): void
    {
        // Explicitly release genuine-root sessions even when PHPUnit retains listener closures.
        try { if ($this->app !== null) DB::disconnect(); }
        finally { parent::tearDown(); }
    }

}
