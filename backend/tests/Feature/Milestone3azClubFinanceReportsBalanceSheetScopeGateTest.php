<?php

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3azClubFinanceReportsBalanceSheetScopeGateTest extends TestCase
{
    public function test_3az_scope_document_exists_and_records_scope_gate_status(): void
    {
        $document = $this->scopeDocument();

        $this->assertStringContainsString('Milestone 3AZ - Club Finance Reports and Balance Sheet Scope Gate', $document);
        $this->assertStringContainsString('Status: SCOPE GATE ONLY', $document);
        $this->assertStringContainsString('docs/tests-only', $document);
        $this->assertStringContainsString('No reporting implementation is allowed in 3AZ.', $document);
    }

    public function test_3az_records_reason_and_closed_finance_foundation(): void
    {
        $document = $this->scopeDocument();

        $this->assertStringContainsString('Milestone 3AY closed the payment receipt frontend plateau', $document);
        $this->assertStringContainsString('read-only club finance reporting and balance sheet scoping', $document);

        $closedCapabilities = [
            'Member ledger entry foundation',
            'Member balance calculation',
            'Member balance read API',
            'Payment recording database foundation',
            'Payment recording service',
            'Payment recording API',
            'Receipt generation service',
            'Receipt JSON view API',
            'Receipt PDF download API',
            'Member finance summary read API',
            'Member finance summary frontend',
            'Payment recording frontend',
            'Payment receipt frontend',
        ];

        foreach ($closedCapabilities as $capability) {
            $this->assertStringContainsString($capability, $document);
        }
    }

    public function test_3az_defines_future_report_candidates_without_implementation(): void
    {
        $document = $this->scopeDocument();

        $futureReports = [
            'Outstanding dues summary',
            'Member dues ageing report',
            'Member receivables report',
            'Payment collection report',
            'Receipt register',
            'Member-wise ledger summary',
            'Club income and payment movement summary',
            'Basic club balance sheet view',
        ];

        foreach ($futureReports as $futureReport) {
            $this->assertStringContainsString($futureReport, $document);
        }

        $this->assertStringContainsString('The reports must be management reports for club operations.', $document);
        $this->assertStringContainsString('They must not be presented as audited statutory financial statements.', $document);
    }

    public function test_3az_defines_future_balance_sheet_boundary(): void
    {
        $document = $this->scopeDocument();

        $balanceSheetItems = [
            'Assets',
            'Liabilities',
            'Member receivables',
            'Cash or bank position if a future cash/bank source exists',
            'Net club fund position',
            'Generated date and reporting period',
        ];

        foreach ($balanceSheetItems as $item) {
            $this->assertStringContainsString($item, $document);
        }

        $this->assertStringContainsString('The first future balance sheet must be read-only.', $document);
        $this->assertStringContainsString('must not introduce accounting journal workflows', $document);
        $this->assertStringContainsString('bank reconciliation', $document);
        $this->assertStringContainsString('statutory audit filing', $document);
        $this->assertStringContainsString('tax filing', $document);
        $this->assertStringContainsString('external accounting integration', $document);
    }

    public function test_3az_preserves_backend_source_of_truth_rule(): void
    {
        $document = $this->scopeDocument();

        $this->assertStringContainsString('Reports must read from backend-approved sources only.', $document);
        $this->assertStringContainsString('Future reports must not calculate privileged finance data in the frontend.', $document);
        $this->assertStringContainsString('Future report calculations must be backend-owned', $document);

        $rules = [
            'Club access rules',
            'Member access rules',
            'Cross-club blocking',
            'Ledger as balance source of truth',
            'Payment and receipt authorization rules',
            'Read-only reporting behavior',
        ];

        foreach ($rules as $rule) {
            $this->assertStringContainsString($rule, $document);
        }
    }

    public function test_3az_defines_role_visibility_boundary(): void
    {
        $document = $this->scopeDocument();

        $this->assertStringContainsString('Normal Member:', $document);
        $this->assertStringContainsString('Must not access club-wide finance reports', $document);
        $this->assertStringContainsString('Must not access club-wide balance sheet', $document);
        $this->assertStringContainsString('May continue to access only their own finance summary, receipts, and receipt PDFs', $document);

        $this->assertStringContainsString('Treasurer:', $document);
        $this->assertStringContainsString('May be eligible for future club-wide finance reports', $document);
        $this->assertStringContainsString('May be eligible for future balance sheet view', $document);

        $this->assertStringContainsString('President/Admin:', $document);
        $this->assertStringContainsString('Secretary:', $document);
        $this->assertStringContainsString('Must remain within own club only', $document);
    }

    public function test_3az_names_future_api_direction_without_registering_routes(): void
    {
        $document = $this->scopeDocument();

        $futureRoutes = [
            'GET /api/v1/clubs/{club}/finance/reports/outstanding-dues',
            'GET /api/v1/clubs/{club}/finance/reports/collections',
            'GET /api/v1/clubs/{club}/finance/reports/receipts',
            'GET /api/v1/clubs/{club}/finance/balance-sheet',
        ];

        foreach ($futureRoutes as $futureRoute) {
            $this->assertStringContainsString($futureRoute, $document);
        }

        $this->assertStringContainsString('These routes are not authorized for implementation in 3AZ.', $document);

        $routeUris = collect(Route::getRoutes())->map(fn ($route) => $route->uri())->all();

        foreach ($routeUris as $uri) {
            $this->assertStringNotContainsString('finance/reports', $uri);
            $this->assertStringNotContainsString('balance-sheet', $uri);
            $this->assertStringNotContainsString('3az', $uri);
        }
    }

    public function test_3az_blocks_runtime_and_workflow_drift(): void
    {
        $document = $this->scopeDocument();

        $blockedItems = [
            'app/page.tsx',
            'backend routes',
            'backend controllers',
            'backend requests',
            'backend resources',
            'backend services',
            'backend models',
            'migrations',
            'seeders',
            'report implementation',
            'balance sheet implementation',
            'export implementation',
            'PDF report generation',
            'Excel import',
            'Excel export',
            'reminder implementation',
            'payment history implementation',
            'payment cancellation',
            'payment reversal',
            'special collection allocation workflow',
            'fellowship payment allocation workflow',
            'receipt sharing workflow',
            'audit dashboard implementation',
            'statutory accounting workflow',
            'bank reconciliation workflow',
            'tax filing workflow',
        ];

        foreach ($blockedItems as $blockedItem) {
            $this->assertStringContainsString($blockedItem, $document);
        }
    }

    public function test_3az_allows_only_scope_document_and_scope_test(): void
    {
        $document = $this->scopeDocument();

        $this->assertStringContainsString('Only these files are allowed:', $document);
        $this->assertStringContainsString('backend/docs/milestone_3az_club_finance_reports_balance_sheet_scope_gate.md', $document);
        $this->assertStringContainsString('backend/tests/Feature/Milestone3azClubFinanceReportsBalanceSheetScopeGateTest.php', $document);
    }

    public function test_3az_selects_3ba_read_api_authorization_without_implementation(): void
    {
        $document = $this->scopeDocument();

        $this->assertStringContainsString('Selected next milestone:', $document);
        $this->assertStringContainsString('Milestone 3BA - Club Finance Reports and Balance Sheet Read API Authorization', $document);
        $this->assertStringContainsString('3BA must be docs/tests-only.', $document);
        $this->assertStringContainsString('may authorize a later narrow read API implementation', $document);
        $this->assertStringContainsString('must not implement reports, balance sheet runtime logic, exports, reminders, allocation workflows, cancellation, reversal, Excel import, or frontend screens', $document);
    }

    private function scopeDocument(): string
    {
        $path = base_path('docs/milestone_3az_club_finance_reports_balance_sheet_scope_gate.md');

        $this->assertFileExists($path);

        return file_get_contents($path);
    }
}
