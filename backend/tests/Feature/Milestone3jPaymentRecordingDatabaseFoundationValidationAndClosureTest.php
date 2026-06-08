<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Receipt;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Milestone3jPaymentRecordingDatabaseFoundationValidationAndClosureTest extends TestCase
{
    use RefreshDatabase;

    private string $closureDocumentPath = 'docs/milestone_3j_payment_recording_database_foundation_validation_and_closure.md';

    public function test_validation_and_closure_document_exists(): void
    {
        $this->assertFileExists(base_path($this->closureDocumentPath));
    }

    public function test_closure_document_records_docs_tests_only_validation_status(): void
    {
        $content = file_get_contents(base_path($this->closureDocumentPath));

        $this->assertStringContainsString('Milestone 3J is a docs/tests-only validation and closure checkpoint.', $content);
        $this->assertStringContainsString('This milestone validates and closes the Payment Recording Database Foundation implemented in Milestone 3I.', $content);
        $this->assertStringContainsString('does not implement any new migration, model, route, controller, service, request class, resource class, ledger mutation code, payment posting workflow, receipt generation workflow, frontend screen, reminder, report, or Excel import.', $content);
    }

    public function test_closure_document_records_3i_database_foundation_validation(): void
    {
        $content = file_get_contents(base_path($this->closureDocumentPath));

        $this->assertStringContainsString('Milestone 3I successfully created the database foundation only.', $content);
        $this->assertStringContainsString('payments table', $content);
        $this->assertStringContainsString('receipts table', $content);
        $this->assertStringContainsString('Payment model', $content);
        $this->assertStringContainsString('Receipt model', $content);
        $this->assertStringContainsString('The implementation remains database-layer only.', $content);
    }

    public function test_closure_document_confirms_ledger_source_of_truth(): void
    {
        $content = file_get_contents(base_path($this->closureDocumentPath));

        $this->assertStringContainsString('The member_ledger_entries table remains the source of truth for member balances.', $content);
        $this->assertStringContainsString('The payments table does not calculate balances.', $content);
        $this->assertStringContainsString('The receipts table does not calculate balances.', $content);
        $this->assertStringContainsString('Total Debits - Total Credits = Current Balance', $content);
    }

    public function test_payments_and_receipts_tables_exist_with_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('payments'));
        $this->assertTrue(Schema::hasTable('receipts'));

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
            'cancelled_at',
            'cancelled_by_user_id',
            'cancellation_reason',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('payments', $column), "payments table must contain [{$column}].");
        }

        foreach ([
            'club_id',
            'payment_id',
            'receipt_number',
            'receipt_date',
            'issued_by_user_id',
            'status',
            'notes',
            'cancelled_at',
            'cancelled_by_user_id',
            'cancellation_reason',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('receipts', $column), "receipts table must contain [{$column}].");
        }
    }

    public function test_payment_and_receipt_models_exist_with_passive_relationships(): void
    {
        $payment = new Payment();
        $receipt = new Receipt();

        $this->assertInstanceOf(BelongsTo::class, $payment->club());
        $this->assertInstanceOf(BelongsTo::class, $payment->member());
        $this->assertInstanceOf(BelongsTo::class, $payment->receivedBy());
        $this->assertInstanceOf(BelongsTo::class, $payment->ledgerEntry());
        $this->assertInstanceOf(BelongsTo::class, $payment->cancelledBy());
        $this->assertInstanceOf(HasMany::class, $payment->receipts());

        $this->assertInstanceOf(BelongsTo::class, $receipt->club());
        $this->assertInstanceOf(BelongsTo::class, $receipt->payment());
        $this->assertInstanceOf(BelongsTo::class, $receipt->issuedBy());
        $this->assertInstanceOf(BelongsTo::class, $receipt->cancelledBy());

        $this->assertFalse(method_exists($payment, 'post'));
        $this->assertFalse(method_exists($payment, 'record'));
        $this->assertFalse(method_exists($payment, 'cancel'));
        $this->assertFalse(method_exists($payment, 'reverse'));
        $this->assertFalse(method_exists($payment, 'generateReceipt'));

        $this->assertFalse(method_exists($receipt, 'issue'));
        $this->assertFalse(method_exists($receipt, 'generate'));
        $this->assertFalse(method_exists($receipt, 'cancel'));
        $this->assertFalse(method_exists($receipt, 'download'));
    }

    public function test_closure_document_confirms_payment_and_receipt_models_remain_behavior_free(): void
    {
        $content = file_get_contents(base_path($this->closureDocumentPath));

        $this->assertStringContainsString('The Payment model does not implement payment posting behavior.', $content);
        $this->assertStringContainsString('The Receipt model does not implement receipt generation behavior.', $content);
        $this->assertStringContainsString('The payments table remains passive.', $content);
        $this->assertStringContainsString('The receipts table remains passive.', $content);
    }

    public function test_no_payment_receipt_or_finance_mutation_routes_exist(): void
    {
        $blockedRouteFragments = [
            'payment',
            'payments',
            'receipt',
            'receipts',
            'dues',
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
            ->values();

        foreach ($blockedRouteFragments as $fragment) {
            $this->assertFalse(
                $registeredUris->contains(fn (string $uri) => str_contains($uri, $fragment)),
                "Milestone 3J must not have route containing [{$fragment}]."
            );
        }
    }

    public function test_no_payment_recording_or_receipt_generation_behavior_files_exist(): void
    {
        foreach ([
            'app/Http/Controllers/PaymentController.php',
            'app/Http/Controllers/ReceiptController.php',
            'app/Http/Requests/PaymentStoreRequest.php',
            'app/Http/Requests/ReceiptStoreRequest.php',
            'app/Http/Resources/PaymentResource.php',
            'app/Http/Resources/ReceiptResource.php',
            'app/Services/Payments/PaymentRecordingService.php',
            'app/Services/Payments/ReceiptGenerationService.php',
            'app/Services/Payments/PaymentPostingService.php',
            'app/Services/Payments/PaymentCancellationService.php',
            'app/Services/Payments/PaymentReversalService.php',
        ] as $forbiddenPath) {
            $this->assertFileDoesNotExist(base_path($forbiddenPath), "Milestone 3J must not create [{$forbiddenPath}].");
        }
    }

    public function test_closure_document_records_closure_decision_and_next_scope_gate(): void
    {
        $content = file_get_contents(base_path($this->closureDocumentPath));

        $this->assertStringContainsString('PAYMENT_RECORDING_DATABASE_FOUNDATION_VALIDATED_AND_CLOSED', $content);
        $this->assertStringContainsString('The project now has a passive payment/receipt database foundation.', $content);
        $this->assertStringContainsString('The project does not yet have payment recording behavior.', $content);
        $this->assertStringContainsString('3K - Payment Recording Service Scope Gate', $content);
        $this->assertStringContainsString('3K should be docs/tests-only.', $content);
    }

    public function test_3j_does_not_add_additional_implementation_beyond_docs_tests_closure(): void
    {
        $content = file_get_contents(base_path($this->closureDocumentPath));

        foreach ([
            'No migration',
            'No model',
            'No route',
            'No controller',
            'No request class',
            'No resource class',
            'No service',
            'No payment recording API',
            'No receipt API',
            'No payment posting workflow',
            'No ledger mutation workflow',
            'No receipt generation workflow',
            'No cancellation workflow',
            'No reversal workflow',
            'No frontend changes',
            'No dashboard',
            'No reminder automation',
            'No reports',
            'No Excel import',
        ] as $blockedItem) {
            $this->assertStringContainsString($blockedItem, $content);
        }
    }
}