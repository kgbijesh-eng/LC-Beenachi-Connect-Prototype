<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3bhClubFinanceReportsBalanceSheetFrontendDisplayNarrowImplementationTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3bh_club_finance_reports_balance_sheet_frontend_display_narrow_implementation.md';

    public function test_3bh_document_exists_and_records_narrow_implementation_scope(): void
    {
        $this->assertFileExists($this->docPath);

        $document = file_get_contents($this->docPath);

        $this->assertStringContainsString('Milestone 3BH - Club Finance Reports and Balance Sheet Frontend Display Narrow Implementation', $document);
        $this->assertStringContainsString('narrow frontend implementation milestone', $document);
        $this->assertStringContainsString('app/page.tsx', $document);
        $this->assertStringContainsString('backend/tests/Feature/Milestone3bhClubFinanceReportsBalanceSheetFrontendDisplayNarrowImplementationTest.php', $document);
        $this->assertStringContainsString('Milestone 3BI - Club Finance Reports and Balance Sheet Frontend Display Validation and Closure.', $document);
    }

    public function test_existing_finance_report_routes_exist_before_frontend_uses_them(): void
    {
        $reportRoute = collect(Route::getRoutes())
            ->first(fn ($route): bool => $route->uri() === 'api/v1/clubs/{club}/finance/reports');

        $balanceSheetRoute = collect(Route::getRoutes())
            ->first(fn ($route): bool => $route->uri() === 'api/v1/clubs/{club}/finance/balance-sheet');

        $this->assertNotNull($reportRoute, 'Expected existing club finance reports route is missing.');
        $this->assertNotNull($balanceSheetRoute, 'Expected existing balance sheet route is missing.');
        $this->assertSame(['GET', 'HEAD'], $reportRoute->methods());
        $this->assertSame(['GET', 'HEAD'], $balanceSheetRoute->methods());
    }

    public function test_frontend_adds_club_finance_report_and_balance_sheet_sections_inside_finance_module(): void
    {
        $page = $this->frontendPage();
        $financeModule = $this->extractPageSection($page, 'function FinanceModule(', 'function ClubFinanceReportsPanel');

        $this->assertStringContainsString('type FinanceSection = "Club Account Statement" | "Member Ledger / Dues" | "Payments / Receipts" | "Club Finance Reports" | "Balance Sheet Summary"', $page);
        $this->assertStringContainsString('{ label: "Club Finance Reports", screen: "finance-module", financeSection: "Club Finance Reports" }', $page);
        $this->assertStringContainsString('{ label: "Balance Sheet Summary", screen: "finance-module", financeSection: "Balance Sheet Summary" }', $page);
        $this->assertStringContainsString('{section === "Club Finance Reports" && <ClubFinanceReportsPanel role={role} displayMode="reports" />}', $financeModule);
        $this->assertStringContainsString('{section === "Balance Sheet Summary" && <ClubFinanceReportsPanel role={role} displayMode="balance-sheet" />}', $financeModule);
    }

    public function test_frontend_uses_only_existing_club_finance_report_and_balance_sheet_apis(): void
    {
        $component = $this->clubFinanceReportsPanel();

        $this->assertStringContainsString('API_BASE_URL + "/clubs/" + loadedClubId + "/finance/reports"', $component);
        $this->assertStringContainsString('API_BASE_URL + "/clubs/" + loadedClubId + "/finance/balance-sheet"', $component);
        $this->assertStringContainsString('Accept: "application/json"', $component);
        $this->assertStringContainsString('Authorization: "Bearer " + token', $component);

        foreach ([
            '/finance/reports/outstanding-dues',
            '/finance/reports/collections',
            '/finance/reports/receipts',
            'method: "PATCH"',
            'method: "PUT"',
            'method: "DELETE"',
            '/payments"',
            '/receipt"',
        ] as $blockedText) {
            $this->assertStringNotContainsString($blockedText, $component);
        }
    }

    public function test_frontend_preserves_backend_as_source_of_truth_and_uses_actual_response_shape(): void
    {
        $page = $this->frontendPage();
        $component = $this->clubFinanceReportsPanel();

        foreach ([
            'type ClubFinanceReports = {',
            'club_finance_overview?:',
            'outstanding_dues_summary?:',
            'member_wise_dues_summary?: ClubFinanceReportMemberSummary[];',
            'payment_collection_summary?:',
            'receipt_summary?:',
            'type ClubBalanceSheet = {',
            'assets?:',
            'liabilities?:',
            'net_position?:',
            'source?: string | null;',
            'const loadedReport = reportPayload.finance_reports as ClubFinanceReports | undefined;',
            'const loadedBalanceSheet = balanceSheetPayload.balance_sheet as ClubBalanceSheet | undefined;',
        ] as $requiredText) {
            $this->assertStringContainsString($requiredText, $page);
        }

        $this->assertStringContainsString('This display uses only the existing club finance reports and balance sheet APIs. Backend values remain the source of truth.', $component);
        $this->assertStringContainsString('financeDisplayAmount', $page);
        $this->assertStringNotContainsString('reduce(', $component);
        $this->assertStringNotContainsString('totalDebits', $component);
        $this->assertStringNotContainsString('totalCredits', $component);
    }

    public function test_frontend_blocks_normal_member_from_ui_and_api_loading(): void
    {
        $component = $this->clubFinanceReportsPanel();

        $this->assertStringContainsString('const canViewClubFinanceReports = role === "president" || role === "secretary" || role === "treasurer";', $component);
        $this->assertStringContainsString('if (!canViewClubFinanceReports) {', $component);
        $this->assertStringContainsString('setReport(null);', $component);
        $this->assertStringContainsString('setBalanceSheet(null);', $component);
        $this->assertStringContainsString('Club-level finance reports and balance sheet are visible only to President, Secretary, and Treasurer roles.', $component);
        $this->assertStringContainsString('Members continue to see only member-safe finance information.', $component);

        $guardPosition = strpos($component, 'if (!canViewClubFinanceReports) {');
        $tokenPosition = strpos($component, 'const token = session.token;');

        $this->assertNotFalse($guardPosition);
        $this->assertNotFalse($tokenPosition);
        $this->assertStringContainsString('const { session } = useDevAuth();', $component);
        $this->assertLessThan($tokenPosition, $guardPosition, 'Member guard must run before authenticated session data is used for club-level report API calls.');
    }

    public function test_frontend_includes_required_safe_states(): void
    {
        $component = $this->clubFinanceReportsPanel();

        foreach ([
            'type ClubFinanceReportStatus = "loading" | "ready" | "empty" | "missing-club" | "unauthenticated" | "forbidden" | "error";',
            'Loading club finance report context...',
            'No finance report values are available yet.',
            'No active club context is available for this finance report.',
            'Please sign in again to view club finance reports.',
            'This role is not authorized to view club-level finance reports.',
            'Club finance reports are unavailable right now. Please try again later.',
            'Club finance report response is empty.',
            'Balance sheet response is empty.',
            'Unavailable',
        ] as $requiredText) {
            $this->assertStringContainsString($requiredText, $this->frontendPage());
        }
    }

    public function test_frontend_displays_successful_report_and_balance_sheet_values_without_inventing_fields(): void
    {
        $page = $this->frontendPage();

        foreach ([
            'function ClubFinanceReportDisplay({ report }: { report: ClubFinanceReports | null })',
            'Metric label="Total Dues" value={financeDisplayAmount(overview?.total_debit_amount)}',
            'Metric label="Amount Collected" value={financeDisplayAmount(overview?.total_credit_amount)}',
            'Metric label="Current Dues" value={financeDisplayAmount(overview?.current_dues_amount)}',
            'Metric label="Members with Dues" value={financeDisplayValue(outstanding?.members_with_dues_count)}',
            'Metric label="Total Outstanding" value={financeDisplayAmount(outstanding?.total_outstanding_amount)}',
            'Metric label="Posted Payment Total" value={financeDisplayAmount(collection?.posted_payment_total)}',
            'Metric label="Issued Receipt Count" value={financeDisplayValue(receipts?.issued_receipt_count ?? overview?.issued_receipt_count)}',
            'function ClubBalanceSheetDisplay({ balanceSheet }: { balanceSheet: ClubBalanceSheet | null })',
            'Metric label="Member Receivables" value={financeDisplayAmount(balanceSheet.assets?.member_receivables)}',
            'Metric label="Cash Collected" value={financeDisplayAmount(balanceSheet.assets?.cash_collected)}',
            'Metric label="Member Advances" value={financeDisplayAmount(balanceSheet.liabilities?.member_advances)}',
            'Metric label="Net Position" value={financeDisplayAmount(balanceSheet.net_position?.amount)}',
            'Field label="Source" value={financeDisplayValue(balanceSheet.source)}',
        ] as $requiredText) {
            $this->assertStringContainsString($requiredText, $page);
        }
    }

    public function test_3bh_blocks_backend_runtime_and_workflow_drift(): void
    {
        $document = file_get_contents($this->docPath);
        $component = $this->clubFinanceReportsPanel();

        foreach ([
            'Backend routes',
            'Backend controllers',
            'Backend services',
            'Backend resources',
            'Backend requests',
            'Backend models',
            'Migrations',
            'Seeders',
            'API behavior',
            'Export',
            'PDF',
            'Excel',
            'Reminders',
            'Payment mutation',
            'Receipt mutation',
            'Payment cancellation',
            'Payment reversal',
            'Allocation workflow',
            'Client-specific hardcoding',
        ] as $blockedText) {
            $this->assertStringContainsString($blockedText, $document);
        }

        foreach ([
            'download',
            'pdf',
            'excel',
            'reminder',
            'cancellation',
            'reversal',
            'allocation',
            'Rotary Central Bathery',
            'Lions Club Beenachi',
        ] as $blockedRuntimeText) {
            $this->assertStringNotContainsString($blockedRuntimeText, strtolower($component));
        }

        $this->assertFileDoesNotExist(app_path('Http/Controllers/Api/V1/Finance/ClubFinanceReportFrontendController.php'));
        $this->assertFileDoesNotExist(app_path('Http/Requests/Finance/ClubFinanceReportFrontendRequest.php'));
        $this->assertFileDoesNotExist(app_path('Services/Finance/ClubFinanceReportFrontendService.php'));
        $this->assertFileDoesNotExist(app_path('Models/ClubFinanceReportView.php'));
        $this->assertFileDoesNotExist(database_path('migrations/create_club_finance_report_views_table.php'));
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

        $this->assertNotFalse($start, "{$startMarker} component start not found.");
        $this->assertNotFalse($end, "{$endMarker} component boundary not found.");

        return substr($page, $start, $end - $start);
    }
}
