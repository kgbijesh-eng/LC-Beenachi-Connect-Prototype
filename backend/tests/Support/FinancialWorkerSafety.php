<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class FinancialWorkerSafety
{
    public static function assert(array $allowedDatabases): void
    {
        $c = DB::connection();
        $database = $c->getDatabaseName();
        if ($c->getDriverName() !== 'pgsql' || ! in_array($database, $allowedDatabases, true) || $c->getConfig('username') !== 'lc_beenachi_app'
            || $c->getConfig('host') !== '127.0.0.1' || ! getenv('B3_WORKER_STARTED') || ! getenv('B3_WORKER_DATABASE_OID')
            || (string) $c->getConfig('port') !== getenv('B3_WORKER_PORT')) {
            throw new RuntimeException('Unsafe financial worker configuration.');
        }
        $inventory = in_array($database, ['lcb_3iizh_concurrency_test', 'lcb_3iizv_expense_concurrency_test', 'lcb_3iizw_expense_reversal_concurrency_test'], true)
            ? ['lcb_3iizh_concurrency_test', 'lcb_3iizv_expense_concurrency_test', 'lcb_3iizw_expense_reversal_concurrency_test'] : [$database];
        $inventory = [...$inventory, 'postgres', 'template0', 'template1'];
        sort($inventory);
        $r = $c->selectOne("SELECT current_database() AS db,current_user AS role,pg_get_userbyid(d.datdba) AS owner,host(inet_server_addr()) AS host,inet_server_port()::text AS port,extract(epoch FROM pg_postmaster_start_time())::text AS started,d.oid::text AS oid,NOT r.rolsuper AND NOT r.rolcreatedb AND NOT r.rolcreaterole AND NOT r.rolreplication AND NOT r.rolbypassrls AND NOT r.rolinherit AND NOT EXISTS(SELECT 1 FROM pg_auth_members WHERE member=r.oid) AS safe,(SELECT string_agg(datname,',' ORDER BY datname) FROM pg_database) AS inventory FROM pg_database d JOIN pg_roles r ON r.rolname=current_user WHERE d.datname=current_database()", [], false);
        if ($r->db !== $database || $r->role !== 'lc_beenachi_app' || $r->owner !== 'lc_beenachi_app' || $r->host !== '127.0.0.1'
            || $r->port !== getenv('B3_WORKER_PORT') || $r->started !== getenv('B3_WORKER_STARTED') || $r->oid !== getenv('B3_WORKER_DATABASE_OID')
            || $r->inventory !== implode(',', $inventory) || ! in_array($r->safe, [true, 1, '1', 't'], true)) {
            throw new RuntimeException('Unsafe financial worker database identity, inventory or role.');
        }
    }
}
