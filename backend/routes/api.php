<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Club\ClubController;
use App\Http\Controllers\Api\V1\Finance\ClubFinanceReportController;
use App\Http\Controllers\Api\V1\Finance\MemberFinanceSummaryController;
use App\Http\Controllers\Api\V1\Finance\MemberLedgerBalanceController;
use App\Http\Controllers\Api\V1\Finance\PaymentController;
use App\Http\Controllers\Api\V1\Finance\PaymentHistoryController;
use App\Http\Controllers\Api\V1\Finance\PaymentReceiptPdfController;
use App\Http\Controllers\Api\V1\Finance\ReceiptController;
use App\Http\Controllers\Api\V1\Member\MemberController;
use App\Http\Controllers\Api\V1\Members\MemberDataImportPreviewController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', fn () => response()->json([
        'success' => true,
        'message' => 'LC Beenachi Connect API is healthy.',
        'data' => [
            'service' => 'lc-beenachi-connect-backend',
            'status' => 'ok',
        ],
    ]));

    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::middleware('club.permission:club.view,club')->group(function (): void {
            Route::get('/clubs/{club}', [ClubController::class, 'show']);
            Route::get('/clubs/{club}/me', [ClubController::class, 'me']);
            Route::get('/clubs/{club}/finance/member-summary', [MemberFinanceSummaryController::class, 'own']);
            Route::get('/clubs/{club}/finance/reports', [ClubFinanceReportController::class, 'reports']);
            Route::get('/clubs/{club}/finance/balance-sheet', [ClubFinanceReportController::class, 'balanceSheet']);

            Route::get('/clubs/{club}/members', [MemberController::class, 'index']);
            Route::post('/clubs/{club}/members/import-preview', [MemberDataImportPreviewController::class, 'store']);
            Route::get('/clubs/{club}/members/me/balance', [MemberLedgerBalanceController::class, 'own']);
            Route::get('/clubs/{club}/members/me/payments/history', [PaymentHistoryController::class, 'own']);
            Route::get('/clubs/{club}/members/{member}/balance', [MemberLedgerBalanceController::class, 'show']);
            Route::get('/clubs/{club}/members/{member}/finance-summary', [MemberFinanceSummaryController::class, 'show']);
            Route::get('/clubs/{club}/members/{member}/payments/history', [PaymentHistoryController::class, 'show']);
            Route::get('/clubs/{club}/members/{member}/payments/{payment}/receipt', [ReceiptController::class, 'show']);
            Route::get('/clubs/{club}/members/{member}/payments/{payment}/receipt/pdf', [PaymentReceiptPdfController::class, 'show']);
            Route::get('/clubs/{club}/members/{member}', [MemberController::class, 'show']);
        });

        Route::middleware('club.permission:members.manage,club')->group(function (): void {
            Route::post('/clubs/{club}/members', [MemberController::class, 'store']);
            Route::patch('/clubs/{club}/members/{member}', [MemberController::class, 'update']);
        });

        Route::middleware('club.permission:payments.record,club')->group(function (): void {
            Route::post('/clubs/{club}/members/{member}/payments', [PaymentController::class, 'store']);
            Route::post('/clubs/{club}/members/{member}/payments/{payment}/receipt', [ReceiptController::class, 'store']);
        });
    });
});
