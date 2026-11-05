<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Services\Finance\PaymentRecordingService;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3oPaymentRecordingApiScopeGateTest extends TestCase
{
    public function test_scope_document_exists_and_records_docs_tests_only_status(): void
    {
        $path = base_path('docs/milestone_3o_payment_recording_api_scope_gate.md');

        $this->assertFileExists($path);

        $content = file_get_contents($path);

        $this->assertStringContainsString('Milestone 3O', $content);
        $this->assertStringContainsString('Payment Recording API Scope Gate', $content);
        $this->assertStringContainsString('Scope gate only', $content);
        $this->assertStringContainsString('docs/tests-only', $content);
        $this->assertStringContainsString('It does not authorize implementation in 3O', $content);
    }

    public function test_scope_gate_defines_future_payment_recording_route_boundary(): void
    {
        $content = file_get_contents(base_path('docs/milestone_3o_payment_recording_api_scope_gate.md'));

        $this->assertStringContainsString('POST /api/v1/clubs/{club}/members/{member}/payments', $content);
        $this->assertStringContainsString('This future route is not implemented in 3O', $content);
        $this->assertStringContainsString('narrow protected API endpoint', $content);
    }

    public function test_scope_gate_defines_future_controller_request_and_resource_boundaries(): void
    {
        $content = file_get_contents(base_path('docs/milestone_3o_payment_recording_api_scope_gate.md'));

        $this->assertStringContainsString('PaymentController', $content);
        $this->assertStringContainsString('RecordPaymentRequest', $content);
        $this->assertStringContainsString('PaymentResource', $content);
        $this->assertStringContainsString('Accept the authenticated user', $content);
        $this->assertStringContainsString('Resolve the selected club', $content);
        $this->assertStringContainsString('Resolve the selected member within that club', $content);
        $this->assertStringContainsString('Pass validated input to PaymentRecordingService', $content);
        $this->assertStringContainsString('Return a safe API response', $content);
    }

    public function test_scope_gate_defines_validation_and_backend_controlled_fields(): void
    {
        $content = file_get_contents(base_path('docs/milestone_3o_payment_recording_api_scope_gate.md'));

        foreach (['amount', 'payment_date', 'payment_mode', 'reference_number', 'notes'] as $field) {
            $this->assertStringContainsString($field, $content);
        }

        $this->assertStringContainsString('Payment::MODES', $content);

        foreach ([
            'club_id',
            'member_id',
            'received_by_user_id',
            'ledger_entry_id',
            'status',
            'cancelled_at',
            'cancelled_by_user_id',
            'cancellation_reason',
            'receipt number fields',
        ] as $blockedField) {
            $this->assertStringContainsString($blockedField, $content);
        }

        $this->assertStringContainsString('Those fields must remain controlled by backend logic', $content);

        $this->assertContains('cash', Payment::MODES);
        $this->assertContains('bank_transfer', Payment::MODES);
        $this->assertContains('upi', Payment::MODES);
    }

    public function test_scope_gate_defines_permission_role_and_failure_boundaries(): void
    {
        $content = file_get_contents(base_path('docs/milestone_3o_payment_recording_api_scope_gate.md'));

        $this->assertStringContainsString(PaymentRecordingService::PERMISSION_RECORD_PAYMENT, $content);
        $this->assertStringContainsString('President/Admin may record member payments', $content);
        $this->assertStringContainsString('Treasurer may record member payments', $content);
        $this->assertStringContainsString('Secretary may record member payments only if', $content);
        $this->assertStringContainsString('Normal Member must not record payments', $content);

        foreach ([
            'unauthenticated users',
            'users without active club membership',
            'users without payments.record',
            'cross-club member IDs',
            'inactive clubs',
            'inactive members',
            'invalid amount',
            'invalid payment date',
            'invalid payment mode',
        ] as $failureBoundary) {
            $this->assertStringContainsString($failureBoundary, $content);
        }
    }

    public function test_scope_gate_preserves_service_as_payment_recording_authority(): void
    {
        $content = file_get_contents(base_path('docs/milestone_3o_payment_recording_api_scope_gate.md'));

        $this->assertStringContainsString('The future API must call PaymentRecordingService', $content);
        $this->assertStringContainsString('The API must not duplicate service behaviour', $content);
        $this->assertStringContainsString('transaction-safe payment creation', $content);
        $this->assertStringContainsString('transaction-safe ledger credit creation', $content);
        $this->assertStringContainsString('payments.ledger_entry_id linkage', $content);
    }

    public function test_3o_allows_only_authorized_3q_payment_recording_route_after_implementation(): void
    {
        $paymentRoutes = collect(Route::getRoutes())
            ->filter(function ($route): bool {
                $uri = $route->uri();

                return str_contains($uri, 'payments')
                    || str_contains($uri, 'payment')
                    || str_contains($uri, 'receipts')
                    || str_contains($uri, 'receipt');
            })
            ->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())
            ->values()
            ->all();

        $this->assertSame([
            'GET|HEAD api/v1/clubs/{club}/members/me/payments/history',
            'GET|HEAD api/v1/clubs/{club}/members/{member}/payments/history',
            'GET|HEAD api/v1/clubs/{club}/members/{member}/payments/{payment}/receipt',
            'GET|HEAD api/v1/clubs/{club}/members/{member}/payments/{payment}/receipt/pdf',
            'GET|HEAD api/v1/clubs/{club}/finance/members/{member}/payments',
            'GET|HEAD api/v1/clubs/{club}/finance/payments/{payment}',
            'GET|HEAD api/v1/clubs/{club}/finance/receipts/{receipt}',
            'GET|HEAD api/v1/clubs/{club}/finance/ordinary-income-receipts',
            'GET|HEAD api/v1/clubs/{club}/finance/ordinary-income-receipts/{receipt}',
            'GET|HEAD api/v1/clubs/{club}/finance/ordinary-income-receipts/options',
            'POST api/v1/clubs/{club}/finance/ordinary-income-receipts',
            'POST api/v1/clubs/{club}/finance/ordinary-income-receipts/{receipt}/correction',
            'GET|HEAD api/v1/clubs/{club}/finance/me/payments',
            'POST api/v1/clubs/{club}/finance/members/{member}/payments',
            'POST api/v1/clubs/{club}/finance/payments/{payment}/void',
            'POST api/v1/clubs/{club}/members/{member}/payments',
            'POST api/v1/clubs/{club}/members/{member}/payments/{payment}/receipt',
        ], $paymentRoutes);
    }

    public function test_3o_does_not_create_api_or_frontend_implementation_files(): void
    {
        $blockedPaths = [
            app_path('Http/Controllers/PaymentController.php'),
            app_path('Http/Controllers/ReceiptController.php'),
            app_path('Http/Requests/RecordPaymentRequest.php'),
            app_path('Http/Resources/PaymentResource.php'),
            app_path('Services/Finance/ReceiptNumberingService.php'),
            app_path('Services/Finance/PaymentCancellationService.php'),
            app_path('Services/Finance/PaymentReversalService.php'),
            app_path('Services/Finance/SpecialCollectionAllocationService.php'),
            app_path('Services/Finance/FellowshipPaymentService.php'),
            app_path('Services/Finance/PaymentReminderService.php'),
            app_path('Services/Reports/FinanceReportService.php'),
            app_path('Services/Imports/ExcelImportService.php'),
            base_path('../app/payments'),
            base_path('../app/finance/payments'),
        ];

        foreach ($blockedPaths as $path) {
            $this->assertFileDoesNotExist($path);
        }
    }

    public function test_scope_gate_explicitly_blocks_3o_implementation(): void
    {
        $content = file_get_contents(base_path('docs/milestone_3o_payment_recording_api_scope_gate.md'));

        foreach ([
            'No route',
            'No controller',
            'No request class',
            'No resource class',
            'No frontend',
            'No service change',
            'No receipt generation',
            'No receipt numbering',
            'No cancellation or reversal workflow',
            'No special collection allocation',
            'No fellowship payment workflow',
            'No reminders',
            'No reports',
            'No Excel import',
        ] as $blockedItem) {
            $this->assertStringContainsString($blockedItem, $content);
        }
    }
}
