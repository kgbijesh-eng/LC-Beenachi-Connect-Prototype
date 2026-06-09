<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3acPaymentReceiptPdfImplementationAuthorizationTest extends TestCase
{
    private function milestoneDocument(): string
    {
        return base_path('docs/milestone_3ac_payment_receipt_pdf_implementation_authorization.md');
    }

    public function test_milestone_3ac_authorization_document_exists(): void
    {
        $this->assertFileExists($this->milestoneDocument());
    }

    public function test_milestone_3ac_records_docs_tests_only_authorization_status(): void
    {
        $document = file_get_contents($this->milestoneDocument());

        $this->assertStringContainsString('docs/tests-only implementation authorization', $document);
        $this->assertStringContainsString('authorizes a future implementation milestone only', $document);
        $this->assertStringContainsString('does not implement PDF generation', $document);
        $this->assertStringContainsString('does not change production finance behavior', $document);
    }

    public function test_milestone_3ac_authorizes_future_3ad_narrow_implementation(): void
    {
        $document = file_get_contents($this->milestoneDocument());

        $this->assertStringContainsString('Milestone 3AD — Payment Receipt PDF Narrow Implementation', $document);
        $this->assertStringContainsString('may implement backend-only receipt PDF generation and download behavior', $document);
        $this->assertStringContainsString('Milestone 3AC itself remains docs/tests-only', $document);
    }

    public function test_milestone_3ac_defines_package_authorization_without_installing_it(): void
    {
        $document = file_get_contents($this->milestoneDocument());
        $composer = file_get_contents(base_path('composer.json'));

        $this->assertStringContainsString('Install and use `barryvdh/laravel-dompdf`', $document);
        $this->assertStringContainsString('Milestone 3AC does not install the package', $document);
        $this->assertStringNotContainsString('barryvdh/laravel-dompdf', $composer);
    }

    public function test_milestone_3ac_defines_future_file_boundary(): void
    {
        $document = file_get_contents($this->milestoneDocument());

        $this->assertStringContainsString('Composer dependency files', $document);
        $this->assertStringContainsString('receipt PDF controller', $document);
        $this->assertStringContainsString('receipt PDF service', $document);
        $this->assertStringContainsString('receipt PDF Blade template', $document);
        $this->assertStringContainsString('receipt PDF feature test', $document);
        $this->assertStringContainsString('Route registration for the approved PDF endpoint only', $document);
        $this->assertStringContainsString('must not modify unrelated finance, member, auth, ledger, report, reminder, Excel, fellowship, or frontend files', $document);
    }

    public function test_milestone_3ac_defines_future_route_boundary(): void
    {
        $document = file_get_contents($this->milestoneDocument());

        $this->assertStringContainsString('GET /api/v1/clubs/{club}/members/{member}/payments/{payment}/receipt/pdf', $document);
        $this->assertStringContainsString('Require authentication', $document);
        $this->assertStringContainsString('Require club access', $document);
        $this->assertStringContainsString('Return a PDF download response', $document);
        $this->assertStringContainsString('Not generate duplicate receipts', $document);
        $this->assertStringContainsString('No public PDF route is authorized', $document);
        $this->assertStringContainsString('No signed public URL route is authorized', $document);
    }

    public function test_milestone_3ac_defines_controller_service_and_template_boundaries(): void
    {
        $document = file_get_contents($this->milestoneDocument());

        $this->assertStringContainsString('Finds an already-issued receipt for the selected payment', $document);
        $this->assertStringContainsString('Verifies the authenticated user\'s permission to view/download the receipt', $document);
        $this->assertStringContainsString('Renders the receipt data into a PDF', $document);
        $this->assertStringContainsString('one Blade template for the receipt PDF', $document);
        $this->assertStringContainsString('must not include editable form fields or frontend interactivity', $document);
    }

    public function test_milestone_3ac_defines_on_demand_no_storage_decision(): void
    {
        $document = file_get_contents($this->milestoneDocument());

        $this->assertStringContainsString('generate PDFs on demand from existing receipt data', $document);
        $this->assertStringContainsString('Permanent PDF file storage is not authorized in 3AD', $document);
        $this->assertStringContainsString('Storage disk configuration', $document);
        $this->assertStringContainsString('Public file URLs', $document);
        $this->assertStringContainsString('Signed file URLs', $document);
        $this->assertStringContainsString('Background PDF generation jobs', $document);
    }

    public function test_milestone_3ac_defines_future_access_and_test_requirements(): void
    {
        $document = file_get_contents($this->milestoneDocument());

        $this->assertStringContainsString('President/Admin may download receipt PDFs for members in their club', $document);
        $this->assertStringContainsString('Normal Member may download only their own already-issued receipt PDFs', $document);
        $this->assertStringContainsString('Cross-club access must fail closed', $document);
        $this->assertStringContainsString('Unauthenticated access must fail closed', $document);
        $this->assertStringContainsString('PDF route does not create duplicate receipts', $document);
        $this->assertStringContainsString('PDF route does not mutate ledger entries', $document);
    }

    public function test_milestone_3ac_blocks_current_implementation_scope(): void
    {
        $document = file_get_contents($this->milestoneDocument());

        $this->assertStringContainsString('No PDF package installation', $document);
        $this->assertStringContainsString('No PDF implementation', $document);
        $this->assertStringContainsString('No PDF template', $document);
        $this->assertStringContainsString('No PDF route', $document);
        $this->assertStringContainsString('No controller change', $document);
        $this->assertStringContainsString('No service change', $document);
        $this->assertStringContainsString('No model change', $document);
        $this->assertStringContainsString('No migration change', $document);
        $this->assertStringContainsString('No frontend', $document);
        $this->assertStringContainsString('No WhatsApp sharing', $document);
        $this->assertStringContainsString('No SMS sharing', $document);
        $this->assertStringContainsString('No email sharing', $document);
        $this->assertStringContainsString('No reports', $document);
        $this->assertStringContainsString('No Excel import', $document);
    }

    public function test_milestone_3ac_does_not_register_pdf_routes_now(): void
    {
        $routes = collect(app('router')->getRoutes())->map(fn ($route) => $route->uri())->implode("\n");

        $this->assertStringNotContainsString('receipt/pdf', strtolower($routes));
    }

    public function test_milestone_3ac_does_not_add_pdf_files_now(): void
    {
        $this->assertFileDoesNotExist(base_path('resources/views/pdf/receipt.blade.php'));
        $this->assertFileDoesNotExist(base_path('app/Services/Finance/PaymentReceiptPdfService.php'));
        $this->assertFileDoesNotExist(base_path('app/Http/Controllers/Api/V1/Finance/PaymentReceiptPdfController.php'));
    }
}