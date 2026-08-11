<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3akFinanceModulePostSummaryClosureReviewNextScopeDecisionTest extends TestCase
{
    public function test_3ak_closure_review_document_exists_and_records_decision(): void
    {
        $path = base_path('docs/milestone_3ak_finance_module_post_summary_closure_review_next_scope_decision.md');

        $this->assertFileExists($path);

        $contents = File::get($path);

        $this->assertStringContainsString('Milestone 3AK', $contents);
        $this->assertStringContainsString('FINANCE_MODULE_POST_SUMMARY_CLOSURE_REVIEW_COMPLETED', $contents);
        $this->assertStringContainsString('Docs/tests-only closure review and next scope decision', $contents);
        $this->assertStringContainsString('Review the completed finance backend foundation from Milestone 3A through Milestone 3AJ', $contents);
    }

    public function test_3ak_summarizes_completed_finance_foundation_through_3aj(): void
    {
        $contents = File::get(base_path('docs/milestone_3ak_finance_module_post_summary_closure_review_next_scope_decision.md'));

        foreach ([
            '3A defined member account ledger and dues scope',
            '3B implemented member ledger database foundation',
            '3C implemented ledger balance calculation foundation',
            '3D through 3F delivered and closed the member ledger balance read API',
            '3G through 3J delivered and closed payment and receipt database foundation',
            '3K through 3N delivered and closed payment recording service foundation',
            '3O through 3R delivered and closed payment recording API foundation',
            '3S through 3V delivered and closed receipt generation service foundation',
            '3W through 3Z delivered and closed receipt JSON API foundation',
            '3AA reviewed finance module closure and selected receipt PDF direction',
            '3AB through 3AE delivered and closed receipt PDF download foundation',
            '3AF reviewed the post-receipt finance plateau',
            '3AG through 3AJ delivered and closed outstanding dues and member finance summary read API foundation',
        ] as $expected) {
            $this->assertStringContainsString($expected, $contents);
        }
    }

    public function test_3ak_confirms_current_backend_finance_capabilities(): void
    {
        $contents = File::get(base_path('docs/milestone_3ak_finance_module_post_summary_closure_review_next_scope_decision.md'));

        foreach ([
            'Ledger entries can store posted debits and credits',
            'Member balance can be calculated from posted ledger entries',
            'Normal member can read own balance and own finance summary',
            'Treasurer, Secretary, and President/Admin can read selected same-club member balance and finance summary',
            'Treasurer, Secretary, and President/Admin can record member payments',
            'Payment recording creates posted payment records and posted ledger credits through the service/API path',
            'Receipts can be generated for posted payments',
            'Receipt JSON can be generated and read through protected API routes',
            'Receipt PDF can be downloaded for issued receipts',
            'Finance summary read API exposes Current Dues, Last Payment, receipt availability, and pending items',
        ] as $expected) {
            $this->assertStringContainsString($expected, $contents);
        }
    }

    public function test_3ak_closes_finance_summary_read_api_plateau(): void
    {
        $contents = File::get(base_path('docs/milestone_3ak_finance_module_post_summary_closure_review_next_scope_decision.md'));

        foreach ([
            '3AI implemented the narrow read-only finance summary API',
            '3AJ validated and closed the finance summary read API',
            'Finance summary routes are GET-only',
            'Finance summary query flags are display controls only',
            'Finance summary access is privacy-safe and fail-closed',
        ] as $expected) {
            $this->assertStringContainsString($expected, $contents);
        }
    }

    public function test_3ak_records_remaining_finance_gaps_without_implementing_them(): void
    {
        $contents = File::get(base_path('docs/milestone_3ak_finance_module_post_summary_closure_review_next_scope_decision.md'));

        foreach ([
            'Finance summary is not yet connected to frontend UI',
            'Member dashboard does not yet show backend Current Dues / Last Payment / Receipt availability',
            'PST finance dashboard/reporting is not yet implemented',
            'Payment correction, reversal, and cancellation workflows are not yet implemented',
            'Special collection allocation workflow is not yet implemented',
            'Fellowship sponsorship/payment allocation workflow is not yet implemented',
            'Payment reminders are not yet implemented',
            'Excel import/migration is not yet implemented',
        ] as $expected) {
            $this->assertStringContainsString($expected, $contents);
        }
    }

    public function test_3ak_selects_3al_frontend_scope_gate_without_authorizing_implementation(): void
    {
        $contents = File::get(base_path('docs/milestone_3ak_finance_module_post_summary_closure_review_next_scope_decision.md'));

        $this->assertStringContainsString('Next recommended milestone:', $contents);
        $this->assertStringContainsString('Milestone 3AL', $contents);
        $this->assertStringContainsString('Member Finance Summary Frontend Scope Gate', $contents);
        $this->assertStringContainsString('3AL must be a scope gate first', $contents);
        $this->assertStringContainsString('Next work should open a frontend scope gate before any UI integration is attempted', $contents);
    }

    public function test_3ak_blocks_runtime_implementation_scope(): void
    {
        $contents = File::get(base_path('docs/milestone_3ak_finance_module_post_summary_closure_review_next_scope_decision.md'));

        foreach ([
            'No controller changes',
            'No service changes',
            'No resource changes',
            'No route changes',
            'No request changes',
            'No model changes',
            'No migration changes',
            'No seeder changes',
            'No frontend',
            'No dashboard',
            'No reports',
            'No reminders',
            'No Excel',
            'No PDF',
            'No payment mutation',
            'No receipt mutation',
            'No ledger mutation',
            'No correction workflow',
            'No reversal workflow',
            'No cancellation workflow',
            'No special collection workflow',
            'No fellowship allocation workflow',
        ] as $expected) {
            $this->assertStringContainsString($expected, $contents);
        }
    }

    public function test_current_report_reminder_and_import_route_matrix_is_exact(): void
    {
        $routes = collect(Route::getRoutes())
            ->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())
            ->filter(fn (string $route): bool => str_contains($route, 'report') || str_contains($route, 'reminder') || str_contains($route, 'import'))
            ->values()->all();

        $this->assertSame(Milestone3gPaymentRecordingScopeGateTest::CURRENT_REPORT_REMINDER_IMPORT_ROUTE_MATRIX, $routes);

        foreach (['dashboard', 'excel', 'correction', 'reversal', 'cancellation', 'special-collection', 'fellowship'] as $blockedFragment) {
            $this->assertFalse(collect(Route::getRoutes())->contains(fn ($route): bool => str_contains($route->uri(), $blockedFragment)));
        }
    }

    public function test_3ak_does_not_add_blocked_runtime_files(): void
    {
        $blockedPaths = [
            app_path('Http/Controllers/Api/V1/Finance/FinanceDashboardController.php'),
            app_path('Http/Controllers/Api/V1/Finance/FinanceReportController.php'),
            app_path('Http/Controllers/Api/V1/Finance/PaymentReminderController.php'),
            app_path('Http/Controllers/Api/V1/Finance/PaymentCorrectionController.php'),
            app_path('Http/Controllers/Api/V1/Finance/PaymentReversalController.php'),
            app_path('Http/Controllers/Api/V1/Finance/PaymentCancellationController.php'),
            app_path('Http/Controllers/Api/V1/Finance/SpecialCollectionController.php'),
            app_path('Http/Controllers/Api/V1/Finance/FellowshipPaymentController.php'),
            app_path('Services/Finance/FinanceDashboardService.php'),
            app_path('Services/Finance/FinanceReportService.php'),
            app_path('Services/Finance/PaymentReminderService.php'),
            app_path('Services/Finance/PaymentCorrectionService.php'),
            app_path('Services/Finance/PaymentReversalService.php'),
            app_path('Services/Finance/PaymentCancellationService.php'),
            app_path('Services/Finance/SpecialCollectionAllocationService.php'),
            app_path('Services/Finance/FellowshipPaymentService.php'),
            app_path('Services/Imports/ExcelImportService.php'),
            base_path('../app/finance'),
            base_path('../app/reports'),
            base_path('../app/reminders'),
        ];

        foreach ($blockedPaths as $blockedPath) {
            $this->assertFileDoesNotExist($blockedPath);
        }
    }

    public function test_3ak_adds_only_expected_document_and_test_scope(): void
    {
        $this->assertFileExists(base_path('docs/milestone_3ak_finance_module_post_summary_closure_review_next_scope_decision.md'));
        $this->assertFileExists(base_path('tests/Feature/Milestone3akFinanceModulePostSummaryClosureReviewNextScopeDecisionTest.php'));

        $this->assertFileDoesNotExist(app_path('Http/Controllers/Api/V1/Finance/MemberFinanceSummaryFrontendController.php'));
        $this->assertFileDoesNotExist(app_path('Services/Finance/MemberFinanceSummaryFrontendService.php'));
        $this->assertFileDoesNotExist(app_path('Http/Resources/Finance/MemberFinanceSummaryFrontendResource.php'));
    }
}
