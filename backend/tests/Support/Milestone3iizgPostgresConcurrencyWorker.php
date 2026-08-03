<?php

declare(strict_types=1);

use App\Services\Finance\ClubOpeningJournalActivationService;
use App\Services\Finance\CutoverExecutionRequest;
use App\Services\Finance\Exceptions\CutoverExecutionConflictException;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $requestPath, $barrierDirectory, $worker] = $argv;
$requestData = json_decode((string) file_get_contents($requestPath), true, flags: JSON_THROW_ON_ERROR);
$request = new CutoverExecutionRequest(...$requestData);
$readyPath = $barrierDirectory.DIRECTORY_SEPARATOR.$worker.'.ready';
$releasePath = $barrierDirectory.DIRECTORY_SEPARATOR.$worker.'.release';
$resultPath = $barrierDirectory.DIRECTORY_SEPARATOR.$worker.'.result.json';

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
try {
    $success = $app->make(ClubOpeningJournalActivationService::class)->execute($request);
    $result = ['worker' => $worker, 'outcome' => 'resolved', 'success_id' => $success->id, 'journal_entry_id' => $success->journal_entry_id];
} catch (CutoverExecutionConflictException $exception) {
    $result = ['worker' => $worker, 'outcome' => 'conflict', 'exception' => $exception::class];
} catch (Throwable $exception) {
    $result = ['worker' => $worker, 'outcome' => 'error', 'exception' => $exception::class, 'message' => $exception->getMessage()];
}
$result['started_at_ns'] = $started;
$result['finished_at_ns'] = hrtime(true);
file_put_contents($resultPath, json_encode($result, JSON_THROW_ON_ERROR));
echo json_encode($result, JSON_THROW_ON_ERROR), PHP_EOL;
exit($result['outcome'] === 'error' ? 1 : 0);
