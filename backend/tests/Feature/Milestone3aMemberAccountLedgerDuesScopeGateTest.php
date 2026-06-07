<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3aMemberAccountLedgerDuesScopeGateTest extends TestCase
{
    private string $scopeDocumentPath = 'docs/milestone_3a_member_account_ledger_dues_scope_gate.md';

    public function test_scope_document_exists(): void
    {
        $this->assertFileExists(base_path($this->scopeDocumentPath));
    }

    public function test_scope_gate_defines_due_creation_payment_recording_and_balance_rules(): void
    {
        $content = file_get_contents(base_path($this->scopeDocumentPath));

        $this->assertStringContainsString('## What Creates Dues', $content);
        $this->assertStringContainsString('Monthly dues', $content);
        $this->assertStringContainsString('Membership fee', $content);
        $this->assertStringContainsString('Fellowship contribution', $content);
        $this->assertStringContainsString('Project share', $content);
        $this->assertStringContainsString('Fines', $content);
        $this->assertStringContainsString('Special collections', $content);

        $this->assertStringContainsString('## What Records Payments', $content);
        $this->assertStringContainsString('Payments must create credit entries against a member account.', $content);

        $this->assertStringContainsString('## Balance Calculation Rule', $content);
        $this->assertStringContainsString('Total Debits - Total Credits = Current Balance', $content);
        $this->assertStringContainsString('The ledger entries must be the source of truth.', $content);
    }

    public function test_scope_gate_defines_role_based_financial_visibility(): void
    {
        $content = file_get_contents(base_path($this->scopeDocumentPath));

        $this->assertStringContainsString('## Normal Member Visibility', $content);
        $this->assertStringContainsString('Normal members can see only their own financial information.', $content);
        $this->assertStringContainsString('Other members\' balances', $content);
        $this->assertStringContainsString('Other members\' payment history', $content);

        $this->assertStringContainsString('## Treasurer Visibility', $content);
        $this->assertStringContainsString('The Treasurer can view and manage club financial records related to dues and payments.', $content);

        $this->assertStringContainsString('## Secretary Visibility', $content);
        $this->assertStringContainsString('The Secretary may need limited financial visibility for coordination.', $content);

        $this->assertStringContainsString('## President/Admin Visibility', $content);
        $this->assertStringContainsString('President/Admin can view complete member account and dues information for supervision.', $content);
    }

    public function test_scope_gate_defines_special_collections_as_separate_identifiable_ledger_items(): void
    {
        $content = file_get_contents(base_path($this->scopeDocumentPath));

        $this->assertStringContainsString('## Special Collections', $content);
        $this->assertStringContainsString('Special collections must be supported as club-approved payable items.', $content);
        $this->assertStringContainsString('Special collections must not be mixed silently with monthly dues.', $content);
        $this->assertStringContainsString('They must remain identifiable in the ledger.', $content);
    }

    public function test_scope_gate_preserves_privacy_and_audit_requirements(): void
    {
        $content = file_get_contents(base_path($this->scopeDocumentPath));

        $this->assertStringContainsString('## Privacy Rules', $content);
        $this->assertStringContainsString('Financial privacy is mandatory.', $content);
        $this->assertStringContainsString('Normal members must never see other members\' financial details.', $content);

        $this->assertStringContainsString('## Audit Requirements', $content);
        $this->assertStringContainsString('Creating dues', $content);
        $this->assertStringContainsString('Recording payments', $content);
        $this->assertStringContainsString('Generating receipts', $content);
    }

    public function test_scope_gate_blocks_implementation_until_3b_or_later(): void
    {
        $content = file_get_contents(base_path($this->scopeDocumentPath));

        $this->assertStringContainsString('Milestone 3A is a docs/tests-only scope gate.', $content);
        $this->assertStringContainsString('Milestone 3B - Member Account Ledger and Dues Database Foundation', $content);
        $this->assertStringContainsString('## What Must Wait For 3B Or Later', $content);

        $blockedItems = [
            'No migration',
            'No ledger table',
            'No dues table',
            'No payment table',
            'No receipt table',
            'No model',
            'No controller',
            'No API route',
            'No balance calculation code',
            'No payment recording code',
            'No frontend changes',
            'No Excel import',
            'No report generation',
            'No reminder automation',
            'No business module implementation',
        ];

        foreach ($blockedItems as $blockedItem) {
            $this->assertStringContainsString($blockedItem, $content);
        }
    }
}