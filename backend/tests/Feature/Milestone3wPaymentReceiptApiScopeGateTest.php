<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3wPaymentReceiptApiScopeGateTest extends TestCase
{
    private function document(): string
    {
        $path = base_path("docs/milestone_3w_payment_receipt_api_scope_gate.md");

        $this->assertFileExists($path);

        return file_get_contents($path);
    }

    public function test_scope_gate_document_records_api_boundary(): void
    {
        $document = $this->document();

        foreach ([
            "Milestone 3W - Payment Receipt API Scope Gate",
            "SCOPE_GATE_ONLY",
            "does not implement the API",
            "only records the approved scope",
            "ReceiptGenerationService",
        ] as $needle) {
            $this->assertStringContainsString($needle, $document);
        }
    }

    public function test_scope_gate_defines_future_receipt_endpoints(): void
    {
        $document = $this->document();

        foreach ([
            "POST /api/v1/clubs/{club}/members/{member}/payments/{payment}/receipt",
            "GET /api/v1/clubs/{club}/members/{member}/payments/{payment}/receipt",
            "Generate a receipt for an already recorded posted payment",
            "View receipt metadata for an already generated receipt",
            "Do not auto-generate receipt from the view endpoint",
        ] as $needle) {
            $this->assertStringContainsString($needle, $document);
        }
    }

    public function test_scope_gate_defines_permission_and_fail_closed_rules(): void
    {
        $document = $this->document();

        foreach ([
            "President/Admin may generate receipts",
            "Treasurer may generate receipts",
            "Normal Member must not generate receipts",
            "Normal Member may view only their own generated receipt metadata",
            "Normal Member must not view other members receipts",
            "Unauthenticated request must fail",
            "Wrong club request must fail",
            "Missing permission must fail",
        ] as $needle) {
            $this->assertStringContainsString($needle, $document);
        }
    }

    public function test_scope_gate_defines_response_boundaries(): void
    {
        $document = $this->document();

        foreach ([
            "receipt_id",
            "receipt_number",
            "club_id",
            "member_id",
            "member_name",
            "payment_id",
            "amount",
            "payment_date",
            "payment_method",
            "receipt_status",
            "generated_at",
            "generated_by",
            "Other members payment details",
            "PDF binary data",
            "WhatsApp/SMS/email delivery payloads",
            "Report data",
            "Excel import/export data",
        ] as $needle) {
            $this->assertStringContainsString($needle, $document);
        }
    }

    public function test_scope_gate_blocks_implementation_surfaces(): void
    {
        $document = $this->document();

        foreach ([
            "No route",
            "No controller",
            "No request class",
            "No resource class",
            "No service change",
            "No PDF",
            "No frontend",
            "No WhatsApp/SMS/email",
            "No reports",
            "No Excel",
            "No cancellation/reversal/correction/reissue workflow",
        ] as $needle) {
            $this->assertStringContainsString($needle, $document);
        }

    }

    public function test_scope_gate_allows_only_scope_document_and_scope_test(): void
    {
        $document = $this->document();

        $this->assertStringContainsString("backend/docs/milestone_3w_payment_receipt_api_scope_gate.md", $document);
        $this->assertStringContainsString("backend/tests/Feature/Milestone3wPaymentReceiptApiScopeGateTest.php", $document);
        $this->assertStringContainsString("Milestone 3X - Payment Receipt API Implementation Authorization", $document);
    }
}
