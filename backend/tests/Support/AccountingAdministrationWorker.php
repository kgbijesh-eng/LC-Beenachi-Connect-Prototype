<?php

declare(strict_types=1);

use App\Models\Club;
use App\Services\Access\OrganisationAccessRejected;
use App\Services\Finance\Exceptions\AccountingIntegrationException;
use App\Services\Finance\Exceptions\JournalPostingException;
use App\Services\Finance\Exceptions\ProvisioningException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingAdministrationFixture;
use Tests\Support\FinancialOperationFixture;
use Tests\Support\FinancialWorkerSafety;
use Tests\Support\IntegrationConfigurationFixture;

require dirname(__DIR__, 2).'/vendor/autoload.php';
if (is_file(dirname(__DIR__, 2).'/bootstrap/cache/config.php')) {
    throw new RuntimeException('Cached config rejected.');
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
[$requestPath, $dir, $name] = array_slice($argv, 1);
$p = json_decode(file_get_contents($requestPath), true, flags: JSON_THROW_ON_ERROR);
$await = function (string $file): void {
    $end = microtime(true) + 20;
    while (! is_file($file)) {
        if (microtime(true) >= $end) {
            throw new RuntimeException('Financial race barrier timed out.');
        } usleep(5000);
    }
};
try {
    FinancialWorkerSafety::assert(['lcb_p02b3_finance_test']);
    file_put_contents($dir.'/'.$name.'.ready', json_encode(['pid' => DB::selectOne('SELECT pg_backend_pid() AS pid')->pid], JSON_THROW_ON_ERROR));
    $await($dir.'/'.$name.'.start');
    $held = false;
    DB::listen(function ($query) use (&$held, $p, $dir, $name, $await): void {
        if ($p['hold'] && ! $held && str_contains($query->sql, '"clubs"') && str_contains($query->sql, 'for update')) {
            if (DB::transactionLevel() !== 1) {
                throw new RuntimeException('Club lock was not acquired in the outer root.');
            }
            $held = true;
            file_put_contents($dir.'/'.$name.'.locked', '1');
            $await($dir.'/'.$name.'.finish');
        }
    });
    if ($p['mode'] === 'suspend') {
        // Test control operation only; never an operator command or production service.
        DB::transaction(function () use ($p, $dir, $name, $await): void {
            if (Club::whereKey($p['club'])->update(['is_active' => false]) !== 1) {
                throw new RuntimeException('Synthetic suspension target missing.');
            }
            if ($p['hold']) {
                file_put_contents($dir.'/'.$name.'.locked', '1');
                $await($dir.'/'.$name.'.finish');
            }
        }, 1);
    } elseif ($p['mode'] === 'operation') {
        AccountingAdministrationFixture::perform($p);
    } elseif ($p['mode'] === 'financial') {
        FinancialOperationFixture::perform($p);
    } elseif ($p['mode'] === 'configuration') {
        IntegrationConfigurationFixture::perform($p);
    } else {
        throw new RuntimeException('Unknown financial race mode.');
    }
    $result = ['status' => 'success', 'depth' => DB::transactionLevel()];
} catch (OrganisationAccessRejected $e) {
    $result = ['status' => 'rejected', 'reason' => $e->reason, 'depth' => DB::transactionLevel()];
} catch (AccountingIntegrationException|\App\Services\Finance\Exceptions\ClubOrdinaryIncomeReceiptException|ProvisioningException|\App\Services\Finance\Exceptions\AccountingPeriodCloseException|JournalPostingException|App\Services\Finance\Exceptions\JournalReversalException|App\Services\Finance\Exceptions\ClubExpenseException|App\Services\Finance\Exceptions\ClubOrdinaryIncomeReceiptCorrectionException|Illuminate\Validation\ValidationException|AuthorizationException $e) {
    $result = ['status' => 'domain_rejected', 'class' => $e::class, 'message' => $e->getMessage(), 'depth' => DB::transactionLevel()];
} catch (Throwable $e) {
    $result = ['status' => 'error', 'class' => $e::class, 'message' => $e->getMessage(), 'depth' => DB::transactionLevel()];
}
file_put_contents($dir.'/'.$name.'.result.json', json_encode($result, JSON_THROW_ON_ERROR));
exit($result['status'] === 'error' ? 1 : 0);
