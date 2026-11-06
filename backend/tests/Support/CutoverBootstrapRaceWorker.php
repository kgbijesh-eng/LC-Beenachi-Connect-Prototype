<?php

declare(strict_types=1);

use App\Models\Club;
use App\Models\ClubAccountingCutoverBootstrapOperation;
use App\Models\User;
use App\Services\Access\OrganisationAccessRejected;
use App\Services\Finance\ClubCutoverPackageBootstrapService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$directory = $argv[1];
$request = json_decode(file_get_contents($directory.'/request.json'), true, flags: JSON_THROW_ON_ERROR);
$locks = [];
try {
    if (env('RUN_P02B2_CUTOVER_POSTGRES') !== '1' || DB::getDriverName() !== 'pgsql'
        || DB::connection()->getDatabaseName() !== 'lcb_p02b2_cutover_test'
        || config('instance.organisation_code') !== 'BOOT') {
        throw new RuntimeException('Worker identity is not allowlisted.');
    }
    $safe = DB::selectOne("SELECT current_user='lc_beenachi_app' AND current_user=pg_get_userbyid(d.datdba) AND NOT r.rolsuper AND NOT r.rolcreatedb AND NOT r.rolcreaterole AND NOT r.rolreplication AND NOT r.rolbypassrls AND NOT EXISTS (SELECT 1 FROM pg_auth_members WHERE member=r.oid) AS safe FROM pg_database d JOIN pg_roles r ON r.rolname=current_user WHERE d.datname=current_database()");
    if (! in_array($safe->safe, [true, 1, '1', 't'], true)) { throw new RuntimeException('Unsafe worker role.'); }
    DB::select("SELECT set_config('application_name', ?, false)", ['p02b2-race-'.$request['mode']]);
    DB::listen(function (QueryExecuted $event) use (&$locks, $directory, $request) {
        if (str_contains(strtolower($event->sql), 'for update')) {
            $locks[] = $event->sql;
            if (count($locks) === 1 && $request['hold']) {
                file_put_contents($directory.'/locked', '1');
                $deadline = microtime(true) + 20;
                while (! is_file($directory.'/release')) {
                    if (microtime(true) > $deadline) { throw new RuntimeException('Guard lock barrier timeout.'); }
                    usleep(5_000);
                }
            }
        }
    });
    file_put_contents($directory.'/started', '1');
    if ($request['mode'] === 'suspend') {
        // Test-only competing writer; no operator command or application bypass.
        DB::transaction(function () use ($request) {
            $club = Club::whereKey($request['club_id'])->where('code', 'BOOT')->lockForUpdate()->firstOrFail();
            $club->update(['is_active' => false]);
        });
        $result = ['outcome' => 'suspended'];
    } else {
        $operation = $app->make(ClubCutoverPackageBootstrapService::class)->approve(ClubAccountingCutoverBootstrapOperation::findOrFail($request['operation_id']), User::findOrFail($request['actor_id']), 'RACE-S');
        $result = ['outcome' => $operation->status, 'package_id' => $operation->cutover_package_id];
    }
} catch (Throwable $exception) {
    $result = ['outcome' => 'rejected', 'exception' => $exception::class, 'reason' => $exception instanceof OrganisationAccessRejected ? $exception->reason : $exception->getMessage()];
}
$result['locks'] = $locks;
file_put_contents($directory.'/result.json', json_encode($result, JSON_THROW_ON_ERROR));
