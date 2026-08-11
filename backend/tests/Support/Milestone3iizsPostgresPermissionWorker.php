<?php

declare(strict_types=1);
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
[, $requestPath,$barrier,$worker] = $argv;
$row = json_decode(file_get_contents($requestPath), true, flags: JSON_THROW_ON_ERROR);
$ready = "$barrier/$worker.ready";
$release = "$barrier/$worker.release";
$attempting = "$barrier/$worker.attempting";
$result = "$barrier/$worker.result.json";
file_put_contents($ready, '1');
$deadline = microtime(true) + 30;
while (! is_file($release)) {
    if (microtime(true) >= $deadline) {
        exit(2);
    }usleep(5000);
} $started = hrtime(true);
file_put_contents($attempting, (string) $started);
try {
    $id = DB::transaction(fn () => DB::table('club_accounting_cutover_permission_assignments')->insertGetId($row));
    $out = ['outcome' => 'inserted', 'id' => $id];
} catch (QueryException $e) {
    $out = ['outcome' => 'rejected', 'sql_state' => $e->errorInfo[0] ?? null];
} catch (Throwable $e) {
    $out = ['outcome' => 'error', 'message' => $e->getMessage()];
}
$out['started_at_ns'] = $started;
$out['finished_at_ns'] = hrtime(true);
file_put_contents($result, json_encode($out, JSON_THROW_ON_ERROR));
exit($out['outcome'] === 'error' ? 1 : 0);
