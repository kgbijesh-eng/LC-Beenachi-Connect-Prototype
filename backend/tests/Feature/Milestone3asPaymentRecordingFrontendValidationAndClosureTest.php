<?php

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3asPaymentRecordingFrontendValidationAndClosureTest extends TestCase
{
    public function test_3as_closure_document_exists_and_records_validation_results(): void
    {
        $document = $this->closureDocument();

        $this->assertStringContainsString('Milestone 3AS — Payment Recording Frontend Validation and Closure', $document);
        $this->assertStringContainsString('Closed by validation', $document);
        $this->assertStringContainsString('ad883864a551c483e89cb0f0a5f835c92a5d33bb', $document);
        $this->assertStringContainsString('Frontend build passed', $document);
        $this->assertStringContainsString('5 passed, 49 assertions', $document);
        $this->assertStringContainsString('449 passed, 4410 assertions', $document);
    }

    public function test_3as_is_docs_tests_only_and_blocks_runtime_scope(): void
    {
        $document = $this->closureDocument();

        $this->assertStringContainsString('docs/tests-only', $document);
        $this->assertStringContainsString('backend/docs/milestone_3as_payment_recording_frontend_validation_and_closure.md', $document);
        $this->assertStringContainsString('backend/tests/Feature/Milestone3asPaymentRecordingFrontendValidationAndClosureTest.php', $document);

        $this->assertStringContainsString('app/page.tsx', $document);
        $this->assertStringContainsString('backend routes', $document);
        $this->assertStringContainsString('backend controllers', $document);
        $this->assertStringContainsString('backend requests', $document);
        $this->assertStringContainsString('backend resources', $document);
        $this->assertStringContainsString('backend services', $document);
        $this->assertStringContainsString('backend models', $document);
        $this->assertStringContainsString('migrations', $document);
        $this->assertStringContainsString('seeders', $document);
    }

    public function test_3ar_frontend_payment_recording_contract_remains_present(): void
    {
        $page = $this->frontendPage();
        $component = $this->recordMemberPaymentComponent($page);

        $this->assertStringContainsString('const LOCAL_PAYMENT_RECORDING_LOGINS: Record<Exclude<Role, "member">, PaymentRecordingLogin>', $page);
        $this->assertStringContainsString('email: "president@lcbeenachi.test"', $page);
        $this->assertStringContainsString('email: "secretary@lcbeenachi.test"', $page);
        $this->assertStringContainsString('email: "treasurer@lcbeenachi.test"', $page);

        $this->assertStringContainsString('const canRecordPayment = role === "president" || role === "secretary" || role === "treasurer";', $component);
        $this->assertStringContainsString('Payment recording is available only to President, Secretary, and Treasurer roles with backend payment permission.', $component);
        $this->assertStringContainsString('Members can view their own dues, but cannot see or use payment recording controls.', $component);
        $this->assertStringContainsString('API_BASE_URL + "/clubs/" + loadedClubId + "/members"', $component);
        $this->assertStringContainsString('API_BASE_URL + "/clubs/" + paymentClubId + "/members/" + selectedMemberId + "/payments"', $component);
        $this->assertStringContainsString('Payment recorded. Member finance summary refreshed from backend.', $component);
    }

    public function test_3ar_frontend_submit_payload_still_excludes_backend_controlled_fields(): void
    {
        $component = $this->recordMemberPaymentComponent($this->frontendPage());

        preg_match(
            '/body:\s*JSON\.stringify\(\{\s*amount:\s*paymentForm\.amount,\s*payment_date:\s*paymentForm\.payment_date,\s*payment_mode:\s*paymentForm\.payment_mode,\s*reference_number:\s*paymentForm\.reference_number,\s*notes:\s*paymentForm\.notes\s*\}\)/s',
            $component,
            $matches
        );

        $payload = $matches[0] ?? '';

        $this->assertNotSame('', $payload, 'Payment POST payload not found.');

        $this->assertStringContainsString('amount: paymentForm.amount', $payload);
        $this->assertStringContainsString('payment_date: paymentForm.payment_date', $payload);
        $this->assertStringContainsString('payment_mode: paymentForm.payment_mode', $payload);
        $this->assertStringContainsString('reference_number: paymentForm.reference_number', $payload);
        $this->assertStringContainsString('notes: paymentForm.notes', $payload);

        $this->assertStringNotContainsString('club_id', $payload);
        $this->assertStringNotContainsString('member_id', $payload);
        $this->assertStringNotContainsString('received_by_user_id', $payload);
        $this->assertStringNotContainsString('ledger_entry_id', $payload);
        $this->assertStringNotContainsString('status', $payload);
        $this->assertStringNotContainsString('receipt_number', $payload);
        $this->assertStringNotContainsString('receipt_id', $payload);
    }

    public function test_3ar_frontend_keeps_receipt_frontend_out_of_record_payment_screen(): void
    {
        $component = $this->recordMemberPaymentComponent($this->frontendPage());

        $this->assertStringNotContainsString('ReceiptPreview', $component);
        $this->assertStringNotContainsString('Dummy Receipt Preview', $component);
        $this->assertStringNotContainsString('Create Payment + Credit Ledger Entry', $component);
        $this->assertStringContainsString('Receipt generation is not part of Milestone 3AR.', $component);
    }

    public function test_payment_recording_backend_route_boundary_remains_single_post_route(): void
    {
        $routes = collect(Route::getRoutes())
            ->filter(fn ($route) => $route->uri() === 'api/v1/clubs/{club}/members/{member}/payments')
            ->values();

        $this->assertCount(1, $routes);

        $route = $routes->first();

        $this->assertSame(['POST'], array_values(array_diff($route->methods(), ['HEAD'])));
        $this->assertSame(
            'App\Http\Controllers\Api\V1\Finance\PaymentController@store',
            $route->getActionName()
        );
    }

    public function test_3as_closes_payment_recording_frontend_without_new_receipt_report_or_excel_scope(): void
    {
        $document = $this->closureDocument();

        $this->assertStringContainsString('receipt frontend', $document);
        $this->assertStringContainsString('receipt list implementation', $document);
        $this->assertStringContainsString('receipt PDF frontend', $document);
        $this->assertStringContainsString('reports', $document);
        $this->assertStringContainsString('reminders', $document);
        $this->assertStringContainsString('Excel import', $document);
        $this->assertStringContainsString('special collection allocation workflow', $document);
        $this->assertStringContainsString('fellowship payment allocation workflow', $document);
        $this->assertStringContainsString('validated and closed', $document);
    }

    private function closureDocument(): string
    {
        $path = base_path('docs/milestone_3as_payment_recording_frontend_validation_and_closure.md');

        $this->assertFileExists($path);

        return file_get_contents($path);
    }

    private function frontendPage(): string
    {
        $path = base_path('../app/page.tsx');

        $this->assertFileExists($path);

        return file_get_contents($path);
    }

    private function recordMemberPaymentComponent(string $page): string
    {
        $start = strpos($page, 'function RecordMemberPayment({ role }: { role: Role }) {');
        $end = strpos($page, 'function CollectionBoxReceipt', $start);

        $this->assertNotFalse($start, 'RecordMemberPayment component not found.');
        $this->assertNotFalse($end, 'CollectionBoxReceipt boundary not found.');

        return substr($page, $start, $end - $start);
    }
}
