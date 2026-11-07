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
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
[$script, $payloadPath, $barrier, $name] = $argv;
$payload = json_decode((string) file_get_contents($payloadPath), true, flags: JSON_THROW_ON_ERROR);
$resultPath = "$barrier/$name.result.json";

try {
    DB::select("SELECT set_config('application_name', ?, false)", ['p02b2-mixed-'.$name]);
    // Observe each public operation's own first lock; never introduce an ambient root.
    $locks = [];
    $held = false;
    DB::listen(function (QueryExecuted $event) use (&$locks, &$held, $payload, $barrier, $name) {
        if (! str_contains(strtolower($event->sql), 'for update')) { return; }
        $locks[] = $event->sql;
        file_put_contents("$barrier/$name.locks.json", json_encode($locks, JSON_THROW_ON_ERROR));
        $financialPackage = str_contains($event->sql, 'club_accounting_cutover_packages');
        if ($held || ($payload['mode'] === 'activate' && ! $financialPackage)) { return; }
        $held = true;
        file_put_contents("$barrier/$name.locked", '1');
        if (! $payload['hold']) { return; }
        $deadline = microtime(true) + 30;
        while (! is_file("$barrier/$name.release") && microtime(true) < $deadline) {
            usleep(5_000);
        }
        if (! is_file("$barrier/$name.release")) { throw new RuntimeException('Owned lock barrier timed out.'); }
    });
    file_put_contents("$barrier/$name.started", '1');
    if ($payload['mode'] === 'activate') {
        $record = $app->make(ClubOpeningJournalActivationService::class)->execute(new CutoverExecutionRequest(...$payload['request']));
        $result = ['outcome' => 'activated', 'id' => $record->id];
    } elseif ($payload['mode'] === 'suspend') {
        DB::transaction(function () use ($payload): void {
            $club = \App\Models\Club::whereKey($payload['club_id'])->lockForUpdate()->firstOrFail();
            $club->update(['is_active' => false]);
        }, 1);
        $result = ['outcome' => 'suspended'];
    } elseif ($payload['mode'] === 'author') {
        $record = $app->make(ClubAccountingCutoverPackageService::class)->replaceEvidenceReferences($payload['club_id'], $payload['package_id'], $payload['actor_id'], $payload['expected_lock_version'], $payload['evidence']);
        $result = ['outcome' => 'authored', 'id' => $record->id];
    } else {
        $actor = User::findOrFail($payload['actor_id']);
        $service = $app->make(ClubCutoverAuthorityAdministrationService::class);
        if ($payload['target_kind'] === 'appointment') {
            $event = $service->selfRevokeAppointment(ClubAccountingCutoverAuthorityAppointment::findOrFail($payload['target_id']), $actor, $payload['reference'], 'Concurrent holder revocation.');
        } elseif ($payload['target_kind'] === 'decision') {
            $event = $app->make(\App\Services\Finance\ClubCutoverLifecycleService::class)->revoke(\App\Models\ClubAccountingCutoverDecision::findOrFail($payload['target_id']), $actor, $payload['reference'], 'Concurrent decision revocation.');
        } else {
            $event = $service->selfRevokePermission(ClubAccountingCutoverPermissionAssignment::findOrFail($payload['target_id']), $actor, $payload['reference'], 'Concurrent holder revocation.');
        }
        $result = ['outcome' => 'revoked', 'id' => $event->id];
    }
} catch (Throwable $exception) {
    $result = ['outcome' => 'rejected', 'exception' => $exception::class, 'message' => $exception->getMessage()];
    $result['cause_codes'] = [];
    for ($cause = $exception; $cause !== null; $cause = $cause->getPrevious()) {
        $result['cause_codes'][] = (string) $cause->getCode();
    }
}
$result['locks'] = $locks ?? [];
file_put_contents($resultPath, json_encode($result, JSON_THROW_ON_ERROR));
exit(0);
