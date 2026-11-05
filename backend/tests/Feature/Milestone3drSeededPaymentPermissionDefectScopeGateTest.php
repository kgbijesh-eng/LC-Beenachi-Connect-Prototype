<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3drSeededPaymentPermissionDefectScopeGateTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3dr_seeded_payment_permission_defect_scope_gate.md';

    public function test_3dr_creates_seeded_payment_permission_scope_gate_document(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Milestone 3DR - Seeded Payment Permission Defect Scope Gate',
            'Milestone 3DR is a docs/tests-only defect-fix scope gate for DN-031.',
            '3DR scopes DN-031 only.',
            '3DR does not implement the permission fix.',
            '3DR does not edit runtime code.',
            '3DR does not edit seeders, roles, permissions, policies, middleware, controllers, services, routes, requests, resources, models, migrations, factories, config, package files, or lock files.',
            '3DR does not edit app/page.tsx.',
            '3DR does not fix DN-020.',
            '3DR does not fix DN-047.',
            '3DR must not start 3DS.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3dr_records_base_branch_created_branch_and_allowed_files(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'milestone-3dq-logout-500-defect-fix-implementation',
            '13fd2e057f37a0871f0d6df395aa779869eb858a',
            'milestone-3dr-seeded-payment-permission-defect-scope-gate',
            'backend/docs/milestone_3dr_seeded_payment_permission_defect_scope_gate.md',
            'backend/tests/Feature/Milestone3drSeededPaymentPermissionDefectScopeGateTest.php',
            'Frontend runtime is not edited in 3DR.',
            'Backend runtime is not edited in 3DR.',
            'Routes/controllers/requests/resources/services/models/migrations are not edited in 3DR.',
            'Seeders, roles, and permissions are not edited in 3DR.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3dr_current_file_changes_are_limited_to_approved_scope_gate_files(): void
    {
        $allowed = [
            'backend/docs/milestone_3dr_seeded_payment_permission_defect_scope_gate.md',
            'backend/tests/Feature/Milestone3drSeededPaymentPermissionDefectScopeGateTest.php',
            'backend/tests/Feature/Milestone3dqLogout500DefectFixImplementationTest.php',
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

        $branch = trim(shell_exec('git -C '.escapeshellarg($repoRoot).' rev-parse --abbrev-ref HEAD') ?? '');
        $this->assertNotSame('', $branch, 'The repository branch must be readable.');

        // The original milestone owns this allowlist. Blocked paths below apply on every branch.
        if ($branch === 'milestone-3dr-seeded-payment-permission-defect-scope-gate') {
            $this->assertSame([], array_values(array_diff($changed, $allowed)), '3DR may change only approved 3DR docs/tests, plus the narrow 3DQ guard exception if used.');
        }

        foreach ($this->blockedPaths() as $path) {
            $this->assertFalse($this->containsPathOrChild($changed, $path), "{$path} must not be edited in 3DR.");
        }
    }

    public function test_3dr_records_relationships_and_current_project_state(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Milestone 3DN - Internal MVP Manual Acceptance Execution recorded corrected evidence',
            'NO-GO PENDING DEFECT FIXES.',
            'Milestone 3DO - Internal MVP Defect Fix Scope Gate classified DN-031 as a backend permission/seeded local role readiness defect.',
            'Milestone 3DP - Logout 500 Defect Fix Scope Gate scoped DN-010.',
            'Milestone 3DQ - Logout 500 Defect Fix Implementation fixed DN-010.',
            'Milestone 3DM authorized the controlled internal MVP manual acceptance execution lane.',
            'Milestone 3DL defined the internal MVP manual acceptance scope gate and result categories.',
            'Milestones 3A through 3AF established finance foundation, payment recording, receipt, receipt PDF, and closure lanes.',
            'Milestone 1C established the Laravel backend foundation and role-permission foundation.',
            'DN-010 is fixed by 3DQ.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3dr_records_dn_031_observed_failure_and_seed_permission_classification(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'DN-031 - FAIL.',
            'Seeded President/Admin payment attempt returned HTTP 403.',
            'Corrected 3DN evidence states the seeded local roles do not include payments.record.',
            'Classification:',
            'Seed/permission defect.',
            'Priority:',
            'Next fix after DN-010.',
            'Acceptance impact:',
            'Blocks payment-recording acceptance for seeded President/Admin personas.',
            'DN-031 is distinct from payment runtime behavior.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3dr_records_read_only_inspection_and_suspected_root_cause(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'backend/database/seeders/DefaultRolesAndPermissionsSeeder.php.',
            'backend/database/seeders/LocalRoleTestUsersSeeder.php.',
            'backend/app/Http/Middleware/EnsureClubPermission.php.',
            'backend/app/Services/Finance/PaymentRecordingService.php.',
            'The payment API route is wrapped in club.permission:payments.record,club.',
            'PaymentRecordingService also requires payments.record through ClubPermissionService.',
            'EnsureClubPermission returns HTTP 403 when a user lacks the required permission.',
            'DefaultRolesAndPermissionsSeeder does not currently define a payments.record permission in its default permission list.',
            'DefaultRolesAndPermissionsSeeder does not currently assign payments.record to president-admin.',
            'LocalRoleTestUsersSeeder assigns president@lcbeenachi.test to the president-admin role, and does not independently grant permissions.',
            'The likely fix belongs in seeded roles/permissions or local test persona setup, not payment controller/service runtime.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3dr_records_3ds_boundary_and_validation_requirements(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Milestone 3DS - Seeded Payment Permission Defect Implementation.',
            '3DS may address DN-031 only.',
            'Existing role/permission seeder if needed.',
            'Existing local role test user setup if needed.',
            'A focused backend feature test proving seeded President/Admin can record payment when intended by the acceptance contract.',
            'No payment controller/service refactor unless inspection proves the authorization contract is wrong.',
            'No frontend changes.',
            'No app/page.tsx change.',
            'No broad role-permission redesign.',
            'Seeded President/Admin persona used by internal MVP acceptance has the intended payment-recording permission if the acceptance contract requires President/Admin payment recording.',
            'A payment recording request by that seeded authorized persona no longer returns HTTP 403 due to missing payments.record.',
            'Unauthorized payment attempts by Normal Member still return HTTP 403.',
            'The fix does not weaken backend authorization.',
            'The fix is covered by a focused backend feature test.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3dr_records_go_no_go_scope_decision_remaining_defects_and_risks(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'GO: No.',
            'NO-GO: Yes, pending DN-031 and remaining defect fixes.',
            'Current recommendation: NO-GO PENDING DEFECT FIXES.',
            'Milestone 3DR scopes DN-031 only.',
            'DN-031 remains unfixed in 3DR.',
            'DN-010 is already fixed by 3DQ.',
            'DN-020 - Frontend member edit-flow confirmation timed out.',
            'DN-047 - Payment refresh blocked because payment recording failed.',
            'No new manual acceptance execution was performed in 3DR.',
            'No production data or real financial data was used in 3DR.',
            '3DR is not a fix milestone.',
            'Payment permission fixes must not weaken backend authorization.',
            'Normal Member unauthorized payment behavior must remain 403.',
            'If President/Admin should not record payments by design, then 3DS must reclassify the acceptance expectation rather than grant permission blindly.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3dr_records_unsupported_exclusions_receipt_pdf_profile_documents_and_next_milestone(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Profile document upload.',
            'Profile document download.',
            'Profile document preview.',
            'Profile document delete.',
            'OCR.',
            'Profile document PDF generation.',
            'Receipt PDF is allowed only because it belongs to the approved finance/receipt lane.',
            'Profile document upload/download/PDF/OCR remains out of scope.',
            'Expected next milestone:',
            'Milestone 3DS - Seeded Payment Permission Defect Implementation.',
            '3DR must not start 3DS.',
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
            'backend/docs/milestone_3dq_logout_500_defect_fix_implementation.md',
            'backend/tests/Feature/Milestone3dqLogout500DefectFixImplementationTest.php',
        ];
    }

    private function containsPathOrChild(array $changed, string $blockedPath): bool
    {
        return collect($changed)->contains(function (string $path) use ($blockedPath): bool {
            return $path === $blockedPath || str_starts_with($path, rtrim($blockedPath, '/').'/');
        });
    }
}
