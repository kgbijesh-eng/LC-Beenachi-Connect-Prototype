<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Services\Finance\PaymentRecordingService;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3pPaymentRecordingApiImplementationAuthorizationTest extends TestCase
{
    public function test_authorization_document_exists_and_records_docs_tests_only_status(): void
    {
        $path = base_path('docs/milestone_3p_payment_recording_api_implementation_authorization.md');

        $this->assertFileExists($path);

        $content = file_get_contents($path);

        $this->assertStringContainsString('Milestone 3P', $content);
        $this->assertStringContainsString('Payment Recording API Implementation Authorization', $content);
        $this->assertStringContainsString('Implementation authorization only', $content);
        $this->assertStringContainsString('docs/tests-only', $content);
        $this->assertStringContainsString('It does not authorize implementation inside 3P', $content);
    }

    public function test_authorization_defines_future_3q_implementation_candidate(): void
    {
        $content = file_get_contents(base_path('docs/milestone_3p_payment_recording_api_implementation_authorization.md'));

        $this->assertStringContainsString('Milestone 3Q', $content);
        $this->assertStringContainsString('Payment Recording API Narrow Implementation', $content);
        $this->assertStringContainsString('3P authorizes only that later narrow implementation', $content);
        $this->assertStringContainsString('It does not implement it', $content);
    }

    public function test_authorization_defines_exact_future_route_boundary(): void
    {
        $content = file_get_contents(base_path('docs/milestone_3p_payment_recording_api_implementation_authorization.md'));

        $this->assertStringContainsString('POST /api/v1/clubs/{club}/members/{member}/payments', $content);
        $this->assertStringContainsString('Record one payment for one selected member', $content);
        $this->assertStringContainsString('Create the payment through PaymentRecordingService', $content);
        $this->assertStringContainsString('Return a safe payment response', $content);
        $this->assertStringContainsString('must not add list, update, delete, cancel, reverse, receipt generation, report, reminder, Excel import, fellowship payment, or special collection allocation routes', $content);
    }

    public function test_authorization_defines_exact_future_file_boundary(): void
    {
        $content = file_get_contents(base_path('docs/milestone_3p_payment_recording_api_implementation_authorization.md'));

        foreach ([
            'routes/api.php',
            'app/Http/Controllers/Api/V1/Finance/PaymentController.php',
            'app/Http/Requests/Finance/RecordPaymentRequest.php',
            'app/Http/Resources/Finance/PaymentResource.php',
            'tests/Feature/Milestone3qPaymentRecordingApiNarrowImplementationTest.php',
            'docs/milestone_3q_payment_recording_api_narrow_implementation.md',
        ] as $futureFile) {
            $this->assertStringContainsString($futureFile, $content);
        }

        $this->assertStringContainsString('Any different file path requires a separate scope decision', $content);
    }

    public function test_authorization_defines_controller_request_and_resource_guardrails(): void
    {
        $content = file_get_contents(base_path('docs/milestone_3p_payment_recording_api_implementation_authorization.md'));

        $this->assertStringContainsString('PaymentController', $content);
        $this->assertStringContainsString('RecordPaymentRequest', $content);
        $this->assertStringContainsString('PaymentResource', $content);

        foreach ([
            'Accept the authenticated user',
            'Resolve the route club',
            'Resolve the route member within that club',
            'Call PaymentRecordingService',
            'Return PaymentResource',
            'Avoid manual payment creation',
            'Avoid manual ledger credit creation',
            'Avoid manual ledger_entry_id linkage outside PaymentRecordingService',
        ] as $guardrail) {
            $this->assertStringContainsString($guardrail, $content);
        }
    }

    public function test_authorization_defines_validation_and_backend_controlled_fields(): void
    {
        $content = file_get_contents(base_path('docs/milestone_3p_payment_recording_api_implementation_authorization.md'));

        foreach (['amount', 'payment_date', 'payment_mode', 'reference_number', 'notes'] as $allowedField) {
            $this->assertStringContainsString($allowedField, $content);
        }

        foreach ([
            'club_id',
            'member_id',
            'received_by_user_id',
            'ledger_entry_id',
            'status',
            'cancelled_at',
            'cancelled_by_user_id',
            'cancellation_reason',
            'receipt_number',
            'receipt_id',
        ] as $blockedField) {
            $this->assertStringContainsString($blockedField, $content);
        }

        $this->assertStringContainsString('Payment::MODES', $content);
        $this->assertContains('cash', Payment::MODES);
        $this->assertContains('bank_transfer', Payment::MODES);
        $this->assertContains('upi', Payment::MODES);
    }

    public function test_authorization_defines_permission_role_and_failure_boundaries(): void
    {
        $content = file_get_contents(base_path('docs/milestone_3p_payment_recording_api_implementation_authorization.md'));

        $this->assertStringContainsString(PaymentRecordingService::PERMISSION_RECORD_PAYMENT, $content);
        $this->assertStringContainsString('President/Admin may record payments only if', $content);
        $this->assertStringContainsString('Treasurer may record payments only if', $content);
        $this->assertStringContainsString('Secretary may record payments only if explicitly granted', $content);
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

    public function test_authorization_preserves_payment_recording_service_as_authority(): void
    {
        $content = file_get_contents(base_path('docs/milestone_3p_payment_recording_api_implementation_authorization.md'));

        $this->assertStringContainsString('PaymentRecordingService remains the source of payment recording behaviour', $content);
        $this->assertStringContainsString('transaction-safe payment creation', $content);
        $this->assertStringContainsString('transaction-safe ledger credit creation', $content);
        $this->assertStringContainsString('payments.ledger_entry_id linkage', $content);
        $this->assertStringContainsString('The future API is only a protected transport layer over PaymentRecordingService', $content);
    }

    public function test_3p_allows_only_authorized_3q_payment_recording_route_after_implementation(): void
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
            'POST api/v1/clubs/{club}/members/{member}/payments',
        ], $paymentRoutes);
    }

    public function test_3p_does_not_create_api_frontend_or_blocked_behavior_files(): void
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

    public function test_authorization_explicitly_blocks_3p_implementation(): void
    {
        $content = file_get_contents(base_path('docs/milestone_3p_payment_recording_api_implementation_authorization.md'));

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
