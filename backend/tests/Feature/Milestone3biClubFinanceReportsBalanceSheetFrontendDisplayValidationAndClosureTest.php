<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3biClubFinanceReportsBalanceSheetFrontendDisplayValidationAndClosureTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3bi_club_finance_reports_balance_sheet_frontend_display_validation_and_closure.md';

    public function test_3bi_closure_document_exists_and_is_docs_tests_only(): void
    {
        $document = $this->closureDocument();

        $this->assertStringContainsString('Milestone 3BI - Club Finance Reports and Balance Sheet Frontend Display Validation and Closure', $document);
        $this->assertStringContainsString('docs/tests-only validation and closure milestone', $document);
        $this->assertStringContainsString('It validates and closes Milestone 3BH.', $document);
        $this->assertStringContainsString('It does not change frontend runtime behavior.', $document);
        $this->assertStringContainsString('It does not change backend runtime behavior.', $document);
    }

    public function test_3bi_allows_only_closure_doc_and_validation_test(): void
    {
        $document = $this->closureDocument();

        $this->assertStringContainsString('backend/docs/milestone_3bi_club_finance_reports_balance_sheet_frontend_display_validation_and_closure.md', $document);
        $this->assertStringContainsString('backend/tests/Feature/Milestone3biClubFinanceReportsBalanceSheetFrontendDisplayValidationAndClosureTest.php', $document);

        foreach ([
            'app/page.tsx',
            'backend routes',
            'backend controllers',
            'backend services',
            'backend resources',
            'backend requests',
            'backend models',
            'backend middleware',
            'backend migrations',
            'backend seeders',
            'backend config',
            'API behavior',
            'export files',
            'PDF files',
            'Excel files',
            'reminder files',
            'payment mutation files',
            'receipt mutation files',
            'cancellation workflow files',
            'reversal workflow files',
            'allocation workflow files',
            'deployment files',
            'SaaS files',
            'client-specific hardcoding',
        ] as $blockedText) {
            $this->assertStringContainsString($blockedText, $document);
        }
    }

    public function test_3bh_three_file_implementation_artifacts_remain_present(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'app/page.tsx',
            'backend/docs/milestone_3bh_club_finance_reports_balance_sheet_frontend_display_narrow_implementation.md',
            'backend/tests/Feature/Milestone3bhClubFinanceReportsBalanceSheetFrontendDisplayNarrowImplementationTest.php',
        ] as $requiredText) {
            $this->assertStringContainsString($requiredText, $document);
        }

        $this->assertFileExists(base_path('../app/page.tsx'));
        $this->assertFileExists(base_path('docs/milestone_3bh_club_finance_reports_balance_sheet_frontend_display_narrow_implementation.md'));
        $this->assertFileExists(base_path('tests/Feature/Milestone3bhClubFinanceReportsBalanceSheetFrontendDisplayNarrowImplementationTest.php'));
    }

    public function test_existing_backend_routes_remain_exact_get_only_routes(): void
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

    public function test_frontend_display_sections_remain_present_inside_finance_module(): void
    {
        $page = $this->frontendPage();
        $financeModule = $this->extractPageSection($page, 'function FinanceModule(', 'function ClubFinanceReportsPanel');

        $this->assertStringContainsString('"Club Finance Reports" | "Balance Sheet Summary"', $page);
        $this->assertStringContainsString('{ label: "Club Finance Reports", screen: "finance-module", financeSection: "Club Finance Reports" }', $page);
        $this->assertStringContainsString('{ label: "Balance Sheet Summary", screen: "finance-module", financeSection: "Balance Sheet Summary" }', $page);
        $this->assertStringContainsString('{section === "Club Finance Reports" && <ClubFinanceReportsPanel role={role} displayMode="reports" />}', $financeModule);
        $this->assertStringContainsString('{section === "Balance Sheet Summary" && <ClubFinanceReportsPanel role={role} displayMode="balance-sheet" />}', $financeModule);
    }

    public function test_frontend_uses_only_existing_report_and_balance_sheet_apis(): void
    {
        $component = $this->clubFinanceReportsPanel();

        $this->assertStringContainsString('API_BASE_URL + "/clubs/" + loadedClubId + "/finance/reports"', $component);
        $this->assertStringContainsString('API_BASE_URL + "/clubs/" + loadedClubId + "/finance/balance-sheet"', $component);

        foreach ([
            '/finance/reports/outstanding-dues',
            '/finance/reports/collections',
            '/finance/reports/receipts',
            'method: "PATCH"',
            'method: "PUT"',
            'method: "DELETE"',
            '/members/" + selectedMemberId + "/payments"',
            '/receipt"',
        ] as $blockedText) {
            $this->assertStringNotContainsString($blockedText, $component);
        }
    }

    public function test_role_visibility_matches_3bh_and_does_not_broaden_access(): void
    {
        $document = $this->closureDocument();
        $component = $this->clubFinanceReportsPanel();

        $this->assertStringContainsString('president / president-admin where already existing', $document);
        $this->assertStringContainsString('secretary', $document);
        $this->assertStringContainsString('treasurer', $document);
        $this->assertStringContainsString('3BI does not broaden access.', $document);
        $this->assertStringContainsString('3BI does not introduce any new Admin-only finance access.', $document);

        $this->assertStringContainsString('const canViewClubFinanceReports = role === "president" || role === "secretary" || role === "treasurer";', $component);
        $this->assertStringNotContainsString('role === "admin"', $component);
        $this->assertStringNotContainsString('role === "member"', $component);
        $this->assertStringContainsString('Club-level finance reports and balance sheet are visible only to President, Secretary, and Treasurer roles.', $component);
    }

    public function test_normal_member_does_not_trigger_club_level_report_loading_or_hidden_state(): void
    {
        $component = $this->clubFinanceReportsPanel();

        $guardPosition = strpos($component, 'if (!canViewClubFinanceReports) {');
        $tokenPosition = strpos($component, 'const token = session.token;');
        $reportFetchPosition = strpos($component, 'API_BASE_URL + "/clubs/" + loadedClubId + "/finance/reports"');
        $balanceSheetFetchPosition = strpos($component, 'API_BASE_URL + "/clubs/" + loadedClubId + "/finance/balance-sheet"');

        $this->assertNotFalse($guardPosition);
        $this->assertNotFalse($tokenPosition);
        $this->assertNotFalse($reportFetchPosition);
        $this->assertNotFalse($balanceSheetFetchPosition);

        $this->assertStringContainsString('const { session } = useDevAuth();', $component);
        $this->assertLessThan($tokenPosition, $guardPosition);
        $this->assertLessThan($reportFetchPosition, $guardPosition);
        $this->assertLessThan($balanceSheetFetchPosition, $guardPosition);
        $this->assertStringContainsString('setReport(null);', $component);
        $this->assertStringContainsString('setBalanceSheet(null);', $component);
        $this->assertStringContainsString('Members continue to see only member-safe finance information.', $component);
    }

    public function test_safe_states_and_success_displays_remain_present(): void
    {
        $page = $this->frontendPage();

        foreach ([
            'type ClubFinanceReportStatus = "loading" | "ready" | "empty" | "missing-club" | "unauthenticated" | "forbidden" | "error";',
            'Loading club finance report context...',
            'No finance report values are available yet.',
            'No active club context is available for this finance report.',
            'Please sign in again to view club finance reports.',
            'This role is not authorized to view club-level finance reports.',
            'Club finance reports are unavailable right now. Please try again later.',
            'ClubFinanceReportDisplay report={report}',
            'ClubBalanceSheetDisplay balanceSheet={balanceSheet}',
            'Club finance report response is empty.',
            'Balance sheet response is empty.',
            'Unavailable',
        ] as $requiredText) {
            $this->assertStringContainsString($requiredText, $page);
        }
    }

    public function test_frontend_uses_backend_response_shape_without_inventing_accounting_calculations(): void
    {
        $page = $this->frontendPage();
        $component = $this->clubFinanceReportsPanel();
        $reportDisplay = $this->extractPageSection($page, 'function ClubFinanceReportDisplay', 'function ClubBalanceSheetDisplay');
        $balanceSheetDisplay = $this->extractPageSection($page, 'function ClubBalanceSheetDisplay', 'function ClubAccountStatement');

        foreach ([
            'const loadedReport = reportPayload.finance_reports as ClubFinanceReports | undefined;',
            'const loadedBalanceSheet = balanceSheetPayload.balance_sheet as ClubBalanceSheet | undefined;',
            'club_finance_overview?:',
            'outstanding_dues_summary?:',
            'member_wise_dues_summary?: ClubFinanceReportMemberSummary[];',
            'payment_collection_summary?:',
            'receipt_summary?:',
            'assets?:',
            'liabilities?:',
            'net_position?:',
            'source?: string | null;',
        ] as $requiredText) {
            $this->assertStringContainsString($requiredText, $page);
        }

        foreach ([
            'total_debit_amount',
            'total_credit_amount',
            'current_dues_amount',
            'members_with_dues_count',
            'total_outstanding_amount',
            'posted_payment_total',
            'issued_receipt_count',
            'member_receivables',
            'cash_collected',
            'member_advances',
            'net_position',
        ] as $backendField) {
            $this->assertStringContainsString($backendField, $page);
        }

        foreach ([$component, $reportDisplay, $balanceSheetDisplay] as $section) {
            $this->assertStringNotContainsString('reduce(', $section);
            $this->assertStringNotContainsString('totalDebits', $section);
            $this->assertStringNotContainsString('totalCredits', $section);
            $this->assertStringNotContainsString('calculate(', strtolower($section));
            $this->assertStringNotContainsString('recalculate', strtolower($section));
            $this->assertStringNotContainsString('audited', strtolower($section));
        }
    }

    public function test_no_backend_runtime_or_workflow_drift_files_are_introduced(): void
    {
        foreach ([
            app_path('Http/Controllers/Api/V1/Finance/ClubFinanceReportFrontendController.php'),
            app_path('Http/Requests/Finance/ClubFinanceReportFrontendRequest.php'),
            app_path('Services/Finance/ClubFinanceReportFrontendService.php'),
            app_path('Models/ClubFinanceReportView.php'),
            app_path('Http/Middleware/EnsureClubFinanceReportFrontend.php'),
            database_path('migrations/create_club_finance_report_views_table.php'),
            database_path('seeders/ClubFinanceReportFrontendSeeder.php'),
            base_path('config/club_finance_reports.php'),
            app_path('Services/Finance/ClubFinanceReportExportService.php'),
            app_path('Services/Finance/ClubFinanceReportPdfService.php'),
            app_path('Services/Finance/ClubFinanceReportExcelService.php'),
            app_path('Services/Finance/ClubFinanceReportReminderService.php'),
            app_path('Services/Finance/PaymentCancellationService.php'),
            app_path('Services/Finance/PaymentReversalService.php'),
            app_path('Services/Finance/PaymentAllocationService.php'),
        ] as $blockedPath) {
            $this->assertFileDoesNotExist($blockedPath);
        }
    }

    public function test_3bi_closes_frontend_display_lane_and_requires_post_closure_decision(): void
    {
        $document = $this->closureDocument();

        $this->assertStringContainsString('Milestone 3BI closes the Club Finance Reports and Balance Sheet Frontend Display lane.', $document);
        $this->assertStringContainsString('After 3BI closes, the next step should be a post-closure roadmap decision before any new implementation milestone starts.', $document);
    }

    private function closureDocument(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }

    private function frontendPage(): string
    {
        $path = base_path('../app/page.tsx');

        $this->assertFileExists($path);

        return file_get_contents($path);
    }

    private function clubFinanceReportsPanel(): string
    {
        return $this->extractPageSection(
            $this->frontendPage(),
            'function ClubFinanceReportsPanel',
            'function ClubFinanceReportDisplay'
        );
    }

    private function extractPageSection(string $page, string $startMarker, string $endMarker): string
    {
        $start = strpos($page, $startMarker);
        $end = strpos($page, $endMarker, $start === false ? 0 : $start);

        $this->assertNotFalse($start, "{$startMarker} start marker not found.");
        $this->assertNotFalse($end, "{$endMarker} end marker not found.");

        return substr($page, $start, $end - $start);
    }
}
