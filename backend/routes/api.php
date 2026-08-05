<?php

use App\Http\Controllers\Api\V1\Announcements\ClubAnnouncementController;
use App\Http\Controllers\Api\V1\Auth\AccountActivationController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Club\ClubController;
use App\Http\Controllers\Api\V1\Events\ClubEventController;
use App\Http\Controllers\Api\V1\Finance\ClubFinanceReportController;
use App\Http\Controllers\Api\V1\Finance\AccountingIntegrationController;
use App\Http\Controllers\Api\V1\Finance\MemberFinanceSummaryController;
use App\Http\Controllers\Api\V1\Finance\MemberLedgerBalanceController;
use App\Http\Controllers\Api\V1\Finance\PaymentController;
use App\Http\Controllers\Api\V1\Finance\PaymentHistoryController;
use App\Http\Controllers\Api\V1\Finance\PaymentReceiptPdfController;
use App\Http\Controllers\Api\V1\Finance\ReceiptController;
use App\Http\Controllers\Api\V1\FinanceCollections\FinanceReadController;
use App\Http\Controllers\Api\V1\FinanceCollections\FinanceWriteController;
use App\Http\Controllers\Api\V1\Member\MemberController;
use App\Http\Controllers\Api\V1\Members\MemberDataImportCommitController;
use App\Http\Controllers\Api\V1\Members\MemberDataImportPreviewController;
use App\Http\Controllers\Api\V1\Members\MemberProfileDocumentController;
use App\Http\Controllers\Api\V1\ProfileUpdates\ProfileUpdateRequestController;
use App\Http\Controllers\Api\V1\Projects\ClubProjectController;
use App\Http\Controllers\Api\V1\Reminders\ClubReminderController;
use App\Http\Controllers\Api\V1\Reports\ClubReportController;
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

    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/auth/activate', [AccountActivationController::class, 'complete'])->middleware('throttle:account-activation');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/clubs/{club}/members/account-activations', [AccountActivationController::class, 'issue'])
            ->middleware('club.permission:members.account_activation.issue,club');
        Route::get('/clubs/{club}/members/me', [MemberController::class, 'own']);

        Route::middleware('club.permission:club.view,club')->group(function (): void {
            Route::get('/clubs/{club}', [ClubController::class, 'show']);
            Route::get('/clubs/{club}/me', [ClubController::class, 'me']);
            Route::get('/clubs/{club}/finance/member-summary', [MemberFinanceSummaryController::class, 'own']);
            Route::get('/clubs/{club}/finance/reports', [ClubFinanceReportController::class, 'reports']);
            Route::get('/clubs/{club}/finance/balance-sheet', [ClubFinanceReportController::class, 'balanceSheet']);

            Route::post('/clubs/{club}/members/import-preview', [MemberDataImportPreviewController::class, 'store']);
            Route::post('/clubs/{club}/members/import-commit', [MemberDataImportCommitController::class, 'store']);
            Route::get('/clubs/{club}/members/me/balance', [MemberLedgerBalanceController::class, 'own']);
            Route::get('/clubs/{club}/members/me/payments/history', [PaymentHistoryController::class, 'own']);
            Route::get('/clubs/{club}/members/{member}/balance', [MemberLedgerBalanceController::class, 'show']);
            Route::get('/clubs/{club}/members/{member}/finance-summary', [MemberFinanceSummaryController::class, 'show']);
            Route::get('/clubs/{club}/members/{member}/payments/history', [PaymentHistoryController::class, 'show']);
            Route::get('/clubs/{club}/members/{member}/profile-documents', [MemberProfileDocumentController::class, 'index']);
            Route::get('/clubs/{club}/members/{member}/profile-documents/{document}', [MemberProfileDocumentController::class, 'show']);
            Route::get('/clubs/{club}/members/{member}/payments/{payment}/receipt', [ReceiptController::class, 'show']);
            Route::get('/clubs/{club}/members/{member}/payments/{payment}/receipt/pdf', [PaymentReceiptPdfController::class, 'show']);
        });

        Route::middleware('club.permission:members.view,club')->group(function (): void {
            Route::get('/clubs/{club}/members', [MemberController::class, 'index']);
            Route::get('/clubs/{club}/members/{member}', [MemberController::class, 'show']);
        });

        Route::middleware('club.permission:events.view,club')->group(function (): void {
            Route::get('/clubs/{club}/events', [ClubEventController::class, 'index']);
            Route::get('/clubs/{club}/events/{event}', [ClubEventController::class, 'show']);
        });

        Route::middleware('club.permission:projects.view,club')->group(function (): void {
            Route::get('/clubs/{club}/projects', [ClubProjectController::class, 'index']);
            Route::get('/clubs/{club}/projects/{project}', [ClubProjectController::class, 'show']);
        });
        Route::middleware('club.permission:reports.view,club')->group(function (): void {
            Route::get('/clubs/{club}/reports', [ClubReportController::class, 'index']);
            Route::get('/clubs/{club}/reports/{reportKey}', [ClubReportController::class, 'show']);
        });
        Route::middleware('club.permission:finance.club.view,club')->group(function (): void {
            Route::get('/clubs/{club}/finance/charges', [FinanceReadController::class, 'charges']);
            Route::get('/clubs/{club}/finance/charges/{charge}', [FinanceReadController::class, 'charge']);
            Route::get('/clubs/{club}/finance/members/{member}/summary', [FinanceReadController::class, 'summary']);
            Route::get('/clubs/{club}/finance/members/{member}/charges', [FinanceReadController::class, 'memberCharges']);
            Route::get('/clubs/{club}/finance/members/{member}/payments', [FinanceReadController::class, 'memberPayments']);
            Route::get('/clubs/{club}/finance/payments/{payment}', [FinanceReadController::class, 'payment']);
            Route::get('/clubs/{club}/finance/receipts/{receipt}', [FinanceReadController::class, 'receipt']);
        });
        Route::middleware('club.permission:finance.self.view,club')->group(function (): void {
            Route::get('/clubs/{club}/finance/me/summary', [FinanceReadController::class, 'ownSummary']);
            Route::get('/clubs/{club}/finance/me/charges', [FinanceReadController::class, 'ownCharges']);
            Route::get('/clubs/{club}/finance/me/payments', [FinanceReadController::class, 'ownPayments']);
        });
        Route::middleware('club.permission:finance.manage,club')->group(function (): void {
            Route::post('/clubs/{club}/finance/charges', [FinanceWriteController::class, 'store']);
            Route::patch('/clubs/{club}/finance/charges/{charge}', [FinanceWriteController::class, 'update']);
            Route::post('/clubs/{club}/finance/charges/{charge}/issue', [FinanceWriteController::class, 'issue']);
            Route::post('/clubs/{club}/finance/charges/{charge}/close', [FinanceWriteController::class, 'close']);
            Route::post('/clubs/{club}/finance/charges/{charge}/cancel', [FinanceWriteController::class, 'cancel']);
            Route::post('/clubs/{club}/finance/members/{member}/payments', [FinanceWriteController::class, 'payment']);
        });
        Route::middleware('club.permission:finance.void,club')->group(function (): void {
            Route::post('/clubs/{club}/finance/payments/{payment}/void', [FinanceWriteController::class, 'void']);
        });
        Route::middleware('club.permission:finance.accounting.integration.configure,club')->prefix('/clubs/{club}/finance/accounting/integration')->group(function (): void {
            Route::get('/readiness', [AccountingIntegrationController::class, 'readiness']);
            Route::get('/configuration', [AccountingIntegrationController::class, 'configuration']);
            Route::post('/mapping-proposals', [AccountingIntegrationController::class, 'mapping']);
            Route::post('/activation-proposals', [AccountingIntegrationController::class, 'activation']);
            Route::post('/revocation-proposals', [AccountingIntegrationController::class, 'revocation']);
            Route::post('/proposals/{proposal}/approve', [AccountingIntegrationController::class, 'approve']);
            Route::post('/proposals/{proposal}/reject', [AccountingIntegrationController::class, 'reject']);
            Route::post('/proposals/{proposal}/withdraw', [AccountingIntegrationController::class, 'withdraw']);
        });

        Route::middleware('club.permission:profile_updates.self.submit,club')->group(function (): void {
            Route::post('/clubs/{club}/profile-update-requests', [ProfileUpdateRequestController::class, 'store']);
            Route::post('/clubs/{club}/profile-update-requests/{profileUpdateRequest}/withdraw', [ProfileUpdateRequestController::class, 'withdraw']);
        });
        Route::middleware('club.permission:profile_updates.self.view,club')->group(function (): void {
            Route::get('/clubs/{club}/profile-update-requests/me', [ProfileUpdateRequestController::class, 'ownIndex']);
            Route::get('/clubs/{club}/profile-update-requests/me/{profileUpdateRequest}', [ProfileUpdateRequestController::class, 'ownShow']);
        });
        Route::middleware('club.permission:profile_updates.approve,club')->group(function (): void {
            Route::post('/clubs/{club}/profile-update-requests/{profileUpdateRequest}/approve', [ProfileUpdateRequestController::class, 'approve']);
            Route::post('/clubs/{club}/profile-update-requests/{profileUpdateRequest}/reject', [ProfileUpdateRequestController::class, 'reject']);
        });
        Route::middleware('club.permission:profile_updates.review,club')->group(function (): void {
            Route::get('/clubs/{club}/profile-update-requests', [ProfileUpdateRequestController::class, 'index']);
            Route::get('/clubs/{club}/profile-update-requests/{profileUpdateRequest}', [ProfileUpdateRequestController::class, 'show']);
        });

        Route::middleware('club.permission:reminders.view,club')->group(function (): void {
            Route::get('/clubs/{club}/reminders', [ClubReminderController::class, 'index']);
            Route::get('/clubs/{club}/reminders/{reminder}', [ClubReminderController::class, 'show']);
        });
        Route::middleware('club.permission:announcements.view,club')->group(function (): void {
            Route::get('/clubs/{club}/announcements', [ClubAnnouncementController::class, 'index']);
            Route::get('/clubs/{club}/announcements/{announcement}', [ClubAnnouncementController::class, 'show']);
            Route::put('/clubs/{club}/announcements/{announcement}/read', [ClubAnnouncementController::class, 'markRead']);
        });
        Route::middleware('club.permission:announcements.drafts.manage,club')->group(function (): void {
            Route::post('/clubs/{club}/announcements', [ClubAnnouncementController::class, 'store']);
            Route::patch('/clubs/{club}/announcements/{announcement}', [ClubAnnouncementController::class, 'update']);
        });
        Route::post('/clubs/{club}/announcements/{announcement}/replacements', [ClubAnnouncementController::class, 'storeReplacement'])
            ->middleware('club.permission:announcements.replace,club');
        Route::middleware('club.permission:announcements.approve,club')->group(function (): void {
            Route::post('/clubs/{club}/announcements/{announcement}/approvals', [ClubAnnouncementController::class, 'approve']);
            Route::post('/clubs/{club}/announcements/{announcement}/approval-rejections', [ClubAnnouncementController::class, 'rejectApproval']);
            Route::post('/clubs/{club}/announcements/{announcement}/approvals/{approval}/revoke', [ClubAnnouncementController::class, 'revokeApproval']);
        });
        Route::post('/clubs/{club}/announcements/{announcement}/publish', [ClubAnnouncementController::class, 'publish'])
            ->middleware('club.permission:announcements.publish,club');
        Route::post('/clubs/{club}/announcements/{announcement}/withdraw', [ClubAnnouncementController::class, 'withdraw'])
            ->middleware('club.permission:announcements.withdraw,club');
        Route::post('/clubs/{club}/announcements/{announcement}/archive', [ClubAnnouncementController::class, 'archive'])
            ->middleware('club.permission:announcements.archive,club');
        Route::middleware('club.permission:reminders.manage,club')->group(function (): void {
            Route::post('/clubs/{club}/reminders', [ClubReminderController::class, 'store']);
            Route::patch('/clubs/{club}/reminders/{reminder}', [ClubReminderController::class, 'update']);
            Route::post('/clubs/{club}/reminders/{reminder}/activate', [ClubReminderController::class, 'activate']);
            Route::post('/clubs/{club}/reminders/{reminder}/complete', [ClubReminderController::class, 'complete']);
            Route::post('/clubs/{club}/reminders/{reminder}/cancel', [ClubReminderController::class, 'cancel']);
        });

        Route::middleware('club.permission:projects.manage,club')->group(function (): void {
            Route::post('/clubs/{club}/projects', [ClubProjectController::class, 'store']);
            Route::patch('/clubs/{club}/projects/{project}', [ClubProjectController::class, 'update']);
            Route::post('/clubs/{club}/projects/{project}/plan', [ClubProjectController::class, 'plan']);
            Route::post('/clubs/{club}/projects/{project}/start', [ClubProjectController::class, 'start']);
            Route::post('/clubs/{club}/projects/{project}/complete', [ClubProjectController::class, 'complete']);
            Route::post('/clubs/{club}/projects/{project}/cancel', [ClubProjectController::class, 'cancel']);
        });
        Route::middleware('club.permission:events.manage,club')->group(function (): void {
            Route::post('/clubs/{club}/events', [ClubEventController::class, 'store']);
            Route::patch('/clubs/{club}/events/{event}', [ClubEventController::class, 'update']);
            Route::post('/clubs/{club}/events/{event}/publish', [ClubEventController::class, 'publish']);
            Route::post('/clubs/{club}/events/{event}/cancel', [ClubEventController::class, 'cancel']);
            Route::post('/clubs/{club}/events/{event}/complete', [ClubEventController::class, 'complete']);
        });

        Route::middleware('club.permission:members.manage,club')->group(function (): void {
            Route::post('/clubs/{club}/members', [MemberController::class, 'store']);
            Route::patch('/clubs/{club}/members/{member}', [MemberController::class, 'update']);
            Route::post('/clubs/{club}/members/{member}/profile-documents', [MemberProfileDocumentController::class, 'store']);
            Route::patch('/clubs/{club}/members/{member}/profile-documents/{document}', [MemberProfileDocumentController::class, 'update']);
        });

        Route::middleware('club.permission:payments.record,club')->group(function (): void {
            Route::post('/clubs/{club}/members/{member}/payments', [PaymentController::class, 'store']);
            Route::post('/clubs/{club}/members/{member}/payments/{payment}/receipt', [ReceiptController::class, 'store']);
        });
    });
});
