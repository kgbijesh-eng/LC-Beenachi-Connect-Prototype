<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3dpLogout500DefectFixScopeGateTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3dp_logout_500_defect_fix_scope_gate.md';

    public function test_3dp_creates_logout_500_defect_fix_scope_gate_document(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Milestone 3DP - Logout 500 Defect Fix Scope Gate',
            'Milestone 3DP is a docs/tests-only defect-fix scope gate for DN-010.',
            '3DP defines and authorizes the narrow future fix path for DN-010.',
            '3DP does not implement the logout fix.',
            '3DP does not edit frontend runtime code.',
            '3DP does not edit app/page.tsx.',
            '3DP does not change backend runtime behavior.',
            '3DP does not perform new manual acceptance execution.',
            '3DP does not deploy the app.',
            '3DP must not start 3DQ.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3dp_records_base_branch_created_branch_and_allowed_files(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'milestone-3do-internal-mvp-defect-fix-scope-gate',
            '2e6d06ddd474cb867f974ac7dfcce6434d5f2275',
            'milestone-3dp-logout-500-defect-fix-scope-gate',
            'backend/docs/milestone_3dp_logout_500_defect_fix_scope_gate.md',
            'backend/tests/Feature/Milestone3dpLogout500DefectFixScopeGateTest.php',
            'app/page.tsx is not edited in 3DP.',
            'Frontend runtime files are not edited in 3DP.',
            'Backend runtime files are not edited in 3DP.',
            'Routes/controllers/requests/resources/services/models/migrations are not edited in 3DP.',
            'Older milestone tests are not edited in 3DP unless the narrow 3DO lifecycle guard exception is required.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3dp_current_file_changes_are_limited_to_approved_scope_gate_files(): void
    {
        $allowed = [
            'backend/docs/milestone_3dp_logout_500_defect_fix_scope_gate.md',
            'backend/tests/Feature/Milestone3dpLogout500DefectFixScopeGateTest.php',
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

        $this->assertSame([], array_values(array_diff($changed, $allowed)), '3DP may change only approved 3DP docs/tests, plus the narrow 3DO guard exception if used.');

        foreach ($this->blockedPaths() as $path) {
            $this->assertFalse($this->containsPathOrChild($changed, $path), "{$path} must not be edited in 3DP.");
        }
    }

    public function test_3dp_records_relationships_to_prior_milestones_and_corrected_3dn_result(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Milestone 3DN - Internal MVP Manual Acceptance Execution recorded corrected localhost evidence',
            'NO-GO PENDING DEFECT FIXES.',
            'Milestone 3DO - Internal MVP Defect Fix Scope Gate classified DN-010 as the first backend/auth defect-fix lane',
            'Milestone 3DM authorized the controlled internal MVP manual acceptance execution lane.',
            'Milestone 3DL defined the internal MVP manual acceptance scope gate and result categories.',
            'Milestone 1C established the Laravel backend foundation, including Sanctum-based authentication foundation',
            '3DP does not reopen 3DN, 3DO, 3DM, 3DL, or 1C.',
            '3DP does not downgrade DN-010 from FAIL to BLOCKED.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3dp_records_dn_010_observed_failure_and_backend_auth_classification(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'DN-010 - FAIL.',
            'Logout deletes or invalidates the token.',
            'Follow-up /auth/me with the logged-out token returns HTTP 500.',
            'Acceptance impact:',
            'This blocks reliable logout/session validation during internal MVP acceptance.',
            'HTTP 500 after logout is unacceptable for internal MVP acceptance because it indicates unsafe error handling.',
            'DN-010 is a backend/auth reliability defect until fixed.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3dp_records_expected_future_safe_response_and_suspected_root_cause_area(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'After logout deletes or invalidates the current token, a follow-up /auth/me request using that logged-out token must not return HTTP 500.',
            'HTTP 401 unauthenticated.',
            'If existing project auth contracts require another safe unauthenticated response, 3DQ may document and use that project-consistent response.',
            'The future fix must not weaken authentication.',
            'The future fix must not mask authorization failures as success.',
            'The future fix must preserve valid /auth/me behavior for authenticated users.',
            '/api/v1/auth/me is protected by auth:sanctum middleware.',
            '/api/v1/auth/logout is protected by auth:sanctum middleware.',
            'Existing tests do not cover a same-token /auth/me request after logout token deletion.',
            'This is a suspected root-cause area based on read-only inspection. 3DP does not claim the root cause is confirmed.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3dp_records_3dq_implementation_boundary_and_validation_requirements(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Milestone 3DQ - Logout 500 Defect Fix Implementation.',
            '3DQ may address DN-010 only.',
            'Existing auth controller/logout/me handling file if needed.',
            'A focused backend feature test for logout follow-up behavior.',
            'backend/app/Http/Controllers/Api/V1/Auth/AuthController.php',
            'backend/tests/Feature/AuthApiTest.php or a focused new backend feature test',
            '3DQ must not authorize or touch:',
            'Frontend runtime files.',
            'app/page.tsx.',
            'Payment, receipt, ledger, or payment-history backend runtime files.',
            'A logout request using a valid token succeeds according to the existing logout contract.',
            'A follow-up /auth/me request using the same logged-out token does not return HTTP 500.',
            'The preferred expected response is HTTP 401 unauthenticated',
            'The fix is covered by a focused backend feature test.',
            'Existing auth behavior for valid logged-in users remains unchanged.',
            'No frontend behavior is changed unless proven necessary.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3dp_records_remaining_defects_go_no_go_and_scope_gate_decision(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            '3DP scopes DN-010 only.',
            'DN-020 - Frontend member edit-flow confirmation timed out.',
            'DN-031 - Seeded President/Admin payment attempt returned 403 due missing payments.record.',
            'DN-047 - Payment refresh blocked because payment recording failed.',
            'GO: No.',
            'NO-GO: Yes, pending DN-010 and remaining defect fixes.',
            'Current recommendation: NO-GO PENDING DEFECT FIXES.',
            'Internal MVP must not be declared accepted in 3DP.',
            'Milestone 3DP scopes DN-010 only.',
            'DN-010 remains unfixed in 3DP.',
            'Next work should be Milestone 3DQ - Logout 500 Defect Fix Implementation.',
            'No fix was implemented in 3DP.',
            'No new manual acceptance execution was performed in 3DP.',
            'No production data or real financial data was used in 3DP.',
            'No deployment occurred in 3DP.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3dp_records_unsupported_feature_exclusions_receipt_pdf_profile_documents_and_risks(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Profile document upload.',
            'Profile document download.',
            'Profile document preview.',
            'Profile document delete.',
            'OCR.',
            'Profile document PDF generation.',
            'Receipt PDF remains allowed only because it belongs to the approved finance/receipt lane.',
            '3DP does not change receipt PDF behavior.',
            'Profile document upload/download/PDF/OCR remains out of scope.',
            '3DP does not modify Profile Documents behavior.',
            '3DP is not a fix milestone.',
            '3DP is not new manual acceptance execution.',
            '3DP is not deployment readiness.',
            '3DP is not SaaS readiness.',
            '3DP does not certify production use.',
            'Backend authorization must remain authoritative.',
            'Existing app/page.tsx is large and future changes must remain narrow.',
            'Missing backend/API gaps discovered later must become separate future milestones, not fake frontend behavior.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3dp_records_expected_next_milestone_without_starting_it(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Expected next milestone:',
            'Milestone 3DQ - Logout 500 Defect Fix Implementation.',
            '3DP must not start 3DQ.',
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
            'backend/docs/milestone_3do_internal_mvp_defect_fix_scope_gate.md',
            'backend/tests/Feature/Milestone3dnInternalMvpManualAcceptanceExecutionTest.php',
            'file upload/storage files',
            'deployment/SaaS files',
        ];
    }

    private function containsPathOrChild(array $changed, string $blockedPath): bool
    {
        return collect($changed)->contains(function (string $path) use ($blockedPath): bool {
            return $path === $blockedPath || str_starts_with($path, rtrim($blockedPath, '/').'/');
        });
    }
}
