<?php

use Tests\TestCase;

class FullProjectDocumentationAndDeploymentStrategyVerificationThrough3azTest extends TestCase
{
    public function test_audit_document_exists_and_records_status_range_and_latest_milestone(): void
    {
        $document = $this->auditDocument();

        $this->assertStringContainsString('Full Project Documentation and Deployment Strategy Verification Through 3AZ', $document);
        $this->assertStringContainsString('Audit status: COMPLETE FOR LOCAL DOCUMENTATION, GIT, AND CONNECTOR-AVAILABLE REPOSITORY METADATA', $document);
        $this->assertStringContainsString('Milestone 1A - UI Prototype Review Closure', $document);
        $this->assertStringContainsString('Milestones 2A through 2E', $document);
        $this->assertStringContainsString('Milestones 3A through 3AZ', $document);
        $this->assertStringContainsString('Latest closed milestone: Milestone 3AZ - Club Finance Reports and Balance Sheet Scope Gate.', $document);
        $this->assertStringContainsString('f5059c6a8e323a5e9632754289924f57b8461f57', $document);
    }

    public function test_audit_records_git_branch_commit_and_3az_changed_files(): void
    {
        $document = $this->auditDocument();

        $this->assertStringContainsString('milestone-3az-club-finance-reports-balance-sheet-scope-gate', $document);
        $this->assertStringContainsString('Add 3AZ finance reports scope gate', $document);
        $this->assertStringContainsString('backend/docs/milestone_3az_club_finance_reports_balance_sheet_scope_gate.md', $document);
        $this->assertStringContainsString('backend/tests/Feature/Milestone3azClubFinanceReportsBalanceSheetScopeGateTest.php', $document);
        $this->assertStringContainsString('The latest closed commit matches the 3AZ scope boundary: docs/tests-only, no runtime implementation files.', $document);
    }

    public function test_audit_records_pr_limitations_without_claiming_unverified_pr_facts(): void
    {
        $document = $this->auditDocument();

        $this->assertStringContainsString('PR record findings', $document);
        $this->assertStringContainsString('Repository metadata was available through the GitHub connector', $document);
        $this->assertStringContainsString('The local environment did not have the GitHub CLI installed', $document);
        $this->assertStringContainsString('did not expose a general PR listing capability', $document);
        $this->assertStringContainsString('No pull request refs, PR descriptions, PR numbers, or saved PR records were found locally.', $document);
        $this->assertStringContainsString('PR titles could not be fully verified.', $document);
    }

    public function test_audit_records_original_plan_coverage_and_missing_items(): void
    {
        $document = $this->auditDocument();

        $coverageItems = [
            'Member master',
            'Role/permission foundation',
            'Club access model',
            'Member finance ledger',
            'Payment recording',
            'Receipts',
            'Receipt PDFs',
            'Member finance summary',
            'Club finance reports',
            'Balance sheet',
            'Reminders',
            'Documents',
            'Announcements',
            'Projects',
            'Meeting notices',
            'Board/general body minutes visibility',
            'Donor consent search',
            'Fellowship sponsorship',
            'Special collections',
            'Excel import',
            'Audit trail',
            'Deployment strategy',
            'Pilot-club rollout',
            'SaaS/global multi-club readiness',
            'Lions/Rotary/BNI/custom-club support',
        ];

        foreach ($coverageItems as $coverageItem) {
            $this->assertStringContainsString($coverageItem, $document);
        }

        $this->assertStringContainsString('missing from older roadmap, now tracked', $document);
        $this->assertStringContainsString('Standalone 2A, 2B, 2C, and 2D milestone documents are less complete', $document);
    }

    public function test_audit_records_deviations_and_preserves_closed_history(): void
    {
        $document = $this->auditDocument();

        $this->assertStringContainsString('Deviation log', $document);
        $this->assertStringContainsString('Immediate broad SaaS/global multi-club platform', $document);
        $this->assertStringContainsString('3AZ selected 3BA as report read API authorization', $document);
        $this->assertStringContainsString('Keep 3AZ unchanged', $document);
        $this->assertStringContainsString('Historical documents changed by this audit: No.', $document);
        $this->assertStringContainsString('Correction notes added to old closed milestones: No.', $document);
        $this->assertStringContainsString('The 3AZ next milestone selection was correct when recorded.', $document);
    }

    public function test_audit_records_pilot_deployment_strategy_and_blocked_paths(): void
    {
        $document = $this->auditDocument();

        $strategyItems = [
            'Use one shared source codebase.',
            'Do not fork the codebase per club.',
            'deploy a separate app instance per club',
            'Use a separate database per club instance.',
            'Use separate environment/config per club instance.',
            'Use separate storage folder or bucket per club instance.',
            'Support club-specific branding/configuration.',
            'Lions Club Beenachi',
            'Rotary Central Bathery',
            'Separate codebase per club.',
            'Hardcoded client-specific forks.',
            'Frontend-only finance/report calculations.',
            'Global shared multi-tenant SaaS before pilot validation.',
            'Report/balance sheet implementation before authorization.',
        ];

        foreach ($strategyItems as $strategyItem) {
            $this->assertStringContainsString($strategyItem, $document);
        }
    }

    public function test_audit_records_saas_readiness_gaps(): void
    {
        $document = $this->auditDocument();

        $gaps = [
            'Tenant hierarchy.',
            'Organization type.',
            'Club/chapter structure.',
            'District/region support.',
            'Configurable role labels.',
            'Configurable permissions.',
            'Financial year setting per club.',
            'Timezone/locale support.',
            'Multi-currency readiness.',
            'Subscription/billing model.',
            'Super-admin/support console.',
            'Onboarding checklist.',
            'Deployment checklist.',
            'Data isolation policy.',
            'Backup/restore policy.',
            'Data export policy.',
            'Privacy/data-retention policy.',
            'Audit log review.',
            'Migration path from isolated instances to shared SaaS if later required.',
        ];

        foreach ($gaps as $gap) {
            $this->assertStringContainsString($gap, $document);
        }
    }

    public function test_audit_corrects_forward_sequence_without_rewriting_3az(): void
    {
        $document = $this->auditDocument();
        $threeAzDocument = file_get_contents(base_path('docs/milestone_3az_club_finance_reports_balance_sheet_scope_gate.md'));

        $this->assertStringContainsString('Milestone 3BA - Club Finance Reports and Balance Sheet Read API Authorization', $threeAzDocument);
        $this->assertStringContainsString('3AZ correctly selected "Milestone 3BA - Club Finance Reports and Balance Sheet Read API Authorization" at the time of 3AZ closure.', $document);
        $this->assertStringContainsString('3BA - Deployment and SaaS Strategy Readiness Review - docs/tests-only.', $document);
        $this->assertStringContainsString('3BB - Club Finance Reports and Balance Sheet Read API Authorization - docs/tests-only.', $document);
        $this->assertStringContainsString('3BC - Club Finance Reports and Balance Sheet Read API Narrow Implementation.', $document);
        $this->assertStringContainsString('3BD - Read API Validation and Closure.', $document);
        $this->assertStringContainsString('Later milestone - Reports Frontend Scope Gate.', $document);
        $this->assertStringContainsString('Recommended next milestone:', $document);
        $this->assertStringContainsString('Milestone 3BA - Deployment and SaaS Strategy Readiness Review.', $document);
        $this->assertStringContainsString('Milestone 3BB - Club Finance Reports and Balance Sheet Read API Authorization.', $document);
    }

    private function auditDocument(): string
    {
        $path = base_path('docs/full_project_documentation_and_deployment_strategy_verification_through_3az.md');

        $this->assertFileExists($path);

        return file_get_contents($path);
    }
}
