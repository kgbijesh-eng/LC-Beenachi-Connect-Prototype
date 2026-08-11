<?php

declare(strict_types=1);

use App\Services\Finance\ClubAccountStatementProjectionService;
use App\Services\Finance\ClubAccountStatementQuery;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $requestPath, $barrier] = $argv;
$request = json_decode((string) file_get_contents($requestPath), true, flags: JSON_THROW_ON_ERROR);
$paused = false;
DB::listen(function (QueryExecuted $query) use ($barrier, &$paused): void {
    if ($paused || ! str_starts_with(strtolower(ltrim($query->sql)), 'select')) {
        return;
    }
    $paused = true;
    file_put_contents($barrier.DIRECTORY_SEPARATOR.'snapshot.ready', '1');
    $deadline = microtime(true) + 30;
    while (! is_file($barrier.DIRECTORY_SEPARATOR.'snapshot.release')) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('Statement snapshot barrier timed out.');
        }
        usleep(5_000);
    }
});

try {
    $result = $app->make(ClubAccountStatementProjectionService::class)->project(
        new ClubAccountStatementQuery(...$request),
    );
    file_put_contents($barrier.DIRECTORY_SEPARATOR.'result.json', json_encode($result, JSON_THROW_ON_ERROR));
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage());
    exit(1);
}
