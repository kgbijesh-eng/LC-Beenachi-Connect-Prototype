<?php

declare(strict_types=1);

use App\Models\Club;
use App\Models\ClubAccountingIntegrationActivation;
use App\Models\ClubAccountingSourceMapping;
use App\Models\ClubCharge;
use App\Models\LedgerAccount;
use App\Models\Member;
use App\Models\User;
use App\Services\Finance\AccountingIntegrationConfigurationService;
use App\Services\Finance\Exceptions\AccountingIntegrationException;
use App\Services\FinanceCollections\FinanceWriteService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
Tests\Support\FinancialWorkerSafety::assert(['lcb_3iizh_concurrency_test']);
[$script, $requestPath, $barrier, $name] = $argv;
$request = json_decode((string) file_get_contents($requestPath), true, flags: JSON_THROW_ON_ERROR);
CarbonImmutable::setTestNow('2027-07-05 10:00:00');
file_put_contents($barrier.DIRECTORY_SEPARATOR.$name.'.ready', (string) getmypid());
while (! is_file($barrier.DIRECTORY_SEPARATOR.$name.'.release')) {
    usleep(5_000);
}
$started = hrtime(true);
file_put_contents($barrier.DIRECTORY_SEPARATOR.$name.'.attempting', (string) $started);
try {
    $service = $app->make(FinanceWriteService::class);
    $actor = User::findOrFail($request['actor_id']);
    $club = Club::findOrFail($request['club_id']);
    if ($request['action'] === 'charge') {
        $result = $service->issue($actor, $club, ClubCharge::findOrFail($request['charge_id']), $request['version']);
        $id = $result->id;
    } elseif ($request['action'] === 'cancel_charge') {
        $result = $service->cancel($actor, $club, ClubCharge::findOrFail($request['charge_id']), $request['version'], $request['reason']);
        $id = $result->id;
    } elseif ($request['action'] === 'payment') {
        $result = $service->recordPayment($actor, $club, Member::findOrFail($request['member_id']), $request['payload']);
        $id = $result['payment']->id;
    } else {
        $configuration = $app->make(AccountingIntegrationConfigurationService::class);
        if ($request['action'] === 'activate') {
            $id = $configuration->activate($club->id, $request['source_type'], '2027-07-01', 'MAP-V1', $request['reference'], $actor)->id;
        } elseif ($request['action'] === 'map') {
            $id = $configuration->map($club->id, $request['source_type'], $request['source_key'], LedgerAccount::findOrFail($request['account_id']), 'MAP-V1', '2027-07-01', $request['reference'], $actor)->id;
        } elseif ($request['action'] === 'revoke_mapping') {
            $id = $configuration->revokeMapping(ClubAccountingSourceMapping::findOrFail($request['mapping_id']), $actor, $request['reference'], 'Concurrent configuration change')->id;
        } elseif ($request['action'] === 'revoke_activation') {
            $id = $configuration->revokeActivation(ClubAccountingIntegrationActivation::findOrFail($request['activation_id']), $actor, $request['reference'], 'Concurrent configuration change')->id;
        } else {
            throw new RuntimeException('Unknown concurrency action.');
        }
    }
    $outcome = ['outcome' => 'resolved', 'source_id' => $id];
} catch (ConflictHttpException $exception) {
    $outcome = ['outcome' => 'conflict', 'message' => $exception->getMessage()];
} catch (AccountingIntegrationException $exception) {
    $outcome = ['outcome' => 'conflict', 'message' => $exception->getMessage()];
} catch (Throwable $exception) {
    $outcome = ['outcome' => 'error', 'class' => $exception::class, 'message' => $exception->getMessage()];
}
$outcome += ['started_at_ns' => $started, 'finished_at_ns' => hrtime(true), 'pid' => getmypid()];
file_put_contents($barrier.DIRECTORY_SEPARATOR.$name.'.result.json', json_encode($outcome, JSON_THROW_ON_ERROR));
exit($outcome['outcome'] === 'error' ? 1 : 0);
