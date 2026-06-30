<?php

use Tests\TestCase;

class Milestone3arPaymentRecordingFrontendNarrowImplementationTest extends TestCase
{
    public function test_payment_recording_frontend_uses_existing_backend_api_and_authorized_roles(): void
    {
        $page = $this->frontendPage();
        $financeBackend = $this->financeBackend();

        $this->assertStringContainsString('import FinanceBackend from "./FinanceBackend";', $page);
        $this->assertStringContainsString('import { DevAuthProvider, type DevIdentity, type DevRole, useDevAuth } from "./DevAuthSession";', $page);
        $this->assertStringContainsString('{screen === "record-member-payment" && <FinanceBackend role={role} memberId={activeMember!.backendId} isPersonal={isPersonalFinanceScreen} />}', $page);
        $this->assertStringContainsString('const { session } = useDevAuth();', $financeBackend);
        $this->assertStringContainsString('const management=role!=="member";', $financeBackend);
        $this->assertStringContainsString('if(!session)throw new Error("No verified development session is available.");', $financeBackend);
        $this->assertStringContainsString('const access=session.token;const club=session.membership.club.id;', $financeBackend);
        $this->assertStringContainsString('api(`/clubs/${clubId}/finance/members/${selected}/payments`', $financeBackend);
        $this->assertStringContainsString('(role==="president"||role==="treasurer")', $financeBackend);
        $this->assertStringNotContainsString('LOCAL_PAYMENT_RECORDING_LOGINS', $financeBackend);
    }

    public function test_payment_submit_payload_contains_only_frontend_allowed_payment_fields(): void
    {
        $component = $this->recordMemberPaymentComponent($this->frontendPage());

        $this->assertMatchesRegularExpression(
            '/body:\s*JSON\.stringify\(\{\s*amount:\s*paymentForm\.amount,\s*payment_date:\s*paymentForm\.payment_date,\s*payment_mode:\s*paymentForm\.payment_mode,\s*reference_number:\s*paymentForm\.reference_number,\s*notes:\s*paymentForm\.notes\s*\}\)/s',
            $component
        );

        preg_match(
            '/body:\s*JSON\.stringify\(\{\s*amount:\s*paymentForm\.amount,\s*payment_date:\s*paymentForm\.payment_date,\s*payment_mode:\s*paymentForm\.payment_mode,\s*reference_number:\s*paymentForm\.reference_number,\s*notes:\s*paymentForm\.notes\s*\}\)/s',
            $component,
            $matches
        );

        $payload = $matches[0] ?? '';

        $this->assertStringNotContainsString('club_id', $payload);
        $this->assertStringNotContainsString('member_id', $payload);
        $this->assertStringNotContainsString('received_by_user_id', $payload);
        $this->assertStringNotContainsString('ledger_entry_id', $payload);
        $this->assertStringNotContainsString('status', $payload);
        $this->assertStringNotContainsString('cancelled_at', $payload);
        $this->assertStringNotContainsString('cancelled_by_user_id', $payload);
        $this->assertStringNotContainsString('cancellation_reason', $payload);
        $this->assertStringNotContainsString('receipt_number', $payload);
        $this->assertStringNotContainsString('receipt_id', $payload);
    }

    public function test_record_member_payment_screen_removed_dummy_receipt_preview_and_ledger_button(): void
    {
        $component = $this->recordMemberPaymentComponent($this->frontendPage());

        $this->assertStringNotContainsString('ReceiptPreview', $component);
        $this->assertStringNotContainsString('Dummy Receipt Preview', $component);
        $this->assertStringNotContainsString('Create Payment + Credit Ledger Entry', $component);
        $this->assertStringNotContainsString('selectedDues', $component);
        $this->assertStringNotContainsString('outstandingDues.filter', $component);
    }

    public function test_secretary_can_reach_finance_navigation_for_authorized_payment_screen(): void
    {
        $page = $this->frontendPage();

        $this->assertMatchesRegularExpression(
            '/secretary:\s*\[\s*\{ title: "Direct".*?\{ title: "Finance", items: financeMenuItems \}.*?\{ title: "Reports & Tools"/s',
            $page
        );

        $this->assertStringContainsString(
            'Record a member payment through the existing backend API without generating a receipt.',
            $page
        );
    }

    public function test_milestone_document_records_scope_and_exclusions(): void
    {
        $documentPath = base_path('docs/milestone_3ar_payment_recording_frontend_narrow_implementation.md');

        $this->assertFileExists($documentPath);

        $document = file_get_contents($documentPath);

        $this->assertStringContainsString('POST /api/v1/clubs/{club}/members/{member}/payments', $document);
        $this->assertStringContainsString('amount', $document);
        $this->assertStringContainsString('payment_date', $document);
        $this->assertStringContainsString('payment_mode', $document);
        $this->assertStringContainsString('reference_number', $document);
        $this->assertStringContainsString('notes', $document);
        $this->assertStringContainsString('receipt generation frontend', $document);
        $this->assertStringContainsString('backend route/controller/service/request/resource/model changes', $document);
        $this->assertStringContainsString('The backend remains the source of truth', $document);
    }

    private function frontendPage(): string
    {
        $path = base_path('../app/page.tsx');

        $this->assertFileExists($path);

        return file_get_contents($path);
    }

    private function financeBackend(): string
    {
        $path = base_path('../app/FinanceBackend.tsx');

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
