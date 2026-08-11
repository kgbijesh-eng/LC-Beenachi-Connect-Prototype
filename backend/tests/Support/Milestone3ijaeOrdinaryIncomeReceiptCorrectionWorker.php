<?php

declare(strict_types=1);

use App\Models\{Club, User};
use App\Services\Finance\ClubOrdinaryIncomeReceiptCorrectionService;
use App\Services\Finance\Exceptions\ClubOrdinaryIncomeReceiptCorrectionConflictException;

require dirname(__DIR__, 2).'/vendor/autoload.php'; $app = require dirname(__DIR__, 2).'/bootstrap/app.php'; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
[$requestPath, $barrier, $worker] = array_slice($argv, 1); $request = json_decode((string) file_get_contents($requestPath), true, flags: JSON_THROW_ON_ERROR); file_put_contents($barrier.DIRECTORY_SEPARATOR."ready.{$worker}", '1'); $deadline = microtime(true) + 20;
while (! file_exists($barrier.DIRECTORY_SEPARATOR.'go')) { if (microtime(true) >= $deadline) throw new RuntimeException('Concurrency barrier timed out.'); usleep(10000); }
try { $receipt = app(ClubOrdinaryIncomeReceiptCorrectionService::class)->correct(Club::findOrFail($request['club']), $request['receipt'], User::findOrFail($request['actor']), $request['payload']); $result = ['status' => 'success', 'receipt' => $receipt->public_id, 'correction' => $receipt->correction->public_id, 'reversal' => $receipt->correction->reversalJournal->public_id, 'replacement' => $receipt->correction->replacementReceipt?->public_id]; }
catch (ClubOrdinaryIncomeReceiptCorrectionConflictException $exception) { $result = ['status' => 'conflict', 'code' => $exception->errorCode]; }
catch (Throwable $exception) { $result = ['status' => 'error', 'class' => $exception::class, 'message' => $exception->getMessage()]; }
file_put_contents($barrier.DIRECTORY_SEPARATOR."result.{$worker}.json", json_encode($result, JSON_THROW_ON_ERROR));
