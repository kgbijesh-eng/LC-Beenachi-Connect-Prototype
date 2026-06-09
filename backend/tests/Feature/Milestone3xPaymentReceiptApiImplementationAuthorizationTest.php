<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3xPaymentReceiptApiImplementationAuthorizationTest extends TestCase
{
    private function document(): string
    {
        $path = base_path("docs/milestone_3x_payment_receipt_api_implementation_authorization.md");

        $this->assertFileExists($path);

        return file_get_contents($path);
    }

    public function test_authorization_document_exists_and_records_docs_tests_only_status(): void
    {
        $document = $this->document();

        foreach ([
            "Milestone 3X - Payment Receipt API Implementation Authorization",
            "DOCS_TESTS_ONLY_IMPLEMENTATION_AUTHORIZATION",
            "does not implement routes",
            "Milestone 3Y - Payment Receipt API Narrow Implementation",
        ] as $needle) {
            $this->assertStringContainsString($needle, $document);
        }
    }

    public function test_authorization_defines_exact_future_route_boundary(): void
    {
        $document = $this->document();

        foreach ([
            "POST /api/v1/clubs/{club}/members/{member}/payments/{payment}/receipt",
            "GET /api/v1/clubs/{club}/members/{member}/payments/{payment}/receipt",
            "The POST route may generate receipt metadata",
            "The GET route may return existing generated receipt metadata",
            "The GET route must not auto-generate a receipt",
            "Both routes must fail closed",
        ] as $needle) {
            $this->assertStringContainsString($needle, $document);
        }
    }

    public function test_authorization_defines_exact_future_file_boundary(): void
    {
        $document = $this->document();

        foreach ([
            "app/Http/Controllers/Api/V1/Finance/ReceiptController.php",
            "app/Http/Requests/Finance/GenerateReceiptRequest.php",
            "app/Http/Resources/Finance/ReceiptResource.php",
            "docs/milestone_3y_payment_receipt_api_narrow_implementation.md",
            "tests/Feature/Milestone3yPaymentReceiptApiNarrowImplementationTest.php",
        ] as $needle) {
            $this->assertStringContainsString($needle, $document);
        }
    }

    public function test_authorization_defines_controller_request_and_resource_guardrails(): void
    {
        $document = $this->document();

        foreach ([
            "ReceiptController may delegate receipt generation to ReceiptGenerationService",
            "ReceiptController must not duplicate receipt number logic",
            "ReceiptController must not mutate ledger entries",
            "GenerateReceiptRequest may authorize authenticated club-scoped receipt generation",
            "must not accept backend controlled fields",
            "ReceiptResource may return safe receipt metadata only",
            "must not expose raw audit log payloads",
        ] as $needle) {
            $this->assertStringContainsString($needle, $document);
        }
    }

    public function test_authorization_defines_response_permission_and_authority_boundaries(): void
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
            "President/Admin may generate and view receipts",
            "Treasurer may generate and view receipts",
            "Normal Member must not generate receipts",
            "Normal Member must not view other members receipts",
            "ReceiptGenerationService remains the receipt generation authority",
        ] as $needle) {
            $this->assertStringContainsString($needle, $document);
        }
    }

    public function test_authorization_blocks_3x_implementation_scope(): void
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

    public function test_authorization_allows_only_3x_doc_and_test_files(): void
    {
        $document = $this->document();

        $this->assertStringContainsString("backend/docs/milestone_3x_payment_receipt_api_implementation_authorization.md", $document);
        $this->assertStringContainsString("backend/tests/Feature/Milestone3xPaymentReceiptApiImplementationAuthorizationTest.php", $document);
        $this->assertStringContainsString("only the two 3X files are changed", $document);
    }
}
