<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3dnInternalMvpManualAcceptanceExecutionTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3dn_internal_mvp_manual_acceptance_execution.md';
    private string $frontendPath = __DIR__ . '/../../../app/page.tsx';

    public function test_3dn_creates_manual_acceptance_execution_document_without_runtime_implementation(): void
    {
        $document = $this->executionDocument();

        foreach ([
            'Milestone 3DN - Internal MVP Manual Acceptance Execution',
            '3DN executes the acceptance checklist using local/static/build/test evidence where safe',
            '3DN does not implement runtime code.',
            '3DN does not edit frontend runtime code.',
            '3DN does not edit app/page.tsx.',
            '3DN does not change backend runtime behavior.',
            '3DN does not deploy the app.',
            '3DN does not use production data.',
            '3DN does not use real club financial records.',
            '3DN does not perform real payment collection.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3dn_current_file_changes_are_limited_to_approved_files_or_3dm_guard_exception(): void
    {
        $allowed = [
            'backend/docs/milestone_3dn_internal_mvp_manual_acceptance_execution.md',
            'backend/tests/Feature/Milestone3dnInternalMvpManualAcceptanceExecutionTest.php',
            'backend/tests/Feature/Milestone3dmInternalMvpManualAcceptanceAuthorizationTest.php',
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

        $this->assertSame([], array_values(array_diff($changed, $allowed)), '3DN may change only approved docs/tests and the optional 3DM lifecycle guard repair.');

        foreach ($this->blockedPaths() as $path) {
            $this->assertNotContains($path, $changed, "{$path} must not be edited in 3DN.");
        }
    }

    public function test_document_records_purpose_relationships_environment_and_branch_hash(): void
    {
        $document = $this->executionDocument();

        foreach ([
            'The purpose of 3DN is to perform the first controlled internal MVP manual acceptance execution',
            'Milestone 3DM authorized this controlled internal MVP manual acceptance execution lane.',
            'Milestone 3DL defined the Internal MVP Manual Acceptance Scope Gate.',
            'Milestone 3DK closed the MVP frontend usability implementation lane.',
            'Environment safety status: PASS WITH MINOR NOTES.',
            'Branch tested:',
            'milestone-3dn-internal-mvp-manual-acceptance-execution',
            'Base hash tested:',
            '68b5b66805d014ef0809da893376a8ba4b7a88ce',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_local_users_required_personas_and_evidence_notes(): void
    {
        $document = $this->executionDocument();

        foreach ([
            'backend/database/seeders/LocalRoleTestUsersSeeder.php',
            'President/Admin: president@lcbeenachi.test',
            'Secretary: secretary@lcbeenachi.test',
            'Treasurer: treasurer@lcbeenachi.test',
            'Normal Member: local.member@lcbeenachi.test',
            'Evidence sources used:',
            'backend .env safety inspection.',
            'LocalRoleTestUsersSeeder inspection.',
            'app/page.tsx static inspection.',
            'npm run build validation.',
            'Backend PHPUnit validation.',
            '127.0.0.1:3000 was not listening during execution.',
            '127.0.0.1:8000 was not listening during execution.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_includes_required_report_table_and_all_checklist_rows(): void
    {
        $document = $this->executionDocument();

        $this->assertStringContainsString('| ID | Area | Role | Test Step | Expected Result | Actual Result | Status | Severity | Notes |', $document);

        foreach (range(1, 55) as $number) {
            $this->assertStringContainsString('DN-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT), $document);
        }
    }

    public function test_document_uses_valid_result_and_severity_categories_and_records_counts(): void
    {
        $document = $this->executionDocument();

        foreach ([
            'PASS',
            'PASS WITH MINOR NOTES',
            'BLOCKED',
            'FAIL',
            'NOT IN MVP SCOPE',
            'Critical defects: 0',
            'Major defects: 0',
            'Minor defects: 0',
            'Notes: 18',
            'Total checks: 55',
            'PASS: 37',
            'PASS WITH MINOR NOTES: 8',
            'BLOCKED: 10',
            'FAIL: 0',
            'NOT IN MVP SCOPE: 0',
            'Go/No-Go recommendation: BLOCKED PENDING SETUP.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_defects_blocked_items_not_in_scope_stop_conditions_and_decision(): void
    {
        $document = $this->executionDocument();

        foreach ([
            'No Critical, Major, or Minor defects were found.',
            'Blocked items:',
            'DN-006 President/Admin login is testable.',
            'DN-047 Refresh after create/update/payment works where safely testable.',
            'No checklist row was marked NOT IN MVP SCOPE.',
            'No safety stop condition was encountered.',
            'Local server unavailability caused selected checks to be marked BLOCKED instead of forcing execution.',
            '3DN execution decision: partial manual acceptance executed with static/build/test evidence and BLOCKED local browser/server-dependent checks.',
            'No fixes were implemented.',
            'No runtime files were edited.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_unsupported_exclusions_receipt_pdf_profile_documents_risks_and_next_milestone(): void
    {
        $document = $this->executionDocument();

        foreach ([
            'Static inspection verified no profile document upload, download, preview, delete, OCR, profile document PDF generation',
            'Receipt PDF is allowed only because it belongs to the approved finance/receipt lane.',
            'Receipt PDF copy remains tied to payment receipts, not Profile Documents.',
            'Profile document upload/download/PDF/OCR remains out of scope.',
            'Profile document storage, public URLs, signed URLs, preview, file download, and DELETE behavior remain out of scope.',
            '3DN is not deployment readiness.',
            '3DN is not SaaS readiness.',
            '3DN does not certify production use.',
            'Manual acceptance was limited to local/test data.',
            'UI role hiding is not security; backend authorization must remain authoritative.',
            'Existing app/page.tsx is large and future changes must remain narrow.',
            'Any BLOCKED items must be resolved before broader real-club use.',
            'Milestone 3DO - Internal MVP Manual Acceptance Closure Review',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_current_frontend_contains_known_internal_mvp_surfaces_read_only(): void
    {
        $frontend = $this->frontendSource();

        foreach ([
            'Internal MVP',
            'Quick Actions',
            'Active Members',
            'Current Dues',
            'Receipt',
            'Payment History',
            'Profile Documents',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $frontend);
        }
    }

    private function executionDocument(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }

    private function frontendSource(): string
    {
        $this->assertFileExists($this->frontendPath);

        return file_get_contents($this->frontendPath);
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
            'backend/tests/Feature/Milestone3dlInternalMvpManualAcceptanceScopeGateTest.php',
            'backend/tests/Feature/Milestone3dkMvpFrontendUsabilityValidationAndClosureTest.php',
            'backend/tests/Feature/Milestone3djMvpFrontendUsabilityNarrowImplementationTest.php',
            'backend/tests/Feature/Milestone3diMvpFrontendUsabilityImplementationAuthorizationTest.php',
        ];
    }
}
