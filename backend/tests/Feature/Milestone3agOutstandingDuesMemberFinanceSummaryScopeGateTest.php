<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3agOutstandingDuesMemberFinanceSummaryScopeGateTest extends TestCase
{
    public function test_milestone_3ag_scope_document_exists_and_records_decision(): void
    {
        $path = base_path('docs/milestone_3ag_outstanding_dues_member_finance_summary_scope_gate.md');

        $this->assertFileExists($path);

        $contents = File::get($path);

        $this->assertStringContainsString('Milestone 3AG — Outstanding Dues and Member Finance Summary Scope Gate', $contents);
        $this->assertStringContainsString('docs/tests-only', $contents);
        $this->assertStringContainsString('OUTSTANDING_DUES_MEMBER_FINANCE_SUMMARY_SCOPE_DEFINED', $contents);
        $this->assertStringContainsString('No finance summary implementation is allowed in this milestone', $contents);
    }

    public function test_milestone_3ag_recommends_api_first_read_only_next_step(): void
    {
        $contents = File::get(base_path('docs/milestone_3ag_outstanding_dues_member_finance_summary_scope_gate.md'));

        $this->assertStringContainsString('The next implementation should be API-first and read-only', $contents);
        $this->assertStringContainsString('Milestone 3AH — Outstanding Dues and Member Finance Summary Read API Authorization', $contents);
        $this->assertStringContainsString('No UI should be added before the read API contract is stable', $contents);
        $this->assertStringContainsString('The API should be authorized separately before any implementation', $contents);
    }

    public function test_milestone_3ag_defines_normal_member_privacy_boundary(): void
    {
        $contents = File::get(base_path('docs/milestone_3ag_outstanding_dues_member_finance_summary_scope_gate.md'));

        $this->assertStringContainsString('Normal Members should see only their own finance summary', $contents);
        $this->assertStringContainsString('Normal Member view should use member-friendly wording', $contents);
        $this->assertStringContainsString('current dues amount', $contents);
        $this->assertStringContainsString('last payment amount', $contents);
        $this->assertStringContainsString('pending dues items', $contents);
        $this->assertStringContainsString('receipt availability for own payments', $contents);
        $this->assertStringContainsString("other members' dues", $contents);
        $this->assertStringContainsString("other members' payment status", $contents);
        $this->assertStringContainsString('club-wide outstanding dues', $contents);
        $this->assertStringContainsString('ledger debit wording', $contents);
        $this->assertStringContainsString('ledger credit wording', $contents);
    }

    public function test_milestone_3ag_defines_pst_visibility_boundaries(): void
    {
        $contents = File::get(base_path('docs/milestone_3ag_outstanding_dues_member_finance_summary_scope_gate.md'));

        $this->assertStringContainsString('Treasurer should be able to view finance summaries for members within the Treasurer\'s own club', $contents);
        $this->assertStringContainsString('Secretary should be able to view member finance status for coordination and follow-up purposes', $contents);
        $this->assertStringContainsString('President/Admin should be able to view club-level finance summaries and selected member finance summaries within the same club', $contents);
        $this->assertStringContainsString('Treasurer access must remain club-scoped and fail closed across clubs', $contents);
        $this->assertStringContainsString('President/Admin access must remain club-scoped and fail closed across clubs', $contents);
        $this->assertStringContainsString('Secretary view should not become an accounting control surface', $contents);
    }

    public function test_milestone_3ag_defines_allowed_summary_fields_and_pending_items(): void
    {
        $contents = File::get(base_path('docs/milestone_3ag_outstanding_dues_member_finance_summary_scope_gate.md'));

        $summaryFields = [
            'member_id',
            'member_code',
            'member_name',
            'current_dues_amount',
            'current_dues_label',
            'last_payment_amount',
            'last_payment_date',
            'pending_items',
            'receipt_available',
            'payment_status',
            'generated_at',
        ];

        foreach ($summaryFields as $field) {
            $this->assertStringContainsString($field, $contents);
        }

        $pendingItems = [
            'monthly dues',
            'membership fee',
            'fellowship contribution',
            'special collection',
            'project share',
            'fine or adjustment if separately authorized later',
        ];

        foreach ($pendingItems as $item) {
            $this->assertStringContainsString($item, $contents);
        }
    }

    public function test_milestone_3ag_defines_member_friendly_wording(): void
    {
        $contents = File::get(base_path('docs/milestone_3ag_outstanding_dues_member_finance_summary_scope_gate.md'));

        $preferredWording = [
            'Current Dues',
            'Pending Items',
            'Last Payment',
            'Receipt Available',
            'Paid',
            'Partly Paid',
            'Pending',
            'Advance',
        ];

        foreach ($preferredWording as $wording) {
            $this->assertStringContainsString($wording, $contents);
        }

        $blockedWording = [
            'debit',
            'credit',
            'ledger posting',
            'receivable',
            'journal',
            'accounting entry',
            'reversal',
            'audit trail',
        ];

        foreach ($blockedWording as $wording) {
            $this->assertStringContainsString($wording, $contents);
        }
    }

    public function test_milestone_3ag_defines_privacy_and_access_rules(): void
    {
        $contents = File::get(base_path('docs/milestone_3ag_outstanding_dues_member_finance_summary_scope_gate.md'));

        $accessRules = [
            'unauthenticated access fails closed',
            'normal member can view only own finance summary',
            'Treasurer can view same-club member finance summaries',
            'Secretary can view same-club member finance summaries',
            'President/Admin can view same-club member and club-level finance summaries',
            'cross-club member finance access fails closed',
            'finance summary remains read-only',
            'route must not mutate payments, receipts, or ledger entries',
        ];

        foreach ($accessRules as $rule) {
            $this->assertStringContainsString($rule, $contents);
        }
    }

    public function test_milestone_3ag_allows_3ai_finance_summary_routes_but_blocks_other_future_routes(): void
    {
        $routeUris = collect(Route::getRoutes())
            ->map(fn ($route) => $route->uri())
            ->implode("\n");

        $blockedRouteFragments = [
            'outstanding-dues',
            'treasurer-finance',
            'secretary-finance',
            'president-finance',
            'special-collections',
            'fellowship-allocations',
            'payment-corrections',
            'payment-reversals',
            'payment-cancellations',
            'dues-reminders',
            'finance-reports',
            'excel-import',
            'excel-export',
        ];

        foreach ($blockedRouteFragments as $fragment) {
            $this->assertStringNotContainsString(
                $fragment,
                $routeUris,
                "3AG must not register non-3AI route fragment: {$fragment}"
            );
        }
    }

    public function test_milestone_3ag_allows_3ai_finance_summary_files_but_blocks_other_future_files(): void
    {
        $appFilenames = collect(File::allFiles(base_path('app')))
            ->map(fn ($file) => $file->getFilename())
            ->all();

        $blockedFilenames = [
            'OutstandingDuesController.php',
            'FinanceSummaryController.php',
            'TreasurerFinanceController.php',
            'SecretaryFinanceController.php',
            'PresidentFinanceController.php',
            'OutstandingDuesService.php',
            'FinanceSummaryService.php',
            'SpecialCollectionController.php',
            'SpecialCollectionService.php',
            'FellowshipPaymentAllocationController.php',
            'FellowshipPaymentAllocationService.php',
            'PaymentCorrectionController.php',
            'PaymentCorrectionService.php',
            'PaymentReversalController.php',
            'PaymentReversalService.php',
            'PaymentCancellationController.php',
            'PaymentCancellationService.php',
            'DuesReminderController.php',
            'DuesReminderService.php',
            'FinanceReportController.php',
            'FinanceReportService.php',
            'ExcelImportController.php',
            'ExcelExportController.php',
        ];

        foreach ($blockedFilenames as $filename) {
            $this->assertNotContains(
                $filename,
                $appFilenames,
                "3AG must not add non-3AI implementation file: {$filename}"
            );
        }
    }

    public function test_milestone_3ag_blocks_all_implementation_scope(): void
    {
        $contents = File::get(base_path('docs/milestone_3ag_outstanding_dues_member_finance_summary_scope_gate.md'));

        $blockedScopes = [
            'routes',
            'controllers',
            'services',
            'requests',
            'resources',
            'models',
            'migrations',
            'frontend finance screens',
            'outstanding dues read API',
            'member finance summary read API',
            'Treasurer finance dashboard',
            'Secretary finance dashboard',
            'President finance dashboard',
            'special collection creation workflow',
            'fellowship payment allocation workflow',
            'payment correction workflow',
            'payment reversal workflow',
            'payment cancellation workflow',
            'reminder implementation',
            'finance reports',
            'Excel import/export',
            'PDF changes',
            'payment mutation',
            'receipt mutation',
            'ledger mutation',
        ];

        foreach ($blockedScopes as $scope) {
            $this->assertStringContainsString($scope, $contents);
        }
    }

    public function test_milestone_3ag_adds_only_scope_document_and_scope_test(): void
    {
        $document = base_path('docs/milestone_3ag_outstanding_dues_member_finance_summary_scope_gate.md');
        $test = base_path('tests/Feature/Milestone3agOutstandingDuesMemberFinanceSummaryScopeGateTest.php');

        $this->assertFileExists($document);
        $this->assertFileExists($test);

        $contents = File::get($document);

        $this->assertStringContainsString('Only these files are allowed for this milestone', $contents);
        $this->assertStringContainsString('docs/milestone_3ag_outstanding_dues_member_finance_summary_scope_gate.md', $contents);
        $this->assertStringContainsString('tests/Feature/Milestone3agOutstandingDuesMemberFinanceSummaryScopeGateTest.php', $contents);
    }
}
