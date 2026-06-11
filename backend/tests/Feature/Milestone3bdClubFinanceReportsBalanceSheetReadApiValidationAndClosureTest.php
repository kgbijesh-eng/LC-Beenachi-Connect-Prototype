<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3bdClubFinanceReportsBalanceSheetReadApiValidationAndClosureTest extends TestCase
{
    public function test_3bd_closure_document_exists_and_records_validation_decision(): void
    {
        $path = base_path('docs/milestone_3bd_club_finance_reports_balance_sheet_read_api_validation_and_closure.md');

        $this->assertFileExists($path);

        $content = file_get_contents($path);

        $this->assertStringContainsString('Milestone 3BD', $content);
        $this->assertStringContainsString('docs/tests-only validation and closure milestone', $content);
        $this->assertStringContainsString('validates and closes Milestone 3BC', $content);
        $this->assertStringContainsString('538 passed (5639 assertions)', $content);
    }

    public function test_3bc_implementation_files_remain_present_for_closure(): void
    {
        $this->assertFileExists(app_path('Http/Controllers/Api/V1/Finance/ClubFinanceReportController.php'));
        $this->assertFileExists(app_path('Http/Resources/Finance/ClubFinanceReportResource.php'));
        $this->assertFileExists(app_path('Services/Finance/ClubFinanceReportService.php'));
        $this->assertFileExists(base_path('docs/milestone_3bc_club_finance_reports_balance_sheet_read_api_narrow_implementation.md'));
        $this->assertFileExists(base_path('tests/Feature/Milestone3bcClubFinanceReportsBalanceSheetReadApiNarrowImplementationTest.php'));
    }

    public function test_finance_report_routes_remain_exact_and_get_only(): void
    {
        $routeSignatures = collect(Route::getRoutes())
            ->filter(fn ($route): bool => in_array($route->uri(), [
                'api/v1/clubs/{club}/finance/reports',
                'api/v1/clubs/{club}/finance/balance-sheet',
            ], true))
            ->map(fn ($route): string => implode('|', $route->methods()).' '.$route->uri())
            ->values()
            ->all();

        sort($routeSignatures);

        $this->assertSame([
            'GET|HEAD api/v1/clubs/{club}/finance/balance-sheet',
            'GET|HEAD api/v1/clubs/{club}/finance/reports',
        ], $routeSignatures);
    }

    public function test_report_routes_still_use_3bc_controller(): void
    {
        $routeActions = collect(Route::getRoutes())
            ->filter(fn ($route): bool => in_array($route->uri(), [
                'api/v1/clubs/{club}/finance/reports',
                'api/v1/clubs/{club}/finance/balance-sheet',
            ], true))
            ->map(fn ($route): string => $route->getActionName())
            ->values()
            ->all();

        $this->assertCount(2, $routeActions);

        foreach ($routeActions as $routeAction) {
            $this->assertStringContainsString('ClubFinanceReportController', $routeAction);
        }
    }

    public function test_3bd_records_read_only_and_access_boundaries(): void
    {
        $content = file_get_contents(base_path('docs/milestone_3bd_club_finance_reports_balance_sheet_read_api_validation_and_closure.md'));

        foreach ([
            'read-only club finance reports and balance sheet',
            'President/Admin',
            'Secretary',
            'Treasurer',
            'Normal members must not access',
            'Cross-club access must fail closed',
            'Unauthenticated access must fail closed',
        ] as $requiredText) {
            $this->assertStringContainsString($requiredText, $content);
        }
    }

    public function test_3bd_blocks_runtime_and_workflow_drift(): void
    {
        $content = file_get_contents(base_path('docs/milestone_3bd_club_finance_reports_balance_sheet_read_api_validation_and_closure.md'));

        foreach ([
            'route changes',
            'controller changes',
            'service changes',
            'resource changes',
            'model changes',
            'migration changes',
            'seeder changes',
            'frontend changes',
            'payment or receipt workflow changes',
        ] as $blockedScope) {
            $this->assertStringContainsString($blockedScope, $content);
        }
    }

    public function test_3bd_adds_only_closure_doc_and_validation_test(): void
    {
        $allowedFiles = [
            'docs/milestone_3bd_club_finance_reports_balance_sheet_read_api_validation_and_closure.md',
            'tests/Feature/Milestone3bdClubFinanceReportsBalanceSheetReadApiValidationAndClosureTest.php',
        ];

        foreach ($allowedFiles as $allowedFile) {
            $this->assertFileExists(base_path($allowedFile));
        }

        $this->assertFileDoesNotExist(app_path('Http/Controllers/Api/V1/Finance/ClubFinanceReportExportController.php'));
        $this->assertFileDoesNotExist(app_path('Http/Controllers/Api/V1/Finance/ClubFinanceReportPdfController.php'));
        $this->assertFileDoesNotExist(app_path('Http/Requests/Finance/ClubFinanceReportRequest.php'));
        $this->assertFileDoesNotExist(app_path('Models/ClubFinanceReport.php'));
    }
}
