<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3dMemberLedgerBalanceReadApiScopeGateTest extends TestCase
{
    private string $scopeDocumentPath = 'docs/milestone_3d_member_ledger_balance_read_api_scope_gate.md';

    public function test_scope_document_exists(): void
    {
        $this->assertFileExists(base_path($this->scopeDocumentPath));
    }

    public function test_scope_gate_defines_privacy_first_balance_read_principle(): void
    {
        $content = file_get_contents(base_path($this->scopeDocumentPath));

        $this->assertStringContainsString('Milestone 3D is a docs/tests-only scope gate.', $content);
        $this->assertStringContainsString('Balance visibility must follow privacy-first role-based access.', $content);
        $this->assertStringContainsString('A member\'s financial balance is private data.', $content);
        $this->assertStringContainsString('Normal members must not see other members\' balances', $content);
    }

    public function test_scope_gate_defines_normal_member_visibility(): void
    {
        $content = file_get_contents(base_path($this->scopeDocumentPath));

        $this->assertStringContainsString('## Normal Member Visibility', $content);
        $this->assertStringContainsString('A normal member may see only their own balance summary.', $content);
        $this->assertStringContainsString('Own current balance', $content);
        $this->assertStringContainsString('Own balance type', $content);
        $this->assertStringContainsString('Other members\' balances', $content);
        $this->assertStringContainsString('Club-wide outstanding list', $content);
        $this->assertStringContainsString('Member-wise collection summary', $content);
    }

    public function test_scope_gate_defines_treasurer_secretary_and_president_admin_visibility(): void
    {
        $content = file_get_contents(base_path($this->scopeDocumentPath));

        $this->assertStringContainsString('## Treasurer Visibility', $content);
        $this->assertStringContainsString('The Treasurer may read member-wise balance summaries for finance management.', $content);
        $this->assertStringContainsString('The Treasurer read API must remain read-only.', $content);

        $this->assertStringContainsString('## Secretary Visibility', $content);
        $this->assertStringContainsString('The Secretary may need limited balance visibility for coordination.', $content);
        $this->assertStringContainsString('The Secretary should not automatically get full finance control.', $content);

        $this->assertStringContainsString('## President/Admin Visibility', $content);
        $this->assertStringContainsString('President/Admin may read member-wise balance summaries for supervision and governance.', $content);
        $this->assertStringContainsString('President/Admin access in this scope gate remains read-only.', $content);
    }

    public function test_scope_gate_defines_future_read_only_api_boundaries(): void
    {
        $content = file_get_contents(base_path($this->scopeDocumentPath));

        $this->assertStringContainsString('## Future Read-Only API Direction', $content);
        $this->assertStringContainsString('The future API must be read-only.', $content);
        $this->assertStringContainsString('The future API must not record payments, generate receipts, create dues, create special collections, or mutate ledger entries.', $content);

        $this->assertStringContainsString('## Future Route Boundary', $content);
        $this->assertStringContainsString('Own member balance summary', $content);
        $this->assertStringContainsString('Selected member balance summary for authorized roles', $content);
        $this->assertStringContainsString('Club member balance summary list for authorized finance/leadership roles', $content);
        $this->assertStringContainsString('Future routes must prevent normal members from reading other members\' balances.', $content);
    }

    public function test_scope_gate_defines_data_boundary_for_future_summary_response(): void
    {
        $content = file_get_contents(base_path($this->scopeDocumentPath));

        $this->assertStringContainsString('## Data Boundary', $content);
        $this->assertStringContainsString('Future read APIs should return only balance summary data unless separately authorized.', $content);

        foreach ([
            'club_id',
            'member_id',
            'debit_total',
            'credit_total',
            'balance',
            'balance_type',
        ] as $allowedField) {
            $this->assertStringContainsString($allowedField, $content);
        }

        $this->assertStringContainsString('Ledger entry lists, audit logs, receipts, and detailed payment history are outside this scope gate.', $content);
    }

    public function test_scope_gate_blocks_implementation_until_later_milestone(): void
    {
        $content = file_get_contents(base_path($this->scopeDocumentPath));

        $this->assertStringContainsString('## Not Implemented In 3D', $content);

        $blockedItems = [
            'No API route',
            'No controller',
            'No Laravel policy',
            'No request class',
            'No resource class',
            'No frontend changes',
            'No payment recording',
            'No receipt generation',
            'No ledger mutation',
            'No dues creation workflow',
            'No special collection workflow',
            'No fellowship payment workflow',
            'No reminder automation',
            'No reports',
            'No Excel import',
            'No dashboard',
            'No member-facing financial screen',
            'No Treasurer dashboard',
        ];

        foreach ($blockedItems as $blockedItem) {
            $this->assertStringContainsString($blockedItem, $content);
        }
    }

    public function test_3d_does_not_register_balance_api_or_finance_workflow_routes(): void
    {
        $blockedRouteFragments = [
            'payment',
            'payments',
            'receipt',
            'receipts',
            'reminder',
            'reminders',
            'report',
            'reports',
            'excel',
            'import',
        ];

        $registeredUris = collect(Route::getRoutes())
            ->map(fn ($route) => $route->uri())
            ->values();

        foreach ($blockedRouteFragments as $fragment) {
            $this->assertFalse(
                $registeredUris->contains(fn (string $uri) => str_contains($uri, $fragment)),
                "Milestone 3D must not register route containing [{$fragment}]."
            );
        }
    }
}