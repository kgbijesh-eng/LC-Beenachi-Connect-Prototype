<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3dkMvpFrontendUsabilityValidationAndClosureTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3dk_mvp_frontend_usability_validation_and_closure.md';
    private string $frontendPath = __DIR__ . '/../../../app/page.tsx';

    public function test_3dk_document_exists_and_records_docs_tests_only_closure_scope(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'Milestone 3DK - MVP Frontend Usability Validation and Closure',
            'Milestone 3DK is a docs/tests-only validation and closure milestone.',
            '3DK validates and closes the 3DJ MVP Frontend Usability Narrow Implementation lane.',
            '3DK does not add new frontend runtime behavior.',
            '3DK does not edit app/page.tsx.',
            '3DK does not change backend runtime behavior.',
            '3DK must not start 3DL.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3dk_records_base_branch_allowed_files_and_next_milestone(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'milestone-3dj-mvp-frontend-usability-narrow-implementation',
            'f580cc1418e27d51d183d62e6d8dcf4978032bce',
            'milestone-3dk-mvp-frontend-usability-validation-closure',
            'backend/docs/milestone_3dk_mvp_frontend_usability_validation_and_closure.md',
            'backend/tests/Feature/Milestone3dkMvpFrontendUsabilityValidationAndClosureTest.php',
            'Milestone 3DL - Internal MVP Manual Acceptance Scope Gate',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3dk_current_file_changes_are_limited_to_approved_docs_and_test_files(): void
    {
        $allowed = [
            'backend/docs/milestone_3dk_mvp_frontend_usability_validation_and_closure.md',
            'backend/tests/Feature/Milestone3dkMvpFrontendUsabilityValidationAndClosureTest.php',
            'backend/tests/Feature/Milestone3djMvpFrontendUsabilityNarrowImplementationTest.php',
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

        $this->assertSame([], array_values(array_diff($changed, $allowed)), '3DK may change only the approved closure doc and test files.');

        foreach ($this->blockedPaths() as $path) {
            $this->assertNotContains($path, $changed, "{$path} must not be edited in 3DK.");
        }
    }

    public function test_document_records_relationships_frontend_location_and_backend_exclusion(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'Milestone 3DH recorded the MVP Frontend Usability Readiness Scope Gate',
            'Milestone 3DI converted the 3DH readiness scope gate into a precise implementation authorization boundary.',
            'Milestone 3DJ implemented the narrow frontend usability pass in app/page.tsx.',
            '3DK validates 3DJ and closes the MVP frontend usability implementation lane.',
            'Frontend implementation location validated by 3DK:',
            'app/page.tsx',
            '3DK does not edit app/page.tsx.',
            'Backend runtime is not edited in 3DK.',
            'Routes/controllers/requests/resources/services/models/migrations are not edited in 3DK.',
            'Older milestone tests are not edited in 3DK.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_existing_apis_reused_and_no_fake_behavior(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'Existing member finance summary APIs remain the source for Current Dues, Last Payment, Receipt Status, and pending items.',
            'Existing payment recording APIs remain the only payment mutation path surfaced by the frontend.',
            'Existing receipt view and Receipt PDF APIs remain the only receipt/PDF behavior surfaced.',
            'Existing payment history GET APIs remain the only Payment History behavior surfaced.',
            'Existing Profile Documents metadata APIs remain metadata-only.',
            'No new backend APIs were added.',
            'No fake unavailable backend behavior was implemented.',
            'No new frontend/backend gap required fake implementation in 3DJ.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_usability_validation_sections(): void
    {
        $document = $this->closureDocument();

        foreach ([
            '3DK validates that 3DJ added or preserved dashboard/internal MVP usability improvements.',
            'Internal MVP Quick Actions',
            'Members.',
            'Finance.',
            'Receipts.',
            'Profile Documents.',
            '3DK validates the MVP Manual Check guidance:',
            '3DK validates that 3DJ improved member flow clarity.',
            '3DK validates that 3DJ improved finance/current dues clarity.',
            'Member Finance Summary surfaces Current Dues, Payment Status, Last Payment, and Receipt Status together through existing read APIs.',
            '3DK validates that 3DJ improved Receipt and Receipt PDF discoverability where already supported.',
            '3DK validates that 3DJ improved Payment History guidance where existing support allows.',
            '3DK validates that Profile Documents remains metadata-only.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_role_state_manual_checklist_guard_repair_risks_and_closure(): void
    {
        $document = $this->closureDocument();

        foreach ([
            '3DK validates that 3DJ preserved role-aware UI behavior.',
            'Backend authorization remains authoritative.',
            'Backend authorization must still be trusted over UI button hiding.',
            '3DK validates that 3DJ preserved existing loading states',
            '3DK validates that 3DJ preserved empty states',
            '3DK validates that 3DJ preserved safe error states',
            '3DK validates that validation messages from backend API payloads remain displayed',
            '3DK validates that success messages after payment recording and Profile Documents metadata save remain present.',
            '3DK validates that refresh behavior after payment recording and Profile Documents metadata create/update remains present.',
            '3DK validates or preserves this manual checklist where existing capabilities permit:',
            '1. President/Admin can log in and see a usable dashboard.',
            '10. Receipt/receipt PDF access is discoverable where already supported.',
            '16. No unsupported upload/download/OCR/profile-document PDF behavior is introduced.',
            '3DJ included narrow lifecycle repairs to older guard tests:',
            'Those repairs removed permanent current-working-tree assumptions',
            '3DK is not deployment readiness.',
            '3DK is not SaaS readiness.',
            '3DK is not final production hardening.',
            'Manual acceptance testing is still required before real club use.',
            'app/page.tsx remains large; future work should avoid broad refactoring unless separately authorized.',
            'Milestone 3DK closes the MVP Frontend Usability implementation lane delivered by 3DJ.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_profile_document_finance_import_deployment_and_saas_exclusions(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'Profile document upload/download/storage/OCR/PDF/DELETE remains out of scope.',
            'Profile document public URLs and signed URLs remain out of scope.',
            'No new finance backend mutation behavior was added.',
            'No member import behavior was added.',
            'No deployment, SaaS, billing, onboarding, support/admin tooling, or client-specific behavior was added.',
            'Receipt PDF remains allowed only because it belongs to the already-approved finance/receipt lane.',
            'Profile document upload/download/PDF/OCR remains out of scope.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_frontend_contains_3dj_mvp_usability_copy_read_only(): void
    {
        $frontend = $this->frontendSource();

        foreach ([
            'Internal MVP',
            'Quick Actions',
            'Members',
            'Finance',
            'Receipts',
            'Profile Documents',
            'Current Dues',
            'Receipt',
            'Payment History',
            'MVP Manual Check',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $frontend);
        }
    }

    public function test_frontend_preserves_profile_documents_metadata_only_copy_read_only(): void
    {
        $frontend = $this->frontendSource();

        foreach ([
            'Profile Documents',
            'Document metadata only. File upload and document storage are not enabled in this version.',
            'No profile document records added yet.',
            'Add Document Metadata',
            'Edit Metadata',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $frontend);
        }
    }

    public function test_frontend_does_not_introduce_blocked_profile_document_ui_copy_or_fields(): void
    {
        $frontend = $this->frontendSource();

        foreach ([
            'Upload Document',
            'Attach File',
            'Download Profile Document',
            'Preview File',
            'Delete Document',
            'Generate Profile PDF',
            'OCR',
            'Storage Link',
            'Public Link',
            'Signed Link',
            'file_path',
            'storage_disk',
            'uploaded_file',
            'binary_data',
            'base64_data',
            'ocr_text',
            'pdf_path',
        ] as $blockedText) {
            $this->assertStringNotContainsString($blockedText, $frontend);
        }
    }

    private function closureDocument(): string
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
            'backend/tests/Feature/Milestone3diMvpFrontendUsabilityImplementationAuthorizationTest.php',
            'backend/tests/Feature/Milestone3dhMvpFrontendUsabilityReadinessScopeGateTest.php',
            'backend/tests/Feature/Milestone3dgMemberProfileDocumentsFrontendValidationAndClosureTest.php',
            'backend/tests/Feature/Milestone3dfMemberProfileDocumentsFrontendNarrowImplementationTest.php',
        ];
    }
}
