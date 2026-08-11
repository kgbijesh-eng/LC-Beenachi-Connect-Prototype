<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $requestPath, $barrierDirectory, $worker] = $argv;
$decision = json_decode((string) file_get_contents($requestPath), true, flags: JSON_THROW_ON_ERROR);
$readyPath = $barrierDirectory.DIRECTORY_SEPARATOR.$worker.'.ready';
$releasePath = $barrierDirectory.DIRECTORY_SEPARATOR.$worker.'.release';
$attemptingPath = $barrierDirectory.DIRECTORY_SEPARATOR.$worker.'.attempting';
$resultPath = $barrierDirectory.DIRECTORY_SEPARATOR.$worker.'.result.json';

DB::connection()->getPdo()->exec("SET application_name = '3iizg-decision-{$worker}'");
file_put_contents($readyPath, json_encode(['worker' => $worker, 'pid' => getmypid(), 'ready_at_ns' => hrtime(true)], JSON_THROW_ON_ERROR));
$deadline = microtime(true) + 30;
while (! is_file($releasePath)) {
    if (microtime(true) >= $deadline) {
        file_put_contents($resultPath, json_encode(['worker' => $worker, 'outcome' => 'barrier_timeout'], JSON_THROW_ON_ERROR));
        exit(2);
    }
    usleep(5_000);
}

$started = hrtime(true);
file_put_contents($attemptingPath, (string) $started);
try {
    $id = DB::transaction(fn (): int => (int) DB::table('club_accounting_cutover_decisions')->insertGetId($decision));
    $result = ['worker' => $worker, 'outcome' => 'inserted', 'decision_id' => $id];
} catch (QueryException $exception) {
    $result = ['worker' => $worker, 'outcome' => 'rejected', 'sql_state' => $exception->errorInfo[0] ?? null];
} catch (Throwable $exception) {
    $result = ['worker' => $worker, 'outcome' => 'error', 'exception' => $exception::class, 'message' => $exception->getMessage()];
}
$result['started_at_ns'] = $started;
$result['finished_at_ns'] = hrtime(true);
file_put_contents($resultPath, json_encode($result, JSON_THROW_ON_ERROR));
echo json_encode($result, JSON_THROW_ON_ERROR), PHP_EOL;
exit($result['outcome'] === 'error' ? 1 : 0);
