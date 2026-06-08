<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3hPaymentRecordingDatabaseFoundationAuthorizationTest extends TestCase
{
    private string $scopeDocumentPath = 'docs/milestone_3h_payment_recording_database_foundation_authorization.md';

    public function test_scope_document_exists(): void
    {
        $this->assertFileExists(base_path($this->scopeDocumentPath));
    }

    public function test_scope_gate_defines_docs_tests_only_database_authorization_status(): void
    {
        $content = file_get_contents(base_path($this->scopeDocumentPath));

        $this->assertStringContainsString('Milestone 3H is a docs/tests-only database foundation authorization and design checkpoint.', $content);
        $this->assertStringContainsString('does not implement any migration, model, route, controller, service, request class, resource class, ledger mutation code, receipt generation code, frontend screen, reminder, report, or Excel import.', $content);
        $this->assertStringContainsString('Milestone 3G defined the payment recording rules.', $content);
        $this->assertStringContainsString('Milestone 3H defines the future database foundation that may support those rules.', $content);
    }

    public function test_scope_gate_preserves_ledger_as_balance_source_of_truth(): void
    {
        $content = file_get_contents(base_path($this->scopeDocumentPath));

        $this->assertStringContainsString('The ledger remains the source of truth for member balances.', $content);
        $this->assertStringContainsString('Payments must not overwrite member balance values.', $content);
        $this->assertStringContainsString('Receipts must not overwrite member balance values.', $content);
        $this->assertStringContainsString('Total Debits - Total Credits = Current Balance', $content);
    }

    public function test_scope_gate_defines_future_payments_table_design(): void
    {
        $content = file_get_contents(base_path($this->scopeDocumentPath));

        $this->assertStringContainsString('## Future Payments Table Design', $content);

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
            $this->assertStringContainsString($field, $content);
        }

        $this->assertStringContainsString('The payment amount must be positive.', $content);
        $this->assertStringContainsString('The selected member must belong to the selected club.', $content);
    }

    public function test_scope_gate_defines_future_receipts_table_design(): void
    {
        $content = file_get_contents(base_path($this->scopeDocumentPath));

        $this->assertStringContainsString('## Future Receipts Table Design', $content);

        foreach ([
            'payment_id',
            'receipt_number',
            'receipt_date',
            'issued_by_user_id',
            'status',
            'cancelled_at',
            'cancelled_by_user_id',
            'cancellation_reason',
        ] as $field) {
            $this->assertStringContainsString($field, $content);
        }

        $this->assertStringContainsString('Receipt numbers must be unique within the club.', $content);
        $this->assertStringContainsString('Receipt generation must not be implemented in Milestone 3H.', $content);
    }

    public function test_scope_gate_defines_payment_ledger_and_receipt_relationships(): void
    {
        $content = file_get_contents(base_path($this->scopeDocumentPath));

        $this->assertStringContainsString('## Payment To Ledger Relationship', $content);
        $this->assertStringContainsString('payments.ledger_entry_id references member_ledger_entries.id', $content);
        $this->assertStringContainsString('member_ledger_entries.source_type = payment', $content);
        $this->assertStringContainsString('member_ledger_entries.entry_type = credit', $content);
        $this->assertStringContainsString('The payment record and the ledger credit must belong to the same club.', $content);
        $this->assertStringContainsString('The payment member and ledger member must match.', $content);

        $this->assertStringContainsString('## Receipt To Payment Relationship', $content);
        $this->assertStringContainsString('receipts.payment_id references payments.id', $content);
        $this->assertStringContainsString('receipts.club_id must match payments.club_id', $content);
        $this->assertStringContainsString('Receipt cancellation must not delete the payment.', $content);
    }

    public function test_scope_gate_defines_status_cancellation_reversal_and_receipt_numbering_boundaries(): void
    {
        $content = file_get_contents(base_path($this->scopeDocumentPath));

        $this->assertStringContainsString('## Status Values', $content);
        $this->assertStringContainsString('draft', $content);
        $this->assertStringContainsString('posted', $content);
        $this->assertStringContainsString('cancelled', $content);
        $this->assertStringContainsString('reversed', $content);
        $this->assertStringContainsString('Only posted payments should affect member balance through posted ledger credit entries.', $content);

        $this->assertStringContainsString('## Cancellation And Reversal Boundary', $content);
        $this->assertStringContainsString('Payment cancellation or reversal must not delete financial history.', $content);
        $this->assertStringContainsString('Milestone 3H does not implement cancellation or reversal.', $content);

        $this->assertStringContainsString('## Receipt Numbering Boundary', $content);
        $this->assertStringContainsString('Receipt numbers must not be reused after cancellation.', $content);
        $this->assertStringContainsString('Race-condition safe numbering must be considered before implementation.', $content);
    }

    public function test_scope_gate_defines_constraints_indexes_audit_and_role_boundaries(): void
    {
        $content = file_get_contents(base_path($this->scopeDocumentPath));

        $this->assertStringContainsString('## Required Constraints', $content);
        $this->assertStringContainsString('Foreign key from payments.club_id to clubs.id', $content);
        $this->assertStringContainsString('Foreign key from payments.member_id to members.id', $content);
        $this->assertStringContainsString('Foreign key from payments.ledger_entry_id to member_ledger_entries.id', $content);
        $this->assertStringContainsString('Unique receipt number per club', $content);
        $this->assertStringContainsString('Indexed receipt_number', $content);

        $this->assertStringContainsString('## Audit Requirements', $content);
        $this->assertStringContainsString('Who recorded the payment', $content);
        $this->assertStringContainsString('Linked ledger entry', $content);
        $this->assertStringContainsString('Receipt number if issued', $content);
        $this->assertStringContainsString('Audit trail must be retained.', $content);

        $this->assertStringContainsString('## Role Boundary', $content);
        $this->assertStringContainsString('Treasurer is the primary payment recording role.', $content);
        $this->assertStringContainsString('Secretary payment recording is not automatically approved.', $content);
        $this->assertStringContainsString('Normal members must never record payments.', $content);
    }

    public function test_scope_gate_defines_3i_boundary_and_blocks_3h_implementation(): void
    {
        $content = file_get_contents(base_path($this->scopeDocumentPath));

        $this->assertStringContainsString('## What 3I May Implement If Separately Approved', $content);
        $this->assertStringContainsString('payments migration', $content);
        $this->assertStringContainsString('receipts migration', $content);
        $this->assertStringContainsString('Payment model', $content);
        $this->assertStringContainsString('Receipt model', $content);
        $this->assertStringContainsString('3I must still avoid actual payment recording behavior unless separately authorized.', $content);

        $this->assertStringContainsString('## Not Implemented In 3H', $content);

        foreach ([
            'No payment migration',
            'No receipt migration',
            'No payment model',
            'No receipt model',
            'No payment route',
            'No receipt route',
            'No payment controller',
            'No receipt controller',
            'No payment recording service',
            'No receipt generation service',
            'No ledger mutation code',
            'No payment posting code',
            'No receipt generation code',
            'No payment cancellation code',
            'No payment reversal code',
            'No special collection payment allocation',
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

    public function test_3h_does_not_register_payment_receipt_or_finance_mutation_routes(): void
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
                "Milestone 3H must not register route containing [{$fragment}]."
            );
        }
    }

    public function test_3h_does_not_create_payment_or_receipt_database_implementation_files_before_3i(): void
    {
        $threeIImplementationDocument = base_path('docs/milestone_3i_payment_recording_database_foundation_implementation.md');

        if (file_exists($threeIImplementationDocument)) {
            $content = file_get_contents($threeIImplementationDocument);

            $this->assertStringContainsString('Milestone 3I implements only the narrow database foundation for future payment recording.', $content);
            $this->assertStringContainsString('This milestone prepares the database layer only.', $content);
            $this->assertStringContainsString('payment recording API', $content);
            $this->assertStringContainsString('ledger posting workflow', $content);

            return;
        }

        $migrationFiles = array_merge(
            glob(base_path('database/migrations/*payment*')) ?: [],
            glob(base_path('database/migrations/*payments*')) ?: [],
            glob(base_path('database/migrations/*receipt*')) ?: [],
            glob(base_path('database/migrations/*receipts*')) ?: [],
        );

        $this->assertSame([], $migrationFiles, 'Milestone 3H must not create payment or receipt migrations before 3I.');

        foreach ([
            'app/Models/Payment.php',
            'app/Models/Receipt.php',
            'app/Http/Controllers/PaymentController.php',
            'app/Http/Controllers/ReceiptController.php',
            'app/Services/Payments/PaymentRecordingService.php',
            'app/Services/Payments/ReceiptGenerationService.php',
        ] as $forbiddenPath) {
            $this->assertFileDoesNotExist(base_path($forbiddenPath), "Milestone 3H must not create [{$forbiddenPath}] before 3I.");
        }
    }
}