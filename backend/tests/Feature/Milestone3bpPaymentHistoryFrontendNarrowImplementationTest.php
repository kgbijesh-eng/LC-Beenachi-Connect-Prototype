<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3bpPaymentHistoryFrontendNarrowImplementationTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3bp_payment_history_frontend_narrow_implementation.md';

    public function test_3bp_document_exists_and_records_narrow_frontend_implementation_status(): void
    {
        $document = $this->implementationDocument();

        $this->assertStringContainsString('Milestone 3BP - Payment History Frontend Narrow Implementation', $document);
        $this->assertStringContainsString('narrow frontend implementation milestone', $document);
        $this->assertStringContainsString('It implements read-only frontend payment history display.', $document);
        $this->assertStringContainsString('It uses only existing 3BM backend GET routes.', $document);
        $this->assertStringContainsString('It does not change backend runtime behavior.', $document);
        $this->assertStringContainsString('It does not add backend API behavior.', $document);
    }

    public function test_3bp_allows_only_frontend_doc_and_validation_test_files(): void
    {
        $document = $this->implementationDocument();

        foreach ([
            'app/page.tsx',
            'backend/docs/milestone_3bp_payment_history_frontend_narrow_implementation.md',
            'backend/tests/Feature/Milestone3bpPaymentHistoryFrontendNarrowImplementationTest.php',
        ] as $allowedFile) {
            $this->assertStringContainsString($allowedFile, $document);
        }

        foreach ([
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
            'payment recording backend files',
            'receipt backend files',
            'PDF backend files',
            'export files',
            'Excel files',
            'reminder files',
            'cancellation workflow files',
            'reversal workflow files',
            'allocation workflow files',
            'deployment files',
            'SaaS files',
            'client-specific hardcoding files',
        ] as $blockedScope) {
            $this->assertStringContainsString($blockedScope, $document);
        }
    }

    public function test_existing_payment_history_backend_routes_remain_exact_get_only_routes(): void
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

    public function test_frontend_payment_history_uses_only_existing_3bm_payment_history_routes(): void
    {
        $page = $this->frontendPage();
        $paymentHistoryBlock = $this->paymentHistoryBlock($page);

        foreach ([
            'API_BASE_URL + "/clubs/" + currentClubId + "/members/" + memberId + "/payments/history?per_page=10&page=" + requestedPage',
            'API_BASE_URL + "/clubs/" + currentClubId + "/members/me/payments/history?per_page=10&page=" + requestedPage',
            'Payment History',
            'My Payment History',
            'This screen uses only the existing payment history GET APIs.',
        ] as $requiredText) {
            $this->assertStringContainsString($requiredText, $paymentHistoryBlock);
        }

        foreach ([
            '"/clubs/" + currentClubId + "/payments/history',
            '"/payments/history",',
            '"/payments/history/"',
        ] as $blockedText) {
            $this->assertStringNotContainsString($blockedText, $paymentHistoryBlock);
        }
    }

    public function test_frontend_defines_normal_member_own_history_only_behavior(): void
    {
        $paymentHistoryBlock = $this->paymentHistoryBlock($this->frontendPage());

        foreach ([
            'canUseSelectedMemberHistory = role === "president" || role === "secretary" || role === "treasurer"',
            'LOCAL_MEMBER_FINANCE_LOGIN',
            'Member view loads only your own payment history.',
            'Other members\' payment history is not requested.',
            'Your payment history loaded from existing backend API.',
        ] as $requiredText) {
            $this->assertStringContainsString($requiredText, $paymentHistoryBlock);
        }
    }

    public function test_frontend_defines_officer_selected_member_history_behavior(): void
    {
        $paymentHistoryBlock = $this->paymentHistoryBlock($this->frontendPage());

        foreach ([
            'LOCAL_PAYMENT_RECORDING_LOGINS',
            '"/members/" + memberId + "/payments/history?per_page=10&page="',
            'Selected member in active club',
            'Selected-member payment history loaded from existing backend API.',
            'setSelectedHistoryMemberId(defaultMemberId)',
            'updateSelectedHistoryMember',
        ] as $requiredText) {
            $this->assertStringContainsString($requiredText, $paymentHistoryBlock);
        }
    }

    public function test_frontend_displays_only_safe_payment_metadata(): void
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
        ] as $safeText) {
            $this->assertStringContainsString($safeText, $paymentHistoryBlock);
        }

        foreach ([
            'raw notes',
            'internal remarks',
            'cancellation_reason',
            'reversal',
            'allocation',
            'ledger_entry_id',
            'receipt notes',
            'audit',
            'balance',
            'total_due',
        ] as $blockedText) {
            $this->assertStringNotContainsString($blockedText, $paymentHistoryBlock);
        }
    }

    public function test_frontend_includes_loading_empty_error_and_success_states(): void
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
            'request->query',
            'status=',
            'payment_mode=',
            'from=',
            'to=',
            'all-member',
        ] as $blockedText) {
            $this->assertStringNotContainsString($blockedText, $paymentHistoryBlock);
        }
    }

    public function test_frontend_navigation_exposes_member_and_officer_payment_history_without_all_member_screen(): void
    {
        $page = $this->frontendPage();

        foreach ([
            '{ label: "Payment History", screen: "payment-history" }',
            '{ label: "My Payment History", screen: "payment-history" }',
            '{ label: "Payments", short: "PH", screen: "payment-history" as Screen }',
            '{screen === "payment-history" && <PaymentHistory role={role} />}',
        ] as $requiredText) {
            $this->assertStringContainsString($requiredText, $page);
        }

        $this->assertStringNotContainsString('All Payment History', $page);
        $this->assertStringNotContainsString('All Members Payment History', $page);
    }

    public function test_3bp_document_records_blocked_mutation_export_reminder_and_client_specific_behavior(): void
    {
        $document = $this->implementationDocument();

        foreach ([
            'all-member payment history screen',
            'all-member payment history API call',
            'payment create UI',
            'payment edit UI',
            'payment delete UI',
            'cancellation UI',
            'reversal UI',
            'correction UI',
            'allocation UI',
            'reallocation UI',
            'receipt generation UI',
            'receipt mutation UI',
            'export UI',
            'PDF UI',
            'Excel UI',
            'reminder UI',
            'notification UI',
            'backend route changes',
            'API behavior changes',
            'deployment implementation',
            'SaaS implementation',
            'client-specific hardcoding',
        ] as $blockedBehavior) {
            $this->assertStringContainsString($blockedBehavior, $document);
        }
    }

    public function test_3bp_selects_3bq_as_next_validation_and_closure_milestone(): void
    {
        $document = $this->implementationDocument();

        $this->assertStringContainsString('Milestone 3BQ - Payment History Frontend Validation and Closure', $document);
        $this->assertStringContainsString('3BQ should validate and close only what 3BP implemented.', $document);
    }

    private function implementationDocument(): string
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
