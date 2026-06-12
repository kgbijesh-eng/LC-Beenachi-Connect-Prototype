<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3kPaymentRecordingServiceScopeGateTest extends TestCase
{
    private string $scopeDocumentPath = 'docs/milestone_3k_payment_recording_service_scope_gate.md';

    public function test_scope_document_exists(): void
    {
        $this->assertFileExists(base_path($this->scopeDocumentPath));
    }

    public function test_scope_gate_defines_docs_tests_only_service_scope_status(): void
    {
        $content = file_get_contents(base_path($this->scopeDocumentPath));

        $this->assertStringContainsString('Milestone 3K is a docs/tests-only service scope gate.', $content);
        $this->assertStringContainsString('does not implement any service class, controller, route, request class, resource class, ledger mutation code, payment recording API, receipt generation code, frontend screen, reminder, report, or Excel import.', $content);
        $this->assertStringContainsString('Payment recording is a financial mutation workflow.', $content);
    }

    public function test_scope_gate_preserves_ledger_source_of_truth(): void
    {
        $content = file_get_contents(base_path($this->scopeDocumentPath));

        $this->assertStringContainsString('The member ledger remains the source of truth for member balances.', $content);
        $this->assertStringContainsString('A future payment recording service must not directly overwrite a member balance value.', $content);
        $this->assertStringContainsString('A valid posted payment must create a posted member ledger credit entry.', $content);
        $this->assertStringContainsString('Total Debits - Total Credits = Current Balance', $content);
    }

    public function test_scope_gate_defines_future_service_responsibility(): void
    {
        $content = file_get_contents(base_path($this->scopeDocumentPath));

        $this->assertStringContainsString('## Future Payment Recording Service Responsibility', $content);

        foreach ([
            'Validating club access',
            'Validating member belongs to the selected club',
            'Validating recorder has payment recording permission',
            'Creating a payment record',
            'Creating a linked ledger credit entry',
            'Linking the payment to the ledger entry',
            'Returning a safe service result',
        ] as $expectedResponsibility) {
            $this->assertStringContainsString($expectedResponsibility, $content);
        }

        $this->assertStringContainsString('Milestone 3K does not create this service.', $content);
    }

    public function test_scope_gate_defines_role_and_permission_boundaries(): void
    {
        $content = file_get_contents(base_path($this->scopeDocumentPath));

        $this->assertStringContainsString('## Role Boundary', $content);
        $this->assertStringContainsString('Treasurer', $content);
        $this->assertStringContainsString('President/Admin', $content);
        $this->assertStringContainsString('Secretary payment recording must require a separate explicit authorization if ever needed.', $content);
        $this->assertStringContainsString('Normal members must never record payments.', $content);

        $this->assertStringContainsString('## Permission Boundary', $content);
        $this->assertStringContainsString('payments.record', $content);
        $this->assertStringContainsString('The permission must be checked against the selected club context.', $content);
        $this->assertStringContainsString('Having a global user account must not be enough.', $content);
    }

    public function test_scope_gate_defines_input_validation_rules(): void
    {
        $content = file_get_contents(base_path($this->scopeDocumentPath));

        $this->assertStringContainsString('## Input Validation Rules', $content);

        foreach ([
            'club_id is required',
            'member_id is required',
            'amount is required',
            'amount must be positive',
            'payment_date is required',
            'payment_date must be valid',
            'payment_mode is required',
            'payment_mode must be from the allowed list',
            'member must belong to the selected club',
            'recorder must have active authorized role in the selected club',
        ] as $validationRule) {
            $this->assertStringContainsString($validationRule, $content);
        }
    }

    public function test_scope_gate_defines_transaction_safety_and_ledger_posting_rules(): void
    {
        $content = file_get_contents(base_path($this->scopeDocumentPath));

        $this->assertStringContainsString('## Transaction Safety Rule', $content);
        $this->assertStringContainsString('Payment recording must be atomic.', $content);
        $this->assertStringContainsString('A payment must not be saved without the required linked ledger credit when the payment is posted.', $content);
        $this->assertStringContainsString('A ledger credit must not be orphaned from its payment when created through payment recording.', $content);

        $this->assertStringContainsString('## Ledger Credit Posting Rule', $content);
        $this->assertStringContainsString('entry_type: credit', $content);
        $this->assertStringContainsString('source_type: payment', $content);
        $this->assertStringContainsString('status: posted', $content);
        $this->assertStringContainsString('The ledger credit amount must match the payment amount.', $content);
    }

    public function test_scope_gate_defines_status_receipt_audit_and_cross_club_boundaries(): void
    {
        $content = file_get_contents(base_path($this->scopeDocumentPath));

        $this->assertStringContainsString('## Payment Status Rule', $content);
        $this->assertStringContainsString('Draft payments must not affect member balance.', $content);
        $this->assertStringContainsString('Posted payments may affect member balance only through posted ledger credit entries.', $content);

        $this->assertStringContainsString('## Receipt Linkage Boundary', $content);
        $this->assertStringContainsString('Receipt generation is not authorized in 3K.', $content);
        $this->assertStringContainsString('Receipt numbering behavior must remain separately authorized.', $content);

        $this->assertStringContainsString('## Audit Requirements', $content);
        $this->assertStringContainsString('who recorded the payment', $content);
        $this->assertStringContainsString('linked ledger entry', $content);
        $this->assertStringContainsString('Audit trail must not be bypassed by President/Admin.', $content);

        $this->assertStringContainsString('## Cross-Club Safety', $content);
        $this->assertStringContainsString('Cross-club payment recording must never be allowed.', $content);
    }

    public function test_scope_gate_defines_special_collection_fellowship_cancellation_and_reversal_boundaries(): void
    {
        $content = file_get_contents(base_path($this->scopeDocumentPath));

        $this->assertStringContainsString('## Special Collection Boundary', $content);
        $this->assertStringContainsString('Milestone 3K does not authorize special collection allocation.', $content);

        $this->assertStringContainsString('## Fellowship Payment Boundary', $content);
        $this->assertStringContainsString('Milestone 3K does not authorize fellowship payment recording.', $content);

        $this->assertStringContainsString('## Cancellation And Reversal Boundary', $content);
        $this->assertStringContainsString('Payment cancellation and reversal are not authorized in Milestone 3K.', $content);
        $this->assertStringContainsString('Milestone 3K does not implement cancellation or reversal behavior.', $content);
    }

    public function test_scope_gate_defines_later_3l_authorization_boundary(): void
    {
        $content = file_get_contents(base_path($this->scopeDocumentPath));

        $this->assertStringContainsString('## What 3L May Authorize If Separately Approved', $content);
        $this->assertStringContainsString('PaymentRecordingService', $content);
        $this->assertStringContainsString('transaction-safe payment creation', $content);
        $this->assertStringContainsString('transaction-safe ledger credit creation', $content);
        $this->assertStringContainsString('no route/controller/API yet unless separately authorized', $content);
        $this->assertStringContainsString('3L must not itself implement behavior unless explicitly defined as an implementation authorization only.', $content);
    }

    public function test_scope_gate_blocks_3k_implementation(): void
    {
        $content = file_get_contents(base_path($this->scopeDocumentPath));

        $this->assertStringContainsString('## Not Implemented In 3K', $content);

        foreach ([
            'No service class',
            'No PaymentRecordingService',
            'No controller',
            'No payment route',
            'No receipt route',
            'No request class',
            'No resource class',
            'No payment recording API',
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

    public function test_3k_does_not_register_payment_receipt_or_finance_mutation_routes(): void
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
                "Milestone 3K must not register route containing [{$fragment}]."
            );
        }
    }

    public function test_3k_does_not_create_service_controller_request_resource_or_behavior_files(): void
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
            $this->assertFileDoesNotExist(base_path($forbiddenPath), "Milestone 3K must not create [{$forbiddenPath}].");
        }
    }
}
