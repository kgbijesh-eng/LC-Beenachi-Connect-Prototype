<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3bjFinanceModulePostReportsFrontendClosureReviewNextScopeDecisionTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3bj_finance_module_post_reports_frontend_closure_review_next_scope_decision.md';

    public function test_3bj_document_exists_and_is_docs_tests_only_decision_gate(): void
    {
        $document = $this->decisionDocument();

        $this->assertStringContainsString('Milestone 3BJ - Finance Module Post Reports Frontend Closure Review and Next Scope Decision', $document);
        $this->assertStringContainsString('docs/tests-only planning and decision gate', $document);
        $this->assertStringContainsString('It does not implement a new finance feature.', $document);
        $this->assertStringContainsString('It does not change frontend runtime behavior.', $document);
        $this->assertStringContainsString('It does not change backend runtime behavior.', $document);
    }

    public function test_3bj_records_base_branch_and_allowed_files_only(): void
    {
        $document = $this->decisionDocument();

        $this->assertStringContainsString('milestone-3bi-club-finance-reports-balance-sheet-frontend-display-validation-and-closure', $document);
        $this->assertStringContainsString('milestone-3bj-finance-module-post-reports-frontend-closure-review-next-scope-decision', $document);
        $this->assertStringContainsString('backend/docs/milestone_3bj_finance_module_post_reports_frontend_closure_review_next_scope_decision.md', $document);
        $this->assertStringContainsString('backend/tests/Feature/Milestone3bjFinanceModulePostReportsFrontendClosureReviewNextScopeDecisionTest.php', $document);
    }

    public function test_3bj_blocks_frontend_backend_runtime_api_and_workflow_scope(): void
    {
        $document = $this->decisionDocument();

        foreach ([
            'app/page.tsx',
            'backend routes',
            'backend controllers',
            'backend services',
            'backend resources',
            'backend requests',
            'backend models',
            'backend middleware',
            'backend migrations',
            'backend seeders',
            'backend config',
            'API behavior',
            'export files',
            'PDF files',
            'Excel files',
            'reminder files',
            'payment mutation files',
            'receipt mutation files',
            'cancellation workflow files',
            'reversal workflow files',
            'allocation workflow files',
            'deployment files',
            'SaaS files',
            'client-specific hardcoding',
            '3BJ must not choose implementation silently.',
            '3BJ must not add runtime behavior.',
        ] as $blockedScope) {
            $this->assertStringContainsString($blockedScope, $document);
        }
    }

    public function test_3bj_reviews_completed_finance_module_lanes_through_3bi(): void
    {
        $document = $this->decisionDocument();

        foreach ([
            'Member account ledger and dues foundation',
            'Member ledger balance calculation foundation',
            'Member ledger balance read API',
            'Outstanding dues and member finance summary read API',
            'Member finance summary frontend',
            'Payment recording database foundation',
            'Payment recording service',
            'Payment recording API',
            'Payment recording frontend',
            'Receipt generation service',
            'Receipt JSON API',
            'Receipt PDF generation and download API',
            'Receipt frontend access for already issued receipts',
            'Club finance reports and balance sheet scope gate',
            'Club finance reports and balance sheet read API',
            'Club finance reports and balance sheet frontend display',
            'Club finance reports and balance sheet frontend display validation and closure',
        ] as $closedLane) {
            $this->assertStringContainsString($closedLane, $document);
        }
    }

    public function test_3bj_confirms_reports_frontend_lane_is_closed_and_not_reopened(): void
    {
        $document = $this->decisionDocument();

        $this->assertStringContainsString('The latest closed lane is:', $document);
        $this->assertStringContainsString('Club Finance Reports and Balance Sheet frontend display.', $document);
        $this->assertStringContainsString('Milestone 3BH - Club Finance Reports and Balance Sheet Frontend Display Narrow Implementation.', $document);
        $this->assertStringContainsString('Milestone 3BI - Club Finance Reports and Balance Sheet Frontend Display Validation and Closure.', $document);
        $this->assertStringContainsString('3BH and 3BI should not be reopened without a new explicitly approved scope gate.', $document);
    }

    public function test_3bj_records_current_role_based_finance_capabilities(): void
    {
        $document = $this->decisionDocument();

        foreach ([
            'Normal Member currently has member-safe finance visibility',
            'Own dues',
            'Own member finance summary',
            'Own issued receipts',
            'Own receipt PDF download',
            'President/Admin, Secretary, and Treasurer',
            'Selected member finance summary',
            'Payment recording through existing backend payment API',
            'Receipt generation through existing backend receipt API',
            'Issued receipt JSON view',
            'Issued receipt PDF download',
            'Club Finance Reports display',
            'Balance Sheet Summary display',
            'Backend authorization remains the source of truth.',
            'Cross-club access must remain blocked by backend authorization.',
        ] as $capability) {
            $this->assertStringContainsString($capability, $document);
        }
    }

    public function test_3bj_records_intentionally_out_of_scope_items(): void
    {
        $document = $this->decisionDocument();

        foreach ([
            'Payment history read-only API and frontend',
            'Reminder workflow',
            'Payment cancellation',
            'Payment reversal',
            'Finance export',
            'Report export',
            'Excel import',
            'Excel export',
            'Special collection allocation workflow',
            'Fellowship payment allocation workflow',
            'Deployment implementation',
            'SaaS implementation',
            'Client-specific hardcoding',
        ] as $outOfScope) {
            $this->assertStringContainsString($outOfScope, $document);
        }
    }

    public function test_3bj_evaluates_all_requested_candidate_areas(): void
    {
        $document = $this->decisionDocument();

        foreach ([
            'Payment History read-only scope gate',
            'Reminder workflow scope gate',
            'Payment cancellation/reversal scope gate',
            'Finance export/report export scope gate',
            'Allocation workflow scope gate',
            'Deployment/SaaS readiness follow-up gate',
            'Non-finance module continuation',
        ] as $candidate) {
            $this->assertStringContainsString($candidate, $document);
        }
    }

    public function test_3bj_uses_lowest_risk_read_only_mvp_selection_logic(): void
    {
        $document = $this->decisionDocument();

        foreach ([
            'Prefer lowest-risk next milestone.',
            'Prefer read-only visibility before mutation-heavy workflows.',
            'Prefer MVP-useful officer/member transparency.',
            'Avoid cancellation, reversal, allocation, reminder delivery, export, deployment, and SaaS implementation until separately scoped.',
        ] as $selectionRule) {
            $this->assertStringContainsString($selectionRule, $document);
        }
    }

    public function test_3bj_selects_exact_next_milestone_title_not_generic_scope_gate(): void
    {
        $document = $this->decisionDocument();

        $this->assertStringContainsString('Selected next milestone:', $document);
        $this->assertStringContainsString('Milestone 3BK - Payment History Read-Only Scope Gate', $document);
        $this->assertStringContainsString('lowest-risk, read-only, MVP-useful next step', $document);
        $this->assertStringContainsString('It naturally follows payment recording, receipt access, and club finance reporting.', $document);
        $this->assertStringNotContainsString('Milestone 3BK - Finance Module Next Feature Scope Gate', $document);
    }

    public function test_3bj_defers_mutation_heavy_and_expansive_candidates(): void
    {
        $document = $this->decisionDocument();

        foreach ([
            'Reminder workflow scope gate',
            'Defer until read-only visibility is more complete.',
            'Payment cancellation/reversal scope gate',
            'Mutation-heavy and accounting-sensitive.',
            'Allocation workflow scope gate',
            'Requires allocation rules, partial payments, ledger implications, reversals, and audit history.',
            'Finance export/report export scope gate',
            'Defer until payment history visibility is scoped.',
        ] as $deferredDecision) {
            $this->assertStringContainsString($deferredDecision, $document);
        }
    }

    public function test_3bk_boundary_recommendation_is_scope_gate_only(): void
    {
        $document = $this->decisionDocument();

        foreach ([
            'Milestone 3BK should be docs/tests-only.',
            'Payment history purpose',
            'Role visibility',
            'Whether member users can see own payment history',
            'Whether President/Admin, Secretary, and Treasurer can see member or club payment history',
            'Existing data sources to use',
            'Empty/loading/error state expectations',
            'Backend source-of-truth rule',
            'Explicitly blocked mutation workflows',
            '3BK must not implement:',
            'Backend routes.',
            'Frontend changes.',
            'Payment cancellation.',
            'Payment reversal.',
            'Allocation workflow.',
            'Export.',
            'PDF.',
            'Excel.',
            'Reminders.',
            'Deployment.',
            'SaaS implementation.',
            'Client-specific hardcoding.',
        ] as $boundaryText) {
            $this->assertStringContainsString($boundaryText, $document);
        }
    }

    public function test_3bj_closure_decision_names_exact_3bk_scope_gate(): void
    {
        $document = $this->decisionDocument();

        $this->assertStringContainsString('Milestone 3BJ closes the post-reports frontend review.', $document);
        $this->assertStringContainsString('The next milestone is selected exactly as:', $document);
        $this->assertStringContainsString('Milestone 3BK - Payment History Read-Only Scope Gate', $document);
    }

    private function decisionDocument(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }
}
