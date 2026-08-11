<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3bfRoadmapCodexDevelopmentGovernanceAuditTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3bf_roadmap_codex_development_governance_audit.md';

    public function test_3bf_document_exists(): void
    {
        $this->assertFileExists($this->docPath);
    }

    public function test_3bf_is_docs_tests_only(): void
    {
        $document = file_get_contents($this->docPath);

        $this->assertStringContainsString('Milestone 3BF - Roadmap and Codex Development Governance Audit', $document);
        $this->assertStringContainsString('docs/tests-only governance audit milestone', $document);
        $this->assertStringContainsString('It does not authorize runtime implementation.', $document);
    }

    public function test_3bf_records_reason_for_inserting_audit_before_frontend_authorization(): void
    {
        $document = file_get_contents($this->docPath);

        $this->assertStringContainsString('Reason for inserting 3BF', $document);
        $this->assertStringContainsString('Before opening frontend authorization', $document);
        $this->assertStringContainsString('frontend authorization should wait until roadmap and Codex operating rules are explicitly documented', $document);
    }

    public function test_3bf_records_latest_closed_milestone_as_3be(): void
    {
        $document = file_get_contents($this->docPath);

        $this->assertStringContainsString('Latest closed milestone before this audit', $document);
        $this->assertStringContainsString('Milestone 3BE - Club Finance Reports and Balance Sheet Frontend Display Scope Gate', $document);
        $this->assertStringContainsString('Did not implement frontend display', $document);
    }

    public function test_3bf_confirms_milestone_method_is_proper_but_future_roadmap_is_not_fully_centralized(): void
    {
        $document = file_get_contents($this->docPath);

        $this->assertStringContainsString('The milestone system is coherent through 3BE.', $document);
        $this->assertStringContainsString('The future roadmap is partially defined, not fully centralized.', $document);
        $this->assertStringContainsString('Centralized roadmap index through the current latest milestone', $document);
    }

    public function test_3bf_defines_codex_operating_rules(): void
    {
        $document = file_get_contents($this->docPath);

        foreach ([
            'Work from the human-approved milestone branch and base branch',
            'Do not open or implement a future milestone until it is explicitly requested',
            'Do not modify app/page.tsx unless the milestone explicitly authorizes it',
            'Report uncertainty instead of assuming facts',
            'Do not commit or push unless the user explicitly asks',
        ] as $rule) {
            $this->assertStringContainsString($rule, $document);
        }
    }

    public function test_3bf_defines_milestone_type_rules(): void
    {
        $document = file_get_contents($this->docPath);

        foreach ([
            'Docs/tests-only milestone',
            'Scope gate milestone',
            'Implementation authorization milestone',
            'Narrow implementation milestone',
            'Validation and closure milestone',
            'Governance or strategy audit milestone',
        ] as $milestoneType) {
            $this->assertStringContainsString($milestoneType, $document);
        }
    }

    public function test_3bf_defines_validation_commit_and_push_rules(): void
    {
        $document = file_get_contents($this->docPath);

        foreach ([
            'php artisan test tests/Feature/Milestone3bfRoadmapCodexDevelopmentGovernanceAuditTest.php',
            'php artisan test',
            'git status --short',
            'git diff --stat',
            'git diff --check',
            'Do not commit until validation output is reviewed',
            'Do not push until commit is explicitly approved',
        ] as $requiredText) {
            $this->assertStringContainsString($requiredText, $document);
        }
    }

    public function test_3bf_preserves_one_shared_codebase_strategy(): void
    {
        $document = file_get_contents($this->docPath);

        foreach ([
            'One shared source codebase',
            'Separate deployed app instance per pilot club',
            'Separate database per club instance',
            'Separate environment/configuration per club instance',
            'Separate storage folder or bucket per club instance',
            'No code fork per club',
        ] as $strategyText) {
            $this->assertStringContainsString($strategyText, $document);
        }
    }

    public function test_3bf_preserves_pilot_club_boundary_without_hardcoded_client_specific_logic(): void
    {
        $document = file_get_contents($this->docPath);

        foreach ([
            'Lions Club Beenachi',
            'Rotary Central Bathery',
            'must not encourage hardcoded client-specific logic',
            'Hardcoded Lions-only business logic',
            'Hardcoded Rotary-only business logic',
            'Client-specific production forks',
        ] as $boundaryText) {
            $this->assertStringContainsString($boundaryText, $document);
        }
    }

    public function test_3bf_records_deferred_and_open_workstreams(): void
    {
        $document = file_get_contents($this->docPath);

        foreach ([
            'Club finance reports frontend display',
            'Balance sheet frontend display',
            'Report exports',
            'Reminders',
            'Payment cancellation',
            'Payment reversal',
            'Special collection allocation workflow',
            'Deployment checklist',
            'Future shared SaaS readiness',
            'Centralized Codex operating manual',
        ] as $workstream) {
            $this->assertStringContainsString($workstream, $document);
        }
    }

    public function test_3bf_shifts_frontend_authorization_from_3bf_to_3bg(): void
    {
        $document = file_get_contents($this->docPath);

        $this->assertStringContainsString('3BE previously identified 3BF as the likely frontend display implementation authorization.', $document);
        $this->assertStringContainsString('Milestone 3BF - Roadmap and Codex Development Governance Audit.', $document);
        $this->assertStringContainsString('Milestone 3BG - Club Finance Reports and Balance Sheet Frontend Display Implementation Authorization.', $document);
        $this->assertStringContainsString('3BH - Frontend Display Narrow Implementation', $document);
        $this->assertStringContainsString('3BI - Frontend Display Validation and Closure', $document);
    }

    public function test_3bf_blocks_all_runtime_frontend_backend_implementation_scope(): void
    {
        $document = file_get_contents($this->docPath);

        foreach ([
            'Frontend implementation',
            'app/page.tsx changes',
            'Backend route changes',
            'Backend controller changes',
            'Backend service changes',
            'Backend resource changes',
            'Backend request changes',
            'Model changes',
            'Migration changes',
            'Seeder changes',
            'API changes',
            'Payment mutation',
            'Receipt mutation',
            'Export features',
            'PDF features',
            'Excel import or export',
            'Reminder work',
            'Cancellation workflow',
            'Reversal workflow',
            'Allocation workflow',
            'Deployment script changes',
            'SaaS implementation',
            'Client-specific hardcoding',
            'Runtime behavior changes',
        ] as $blockedScope) {
            $this->assertStringContainsString($blockedScope, $document);
        }
    }

    public function test_3bf_adds_only_audit_doc_and_audit_test(): void
    {
        $document = file_get_contents($this->docPath);

        $this->assertStringContainsString('backend/docs/milestone_3bf_roadmap_codex_development_governance_audit.md', $document);
        $this->assertStringContainsString('backend/tests/Feature/Milestone3bfRoadmapCodexDevelopmentGovernanceAuditTest.php', $document);

        $this->assertFileExists(base_path('docs/milestone_3bf_roadmap_codex_development_governance_audit.md'));
        $this->assertFileExists(base_path('tests/Feature/Milestone3bfRoadmapCodexDevelopmentGovernanceAuditTest.php'));

        $this->assertFileDoesNotExist(app_path('Http/Controllers/Api/V1/Finance/ClubFinanceReportFrontendController.php'));
        $this->assertFileDoesNotExist(app_path('Http/Requests/Finance/ClubFinanceReportFrontendRequest.php'));
        $this->assertFileDoesNotExist(app_path('Services/Finance/ClubFinanceReportFrontendService.php'));
        $this->assertFileDoesNotExist(app_path('Models/ClubFinanceReportView.php'));
        $this->assertFileDoesNotExist(database_path('migrations/create_club_finance_report_views_table.php'));
        $this->assertFileDoesNotExist(base_path('../app/page.tsx.3bf'));
    }
}
