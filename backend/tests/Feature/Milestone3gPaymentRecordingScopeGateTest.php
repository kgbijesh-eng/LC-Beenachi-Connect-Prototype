<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3gPaymentRecordingScopeGateTest extends TestCase
{
    private string $scopeDocumentPath = 'docs/milestone_3g_payment_recording_scope_gate.md';

    public function test_scope_document_exists(): void
    {
        $this->assertFileExists(base_path($this->scopeDocumentPath));
    }

    public function test_scope_gate_defines_docs_tests_only_status_and_payment_risk(): void
    {
        $content = file_get_contents(base_path($this->scopeDocumentPath));

        $this->assertStringContainsString('Milestone 3G is a docs/tests-only scope gate.', $content);
        $this->assertStringContainsString('Payment recording is higher risk than balance reading because it changes member financial data.', $content);
        $this->assertStringContainsString('The ledger must remain the source of truth for member balances.', $content);
        $this->assertStringContainsString('Payments must not directly overwrite member balance values.', $content);
    }

    public function test_scope_gate_defines_who_can_record_payments(): void
    {
        $content = file_get_contents(base_path($this->scopeDocumentPath));

        $this->assertStringContainsString('## Who Can Record Payments', $content);
        $this->assertStringContainsString('Treasurer', $content);
        $this->assertStringContainsString('President/Admin', $content);
        $this->assertStringContainsString('Secretary payment recording must not be automatically authorized.', $content);
        $this->assertStringContainsString('Normal members must never record payments.', $content);
    }

    public function test_scope_gate_defines_role_boundaries(): void
    {
        $content = file_get_contents(base_path($this->scopeDocumentPath));

        $this->assertStringContainsString('## Treasurer Permission Boundary', $content);
        $this->assertStringContainsString('Treasurer is the primary future role for recording payments.', $content);

        $this->assertStringContainsString('## President/Admin Permission Boundary', $content);
        $this->assertStringContainsString('President/Admin access must not bypass audit trail requirements.', $content);

        $this->assertStringContainsString('## Secretary Permission Boundary', $content);
        $this->assertStringContainsString('Secretary payment recording, if ever allowed, must be separately authorized.', $content);

        $this->assertStringContainsString('## Normal Member Visibility', $content);
        $this->assertStringContainsString('Normal members must not see:', $content);
    }

    public function test_scope_gate_defines_required_payment_fields(): void
    {
        $content = file_get_contents(base_path($this->scopeDocumentPath));

        $this->assertStringContainsString('## Required Future Payment Fields', $content);

        foreach ([
            'club_id',
            'member_id',
            'amount',
            'payment_date',
            'payment_mode',
            'reference_number',
            'notes',
            'received_by_user_id',
            'status',
        ] as $field) {
            $this->assertStringContainsString($field, $content);
        }

        $this->assertStringContainsString('Payment amount must be positive.', $content);
        $this->assertStringContainsString('Member must belong to the selected club.', $content);
    }

    public function test_scope_gate_defines_payment_to_ledger_credit_rule(): void
    {
        $content = file_get_contents(base_path($this->scopeDocumentPath));

        $this->assertStringContainsString('## Ledger Credit Rule', $content);
        $this->assertStringContainsString('A valid posted payment must create a member ledger credit entry.', $content);
        $this->assertStringContainsString('entry_type: credit', $content);
        $this->assertStringContainsString('source_type: payment', $content);
        $this->assertStringContainsString('The payment must not silently modify debit entries.', $content);
        $this->assertStringContainsString('The payment must not overwrite calculated balance.', $content);
    }

    public function test_scope_gate_defines_receipt_cancellation_and_audit_boundaries(): void
    {
        $content = file_get_contents(base_path($this->scopeDocumentPath));

        $this->assertStringContainsString('## Receipt Numbering Rule', $content);
        $this->assertStringContainsString('Receipt numbers must be unique within the club.', $content);
        $this->assertStringContainsString('Receipt numbers should not be reused after cancellation.', $content);

        $this->assertStringContainsString('## Cancellation and Reversal Rule', $content);
        $this->assertStringContainsString('Payment cancellation or reversal must not delete financial history.', $content);
        $this->assertStringContainsString('Normal members must not cancel payments.', $content);

        $this->assertStringContainsString('## Audit Requirements', $content);
        $this->assertStringContainsString('Who recorded the payment', $content);
        $this->assertStringContainsString('Cancellation or reversal reason when applicable', $content);
        $this->assertStringContainsString('Audit trail must be retained.', $content);
    }

    public function test_scope_gate_defines_special_collection_and_fellowship_boundaries(): void
    {
        $content = file_get_contents(base_path($this->scopeDocumentPath));

        $this->assertStringContainsString('## Special Collections', $content);
        $this->assertStringContainsString('Payments made toward special collections must remain identifiable.', $content);
        $this->assertStringContainsString('Milestone 3G does not implement special collection payment allocation.', $content);

        $this->assertStringContainsString('## Fellowship Payments', $content);
        $this->assertStringContainsString('Milestone 3G does not implement fellowship payment recording.', $content);
    }

    public function test_scope_gate_blocks_payment_implementation_until_later_milestone(): void
    {
        $content = file_get_contents(base_path($this->scopeDocumentPath));

        $this->assertStringContainsString('## Not Implemented In 3G', $content);

        $blockedItems = [
            'No payment route',
            'No payment controller',
            'No payment request class',
            'No payment resource class',
            'No payment table',
            'No receipt table',
            'No migration',
            'No ledger mutation code',
            'No payment recording service',
            'No receipt generation code',
            'No payment cancellation code',
            'No payment reversal code',
            'No dues creation workflow',
            'No special collection workflow',
            'No fellowship payment workflow',
            'No frontend changes',
            'No dashboard',
            'No reminder automation',
            'No reports',
            'No Excel import',
        ];

        foreach ($blockedItems as $blockedItem) {
            $this->assertStringContainsString($blockedItem, $content);
        }
    }

    public function test_3g_does_not_register_payment_receipt_or_finance_mutation_routes(): void
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
                "Milestone 3G must not register route containing [{$fragment}]."
            );
        }
    }
}
