<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3afFinanceModulePostReceiptClosureReviewTest extends TestCase
{
    public function test_milestone_3af_document_exists_and_records_review_decision(): void
    {
        $path = base_path('docs/milestone_3af_finance_module_post_receipt_closure_review.md');

        $this->assertFileExists($path);

        $contents = File::get($path);

        $this->assertStringContainsString('Milestone 3AF — Finance Module Post-Receipt Closure Review and Next Scope Decision', $contents);
        $this->assertStringContainsString('docs/tests-only', $contents);
        $this->assertStringContainsString('FINANCE_MODULE_POST_RECEIPT_CLOSURE_REVIEW_COMPLETE', $contents);
        $this->assertStringContainsString('Milestone 3AG — Outstanding Dues and Member Finance Summary Scope Gate', $contents);
        $this->assertStringContainsString('No implementation is allowed in this milestone', $contents);
    }

    public function test_milestone_3af_summarizes_completed_finance_foundation(): void
    {
        $contents = File::get(base_path('docs/milestone_3af_finance_module_post_receipt_closure_review.md'));

        $completedItems = [
            'Member account ledger and dues scope gate',
            'Member ledger database foundation',
            'Member ledger balance calculation foundation',
            'Member ledger balance read API',
            'Payment recording database foundation',
            'Payment recording service',
            'Payment recording API',
            'Payment receipt generation service',
            'Payment receipt API',
            'Payment receipt PDF download',
            'Payment receipt PDF validation and closure',
        ];

        foreach ($completedItems as $item) {
            $this->assertStringContainsString($item, $contents);
        }
    }

    public function test_milestone_3af_records_receipt_workflow_plateau_as_closed(): void
    {
        $contents = File::get(base_path('docs/milestone_3af_finance_module_post_receipt_closure_review.md'));

        $receiptCapabilities = [
            'authorized payment recording',
            'ledger credit creation from posted payment',
            'receipt generation for posted payment',
            'duplicate receipt prevention',
            'receipt JSON API',
            'receipt PDF download',
            'normal member own-receipt privacy',
            'cross-club fail-closed access',
            'unauthenticated fail-closed access',
        ];

        foreach ($receiptCapabilities as $capability) {
            $this->assertStringContainsString($capability, $contents);
        }
    }

    public function test_milestone_3af_identifies_pending_finance_workstreams(): void
    {
        $contents = File::get(base_path('docs/milestone_3af_finance_module_post_receipt_closure_review.md'));

        $pendingItems = [
            'Outstanding dues dashboard or report',
            'Member-facing dues and payment history UI',
            'Treasurer finance views',
            'President and Secretary finance review views',
            'Special collection workflow',
            'Fellowship payment allocation',
            'Payment correction workflow',
            'Payment reversal workflow',
            'Payment cancellation workflow',
            'Finance reports',
            'Excel import and export',
            'Reminder system for dues and important information',
        ];

        foreach ($pendingItems as $item) {
            $this->assertStringContainsString($item, $contents);
        }
    }

    public function test_milestone_3af_selects_3ag_without_authorizing_implementation(): void
    {
        $contents = File::get(base_path('docs/milestone_3af_finance_module_post_receipt_closure_review.md'));

        $this->assertStringContainsString('Milestone 3AF does not authorize immediate implementation', $contents);
        $this->assertStringContainsString('Milestone 3AG should be docs/tests-only', $contents);
        $this->assertStringContainsString('who can view outstanding dues', $contents);
        $this->assertStringContainsString('what normal members can see', $contents);
        $this->assertStringContainsString('how privacy is protected', $contents);
        $this->assertStringContainsString('what remains blocked', $contents);
    }

    public function test_milestone_3af_does_not_register_future_finance_feature_routes(): void
    {
        $routeUris = collect(Route::getRoutes())
            ->map(fn ($route) => $route->uri())
            ->implode("\n");

        $blockedRouteFragments = [
            'outstanding-dues',
            'finance-summary',
            'payment-corrections',
            'payment-reversals',
            'payment-cancellations',
            'finance-reports',
            'dues-reminders',
            'excel-import',
            'excel-export',
            'special-collections',
            'fellowship-allocations',
        ];

        foreach ($blockedRouteFragments as $fragment) {
            $this->assertStringNotContainsString(
                $fragment,
                $routeUris,
                "3AF must not register future finance route fragment: {$fragment}"
            );
        }
    }

    public function test_milestone_3af_does_not_add_future_feature_implementation_files(): void
    {
        $appFilenames = collect(File::allFiles(base_path('app')))
            ->map(fn ($file) => $file->getFilename())
            ->all();

        $blockedFilenames = [
            'OutstandingDuesController.php',
            'FinanceSummaryController.php',
            'MemberFinanceSummaryController.php',
            'TreasurerFinanceViewController.php',
            'SpecialCollectionController.php',
            'FellowshipPaymentAllocationController.php',
            'PaymentCorrectionController.php',
            'PaymentReversalController.php',
            'PaymentCancellationController.php',
            'FinanceReportController.php',
            'DuesReminderController.php',
            'ExcelImportController.php',
            'ExcelExportController.php',
            'OutstandingDuesService.php',
            'FinanceSummaryService.php',
            'PaymentCorrectionService.php',
            'PaymentReversalService.php',
            'PaymentCancellationService.php',
        ];

        foreach ($blockedFilenames as $filename) {
            $this->assertNotContains(
                $filename,
                $appFilenames,
                "3AF must not add future finance implementation file: {$filename}"
            );
        }
    }

    public function test_milestone_3af_adds_only_review_document_and_review_test_scope(): void
    {
        $document = base_path('docs/milestone_3af_finance_module_post_receipt_closure_review.md');
        $test = base_path('tests/Feature/Milestone3afFinanceModulePostReceiptClosureReviewTest.php');

        $this->assertFileExists($document);
        $this->assertFileExists($test);

        $contents = File::get($document);

        $blockedScopes = [
            'new routes',
            'new controllers',
            'new services',
            'new requests',
            'new resources',
            'new models',
            'new migrations',
            'frontend finance screens',
            'outstanding dues implementation',
            'finance summary implementation',
            'special collection workflow',
            'fellowship allocation workflow',
            'payment correction workflow',
            'payment reversal workflow',
            'payment cancellation workflow',
            'reminder implementation',
            'finance reports',
            'Excel import/export',
        ];

        foreach ($blockedScopes as $scope) {
            $this->assertStringContainsString($scope, $contents);
        }
    }
}