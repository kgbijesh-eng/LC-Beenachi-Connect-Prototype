<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\FinancialWorkerSafety;

require dirname(__DIR__, 2).'/vendor/autoload.php';
if (is_file(dirname(__DIR__, 2).'/bootstrap/cache/config.php')) {
    throw new RuntimeException('Cached config rejected.');
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
[$fixture, $ready] = array_slice($argv, 1);
FinancialWorkerSafety::assert(['lcb_p02b3_finance_test']);
DB::statement("SET lock_timeout = '15s'");
file_put_contents($ready, (string) DB::selectOne('SELECT pg_backend_pid() AS pid')->pid);
try {
    DB::table('club_officer_terms')->insert(json_decode(file_get_contents($fixture), true, flags: JSON_THROW_ON_ERROR));
    echo 'inserted';
} catch (QueryException $e) {
    echo $e->getCode();
}
