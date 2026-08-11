<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3dtPaymentRefreshRetestScopeGateTest extends TestCase
{
    private string $docPath = __DIR__.'/../../docs/milestone_3dt_payment_refresh_retest_scope_gate.md';

    public function test_3dt_creates_payment_refresh_retest_scope_gate_document(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Milestone 3DT - Payment Refresh Retest Scope Gate',
            'Milestone 3DT is a docs/tests-only retest scope gate for DN-047.',
            '3DT scopes DN-047 retest only.',
            '3DT does not execute the payment refresh retest.',
            '3DT does not implement fixes.',
            '3DT does not edit runtime code.',
            '3DT does not edit frontend runtime code.',
            '3DT does not edit app/page.tsx.',
            '3DT does not change backend runtime behavior.',
            '3DT does not edit payment controllers, payment services, routes, requests, resources, models, migrations, seeders, factories, policies, middleware, config, package files, or lock files.',
            '3DT does not fix DN-020.',
            'Narrow 3DS lifecycle guard exception:',
            'Not used.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3dt_current_file_changes_are_limited_to_approved_scope_gate_files(): void
    {
        $allowed = [
            'backend/docs/milestone_3dt_payment_refresh_retest_scope_gate.md',
            'backend/tests/Feature/Milestone3dtPaymentRefreshRetestScopeGateTest.php',
            'backend/tests/Feature/Milestone3dsSeededPaymentPermissionDefectImplementationTest.php',
            'backend/docs/milestone_3du_payment_refresh_retest_execution.md',
            'backend/tests/Feature/Milestone3duPaymentRefreshRetestExecutionTest.php',
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

        $this->assertSame([], array_values(array_diff($changed, $allowed)), '3DT may change only approved 3DT docs/tests, plus the narrow 3DS lifecycle guard exception if used.');

        foreach ($this->blockedPaths() as $path) {
            $this->assertFalse($this->containsPathOrChild($changed, $path), "{$path} must not be edited in 3DT.");
        }
    }

    public function test_3dt_records_base_branch_created_branch_and_allowed_files(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'milestone-3ds-seeded-payment-permission-defect-implementation',
            'f43a52250c64c9b15d0c963299b083279148128b',
            'milestone-3dt-payment-refresh-retest-scope-gate',
            'backend/docs/milestone_3dt_payment_refresh_retest_scope_gate.md',
            'backend/tests/Feature/Milestone3dtPaymentRefreshRetestScopeGateTest.php',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3dt_records_relationship_to_corrected_3dn_and_defect_fix_lineage(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Milestone 3DN - Internal MVP Manual Acceptance Execution recorded corrected evidence',
            'NO-GO PENDING DEFECT FIXES.',
            'Milestone 3DO - Internal MVP Defect Fix Scope Gate classified DN-047 as a dependent retest item blocked by DN-031.',
            'Milestone 3DP - Logout 500 Defect Fix Scope Gate scoped DN-010.',
            'Milestone 3DQ - Logout 500 Defect Fix Implementation fixed DN-010.',
            'Milestone 3DR - Seeded Payment Permission Defect Scope Gate scoped DN-031.',
            'Milestone 3DS - Seeded Payment Permission Defect Implementation fixed DN-031',
            'Milestone 3DM authorized the controlled internal MVP manual acceptance execution lane.',
            'Milestone 3DL defined the internal MVP manual acceptance scope gate and result categories.',
            'Milestones 3A-3AF established finance foundation, payment recording, receipt, receipt PDF, and closure lanes.',
            'Milestones 3AG-3AI established outstanding dues and member finance summary read API behavior.',
            'Milestones 3AJ-3BR established finance frontend, reports, payment history, and related closure lanes.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3dt_records_dn_047_observed_failure_and_dependent_retest_classification(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'DN-047 - FAIL.',
            'Member refresh worked, but payment refresh could not be accepted because payment recording failed.',
            'payment refresh could not be verified because payment recording returned HTTP 403.',
            'DN-047 classification in 3DT:',
            'Dependent retest item.',
            'Priority: retest after DN-031 fix.',
            'Acceptance impact: payment refresh behavior could not be validated while payment recording was blocked.',
            'Expected future action: perform a narrow targeted retest using synthetic local data after DN-031 fix.',
            'DN-047 was not conclusively proven to be an independent payment refresh defect in 3DN.',
            'DN-047 must not be treated as fixed without retest evidence.',
            'DN-047 must not be treated as an independent runtime defect without retest evidence.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3dt_records_3ds_change_and_that_dn_047_is_not_retested_or_fixed(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            '3DS fixed DN-031.',
            'The seeded President/Admin persona now has payments.record in local/testing seeded data.',
            'The seeded President/Admin persona can record a synthetic member payment.',
            'Normal Member payment authorization remains denied with HTTP 403.',
            'Because DN-031 is now fixed, DN-047 is ready for targeted retest.',
            '3DT does not retest DN-047.',
            '3DT does not fix DN-047.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3dt_records_3du_retest_objective_boundary_data_rules_and_classification_rules(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Milestone 3DU — Payment Refresh Retest Execution.',
            '3DU must perform a narrow targeted retest of DN-047 only.',
            'The 3DU retest objective is to confirm whether payment refresh behavior works after the seeded President/Admin can successfully record a synthetic payment.',
            '3DU must verify the relevant payment refresh behavior no longer fails due to payment-recording authorization.',
            'Use local/test environment only.',
            'Use synthetic seeded/local data only.',
            'Use the seeded President/Admin persona after the 3DS payments.record fix.',
            'Record a synthetic payment through the approved payment-recording flow.',
            'Trigger or verify the relevant payment refresh behavior.',
            'Confirm refreshed payment state is visible/consistent according to existing UI/API behavior.',
            'Confirm Normal Member payment authorization remains denied where relevant.',
            'Record DN-047 as PASS if payment recording and refresh behavior work end-to-end within the scoped retest.',
            'Record DN-047 as FAIL if payment recording works but refresh behavior still fails.',
            'Record DN-047 as BLOCKED only if the retest cannot be completed due to environment/setup outside the application behavior.',
            'Do not use production data or real financial records.',
            'Do not perform real payment collection.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3dt_records_go_no_go_scope_decision_remaining_defects_and_no_execution(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'GO: No.',
            'NO-GO: Yes, pending DN-047 retest and DN-020 resolution.',
            'Current recommendation: NO-GO PENDING DEFECT FIXES.',
            'DN-047 is a retest item, not yet a confirmed independent runtime defect.',
            'DN-047 cannot be closed until 3DU executes the retest.',
            'DN-020 remains unresolved and outside this lane.',
            'Internal MVP must not be declared accepted in 3DT.',
            'Milestone 3DT scopes DN-047 retest only.',
            'DN-047 remains unresolved in 3DT because no retest is executed here.',
            'DN-010 remains fixed by 3DQ.',
            'DN-031 remains fixed by 3DS.',
            'Internal MVP remains NO-GO PENDING DEFECT FIXES.',
            'No new full manual acceptance execution was performed in 3DT.',
            'No production data or real financial data was used in 3DT.',
            'No deployment occurred in 3DT.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3dt_records_unsupported_exclusions_receipt_pdf_profile_documents_and_risks(): void
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
            '3DT is not a fix milestone.',
            '3DT is not a retest execution milestone.',
            '3DT is not new full manual acceptance execution.',
            '3DT is not deployment readiness.',
            '3DT is not SaaS readiness.',
            '3DT does not certify production use.',
            'Dependent failures must not be treated as fixed without retest evidence.',
            'Backend authorization must remain authoritative.',
            'UI role hiding is not security.',
            'Existing app/page.tsx is large and future changes must remain narrow.',
            'Missing backend/API gaps discovered later must become separate future milestones, not fake frontend behavior.',
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
        ];
    }

    private function containsPathOrChild(array $changed, string $blockedPath): bool
    {
        return collect($changed)->contains(function (string $path) use ($blockedPath): bool {
            return $path === $blockedPath || str_starts_with($path, rtrim($blockedPath, '/').'/');
        });
    }
}
