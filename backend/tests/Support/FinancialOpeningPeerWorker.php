<?php

declare(strict_types=1);
use App\Services\Access\OrganisationTransactionGuard;
use App\Services\Finance\ClubJournalPostingService;
use App\Services\Finance\ClubOpeningJournalActivationService;
use App\Services\Finance\CutoverExecutionRequest;
use App\Services\Finance\JournalLineInput;
use App\Services\Finance\JournalPostingRequest;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\Support\FinancialWorkerSafety;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
[$file,$dir,$name] = array_slice($argv, 1);
$p = json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
$await = function ($file) {
    $end = microtime(true) + 20;
    while (! is_file($file)) {
        if (microtime(true) >= $end) {
            throw new RuntimeException('Opening peer barrier timed out.');
        }usleep(5000);
    }
};
try {
    FinancialWorkerSafety::assert(['lcb_p02b2_cutover_test']);
    file_put_contents($dir.'/'.$name.'.ready', json_encode(['pid' => DB::selectOne('SELECT pg_backend_pid() AS pid')->pid], JSON_THROW_ON_ERROR));
    $await($dir.'/'.$name.'.start');
    $held = false;
    DB::listen(function ($q) use (&$held, $p, $dir, $name, $await) {
        if (! $p['hold'] || $held || ! str_contains($q->sql, '"clubs"') || ! str_contains($q->sql, 'for update')) {
            return;
        }
        // Opening's separate attempt-audit transaction is not the financial root.
        $guarded = false;
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $f) {
            if (($f['class'] ?? null) === OrganisationTransactionGuard::class && ($f['function'] ?? null) === 'run') {
                $guarded = true;
            }
        }
        if (! $guarded) {
            return;
        }
        if (DB::transactionLevel() !== 1) {
            throw new RuntimeException('Opening peer club lock is not outermost.');
        }
        $held = true;
        file_put_contents($dir.'/'.$name.'.locked', '1');
        $await($dir.'/'.$name.'.finish');
    });
    if ($p['mode'] === 'opening') {
        app(ClubOpeningJournalActivationService::class)->execute(new CutoverExecutionRequest($p['club'], $p['package'], $p['version'], $p['checksum'], 'B3-OPENING-PEER', $p['opening_key'], $p['actor']));
    } elseif ($p['mode'] === 'journal') {
        app(ClubJournalPostingService::class)->post(new JournalPostingRequest($p['club'], $p['period'], '2027-07-04', 'B3 opening peer', 'INR', 'b3_opening_peer', 'one', 'posted', $p['journal_key'], $p['actor'], [new JournalLineInput($p['bank'], 'debit', 100, fundCode: 'GENERAL_CLUB_FUND'), new JournalLineInput($p['cash'], 'credit', 100, fundCode: 'GENERAL_CLUB_FUND')]));
    } else {
        throw new RuntimeException('Unknown opening peer mode.');
    }
    $r = ['status' => 'success', 'depth' => DB::transactionLevel()];
} catch (Throwable$e) {
    $r = ['status' => 'error', 'class' => $e::class, 'message' => $e->getMessage(), 'depth' => DB::transactionLevel()];
}
file_put_contents($dir.'/'.$name.'.result.json',json_encode($r,JSON_THROW_ON_ERROR));
exit($r['status'] === 'success' ? 0 : 1);
