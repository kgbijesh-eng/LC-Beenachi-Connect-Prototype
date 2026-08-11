<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3fqa3fpaGovernanceTestLifecycleRepairTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3fqa_3fpa_governance_test_lifecycle_repair.md';
    private string $repaired3fmTestPath = __DIR__ . '/Milestone3fmEventsMeetingsBackendActivationScopeGateTest.php';
    private string $repaired3fnTestPath = __DIR__ . '/Milestone3fnEventsMeetingsBackendAuthorizationContractApprovalTest.php';
    private string $repaired3foTestPath = __DIR__ . '/Milestone3foEventsMeetingsDatabaseReadApiFoundationTest.php';
    private string $repaired3fpTestPath = __DIR__ . '/Milestone3fpEventsMeetingsLifecycleBackendWorkflowTest.php';
    private string $repaired3fpaTestPath = __DIR__ . '/Milestone3fpaEventsMeetingsIsolatedBrowserPermissionAlignmentTest.php';

    public function test_3fqa_document_identifies_complete_pre_frontend_lifecycle_repair_scope_and_blocks_3fr_until_verified(): void
    {
        $document = $this->document();

        foreach ([
            'Milestone 3FQA - Events Governance-Test Lifecycle Repair',
            'Base commit: `717781ef8a0e009f62ba451ae591dcd8db76471a`',
            'Verified 3FM commit: `d3fb29775c83f8c16b99fd53808a5d0e27278eb4`',
            'Verified 3FN commit: `02206652d88317b94c025b29d5ed0a205f107ff8`',
            'Verified 3FO commit: `360c6f6a60f91bf406ce71101d17e23228ad3176`',
            'Verified 3FP commit: `9e27a7ab75d10cf2a9affab3b4d85fd576c43617`',
            'Verified 3FPA commit: `3d8c9adb164815382171d5628548ea64023463ba`',
            'Verified 3FQ commit: `717781ef8a0e009f62ba451ae591dcd8db76471a`',
            'completes the pre-frontend governance lifecycle review across 3FM, 3FN, 3FO, 3FP, and 3FPA',
            '3FQ legitimately connected the frontend to verified backend Events & Meetings data',
            'Milestone 3FR remains blocked until every test and the build pass',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_document_records_historical_git_evidence_for_3fm_through_3fpa(): void
    {
        $document = $this->document();

        foreach ([
            'git show d3fb29775c83f8c16b99fd53808a5d0e27278eb4:app/page.tsx',
            'git show 02206652d88317b94c025b29d5ed0a205f107ff8:app/page.tsx',
            'git show 360c6f6a60f91bf406ce71101d17e23228ad3176:app/page.tsx',
            'git show 9e27a7ab75d10cf2a9affab3b4d85fd576c43617:app/page.tsx',
            'git show 3d8c9adb164815382171d5628548ea64023463ba:app/page.tsx',
            'git diff-tree --no-commit-id --name-only -r 3d8c9adb164815382171d5628548ea64023463ba',
            'checks exit status',
            'normalizes line endings',
            '`app/page.tsx` was not modified.',
            'No runtime backend file changed.',
            'No milestone commit was amended, rebased, rewritten, or force-pushed.',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_repaired_3fm_test_uses_actual_historical_commit_not_current_frontend_state(): void
    {
        $test = $this->repairedTest($this->repaired3fmTestPath);

        $this->assertStringContainsString("private const VERIFIED_3FM_COMMIT = 'd3fb29775c83f8c16b99fd53808a5d0e27278eb4';", $test);
        $this->assertStringContainsString("'show', self::VERIFIED_3FM_COMMIT.':app/page.tsx'", $test);
        $this->assertStringContainsString('test_historical_3fm_contract_blocked_mutation_and_frontend_activation', $test);
        $this->assertStringContainsString('gitOutput', $test);
        $this->assertStringContainsString('normalizeLineEndings', $test);
        $this->assertStringNotContainsString("file_get_contents(base_path('../app/page.tsx'))", $test);
        $this->assertStringNotContainsString('private string $frontendPath', $test);
    }

    public function test_repaired_3fn_test_uses_actual_historical_commit_not_current_frontend_state(): void
    {
        $test = $this->repairedTest($this->repaired3fnTestPath);

        $this->assertStringContainsString("private const VERIFIED_3FN_COMMIT = '02206652d88317b94c025b29d5ed0a205f107ff8';", $test);
        $this->assertStringContainsString("'show', self::VERIFIED_3FN_COMMIT.':app/page.tsx'", $test);
        $this->assertStringContainsString('test_historical_3fn_contract_blocked_mutation_and_frontend_activation', $test);
        $this->assertStringContainsString('gitOutput', $test);
        $this->assertStringContainsString('normalizeLineEndings', $test);
        $this->assertStringNotContainsString("file_get_contents(base_path('../app/page.tsx'))", $test);
        $this->assertStringNotContainsString('private string $frontendPath', $test);
    }

    public function test_repaired_3fo_test_uses_actual_historical_commit_not_current_frontend_state(): void
    {
        $test = $this->repairedTest($this->repaired3foTestPath);

        $this->assertStringContainsString("private const VERIFIED_3FO_COMMIT = '360c6f6a60f91bf406ce71101d17e23228ad3176';", $test);
        $this->assertStringContainsString("'show', self::VERIFIED_3FO_COMMIT.':app/page.tsx'", $test);
        $this->assertStringContainsString('test_historical_3fo_frontend_remained_fail_closed_and_not_connected', $test);
        $this->assertStringContainsString('gitOutput', $test);
        $this->assertStringContainsString('normalizeLineEndings', $test);
        $this->assertStringNotContainsString('$this->frontendPath', $test);
        $this->assertStringNotContainsString('file_get_contents($this->frontendPath)', $test);
        $this->assertStringNotContainsString('private string $frontendPath', $test);
    }

    public function test_repaired_3fp_test_uses_actual_historical_commit_not_current_frontend_state(): void
    {
        $test = $this->repairedTest($this->repaired3fpTestPath);

        $this->assertStringContainsString("private const VERIFIED_3FP_COMMIT = '9e27a7ab75d10cf2a9affab3b4d85fd576c43617';", $test);
        $this->assertStringContainsString("'show', self::VERIFIED_3FP_COMMIT.':app/page.tsx'", $test);
        $this->assertStringContainsString('test_historical_3fp_frontend_remained_not_connected', $test);
        $this->assertStringContainsString('gitOutput', $test);
        $this->assertStringContainsString('normalizeLineEndings', $test);
        $this->assertStringNotContainsString('$this->frontendPath', $test);
        $this->assertStringNotContainsString('file_get_contents($this->frontendPath)', $test);
        $this->assertStringNotContainsString('private string $frontendPath', $test);
    }

    public function test_repaired_3fpa_test_uses_actual_historical_commit_and_scope_not_current_frontend_state(): void
    {
        $test = $this->repairedTest($this->repaired3fpaTestPath);

        $this->assertStringContainsString("private const VERIFIED_3FPA_COMMIT = '3d8c9adb164815382171d5628548ea64023463ba';", $test);
        $this->assertStringContainsString("'show', self::VERIFIED_3FPA_COMMIT.':app/page.tsx'", $test);
        $this->assertStringContainsString("'diff-tree'", $test);
        $this->assertStringContainsString("'--no-commit-id'", $test);
        $this->assertStringContainsString("'--name-only'", $test);
        $this->assertStringContainsString("'backend/docs/milestone_3fpa_events_meetings_isolated_browser_permission_alignment.md'", $test);
        $this->assertStringContainsString("'backend/tests/Feature/Milestone3fpaEventsMeetingsIsolatedBrowserPermissionAlignmentTest.php'", $test);
        $this->assertStringNotContainsString('$this->frontendPath', $test);
        $this->assertStringNotContainsString('file_get_contents($this->frontendPath)', $test);
        $this->assertStringNotContainsString('private string $frontendPath', $test);
    }

    public function test_3fqa_authorizes_only_governance_files_and_preserves_runtime_boundary(): void
    {
        $document = $this->document();

        foreach ([
            'backend/tests/Feature/Milestone3fmEventsMeetingsBackendActivationScopeGateTest.php',
            'backend/tests/Feature/Milestone3fnEventsMeetingsBackendAuthorizationContractApprovalTest.php',
            'backend/tests/Feature/Milestone3foEventsMeetingsDatabaseReadApiFoundationTest.php',
            'backend/tests/Feature/Milestone3fpEventsMeetingsLifecycleBackendWorkflowTest.php',
            'backend/tests/Feature/Milestone3fpaEventsMeetingsIsolatedBrowserPermissionAlignmentTest.php',
            'backend/docs/milestone_3fqa_3fpa_governance_test_lifecycle_repair.md',
            'backend/tests/Feature/Milestone3fqa3fpaGovernanceTestLifecycleRepairTest.php',
            'The 3FQ frontend remains unchanged by 3FQA.',
            '`app/page.tsx` was not modified.',
            'No runtime backend file changed.',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_document_records_original_assertions_preserved_by_category(): void
    {
        $document = $this->document();

        foreach ([
            'scope-gate-only governance, fail-closed frontend documentation, backend inventory, unified Events & Meetings architecture',
            'permissions, roles, visibility, lifecycle, routes, request contract, response contract, club scoping, authentication, concurrency, audit',
            'migration structure, model behavior, read API behavior, authorization, club scoping, filters, response shape, absence of mutation routes',
            'create, update, publish, cancel, complete, lifecycle transitions, audit atomicity, concurrency and lock version behavior, role authorization, club scoping',
            'isolated database safety procedure, permission alignment, active membership requirements, cross-club protection, management-only visibility',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    private function document(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }

    private function repairedTest(string $path): string
    {
        $this->assertFileExists($path);

        return file_get_contents($path);
    }
}
