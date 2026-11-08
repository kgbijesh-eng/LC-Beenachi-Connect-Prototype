<?php

declare(strict_types=1);

use App\Models\Club;
use App\Models\User;
use App\Services\Finance\ClubExpenseReversalService;
use App\Services\Finance\Exceptions\ClubExpenseReversalConflictException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Tests\Support\FinancialWorkerSafety::assert(['lcb_3iizw_expense_reversal_concurrency_test']);

[$requestPath, $barrier, $worker] = array_slice($argv, 1); $request = json_decode((string) file_get_contents($requestPath), true, flags: JSON_THROW_ON_ERROR);
file_put_contents($barrier.DIRECTORY_SEPARATOR."ready.{$worker}", '1'); $deadline = microtime(true) + 20;
while (! file_exists($barrier.DIRECTORY_SEPARATOR.'go')) { if (microtime(true) >= $deadline) throw new RuntimeException('Concurrency release barrier timed out.'); usleep(10000); }
try {
    $expense = app(ClubExpenseReversalService::class)->reverse(Club::query()->findOrFail($request['club']), $request['expense'], User::query()->findOrFail($request['actor']), $request['payload']);
    $result = ['status' => 'success', 'expense' => $expense->public_id, 'reversal' => $expense->reversal->public_id, 'journal' => $expense->reversal->journalEntry->public_id];
} catch (ClubExpenseReversalConflictException $exception) { $result = ['status' => 'conflict', 'code' => 'EXPENSE_REVERSAL_CONFLICT']; }
catch (Throwable $exception) { $result = ['status' => 'error', 'class' => $exception::class, 'message' => $exception->getMessage()]; }
file_put_contents($barrier.DIRECTORY_SEPARATOR."result.{$worker}.json", json_encode($result, JSON_THROW_ON_ERROR));
