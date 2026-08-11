<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3doInternalMvpDefectFixScopeGateTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3do_internal_mvp_defect_fix_scope_gate.md';

    public function test_3do_document_exists_and_records_scope_gate_only_status(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Milestone 3DO - Internal MVP Defect Fix Scope Gate',
            'Milestone 3DO is a docs/tests-only defect fix scope gate opened after the corrected 3DN manual acceptance evidence.',
            '3DO classifies the 3DN FAIL items.',
            '3DO decides defect fix order.',
            '3DO authorizes narrow future defect-fix milestones.',
            '3DO does not fix anything.',
            '3DO does not implement frontend runtime behavior.',
            '3DO does not edit app/page.tsx.',
            '3DO does not change backend runtime behavior.',
            '3DO does not rerun manual acceptance.',
            '3DO does not claim the internal MVP is accepted.',
            '3DO must not start 3DP.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3do_records_base_branch_hash_allowed_files_and_scope_limits(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'milestone-3dn-internal-mvp-manual-acceptance-execution',
            '83b8beb0d2c2d2b65ba47651c5a478983cd90cd5',
            'milestone-3do-internal-mvp-defect-fix-scope-gate',
            'backend/docs/milestone_3do_internal_mvp_defect_fix_scope_gate.md',
            'backend/tests/Feature/Milestone3doInternalMvpDefectFixScopeGateTest.php',
            'app/page.tsx is not edited in 3DO.',
            'Backend runtime is not edited in 3DO.',
            'Seeders and permission data are not edited in 3DO.',
            'Package files and lock files are not edited in 3DO.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3do_current_file_changes_are_limited_to_approved_docs_and_test_files(): void
    {
        $allowed = [
            'backend/docs/milestone_3do_internal_mvp_defect_fix_scope_gate.md',
            'backend/tests/Feature/Milestone3doInternalMvpDefectFixScopeGateTest.php',
        ];

        $repoRoot = dirname(__DIR__, 3);
        $status = shell_exec('git -C '.escapeshellarg($repoRoot).' status --short --untracked-files=all') ?? '';
        $changed = collect(preg_split('/\R/', trim($status)))
            ->filter()
            ->map(function (string $line): string {
                $path = preg_replace('/^[ MARCUD?!]{1,2}\s+/', '', $line) ?? $line;
                $path = trim($path);
                if (str_contains($path, ' -> ')) {
                    $path = trim(substr($path, strrpos($path, ' -> ') + 4));
                }

                return str_replace('\\', '/', $path);
            })
            ->values()
            ->all();

        $this->assertSame([], array_values(array_diff($changed, $allowed)), '3DO may change only approved docs/tests scope-gate files.');

        foreach ($this->blockedPaths() as $path) {
            $this->assertNotContains($path, $changed, "{$path} must not be edited in 3DO.");
        }
    }

    public function test_3do_accepts_corrected_3dn_no_go_evidence_as_controlling(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Milestone 3DN is closed, pushed, remote verified, and tracking clean',
            'NO-GO PENDING DEFECT FIXES.',
            '3DO accepts the corrected 3DN evidence as the controlling source of truth.',
            '3DO does not relitigate 3DN evidence.',
            '3DO does not downgrade 3DN FAIL items to BLOCKED.',
            '3DO does not treat the old closure-review prompt as valid.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3do_records_all_controlling_3dn_defects_and_classifications(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'DN-010 | Login/session | Major | Logout token follow-up /auth/me returns HTTP 500 after logout. | Backend auth/session defect.',
            'DN-020 | Members | Major | Frontend member edit-flow confirmation timed out, although backend member create/update/read worked. | Frontend/browser confirmation defect or investigation item.',
            'DN-031 | Payment recording | Major | Seeded President/Admin payment attempt returned HTTP 403 because seeded local roles do not include payments.record. | Backend permission/seeded local role readiness defect.',
            'DN-047 | States and errors | Major | Payment refresh could not be accepted because payment recording failed. | Dependent retest defect blocked by DN-031.',
            'No Critical defects were recorded in 3DN.',
            'No defect fix is authorized inside 3DO.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3do_records_backend_first_fix_order_and_future_milestones(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Fix backend/auth/permission defects before frontend retesting.',
            '1. 3DP - Logout 500 Backend Fix Authorization and Narrow Implementation.',
            '2. 3DQ - Seeded Role Payment Permission Fix Authorization and Narrow Implementation.',
            '3. 3DR - Member Edit-Flow Browser Confirmation Fix or Investigation.',
            '4. 3DS - Payment Refresh Retest After Payment Recording Fix.',
            '5. 3DT - Internal MVP Manual Acceptance Re-Run Scope Gate.',
            'DN-031 blocks payment recording and directly blocks DN-047.',
            'DN-020 frontend/browser confirmation should wait until backend/auth/permission defects are no longer adding noise.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3do_records_future_authorization_boundaries_and_blocked_scope(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            '3DO authorizes future narrow defect-fix milestones only.',
            'Exact defect IDs addressed.',
            'Exact files allowed.',
            'Exact runtime behavior allowed.',
            'Explicit blocked scope.',
            'Validation commands.',
            '3DO does not authorize broad refactors.',
            '3DO does not authorize deployment/SaaS work.',
            '3DO does not authorize production data usage.',
            'Runtime fixes.',
            'Seeder or permission data changes.',
            'Frontend runtime changes.',
            'Manual acceptance re-run.',
            'Profile document upload/download/preview/delete/OCR/PDF behavior.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3do_records_expected_next_milestone_without_starting_it(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Expected next milestone:',
            'Milestone 3DP - Logout 500 Backend Fix Authorization and Narrow Implementation.',
            '3DO must not start 3DP.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    private function scopeDocument(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }

    private function blockedPaths(): array
    {
        return [
            'app/page.tsx',
            'backend/routes/api.php',
            'backend/app/Http/Controllers',
            'backend/app/Http/Requests',
            'backend/app/Http/Resources',
            'backend/app/Services',
            'backend/app/Models',
            'backend/database/migrations',
            'backend/database/seeders',
            'backend/database/factories',
            'backend/app/Policies',
            'backend/app/Providers',
            'backend/app/Http/Middleware',
            'backend/config',
            'composer.json',
            'composer.lock',
            'package.json',
            'package-lock.json',
            'pnpm-lock.yaml',
            'yarn.lock',
            'backend/docs/milestone_3dn_internal_mvp_manual_acceptance_execution.md',
            'backend/tests/Feature/Milestone3dnInternalMvpManualAcceptanceExecutionTest.php',
        ];
    }
}
