<?php

declare(strict_types=1);

use App\Models\Club;
use App\Services\Access\OrganisationAccessRejected;
use App\Services\Finance\Exceptions\AccountingIntegrationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\Support\FinancialOperationFixture;
use Tests\Support\FinancialWorkerSafety;
use Tests\Support\IntegrationConfigurationFixture;
use Tests\Support\SourceCoverageFixture;

require dirname(__DIR__, 2).'/vendor/autoload.php';
if (is_file(dirname(__DIR__, 2).'/bootstrap/cache/config.php')) {
    throw new RuntimeException('Cached configuration rejected.');
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
[$requestPath, $dir, $name] = array_slice($argv, 1);
$p = json_decode(file_get_contents($requestPath), true, flags: JSON_THROW_ON_ERROR);
$await = function ($file): void {
    $end = microtime(true) + 20;
    while (! is_file($file)) {
        if (microtime(true) >= $end) {
            throw new RuntimeException('B4 worker barrier timed out.');
        } usleep(5000);
    }
};
try {
    FinancialWorkerSafety::assert(['lcb_p02b3_finance_test']);
    file_put_contents($dir.'/'.$name.'.ready', json_encode(['pid' => DB::selectOne('SELECT pg_backend_pid() AS pid')->pid], JSON_THROW_ON_ERROR));
    $await($dir.'/'.$name.'.start');
    $held = false;
    DB::listen(function ($q) use (&$held, $p, $dir, $name, $await): void {
        if ($p['hold'] && ! $held && str_contains($q->sql, '"clubs"') && str_contains($q->sql, 'for update')) {
            if (DB::transactionLevel() !== 1) {
                throw new RuntimeException('B4 club lock must belong to the root.');
            }
            $held = true;
            file_put_contents($dir.'/'.$name.'.locked', '1');
            $await($dir.'/'.$name.'.finish');
        }
    });
    if ($p['mode'] === 'suspend') {
        // Synthetic race control only, not an operator/suspension implementation.
        DB::transaction(function () use ($p, $dir, $name, $await): void {
            if (Club::whereKey($p['club'])->update(['is_active' => false]) !== 1) {
                throw new RuntimeException('Missing synthetic club.');
            }
            if ($p['hold']) {
                file_put_contents($dir.'/'.$name.'.locked', '1');
                $await($dir.'/'.$name.'.finish');
            }
        }, 1);
        $result = null;
    } elseif ($p['mode'] === 'coverage-source') {
        $result = SourceCoverageFixture::perform($p);
    } elseif ($p['mode'] === 'source') {
        $result = FinancialOperationFixture::perform($p);
    } else {
        $result = IntegrationConfigurationFixture::perform($p);
    }
    $outcome = ['status' => 'success', 'id' => is_object($result) ? $result->getKey() : null];
} catch (OrganisationAccessRejected $e) {
    $outcome = ['status' => 'rejected', 'reason' => $e->reason];
} catch (AccountingIntegrationException|AuthorizationException $e) {
    $outcome = ['status' => 'domain_rejected', 'class' => $e::class, 'message' => $e->getMessage()];
} catch (Throwable $e) {
    $outcome = ['status' => 'error', 'class' => $e::class, 'message' => $e->getMessage()];
}
$outcome['depth'] = DB::transactionLevel();
file_put_contents($dir.'/'.$name.'.result.json', json_encode($outcome, JSON_THROW_ON_ERROR));
exit($outcome['status'] === 'error' ? 1 : 0);
