<?php

declare(strict_types=1);

use App\Models\ClubAccountingCutoverAuthorityAppointment;
use App\Models\ClubAccountingCutoverPermissionAssignment;
use App\Models\User;
use App\Services\Finance\ClubAccountingCutoverPackageService;
use App\Services\Finance\ClubCutoverAuthorityAdministrationService;
use App\Services\Finance\ClubOpeningJournalActivationService;
use App\Services\Finance\CutoverExecutionRequest;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
[$script, $payloadPath, $barrier, $name] = $argv;
$payload = json_decode((string) file_get_contents($payloadPath), true, flags: JSON_THROW_ON_ERROR);
$resultPath = "$barrier/$name.result.json";

try {
    DB::beginTransaction();
    DB::table('club_accounting_cutover_packages')->where('id', $payload['package_id'])->lockForUpdate()->first();
    file_put_contents("$barrier/$name.locked", '1');
    if ($payload['hold']) {
        $deadline = microtime(true) + 30;
        while (! is_file("$barrier/$name.release") && microtime(true) < $deadline) {
            usleep(5_000);
        }
    }
    if ($payload['mode'] === 'activate') {
        $record = $app->make(ClubOpeningJournalActivationService::class)->execute(new CutoverExecutionRequest(...$payload['request']));
        $result = ['outcome' => 'activated', 'id' => $record->id];
    } elseif ($payload['mode'] === 'author') {
        $record = $app->make(ClubAccountingCutoverPackageService::class)->replaceEvidenceReferences($payload['club_id'], $payload['package_id'], $payload['actor_id'], $payload['expected_lock_version'], $payload['evidence']);
        $result = ['outcome' => 'authored', 'id' => $record->id];
    } else {
        $actor = User::findOrFail($payload['actor_id']);
        $service = $app->make(ClubCutoverAuthorityAdministrationService::class);
        if ($payload['target_kind'] === 'appointment') {
            $event = $service->selfRevokeAppointment(ClubAccountingCutoverAuthorityAppointment::findOrFail($payload['target_id']), $actor, $payload['reference'], 'Concurrent holder revocation.');
        } else {
            $event = $service->selfRevokePermission(ClubAccountingCutoverPermissionAssignment::findOrFail($payload['target_id']), $actor, $payload['reference'], 'Concurrent holder revocation.');
        }
        $result = ['outcome' => 'revoked', 'id' => $event->id];
    }
    DB::commit();
} catch (Throwable $exception) {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
    $result = ['outcome' => 'rejected', 'exception' => $exception::class, 'message' => $exception->getMessage()];
}
file_put_contents($resultPath, json_encode($result, JSON_THROW_ON_ERROR));
exit(0);
