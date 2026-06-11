<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3bqPaymentHistoryFrontendValidationAndClosureTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3bq_payment_history_frontend_validation_and_closure.md';

    public function test_3bq_document_exists_and_is_docs_tests_only_closure(): void
    {
        $document = $this->closureDocument();

        $this->assertStringContainsString('Milestone 3BQ - Payment History Frontend Validation and Closure', $document);
        $this->assertStringContainsString('docs/tests-only validation and closure milestone', $document);
        $this->assertStringContainsString('It validates and closes Milestone 3BP.', $document);
        $this->assertStringContainsString('It does not change frontend runtime behavior.', $document);
        $this->assertStringContainsString('It does not change backend runtime behavior.', $document);
        $this->assertStringContainsString('It does not change API behavior.', $document);
    }

    public function test_3bq_allows_only_closure_doc_and_validation_test(): void
    {
        $document = $this->closureDocument();

        $this->assertStringContainsString('backend/docs/milestone_3bq_payment_history_frontend_validation_and_closure.md', $document);
        $this->assertStringContainsString('backend/tests/Feature/Milestone3bqPaymentHistoryFrontendValidationAndClosureTest.php', $document);

        foreach ([
            'app/page.tsx',
            'frontend runtime files',
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
            'payment history backend implementation files',
            'payment recording files',
            'receipt files',
            'PDF files',
            'export files',
            'Excel files',
            'reminder files',
            'cancellation workflow files',
            'reversal workflow files',
            'allocation workflow files',
            'deployment files',
            'SaaS files',
            'client-specific hardcoding files',
        ] as $blockedText) {
            $this->assertStringContainsString($blockedText, $document);
        }
    }

    public function test_3bp_artifacts_remain_present_for_frontend_closure(): void
    {
        foreach ([
            base_path('docs/milestone_3bp_payment_history_frontend_narrow_implementation.md'),
            base_path('tests/Feature/Milestone3bpPaymentHistoryFrontendNarrowImplementationTest.php'),
            base_path('../app/page.tsx'),
        ] as $requiredPath) {
            $this->assertFileExists($requiredPath);
        }
    }

    public function test_payment_history_backend_routes_remain_exact_get_only_routes(): void
    {
        $routes = collect(Route::getRoutes())
            ->filter(fn ($route): bool => str_contains($route->uri(), 'payments/history'))
            ->map(fn ($route): string => implode('|', $route->methods()).' '.$route->uri())
            ->sort()
            ->values()
            ->all();

        $this->assertSame([
            'GET|HEAD api/v1/clubs/{club}/members/me/payments/history',
            'GET|HEAD api/v1/clubs/{club}/members/{member}/payments/history',
        ], $routes);
    }

    public function test_frontend_payment_history_uses_only_existing_3bm_payment_history_get_routes(): void
    {
        $paymentHistoryBlock = $this->paymentHistoryBlock($this->frontendPage());

        foreach ([
            'API_BASE_URL + "/clubs/" + currentClubId + "/members/" + memberId + "/payments/history?per_page=10&page=" + requestedPage',
            'API_BASE_URL + "/clubs/" + currentClubId + "/members/me/payments/history?per_page=10&page=" + requestedPage',
            'This screen uses only the existing payment history GET APIs.',
            'POST /api/v1/auth/login',
            'GET /api/v1/auth/me',
            'GET /api/v1/clubs/{club}/members',
        ] as $requiredText) {
            $this->assertStringContainsString($requiredText, $this->closureDocument().$paymentHistoryBlock);
        }

        foreach ([
            '"/clubs/" + currentClubId + "/payments/history',
            '"/payments/history",',
            '"/payments/history/"',
        ] as $blockedText) {
            $this->assertStringNotContainsString($blockedText, $paymentHistoryBlock);
        }
    }

    public function test_frontend_closes_normal_member_own_history_only_behavior(): void
    {
        $paymentHistoryBlock = $this->paymentHistoryBlock($this->frontendPage());

        foreach ([
            'LOCAL_MEMBER_FINANCE_LOGIN',
            'Member view loads only your own payment history.',
            'Other members\' payment history is not requested.',
            'Your payment history loaded from existing backend API.',
            'setHistoryMembers([])',
            'setSelectedHistoryMemberId(null)',
        ] as $requiredText) {
            $this->assertStringContainsString($requiredText, $paymentHistoryBlock);
        }
    }

    public function test_frontend_closes_officer_selected_member_history_behavior(): void
    {
        $paymentHistoryBlock = $this->paymentHistoryBlock($this->frontendPage());

        foreach ([
            'canUseSelectedMemberHistory = role === "president" || role === "secretary" || role === "treasurer"',
            'LOCAL_PAYMENT_RECORDING_LOGINS',
            '"/members/" + memberId + "/payments/history?per_page=10&page="',
            'Selected member in active club',
            'Selected-member payment history loaded from existing backend API.',
            'setHistoryMembers(activeMembers)',
            'setSelectedHistoryMemberId(defaultMemberId)',
            'updateSelectedHistoryMember',
        ] as $requiredText) {
            $this->assertStringContainsString($requiredText, $paymentHistoryBlock);
        }
    }

    public function test_frontend_displays_only_safe_payment_metadata_and_unavailable_fallbacks(): void
    {
        $paymentHistoryBlock = $this->paymentHistoryBlock($this->frontendPage());

        foreach ([
            'Payment ID:',
            'Payment Date:',
            'Member:',
            'Member Code:',
            'Amount:',
            'Payment Mode:',
            'Status:',
            'Receipt:',
            'Receipt Status:',
            'Recorded By:',
            'Created At:',
            'financeDisplayValue',
            'financeDisplayAmount',
            'Unavailable',
            'No payment history records are available yet.',
        ] as $safeText) {
            $this->assertStringContainsString($safeText, $paymentHistoryBlock);
        }

        foreach ([
            'raw notes',
            'internal remarks',
            'cancellation_reason',
            'cancelled_at',
            'reversal',
            'allocation',
            'ledger_entry_id',
            'receipt notes',
            'audit',
            'balance',
            'total_due',
            'total balance',
        ] as $blockedText) {
            $this->assertStringNotContainsString($blockedText, $paymentHistoryBlock);
        }
    }

    public function test_frontend_closes_loading_empty_error_and_success_states(): void
    {
        $paymentHistoryBlock = $this->paymentHistoryBlock($this->frontendPage());

        foreach ([
            'PaymentHistoryStatus',
            'initializing',
            'loading',
            'success',
            'empty',
            'error',
            'Loading payment history access context...',
            'Loading payment history...',
            'No payment history records are available yet.',
            'No active club context is available for payment history.',
            'Please sign in again to view payment history.',
            'This role is not authorized to view that payment history.',
            'Payment history is unavailable right now.',
            'Payment history context unavailable.',
        ] as $stateText) {
            $this->assertStringContainsString($stateText, $paymentHistoryBlock);
        }
    }

    public function test_frontend_uses_backend_pagination_without_filters_or_all_member_list(): void
    {
        $paymentHistoryBlock = $this->paymentHistoryBlock($this->frontendPage());

        foreach ([
            'per_page=10&page=',
            'Payment History Pages',
            'Previous Page',
            'Next Page',
            'meta?.current_page',
            'meta?.last_page',
            'meta.per_page',
            'meta.total',
        ] as $paginationText) {
            $this->assertStringContainsString($paginationText, $paymentHistoryBlock);
        }

        foreach ([
            'status=',
            'payment_mode=',
            'from=',
            'to=',
            'all-member',
        ] as $blockedText) {
            $this->assertStringNotContainsString($blockedText, $paymentHistoryBlock);
        }
    }

    public function test_no_all_member_payment_history_screen_or_mutation_ui_was_added(): void
    {
        $page = $this->frontendPage();
        $paymentHistoryBlock = $this->paymentHistoryBlock($page);

        foreach ([
            '{ label: "Payment History", screen: "payment-history" }',
            '{ label: "My Payment History", screen: "payment-history" }',
            '{screen === "payment-history" && <PaymentHistory role={role} />}',
        ] as $requiredText) {
            $this->assertStringContainsString($requiredText, $page);
        }

        foreach ([
            'All Payment History',
            'All Members Payment History',
            'Create Payment',
            'Edit Payment',
            'Delete Payment',
            'Cancel Payment',
            'Reverse Payment',
            'Correct Payment',
            'Allocate Payment',
            'Generate Receipt',
            'Export Payment History',
            'Download Excel',
            'Send Reminder',
        ] as $blockedText) {
            $this->assertStringNotContainsString($blockedText, $paymentHistoryBlock);
        }
    }

    public function test_no_backend_runtime_or_blocked_frontend_files_were_added_for_3bq(): void
    {
        foreach ([
            app_path('Http/Requests/Finance/PaymentHistoryRequest.php'),
            app_path('Services/Finance/PaymentHistoryService.php'),
            app_path('Models/PaymentHistory.php'),
            app_path('Http/Middleware/EnsurePaymentHistoryAccess.php'),
            database_path('migrations/create_payment_histories_table.php'),
            database_path('seeders/PaymentHistorySeeder.php'),
            app_path('Services/Finance/PaymentCancellationService.php'),
            app_path('Services/Finance/PaymentReversalService.php'),
            app_path('Services/Finance/PaymentAllocationService.php'),
            app_path('Services/Finance/PaymentReminderService.php'),
            app_path('Services/Finance/PaymentHistoryExportService.php'),
            base_path('../app/payment-history'),
            base_path('../app/finance/payment-history'),
            base_path('../components/payment-history'),
            base_path('../lib/payment-history.ts'),
        ] as $blockedPath) {
            $this->assertFileDoesNotExist($blockedPath);
        }
    }

    public function test_3bp_focused_test_remains_present_for_frontend_closure(): void
    {
        $test = file_get_contents(base_path('tests/Feature/Milestone3bpPaymentHistoryFrontendNarrowImplementationTest.php'));

        foreach ([
            'test_frontend_payment_history_uses_only_existing_3bm_payment_history_routes',
            'test_frontend_defines_normal_member_own_history_only_behavior',
            'test_frontend_defines_officer_selected_member_history_behavior',
            'test_frontend_displays_only_safe_payment_metadata',
            'test_frontend_includes_loading_empty_error_and_success_states',
            'test_frontend_uses_backend_pagination_without_filters_or_all_member_list',
            'test_frontend_navigation_exposes_member_and_officer_payment_history_without_all_member_screen',
        ] as $requiredTest) {
            $this->assertStringContainsString($requiredTest, $test);
        }
    }

    public function test_3bq_closes_payment_history_frontend_display_lane(): void
    {
        $document = $this->closureDocument();

        $this->assertStringContainsString('Milestone 3BQ closes the Payment History frontend display lane.', $document);
        $this->assertStringContainsString('No next milestone is started by 3BQ.', $document);
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

    private function paymentHistoryBlock(string $page): string
    {
        $start = strpos($page, 'function PaymentHistory({ role }: { role: Role })');
        $end = strpos($page, 'function ReceiptPreview', $start === false ? 0 : $start);

        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        return substr($page, $start, $end - $start);
    }
}
