<?php

declare(strict_types=1);

use App\Models\Club;
use App\Models\User;
use App\Services\Finance\ClubCutoverPackageBootstrapService;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
[$script, $payloadPath, $barrier, $name] = $argv;
$payload = json_decode((string) file_get_contents($payloadPath), true, flags: JSON_THROW_ON_ERROR);
file_put_contents("$barrier/$name.ready", '1');
while (! is_file("$barrier/release")) {
    usleep(5_000);
}
try {
    $service = $app->make(ClubCutoverPackageBootstrapService::class);
    $operation = $service->request(Club::findOrFail($payload['club_id']), User::findOrFail($payload['preparer_id']), 1, $payload['reference'], User::findOrFail($payload['secretary_id']));
    $service->approve($operation, User::findOrFail($payload['president_id']), $payload['reference'].'-P');
    $operation = $service->approve($operation, User::findOrFail($payload['secretary_id']), $payload['reference'].'-S');
    $result = ['outcome' => 'applied', 'package_id' => $operation->cutover_package_id];
} catch (Throwable $exception) {
    $result = ['outcome' => 'rejected', 'exception' => $exception::class];
}
file_put_contents("$barrier/$name.result.json", json_encode($result, JSON_THROW_ON_ERROR));
