<?php

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3axPaymentReceiptFrontendValidationAndClosureTest extends TestCase
{
    public function test_3ax_closure_document_exists_and_records_validation_results(): void
    {
        $document = $this->closureDocument();

        $this->assertStringContainsString('Milestone 3AX - Payment Receipt Frontend Validation and Closure', $document);
        $this->assertStringContainsString('Status: CLOSED BY VALIDATION', $document);
        $this->assertStringContainsString('ca69549fb68c5b0d174f19927e79d594d4a6aee4', $document);
        $this->assertStringContainsString('Frontend build passed', $document);
        $this->assertStringContainsString('7 passed, 42 assertions', $document);
        $this->assertStringContainsString('475 passed, 4627 assertions', $document);
        $this->assertStringContainsString('app/page.tsx', $document);
    }

    public function test_3ax_is_docs_tests_only_and_blocks_runtime_scope(): void
    {
        $document = $this->closureDocument();

        $this->assertStringContainsString('docs/tests-only', $document);
        $this->assertStringContainsString('backend/docs/milestone_3ax_payment_receipt_frontend_validation_and_closure.md', $document);
        $this->assertStringContainsString('backend/tests/Feature/Milestone3axPaymentReceiptFrontendValidationAndClosureTest.php', $document);

        $blockedItems = [
            'app/page.tsx',
            'backend routes',
            'backend controllers',
            'backend requests',
            'backend resources',
            'backend services',
            'backend models',
            'migrations',
            'seeders',
            'receipt generation from frontend',
            'payment history implementation',
            'reports',
            'reminders',
            'Excel import',
            'special collection allocation workflow',
            'fellowship payment allocation workflow',
            'payment cancellation',
            'payment reversal',
        ];

        foreach ($blockedItems as $blockedItem) {
            $this->assertStringContainsString($blockedItem, $document);
        }
    }

    public function test_3aw_receipt_frontend_contract_remains_present(): void
    {
        $page = $this->frontendPage();
        $component = $this->receiptListComponent($page);

        $this->assertStringContainsString('type ReceiptLookupStatus = "initializing" | "ready" | "loading" | "success" | "error";', $page);
        $this->assertStringContainsString('type ReceiptApiReceipt = {', $page);
        $this->assertStringContainsString('{screen === "receipt-list" && <ReceiptList role={role} />}', $page);

        $this->assertStringContainsString('function ReceiptList({ role }: { role: Role }) {', $component);
        $this->assertStringContainsString('const canUsePstReceiptAccess = role === "president" || role === "secretary" || role === "treasurer";', $component);
        $this->assertStringContainsString('LOCAL_PAYMENT_RECORDING_LOGINS[role as Exclude<Role, "member">]', $component);
        $this->assertStringContainsString('LOCAL_MEMBER_FINANCE_LOGIN', $component);
        $this->assertStringContainsString('This screen uses only existing receipt view and PDF download APIs. It does not generate receipts, change payments, or show payment history.', $component);
    }

    public function test_3aw_receipt_frontend_uses_existing_receipt_view_and_pdf_apis_only(): void
    {
        $component = $this->receiptListComponent($this->frontendPage());

        $this->assertStringContainsString('API_BASE_URL + "/clubs/" + receiptClubId + "/members/" + selectedReceiptMemberId + "/payments/" + receiptPaymentId.trim() + "/receipt"', $component);
        $this->assertStringContainsString('API_BASE_URL + "/clubs/" + receiptClubId + "/members/" + selectedReceiptMemberId + "/payments/" + receipt.payment_id + "/receipt/pdf"', $component);
        $this->assertStringContainsString('Accept: "application/json"', $component);
        $this->assertStringContainsString('Accept: "application/pdf"', $component);
        $this->assertStringContainsString('Authorization: "Bearer " + receiptToken', $component);
        $this->assertStringContainsString('anchor.download = receipt.receipt_number + ".pdf";', $component);

        $receiptActionsStart = strpos($component, 'async function loadIssuedReceipt()');

        $this->assertNotFalse($receiptActionsStart, 'Receipt action boundary not found.');

        $receiptActions = substr($component, $receiptActionsStart);

        $this->assertStringNotContainsString('method: "POST"', $receiptActions);
        $this->assertStringNotContainsString('/receipt", { method: "POST"', $receiptActions);
        $this->assertStringNotContainsString('Receipt generation', $receiptActions);
    }

    public function test_receipt_backend_routes_remain_existing_get_routes(): void
    {
        $receiptRoute = collect(Route::getRoutes())
            ->first(fn ($route) => $route->uri() === 'api/v1/clubs/{club}/members/{member}/payments/{payment}/receipt'
                && in_array('GET', $route->methods(), true));

        $pdfRoute = collect(Route::getRoutes())
            ->first(fn ($route) => $route->uri() === 'api/v1/clubs/{club}/members/{member}/payments/{payment}/receipt/pdf'
                && in_array('GET', $route->methods(), true));

        $this->assertNotNull($receiptRoute, 'Receipt JSON GET route not found.');
        $this->assertNotNull($pdfRoute, 'Receipt PDF GET route not found.');

        $this->assertSame(
            'App\Http\Controllers\Api\V1\Finance\ReceiptController@show',
            $receiptRoute->getActionName()
        );

        $this->assertSame(
            'App\Http\Controllers\Api\V1\Finance\PaymentReceiptPdfController@show',
            $pdfRoute->getActionName()
        );
    }

    public function test_3ax_closes_receipt_frontend_without_payment_history_or_workflow_drift(): void
    {
        $document = $this->closureDocument();

        $this->assertStringContainsString('Payment Receipt Frontend is validated and closed.', $document);
        $this->assertStringContainsString('The finance module now has frontend access for existing issued payment receipts and protected receipt PDF download', $document);
        $this->assertStringContainsString('backend source-of-truth rules remain unchanged', $document);

        $this->assertStringContainsString('payment history implementation', $document);
        $this->assertStringContainsString('reports', $document);
        $this->assertStringContainsString('reminders', $document);
        $this->assertStringContainsString('Excel import', $document);
        $this->assertStringContainsString('special collection allocation workflow', $document);
        $this->assertStringContainsString('fellowship payment allocation workflow', $document);
        $this->assertStringContainsString('payment cancellation', $document);
        $this->assertStringContainsString('payment reversal', $document);
    }

    private function closureDocument(): string
    {
        $path = base_path('docs/milestone_3ax_payment_receipt_frontend_validation_and_closure.md');

        $this->assertFileExists($path);

        return file_get_contents($path);
    }

    private function frontendPage(): string
    {
        $path = base_path('../app/page.tsx');

        $this->assertFileExists($path);

        return file_get_contents($path);
    }

    private function receiptListComponent(string $page): string
    {
        $start = strpos($page, 'function ReceiptList({ role }: { role: Role }) {');
        $end = strpos($page, 'function PaymentHistory', $start);

        $this->assertNotFalse($start, 'ReceiptList component not found.');
        $this->assertNotFalse($end, 'PaymentHistory boundary not found.');

        return substr($page, $start, $end - $start);
    }
}

