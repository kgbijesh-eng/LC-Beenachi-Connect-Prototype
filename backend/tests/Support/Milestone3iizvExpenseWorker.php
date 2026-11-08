<?php

declare(strict_types=1);

use App\Models\Club;
use App\Models\User;
use App\Services\Finance\ClubExpenseRecordingService;
use App\Services\Finance\Exceptions\ClubExpenseConflictException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Tests\Support\FinancialWorkerSafety::assert(['lcb_3iizv_expense_concurrency_test','lcb_3ijab_replay_provenance_hardening_test']);

[$requestPath, $barrier, $worker] = array_slice($argv, 1);
$request = json_decode((string) file_get_contents($requestPath), true, flags: JSON_THROW_ON_ERROR);
file_put_contents($barrier.DIRECTORY_SEPARATOR."ready.{$worker}", '1');
$deadline = microtime(true) + 20;
while (! file_exists($barrier.DIRECTORY_SEPARATOR.'go')) {
    if (microtime(true) >= $deadline) { throw new RuntimeException('Concurrency release barrier timed out.'); }
    usleep(10000);
}

try {
    $expense = app(ClubExpenseRecordingService::class)->record(
        Club::query()->whereKey($request['club'])->firstOrFail(),
        User::query()->where('id', $request['actor'])->firstOrFail(),
        $request['payload'],
    );
    $result = ['status' => 'success', 'expense' => $expense->public_id, 'journal' => $expense->journalEntry->public_id];
} catch (ClubExpenseConflictException $exception) {
    $result = ['status' => 'conflict', 'code' => 'EXPENSE_IDEMPOTENCY_CONFLICT'];
} catch (Throwable $exception) {
    $result = ['status' => 'error', 'class' => $exception::class, 'message' => $exception->getMessage()];
}
file_put_contents($barrier.DIRECTORY_SEPARATOR."result.{$worker}.json", json_encode($result, JSON_THROW_ON_ERROR));
