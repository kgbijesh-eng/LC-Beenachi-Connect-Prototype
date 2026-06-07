<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone2eMemberModuleClosureReviewTest extends TestCase
{
    public function test_milestone_2e_closure_review_document_exists(): void
    {
        $path = base_path('docs/milestone_2e_member_module_closure_review.md');

        $this->assertFileExists($path);
    }

    public function test_milestone_2e_closes_member_module_foundation(): void
    {
        $content = file_get_contents(base_path('docs/milestone_2e_member_module_closure_review.md'));

        $this->assertStringContainsString('MEMBER_MODULE_FOUNDATION_CLOSED', $content);
        $this->assertStringContainsString('Milestone 2E is a closure and decision checkpoint only', $content);
        $this->assertStringContainsString('does not add new backend routes, frontend screens, database tables, business modules, or permission behavior', $content);
    }

    public function test_milestone_2e_records_completed_member_capabilities(): void
    {
        $content = file_get_contents(base_path('docs/milestone_2e_member_module_closure_review.md'));

        $this->assertStringContainsString('Protected member list route', $content);
        $this->assertStringContainsString('Protected member detail route', $content);
        $this->assertStringContainsString('Protected member create route', $content);
        $this->assertStringContainsString('Protected member update route', $content);
        $this->assertStringContainsString('API smoke tests using real seeded local role users', $content);
    }

    public function test_milestone_2e_preserves_role_permission_summary(): void
    {
        $content = file_get_contents(base_path('docs/milestone_2e_member_module_closure_review.md'));

        $this->assertStringContainsString('President/Admin:', $content);
        $this->assertStringContainsString('Secretary:', $content);
        $this->assertStringContainsString('Treasurer:', $content);
        $this->assertStringContainsString('Normal Member:', $content);
        $this->assertStringContainsString('Cannot create members', $content);
        $this->assertStringContainsString('Cannot update members', $content);
    }

    public function test_milestone_2e_blocks_scope_drift(): void
    {
        $content = file_get_contents(base_path('docs/milestone_2e_member_module_closure_review.md'));

        $this->assertStringContainsString('No member delete route', $content);
        $this->assertStringContainsString('No Excel import', $content);
        $this->assertStringContainsString('No member account ledger', $content);
        $this->assertStringContainsString('No dues tracking', $content);
        $this->assertStringContainsString('No payments or receipts', $content);
        $this->assertStringContainsString('No real frontend auth redesign', $content);
    }

    public function test_milestone_2e_selects_next_module_without_implementing_it(): void
    {
        $content = file_get_contents(base_path('docs/milestone_2e_member_module_closure_review.md'));

        $this->assertStringContainsString('Milestone 3A — Member Account Ledger and Dues Foundation Scope Gate', $content);
        $this->assertStringContainsString('docs/tests-only first', $content);
        $this->assertStringContainsString('No ledger table migration yet', $content);
        $this->assertStringContainsString('No payment routes yet', $content);
        $this->assertStringContainsString('No frontend changes', $content);
        $this->assertStringContainsString('No reminder sending', $content);
    }
}
