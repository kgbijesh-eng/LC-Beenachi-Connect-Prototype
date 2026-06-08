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

class Milestone3iPaymentRecordingDatabaseFoundationImplementationTest extends TestCase
{
    use RefreshDatabase;

    private string $implementationDocumentPath = 'docs/milestone_3i_payment_recording_database_foundation_implementation.md';

    public function test_implementation_document_exists_and_records_narrow_boundary(): void
    {
        $this->assertFileExists(base_path($this->implementationDocumentPath));

        $content = file_get_contents(base_path($this->implementationDocumentPath));

        $this->assertStringContainsString('Milestone 3I implements only the narrow database foundation for future payment recording.', $content);
        $this->assertStringContainsString('This milestone prepares the database layer only.', $content);
        $this->assertStringContainsString('The member_ledger_entries table remains the source of truth for member balances.', $content);
        $this->assertStringContainsString('Milestone 3I does not implement:', $content);
        $this->assertStringContainsString('payment recording API', $content);
        $this->assertStringContainsString('ledger posting workflow', $content);
        $this->assertStringContainsString('frontend payment screen', $content);
    }

    public function test_payments_and_receipts_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('payments'));
        $this->assertTrue(Schema::hasTable('receipts'));
    }

    public function test_payments_table_has_required_foundation_columns(): void
    {
        foreach ([
            'id',
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
            'created_at',
            'updated_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('payments', $column),
                "payments table must contain [{$column}]."
            );
        }
    }

    public function test_receipts_table_has_required_foundation_columns(): void
    {
        foreach ([
            'id',
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
            'created_at',
            'updated_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('receipts', $column),
                "receipts table must contain [{$column}]."
            );
        }
    }

    public function test_payment_model_defines_fillable_fields_modes_statuses_and_casts(): void
    {
        $payment = new Payment();

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
        ] as $field) {
            $this->assertContains($field, $payment->getFillable());
        }

        $this->assertContains('cash', Payment::MODES);
        $this->assertContains('upi', Payment::MODES);
        $this->assertContains('bank_transfer', Payment::MODES);
        $this->assertContains('draft', Payment::STATUSES);
        $this->assertContains('posted', Payment::STATUSES);
        $this->assertContains('cancelled', Payment::STATUSES);
        $this->assertContains('reversed', Payment::STATUSES);

        $this->assertSame('decimal:2', $payment->getCasts()['amount']);
        $this->assertSame('date', $payment->getCasts()['payment_date']);
        $this->assertSame('datetime', $payment->getCasts()['cancelled_at']);
    }

    public function test_receipt_model_defines_fillable_fields_statuses_and_casts(): void
    {
        $receipt = new Receipt();

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
        ] as $field) {
            $this->assertContains($field, $receipt->getFillable());
        }

        $this->assertContains('issued', Receipt::STATUSES);
        $this->assertContains('cancelled', Receipt::STATUSES);

        $this->assertSame('date', $receipt->getCasts()['receipt_date']);
        $this->assertSame('datetime', $receipt->getCasts()['cancelled_at']);
    }

    public function test_payment_model_relationships_are_defined_without_posting_behavior(): void
    {
        $payment = new Payment();

        $this->assertInstanceOf(BelongsTo::class, $payment->club());
        $this->assertInstanceOf(BelongsTo::class, $payment->member());
        $this->assertInstanceOf(BelongsTo::class, $payment->receivedBy());
        $this->assertInstanceOf(BelongsTo::class, $payment->ledgerEntry());
        $this->assertInstanceOf(BelongsTo::class, $payment->cancelledBy());
        $this->assertInstanceOf(HasMany::class, $payment->receipts());

        $this->assertFalse(method_exists($payment, 'post'));
        $this->assertFalse(method_exists($payment, 'record'));
        $this->assertFalse(method_exists($payment, 'cancel'));
        $this->assertFalse(method_exists($payment, 'reverse'));
        $this->assertFalse(method_exists($payment, 'generateReceipt'));
    }

    public function test_receipt_model_relationships_are_defined_without_generation_behavior(): void
    {
        $receipt = new Receipt();

        $this->assertInstanceOf(BelongsTo::class, $receipt->club());
        $this->assertInstanceOf(BelongsTo::class, $receipt->payment());
        $this->assertInstanceOf(BelongsTo::class, $receipt->issuedBy());
        $this->assertInstanceOf(BelongsTo::class, $receipt->cancelledBy());

        $this->assertFalse(method_exists($receipt, 'issue'));
        $this->assertFalse(method_exists($receipt, 'generate'));
        $this->assertFalse(method_exists($receipt, 'cancel'));
        $this->assertFalse(method_exists($receipt, 'download'));
    }

    public function test_3i_does_not_register_payment_receipt_or_finance_mutation_routes(): void
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
            ->reject(fn (string $uri): bool => $uri === 'api/v1/clubs/{club}/members/{member}/payments') // authorized 3Q payment route
            ->values();

        foreach ($blockedRouteFragments as $fragment) {
            $this->assertFalse(
                $registeredUris->contains(fn (string $uri) => str_contains($uri, $fragment)),
                "Milestone 3I must not register route containing [{$fragment}]."
            );
        }
    }

    public function test_3i_does_not_create_payment_recording_or_receipt_generation_behavior_files(): void
    {
        foreach ([
            'app/Http/Controllers/PaymentController.php',
            'app/Http/Controllers/ReceiptController.php',
            'app/Http/Requests/PaymentStoreRequest.php',
            'app/Http/Resources/PaymentResource.php',
            'app/Http/Resources/ReceiptResource.php',
            'app/Services/Payments/PaymentRecordingService.php',
            'app/Services/Payments/ReceiptGenerationService.php',
            'app/Services/Payments/PaymentPostingService.php',
            'app/Services/Payments/PaymentCancellationService.php',
            'app/Services/Payments/PaymentReversalService.php',
        ] as $forbiddenPath) {
            $this->assertFileDoesNotExist(base_path($forbiddenPath), "Milestone 3I must not create [{$forbiddenPath}].");
        }
    }
}