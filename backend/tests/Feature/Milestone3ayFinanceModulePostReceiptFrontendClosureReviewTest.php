<?php

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3ayFinanceModulePostReceiptFrontendClosureReviewTest extends TestCase
{
    public function test_3ay_review_document_exists_and_records_status(): void
    {
        $document = $this->reviewDocument();

        $this->assertStringContainsString('Milestone 3AY - Finance Module Post Payment Receipt Frontend Closure Review and Next Scope Decision', $document);
        $this->assertStringContainsString('Status: CLOSED BY REVIEW', $document);
        $this->assertStringContainsString('Milestone 3AX - Payment Receipt Frontend Validation and Closure', $document);
        $this->assertStringContainsString('0c48bb8065d8dd84d3923c7e71e64ed48b2285b1', $document);
        $this->assertStringContainsString('Frontend build passed', $document);
        $this->assertStringContainsString('6 passed, 70 assertions', $document);
        $this->assertStringContainsString('481 passed, 4697 assertions', $document);
    }

    public function test_3ay_summarizes_closed_finance_capabilities(): void
    {
        $document = $this->reviewDocument();

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

    public function test_3ay_records_current_role_based_finance_state(): void
    {
        $document = $this->reviewDocument();

        $this->assertStringContainsString('Normal Member:', $document);
        $this->assertStringContainsString('Can view own dues and own finance summary', $document);
        $this->assertStringContainsString('Can download own issued receipt PDF', $document);
        $this->assertStringContainsString('Cannot record payments', $document);
        $this->assertStringContainsString('Cannot generate receipts', $document);
        $this->assertStringContainsString('Cannot view other members', $document);

        $this->assertStringContainsString('President/Admin, Secretary, Treasurer:', $document);
        $this->assertStringContainsString('Can record member payments through the existing backend payment API', $document);
        $this->assertStringContainsString('Can generate receipts through the existing backend receipt API', $document);
        $this->assertStringContainsString('Can download issued receipt PDFs through existing backend receipt PDF API', $document);
        $this->assertStringContainsString('Must remain blocked from cross-club access', $document);
    }

    public function test_3ay_preserves_backend_source_of_truth_rules(): void
    {
        $document = $this->reviewDocument();

        $this->assertStringContainsString('The frontend remains a client of existing backend APIs.', $document);
        $this->assertStringContainsString('The backend remains the source of truth', $document);
        $this->assertStringContainsString('No frontend screen may bypass backend authorization.', $document);

        $sourceOfTruthItems = [
            'Club access',
            'Member access',
            'Payment recording',
            'Receipt generation',
            'Receipt visibility',
            'Receipt PDF authorization',
            'Cross-club blocking',
            'Ledger balance calculation',
        ];

        foreach ($sourceOfTruthItems as $item) {
            $this->assertStringContainsString($item, $document);
        }
    }

    public function test_existing_payment_and_receipt_routes_remain_available_without_new_review_routes(): void
    {
        $routeUris = collect(Route::getRoutes())->map(fn ($route) => $route->uri())->all();

        $this->assertContains('api/v1/clubs/{club}/members/{member}/payments', $routeUris);
        $this->assertContains('api/v1/clubs/{club}/members/{member}/payments/{payment}/receipt', $routeUris);
        $this->assertContains('api/v1/clubs/{club}/members/{member}/payments/{payment}/receipt/pdf', $routeUris);
        $this->assertContains('api/v1/clubs/{club}/members/{member}/finance-summary', $routeUris);

        foreach ($routeUris as $uri) {
            $this->assertStringNotContainsString('3ay', $uri);
            $this->assertStringNotContainsString('balance-sheet', $uri);
            $this->assertStringNotContainsString('finance-reports', $uri);
            $this->assertStringNotContainsString('reminders', $uri);
            $this->assertStringNotContainsString('excel', $uri);
        }
    }

    public function test_3ay_records_remaining_gaps_without_implementing_them(): void
    {
        $document = $this->reviewDocument();

        $remainingGaps = [
            'Payment history implementation',
            'Club finance reports',
            'Club balance sheet',
            'Outstanding dues report exports',
            'Member reminder workflow',
            'Excel import',
            'Special collection allocation workflow',
            'Fellowship payment allocation workflow',
            'Payment cancellation',
            'Payment reversal',
            'Receipt sharing workflow',
            'Audit dashboard',
        ];

        foreach ($remainingGaps as $gap) {
            $this->assertStringContainsString($gap, $document);
        }
    }

    public function test_3ay_selects_3az_reports_and_balance_sheet_scope_gate_without_implementation(): void
    {
        $document = $this->reviewDocument();

        $this->assertStringContainsString('Selected next milestone:', $document);
        $this->assertStringContainsString('Milestone 3AZ - Club Finance Reports and Balance Sheet Scope Gate', $document);
        $this->assertStringContainsString('The next clean direction should be reporting-first, not mutation-first.', $document);
        $this->assertStringContainsString('Milestone 3AZ must be docs/tests-only.', $document);
        $this->assertStringContainsString('must not implement reports, balance sheet runtime logic, exports, reminders, cancellation, reversal, allocation workflows, or Excel import', $document);
    }

    public function test_3ay_is_docs_tests_only_and_blocks_runtime_drift(): void
    {
        $document = $this->reviewDocument();

        $this->assertStringContainsString('3AY is docs/tests-only.', $document);
        $this->assertStringContainsString('backend/docs/milestone_3ay_finance_module_post_receipt_frontend_closure_review.md', $document);
        $this->assertStringContainsString('backend/tests/Feature/Milestone3ayFinanceModulePostReceiptFrontendClosureReviewTest.php', $document);

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
            'payment history implementation',
            'report implementation',
            'balance sheet implementation',
            'reminder implementation',
            'Excel import',
            'special collection allocation workflow',
            'fellowship payment allocation workflow',
            'payment cancellation',
            'payment reversal',
            'receipt sharing workflow',
        ];

        foreach ($blockedItems as $blockedItem) {
            $this->assertStringContainsString($blockedItem, $document);
        }
    }

    private function reviewDocument(): string
    {
        $path = base_path('docs/milestone_3ay_finance_module_post_receipt_frontend_closure_review.md');

        $this->assertFileExists($path);

        return file_get_contents($path);
    }
}
