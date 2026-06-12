<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3lPaymentRecordingServiceImplementationAuthorizationTest extends TestCase
{
    private string $authorizationDocumentPath = 'docs/milestone_3l_payment_recording_service_implementation_authorization.md';

    public function test_authorization_document_exists(): void
    {
        $this->assertFileExists(base_path($this->authorizationDocumentPath));
    }

    public function test_authorization_document_records_docs_tests_only_status_and_decision(): void
    {
        $content = file_get_contents(base_path($this->authorizationDocumentPath));

        $this->assertStringContainsString('Milestone 3L is a docs/tests-only implementation authorization checkpoint.', $content);
        $this->assertStringContainsString('Milestone 3L does not create any service class, controller, route, request class, resource class, API endpoint, ledger mutation implementation, receipt generation implementation, frontend screen, reminder, report, or Excel import.', $content);
        $this->assertStringContainsString('PAYMENT_RECORDING_SERVICE_NARROW_IMPLEMENTATION_AUTHORIZED', $content);
        $this->assertStringContainsString('3M - Payment Recording Service Narrow Implementation', $content);
    }

    public function test_authorization_document_defines_future_service_and_3m_scope(): void
    {
        $content = file_get_contents(base_path($this->authorizationDocumentPath));

        $this->assertStringContainsString('## Authorized Future Service', $content);
        $this->assertStringContainsString('PaymentRecordingService', $content);
        $this->assertStringContainsString('app/Services/Payments/PaymentRecordingService.php', $content);

        $this->assertStringContainsString('## Authorized Future 3M Scope', $content);

        foreach ([
            'transaction-safe payment creation',
            'transaction-safe member ledger credit creation',
            'payment-to-ledger linkage',
            'role/permission checks',
            'club/member validation',
            'amount/date/payment mode validation',
            'safe service result return',
            'no route/controller/API wiring',
        ] as $expectedScope) {
            $this->assertStringContainsString($expectedScope, $content);
        }
    }

    public function test_authorization_preserves_ledger_source_of_truth(): void
    {
        $content = file_get_contents(base_path($this->authorizationDocumentPath));

        $this->assertStringContainsString('The member ledger remains the source of truth for balances.', $content);
        $this->assertStringContainsString('The future service must not directly overwrite member balances.', $content);
        $this->assertStringContainsString('A posted payment must affect balance only through a posted member ledger credit entry.', $content);
        $this->assertStringContainsString('Total Debits - Total Credits = Current Balance', $content);
    }

    public function test_authorization_defines_permission_role_and_validation_boundaries(): void
    {
        $content = file_get_contents(base_path($this->authorizationDocumentPath));

        $this->assertStringContainsString('## Permission Boundary', $content);
        $this->assertStringContainsString('payments.record', $content);
        $this->assertStringContainsString('The permission must be checked against the selected club.', $content);
        $this->assertStringContainsString('An authenticated user without active club membership must fail closed.', $content);

        $this->assertStringContainsString('## Role Boundary', $content);
        $this->assertStringContainsString('Treasurer', $content);
        $this->assertStringContainsString('President/Admin', $content);
        $this->assertStringContainsString('Secretary payment recording must remain blocked unless a later milestone explicitly authorizes it.', $content);
        $this->assertStringContainsString('Normal members must never record payments.', $content);

        $this->assertStringContainsString('## Input Validation Boundary', $content);
        $this->assertStringContainsString('amount must be positive', $content);
        $this->assertStringContainsString('payment_mode must be allowed', $content);
        $this->assertStringContainsString('member must belong to selected club', $content);
        $this->assertStringContainsString('actor must have payment recording permission in selected club', $content);
    }

    public function test_authorization_defines_transaction_and_ledger_credit_rules(): void
    {
        $content = file_get_contents(base_path($this->authorizationDocumentPath));

        $this->assertStringContainsString('## Transaction Safety Authorization', $content);
        $this->assertStringContainsString('The future service must use a database transaction.', $content);
        $this->assertStringContainsString('If ledger credit creation fails, payment creation must roll back.', $content);
        $this->assertStringContainsString('The service must not leave orphan payment records or orphan ledger credit entries.', $content);

        $this->assertStringContainsString('## Ledger Credit Authorization', $content);
        $this->assertStringContainsString('entry_type: credit', $content);
        $this->assertStringContainsString('source_type: payment', $content);
        $this->assertStringContainsString('amount equal to payment amount', $content);
        $this->assertStringContainsString('status: posted', $content);
        $this->assertStringContainsString('The service must not manually update stored member balance values.', $content);
    }

    public function test_authorization_defines_payment_record_boundary(): void
    {
        $content = file_get_contents(base_path($this->authorizationDocumentPath));

        $this->assertStringContainsString('## Payment Record Authorization', $content);

        foreach ([
            'club_id',
            'member_id',
            'amount',
            'payment_date',
            'payment_mode',
            'reference_number',
            'notes',
            'received_by_user_id',
            'ledger_entry_id',
            'status',
        ] as $field) {
            $this->assertStringContainsString($field, $content);
        }

        $this->assertStringContainsString('Draft workflows are not authorized in 3L.', $content);
    }

    public function test_authorization_blocks_receipt_cancellation_reversal_special_collection_and_fellowship_behavior(): void
    {
        $content = file_get_contents(base_path($this->authorizationDocumentPath));

        $this->assertStringContainsString('## Receipt Boundary', $content);
        $this->assertStringContainsString('Receipt generation is not authorized in 3L.', $content);
        $this->assertStringContainsString('Receipt generation must be separately scoped later.', $content);

        $this->assertStringContainsString('## Cancellation And Reversal Boundary', $content);
        $this->assertStringContainsString('Payment cancellation and reversal are not authorized in 3L.', $content);

        $this->assertStringContainsString('## Special Collection Boundary', $content);
        $this->assertStringContainsString('Special collection allocation is not authorized in 3L.', $content);

        $this->assertStringContainsString('## Fellowship Payment Boundary', $content);
        $this->assertStringContainsString('Fellowship payment recording is not authorized in 3L.', $content);
    }

    public function test_authorization_blocks_api_and_frontend_scope(): void
    {
        $content = file_get_contents(base_path($this->authorizationDocumentPath));

        $this->assertStringContainsString('## API Boundary', $content);

        foreach ([
            'payment routes',
            'receipt routes',
            'payment controllers',
            'receipt controllers',
            'API request classes',
            'API resource classes',
            'route registration',
            'frontend integration',
        ] as $blockedScope) {
            $this->assertStringContainsString($blockedScope, $content);
        }

        $this->assertStringContainsString('Payment API work must require a later separate API scope gate.', $content);
    }

    public function test_authorization_defines_audit_cross_club_and_normal_member_boundaries(): void
    {
        $content = file_get_contents(base_path($this->authorizationDocumentPath));

        $this->assertStringContainsString('## Audit Boundary', $content);
        $this->assertStringContainsString('who recorded the payment', $content);
        $this->assertStringContainsString('linked ledger entry', $content);
        $this->assertStringContainsString('President/Admin must not bypass audit trail requirements.', $content);

        $this->assertStringContainsString('## Cross-Club Safety Boundary', $content);
        $this->assertStringContainsString('Cross-club payment recording must never be allowed.', $content);

        $this->assertStringContainsString('## Normal Member Boundary', $content);
        $this->assertStringContainsString('Normal members must not record payments.', $content);
        $this->assertStringContainsString('Normal members must not access internal payment recording service behavior.', $content);
    }

    public function test_authorization_blocks_3l_implementation(): void
    {
        $content = file_get_contents(base_path($this->authorizationDocumentPath));

        $this->assertStringContainsString('## Forbidden In 3L', $content);

        foreach ([
            'No PaymentRecordingService implementation',
            'No service class',
            'No payment controller',
            'No receipt controller',
            'No payment route',
            'No receipt route',
            'No request class',
            'No resource class',
            'No payment API',
            'No receipt API',
            'No ledger mutation implementation',
            'No payment posting implementation',
            'No receipt generation implementation',
            'No receipt numbering implementation',
            'No payment cancellation implementation',
            'No payment reversal implementation',
            'No special collection allocation',
            'No fellowship payment workflow',
            'No frontend changes',
            'No dashboard',
            'No reminder automation',
            'No reports',
            'No Excel import',
        ] as $blockedItem) {
            $this->assertStringContainsString($blockedItem, $content);
        }
    }

    public function test_authorization_defines_3m_guardrails(): void
    {
        $content = file_get_contents(base_path($this->authorizationDocumentPath));

        $this->assertStringContainsString('## 3M Implementation Guardrails', $content);
        $this->assertStringContainsString('3M may implement the internal service only.', $content);
        $this->assertStringContainsString('3M must not expose the service through routes or controllers.', $content);
        $this->assertStringContainsString('3M must not implement receipt generation.', $content);
        $this->assertStringContainsString('3M must include tests proving transaction safety, permission safety, cross-club safety, and ledger consistency.', $content);
    }

    public function test_3l_does_not_register_payment_receipt_or_finance_mutation_routes(): void
    {
        $blockedRouteFragments = [
            'payment',
            'payments',
            'receipt',
            'receipts',
            'special-collection',
            'special-collections',
            'fellowship-payment',
            'fellowship-payments',
            'reminder',
            'reminders',
            'report',
            'reports',
            'excel',
            'import',
        ];

        $registeredUris = collect(Route::getRoutes())
            ->map(fn ($route) => $route->uri())
            ->reject(fn (string $uri): bool => in_array($uri, [
                'api/v1/clubs/{club}/members/{member}/payments',
                'api/v1/clubs/{club}/members/me/payments/history',
                'api/v1/clubs/{club}/members/{member}/payments/history',
                'api/v1/clubs/{club}/members/{member}/payments/{payment}/receipt',
                'api/v1/clubs/{club}/members/{member}/payments/{payment}/receipt/pdf',
                'api/v1/clubs/{club}/members/import-preview',
            ], true)) // authorized 3Q payment route, 3BM payment history routes, 3Y receipt route, 3AD PDF route, and 3BY import preview route
            ->values();

        foreach ($blockedRouteFragments as $fragment) {
            $this->assertFalse(
                $registeredUris->contains(fn (string $uri) => ! in_array($uri, [
                    'api/v1/clubs/{club}/finance/reports',
                    'api/v1/clubs/{club}/finance/balance-sheet',
                ], true) && str_contains($uri, $fragment)),
                "Milestone 3L must not register route containing [{$fragment}]."
            );
        }
    }

    public function test_3l_does_not_create_service_controller_request_resource_or_behavior_files(): void
    {
        foreach ([
            'app/Services/Payments/PaymentRecordingService.php',
            'app/Services/Payments/ReceiptGenerationService.php',
            'app/Services/Payments/PaymentPostingService.php',
            'app/Services/Payments/PaymentCancellationService.php',
            'app/Services/Payments/PaymentReversalService.php',
            'app/Http/Controllers/PaymentController.php',
            'app/Http/Controllers/ReceiptController.php',
            'app/Http/Requests/PaymentStoreRequest.php',
            'app/Http/Requests/ReceiptStoreRequest.php',
            'app/Http/Resources/PaymentResource.php',
            'app/Http/Resources/ReceiptResource.php',
        ] as $forbiddenPath) {
            $this->assertFileDoesNotExist(base_path($forbiddenPath), "Milestone 3L must not create [{$forbiddenPath}].");
        }
    }
}
