<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3beClubFinanceReportsBalanceSheetFrontendDisplayScopeGateTest extends TestCase
{
    public function test_3be_scope_document_exists_and_records_docs_tests_only_status(): void
    {
        $path = base_path('docs/milestone_3be_club_finance_reports_balance_sheet_frontend_display_scope_gate.md');

        $this->assertFileExists($path);

        $content = file_get_contents($path);

        $this->assertStringContainsString('Milestone 3BE', $content);
        $this->assertStringContainsString('docs/tests-only scope gate', $content);
        $this->assertStringContainsString('does not implement frontend display', $content);
    }

    public function test_3be_defines_future_frontend_display_goal_without_implementation(): void
    {
        $content = file_get_contents(base_path('docs/milestone_3be_club_finance_reports_balance_sheet_frontend_display_scope_gate.md'));

        foreach ([
            'Club Finance Reports',
            'Balance Sheet Summary',
            'Outstanding dues summary',
            'Member-wise dues summary',
            'Payment collection summary',
            'Receipt summary',
        ] as $requiredText) {
            $this->assertStringContainsString($requiredText, $content);
        }
    }

    public function test_3be_limits_future_frontend_to_existing_3bc_api_routes(): void
    {
        $content = file_get_contents(base_path('docs/milestone_3be_club_finance_reports_balance_sheet_frontend_display_scope_gate.md'));

        $this->assertStringContainsString('GET /api/v1/clubs/{club}/finance/reports', $content);
        $this->assertStringContainsString('GET /api/v1/clubs/{club}/finance/balance-sheet', $content);
        $this->assertStringContainsString('must not create new backend API routes', $content);
    }

    public function test_existing_backend_report_routes_remain_exact_get_routes(): void
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

    public function test_3be_defines_role_visibility_boundary(): void
    {
        $content = file_get_contents(base_path('docs/milestone_3be_club_finance_reports_balance_sheet_frontend_display_scope_gate.md'));

        foreach ([
            'President/Admin',
            'Secretary',
            'Treasurer',
            'Normal Member role must not see',
            'member-safe finance information',
        ] as $requiredText) {
            $this->assertStringContainsString($requiredText, $content);
        }
    }

    public function test_3be_defines_allowed_future_display_fields(): void
    {
        $content = file_get_contents(base_path('docs/milestone_3be_club_finance_reports_balance_sheet_frontend_display_scope_gate.md'));

        foreach ([
            'club name',
            'total debit amount',
            'total credit amount',
            'current dues amount',
            'members with dues count',
            'total outstanding amount',
            'member-wise dues summary',
            'posted payment total',
            'issued receipt count',
            'member receivables',
            'cash collected',
            'member advances',
            'net position',
            'generated at',
        ] as $requiredField) {
            $this->assertStringContainsString($requiredField, $content);
        }
    }

    public function test_3be_defines_safe_frontend_states(): void
    {
        $content = file_get_contents(base_path('docs/milestone_3be_club_finance_reports_balance_sheet_frontend_display_scope_gate.md'));

        foreach ([
            'loading',
            'no finance records yet',
            'API error',
            'forbidden access',
            'unauthenticated access',
            'must not expose raw technical errors',
        ] as $requiredState) {
            $this->assertStringContainsString($requiredState, $content);
        }
    }

    public function test_3be_blocks_runtime_backend_frontend_and_workflow_changes(): void
    {
        $content = file_get_contents(base_path('docs/milestone_3be_club_finance_reports_balance_sheet_frontend_display_scope_gate.md'));

        foreach ([
            'frontend changes',
            'app/page.tsx changes',
            'backend route changes',
            'controller changes',
            'service changes',
            'resource changes',
            'model changes',
            'migration changes',
            'seeder changes',
            'export features',
            'PDF features',
            'Excel import or export',
            'reminder features',
            'payment mutation',
            'receipt sharing',
            'cancellation workflows',
            'reversal workflows',
            'allocation workflows',
        ] as $blockedScope) {
            $this->assertStringContainsString($blockedScope, $content);
        }
    }

    public function test_3be_preserves_backend_source_of_truth_rule(): void
    {
        $content = file_get_contents(base_path('docs/milestone_3be_club_finance_reports_balance_sheet_frontend_display_scope_gate.md'));

        $this->assertStringContainsString('3BC backend API as the source of truth', $content);
        $this->assertStringContainsString('must not calculate official finance balances independently', $content);
        $this->assertStringContainsString('must not replace backend finance calculations', $content);
    }

    public function test_3be_selects_3bf_authorization_candidate_without_implementation(): void
    {
        $content = file_get_contents(base_path('docs/milestone_3be_club_finance_reports_balance_sheet_frontend_display_scope_gate.md'));

        $this->assertStringContainsString('Milestone 3BF', $content);
        $this->assertStringContainsString('Frontend Display Implementation Authorization', $content);
        $this->assertStringContainsString('3BE does not itself authorize implementation', $content);
    }

    public function test_3be_adds_only_scope_doc_and_scope_test(): void
    {
        $this->assertFileExists(base_path('docs/milestone_3be_club_finance_reports_balance_sheet_frontend_display_scope_gate.md'));
        $this->assertFileExists(base_path('tests/Feature/Milestone3beClubFinanceReportsBalanceSheetFrontendDisplayScopeGateTest.php'));

        $this->assertFileDoesNotExist(app_path('Http/Controllers/Api/V1/Finance/ClubFinanceReportFrontendController.php'));
        $this->assertFileDoesNotExist(app_path('Http/Requests/Finance/ClubFinanceReportFrontendRequest.php'));
        $this->assertFileDoesNotExist(app_path('Services/Finance/ClubFinanceReportFrontendService.php'));
        $this->assertFileDoesNotExist(app_path('Models/ClubFinanceReportView.php'));
    }
}
