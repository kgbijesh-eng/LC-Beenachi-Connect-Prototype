<?php

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3atFinanceModulePostPaymentRecordingFrontendClosureReviewTest extends TestCase
{
    public function test_3at_review_document_exists_and_is_docs_tests_only(): void
    {
        $document = $this->reviewDocument();

        $this->assertStringContainsString('Milestone 3AT — Finance Module Post Payment Recording Frontend Closure Review', $document);
        $this->assertStringContainsString('Review only', $document);
        $this->assertStringContainsString('docs/tests-only', $document);
        $this->assertStringContainsString('43f436493c57986d3f912fc05a836db00d6b8465', $document);
        $this->assertStringContainsString('backend/docs/milestone_3at_finance_module_post_payment_recording_frontend_closure_review.md', $document);
        $this->assertStringContainsString('backend/tests/Feature/Milestone3atFinanceModulePostPaymentRecordingFrontendClosureReviewTest.php', $document);
    }

    public function test_3at_reconciles_closed_finance_capabilities_through_payment_recording_frontend(): void
    {
        $document = $this->reviewDocument();

        $this->assertStringContainsString('Member ledger database foundation', $document);
        $this->assertStringContainsString('Member ledger balance calculation service', $document);
        $this->assertStringContainsString('Member ledger balance read API', $document);
        $this->assertStringContainsString('Payment database foundation', $document);
        $this->assertStringContainsString('Payment recording service', $document);
        $this->assertStringContainsString('Payment recording API', $document);
        $this->assertStringContainsString('Receipt generation backend service', $document);
        $this->assertStringContainsString('Receipt JSON API', $document);
        $this->assertStringContainsString('Receipt PDF download backend route', $document);
        $this->assertStringContainsString('Outstanding dues and member finance summary read API', $document);
        $this->assertStringContainsString('Member finance summary frontend', $document);
        $this->assertStringContainsString('Payment recording frontend', $document);
    }

    public function test_3at_records_current_role_based_finance_state(): void
    {
        $document = $this->reviewDocument();

        $this->assertStringContainsString('Normal Member can:', $document);
        $this->assertStringContainsString('View own member finance summary', $document);
        $this->assertStringContainsString('View own current dues', $document);
        $this->assertStringContainsString('Remain blocked from recording payments', $document);

        $this->assertStringContainsString('President, Secretary, and Treasurer can:', $document);
        $this->assertStringContainsString('View selected member finance summary', $document);
        $this->assertStringContainsString('Record member payment through the existing backend payment API', $document);
    }

    public function test_3at_preserves_backend_source_of_truth_rules(): void
    {
        $document = $this->reviewDocument();

        $this->assertStringContainsString('The backend remains the source of truth', $document);
        $this->assertStringContainsString('ledger entries', $document);
        $this->assertStringContainsString('member balances', $document);
        $this->assertStringContainsString('payment records', $document);
        $this->assertStringContainsString('payment status', $document);
        $this->assertStringContainsString('received_by_user_id', $document);
        $this->assertStringContainsString('ledger_entry_id', $document);
        $this->assertStringContainsString('receipt generation', $document);
        $this->assertStringContainsString('receipt numbers', $document);
        $this->assertStringContainsString('The frontend must not locally mutate ledger entries', $document);
    }

    public function test_3at_identifies_remaining_finance_gaps_without_implementing_them(): void
    {
        $document = $this->reviewDocument();

        $this->assertStringContainsString('Receipt generation frontend', $document);
        $this->assertStringContainsString('Receipt view/download frontend', $document);
        $this->assertStringContainsString('Receipt list real implementation', $document);
        $this->assertStringContainsString('Payment history real implementation', $document);
        $this->assertStringContainsString('Finance reports', $document);
        $this->assertStringContainsString('Balance sheet', $document);
        $this->assertStringContainsString('Member due reminders', $document);
        $this->assertStringContainsString('Important information reminders', $document);
        $this->assertStringContainsString('Excel import', $document);
        $this->assertStringContainsString('Special collection allocation workflow', $document);
        $this->assertStringContainsString('Fellowship payment allocation workflow', $document);
        $this->assertStringContainsString('Payment cancellation', $document);
        $this->assertStringContainsString('Payment reversal', $document);
    }

    public function test_3at_selects_3au_receipt_frontend_scope_gate_without_implementation(): void
    {
        $document = $this->reviewDocument();

        $this->assertStringContainsString('The next clean step should be a new docs/tests-only scope gate', $document);
        $this->assertStringContainsString('Milestone 3AU — Payment Receipt Frontend Scope Gate', $document);
        $this->assertStringContainsString('Milestone 3AU should define future receipt frontend behavior only. It should not implement it.', $document);
        $this->assertStringContainsString('Who can generate a receipt from an already recorded payment', $document);
        $this->assertStringContainsString('Who can view/download receipt PDF', $document);
        $this->assertStringContainsString('avoid generating duplicate receipts', $document);
        $this->assertStringContainsString('receipt number and PDF authority in the backend', $document);
    }

    public function test_3at_blocks_runtime_and_workflow_drift(): void
    {
        $document = $this->reviewDocument();

        $this->assertStringContainsString('3AT does not modify:', $document);
        $this->assertStringContainsString('app/page.tsx', $document);
        $this->assertStringContainsString('routes', $document);
        $this->assertStringContainsString('controllers', $document);
        $this->assertStringContainsString('requests', $document);
        $this->assertStringContainsString('resources', $document);
        $this->assertStringContainsString('services', $document);
        $this->assertStringContainsString('models', $document);
        $this->assertStringContainsString('migrations', $document);
        $this->assertStringContainsString('seeders', $document);
        $this->assertStringContainsString('receipt frontend', $document);
        $this->assertStringContainsString('reports', $document);
        $this->assertStringContainsString('reminders', $document);
        $this->assertStringContainsString('Excel import', $document);
    }

    public function test_existing_payment_and_receipt_routes_remain_available_without_new_review_routes(): void
    {
        $paymentRoutes = collect(Route::getRoutes())
            ->filter(fn ($route) => $route->uri() === 'api/v1/clubs/{club}/members/{member}/payments')
            ->values();

        $receiptRoutes = collect(Route::getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1/clubs/{club}/members/{member}/payments/{payment}/receipt'))
            ->values();

        $reviewRoutes = collect(Route::getRoutes())
            ->filter(fn ($route) => str_contains($route->uri(), '3at') || str_contains($route->uri(), 'closure-review'))
            ->values();

        $this->assertCount(1, $paymentRoutes);
        $this->assertNotEmpty($receiptRoutes);
        $this->assertCount(0, $reviewRoutes);
    }

    private function reviewDocument(): string
    {
        $path = base_path('docs/milestone_3at_finance_module_post_payment_recording_frontend_closure_review.md');

        $this->assertFileExists($path);

        return file_get_contents($path);
    }
}
