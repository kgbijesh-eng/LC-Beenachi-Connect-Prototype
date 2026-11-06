<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Genuine roots for guarded bootstrap; safety must precede fixture writes. */
abstract class CutoverPostgresTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (env('RUN_P02B2_CUTOVER_POSTGRES') !== '1') {
            $this->markTestSkipped('Requires the isolated P0.2B2 cutover PostgreSQL runner.');
        }
        $this->assertSame('pgsql', DB::getDriverName());
        $this->assertSame('lcb_p02b2_cutover_test', DB::connection()->getDatabaseName());
        $this->assertSame('lc_beenachi_app', DB::connection()->getConfig('username'));
        $this->assertSame(0, DB::transactionLevel());
        $safe = DB::selectOne("SELECT current_database() = 'lcb_p02b2_cutover_test' AND current_user = 'lc_beenachi_app' AND current_user = pg_get_userbyid(d.datdba) AND NOT r.rolsuper AND NOT r.rolcreatedb AND NOT r.rolcreaterole AND NOT r.rolreplication AND NOT r.rolbypassrls AND NOT EXISTS (SELECT 1 FROM pg_auth_members WHERE member=r.oid) AND NOT EXISTS (SELECT 1 FROM pg_database WHERE datname NOT IN ('lcb_p02b2_cutover_test','postgres','template0','template1')) AS safe FROM pg_database d JOIN pg_roles r ON r.rolname=current_user WHERE d.datname=current_database()");
        $this->assertTrue(in_array($safe->safe, [true, 1, '1', 't'], true));
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        config(['instance.organisation_code' => null]);
    }
}
