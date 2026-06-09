<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3abPaymentReceiptPdfScopeGateTest extends TestCase
{
    private function milestoneDocument(): string
    {
        return base_path('docs/milestone_3ab_payment_receipt_pdf_scope_gate.md');
    }

    public function test_milestone_3ab_scope_document_exists(): void
    {
        $this->assertFileExists($this->milestoneDocument());
    }

    public function test_milestone_3ab_declares_docs_tests_only_scope_gate(): void
    {
        $document = file_get_contents($this->milestoneDocument());

        $this->assertStringContainsString('docs/tests-only scope gate', $document);
        $this->assertStringContainsString('does not implement PDF generation', $document);
        $this->assertStringContainsString('changes only documentation and validation tests', $document);
        $this->assertStringContainsString('does not change production finance behavior', $document);
    }

    public function test_milestone_3ab_defines_pdf_receipt_purpose(): void
    {
        $document = file_get_contents($this->milestoneDocument());

        $this->assertStringContainsString('printable and downloadable proof of an already issued receipt', $document);
        $this->assertStringContainsString('must represent receipt data already created by the backend receipt generation service', $document);
        $this->assertStringContainsString('must not create payments', $document);
        $this->assertStringContainsString('generate duplicate receipts', $document);
        $this->assertStringContainsString('cancel receipts', $document);
        $this->assertStringContainsString('reverse payments', $document);
    }

    public function test_milestone_3ab_defines_future_pdf_fields(): void
    {
        $document = file_get_contents($this->milestoneDocument());

        $this->assertStringContainsString('Club name', $document);
        $this->assertStringContainsString('Receipt number', $document);
        $this->assertStringContainsString('Receipt date', $document);
        $this->assertStringContainsString('Financial year', $document);
        $this->assertStringContainsString('Member name', $document);
        $this->assertStringContainsString('Payment amount', $document);
        $this->assertStringContainsString('Payment mode', $document);
        $this->assertStringContainsString('Receipt status', $document);
        $this->assertStringContainsString('Non-editable receipt disclaimer', $document);
    }

    public function test_milestone_3ab_defines_access_rules(): void
    {
        $document = file_get_contents($this->milestoneDocument());

        $this->assertStringContainsString('President/Admin may generate or download receipt PDFs', $document);
        $this->assertStringContainsString('Secretary may generate or download receipt PDFs when permission allows', $document);
        $this->assertStringContainsString('Treasurer may generate or download receipt PDFs when permission allows', $document);
        $this->assertStringContainsString('Normal Member may download only their own already-issued receipt PDFs', $document);
        $this->assertStringContainsString('Cross-club receipt PDF access must fail closed', $document);
        $this->assertStringContainsString('Unauthenticated users must not access receipt PDFs', $document);
    }

    public function test_milestone_3ab_defines_storage_decision_boundary(): void
    {
        $document = file_get_contents($this->milestoneDocument());

        $this->assertStringContainsString('Generated on demand without permanent file storage', $document);
        $this->assertStringContainsString('Stored after generation', $document);
        $this->assertStringContainsString('Regenerated from receipt data when requested', $document);
        $this->assertStringContainsString('Milestone 3AB does not decide or implement storage', $document);
        $this->assertStringContainsString('No storage disk, file path, public URL, signed URL, or media table behavior is authorized', $document);
    }

    public function test_milestone_3ab_defines_future_authorization_requirements(): void
    {
        $document = file_get_contents($this->milestoneDocument());

        $this->assertStringContainsString('PDF generation library or package choice', $document);
        $this->assertStringContainsString('PDF rendering method', $document);
        $this->assertStringContainsString('PDF template location', $document);
        $this->assertStringContainsString('Controller or service boundary', $document);
        $this->assertStringContainsString('Route boundary', $document);
        $this->assertStringContainsString('Download response behavior', $document);
        $this->assertStringContainsString('Permission and privacy checks', $document);
        $this->assertStringContainsString('No duplicate receipt generation behavior', $document);
    }

    public function test_milestone_3ab_blocks_implementation_scope(): void
    {
        $document = file_get_contents($this->milestoneDocument());

        $this->assertStringContainsString('No PDF implementation', $document);
        $this->assertStringContainsString('No PDF package or library installation', $document);
        $this->assertStringContainsString('No PDF route', $document);
        $this->assertStringContainsString('No receipt controller change', $document);
        $this->assertStringContainsString('No receipt service change', $document);
        $this->assertStringContainsString('No model change', $document);
        $this->assertStringContainsString('No migration change', $document);
        $this->assertStringContainsString('No frontend receipt download button', $document);
        $this->assertStringContainsString('No WhatsApp sharing', $document);
        $this->assertStringContainsString('No SMS sharing', $document);
        $this->assertStringContainsString('No email sharing', $document);
        $this->assertStringContainsString('No reports', $document);
        $this->assertStringContainsString('No Excel import', $document);
    }

    public function test_milestone_3ab_selects_next_authorization_milestone_without_implementation(): void
    {
        $document = file_get_contents($this->milestoneDocument());

        $this->assertStringContainsString('Milestone 3AC — Payment Receipt PDF Implementation Authorization', $document);
        $this->assertStringContainsString('Milestone 3AC should remain docs/tests-only', $document);
        $this->assertStringContainsString('Actual PDF generation should not happen until a later implementation milestone after authorization', $document);
    }

    public function test_milestone_3ab_does_not_register_pdf_routes(): void
    {
        $routes = collect(app('router')->getRoutes())->map(fn ($route) => $route->uri())->implode("\n");

        $this->assertStringNotContainsString('pdf', strtolower($routes));
    }

    public function test_milestone_3ab_does_not_install_common_pdf_packages(): void
    {
        $composer = file_get_contents(base_path('composer.json'));

        $this->assertStringNotContainsString('barryvdh/laravel-dompdf', $composer);
        $this->assertStringNotContainsString('dompdf/dompdf', $composer);
        $this->assertStringNotContainsString('mpdf/mpdf', $composer);
        $this->assertStringNotContainsString('spatie/browsershot', $composer);
    }
}