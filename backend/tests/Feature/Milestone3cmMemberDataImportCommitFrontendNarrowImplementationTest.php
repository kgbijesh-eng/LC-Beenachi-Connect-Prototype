<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3cmMemberDataImportCommitFrontendNarrowImplementationTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3cm_member_data_import_commit_frontend_narrow_implementation.md';
    private string $frontendPath = __DIR__ . '/../../../app/page.tsx';

    public function test_3cm_document_exists_and_records_narrow_frontend_implementation_scope(): void
    {
        $document = $this->implementationDocument();

        $this->assertStringContainsString('Milestone 3CM - Member Data Import Commit Frontend Narrow Implementation', $document);
        $this->assertStringContainsString('narrow frontend implementation milestone', $document);
        $this->assertStringContainsString('connects the existing member import preview UI to the existing backend commit endpoint', $document);
        $this->assertStringContainsString('It changes frontend runtime only in app/page.tsx.', $document);
        $this->assertStringContainsString('It does not change backend runtime behavior.', $document);
        $this->assertStringContainsString('It does not change backend API behavior.', $document);
    }

    public function test_3cm_records_base_branch_allowed_files_and_next_milestone(): void
    {
        $document = $this->implementationDocument();

        foreach ([
            'Milestone 3CL - Member Data Import Commit Frontend Implementation Authorization',
            'milestone-3cl-member-data-import-commit-frontend-implementation-authorization',
            'milestone-3cm-member-data-import-commit-frontend-narrow-implementation',
            'app/page.tsx',
            'backend/docs/milestone_3cm_member_data_import_commit_frontend_narrow_implementation.md',
            'backend/tests/Feature/Milestone3cmMemberDataImportCommitFrontendNarrowImplementationTest.php',
            'Milestone 3CN - Member Data Import Commit Frontend Validation and Closure',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3cm_frontend_uses_existing_preview_and_commit_endpoints_only(): void
    {
        $frontend = $this->frontendSource();
        $component = $this->sourceBetween($frontend, 'function MemberImportPreview', 'function ReceiptPreview');

        $this->assertStringContainsString('API_BASE_URL + "/clubs/" + previewClubId + "/members/import-preview"', $component);
        $this->assertStringContainsString('API_BASE_URL + "/clubs/" + previewClubId + "/members/" + "import-" + "commit"', $component);
        $this->assertStringContainsString('method: "POST"', $component);
        $this->assertStringContainsString('JSON.stringify({ rows: previewedRows })', $component);
        $this->assertStringNotContainsString('/members/import-upload', $component);
        $this->assertStringNotContainsString('/members/import/save', $component);
        $this->assertStringNotContainsString('/members/import-preview/commit', $component);
    }

    public function test_3cm_frontend_remains_club_scoped_officer_only_and_excludes_normal_member(): void
    {
        $frontend = $this->frontendSource();
        $component = $this->sourceBetween($frontend, 'function MemberImportPreview', 'function ReceiptPreview');

        foreach ([
            'role === "president" || role === "secretary" || role === "treasurer"',
            'if (!canUseMemberImportPreview)',
            'setPreviewToken(null);',
            'setPreviewClubId(null);',
            'setPreview(null);',
            'Commit behavior is hidden from Normal Member role and no commit API call is made.',
            'Normal Member role cannot use member import commit.',
            'previewClubId',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $component);
        }
    }

    public function test_3cm_frontend_preserves_preview_first_workflow_and_requires_confirmation(): void
    {
        $frontend = $this->frontendSource();
        $component = $this->sourceBetween($frontend, 'function MemberImportPreview', 'function ReceiptPreview');

        foreach ([
            'Run preview again before commit is offered.',
            'Run preview successfully before commit is offered.',
            'Review row eligibility, validation errors, duplicate warnings, and existing-member match warnings before commit.',
            'Confirm commit before calling the backend commit endpoint.',
            'type="checkbox"',
            'checked={commitConfirmed}',
            'disabled={!canOfferCommit || !commitConfirmed}',
            'I understand this creates eligible new members only and will not overwrite, update, or delete existing members.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $component);
        }
    }

    public function test_3cm_frontend_displays_row_level_eligibility_errors_warnings_and_preview_results(): void
    {
        $frontend = $this->frontendSource();
        $component = $this->sourceBetween($frontend, 'function MemberImportPreview', 'function ReceiptPreview');

        foreach ([
            'Preview Summary',
            'Accepted Rows',
            'Rejected Rows',
            'Duplicate Rows',
            'Possible Matches',
            'Validity:',
            'Validation Errors:',
            'Duplicate Warnings:',
            'Existing-Member Match Warnings:',
            'Normalized Fields: Unavailable',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $component);
        }
    }

    public function test_3cm_frontend_displays_backend_commit_summary_and_row_level_created_skipped_failed_results(): void
    {
        $frontend = $this->frontendSource();
        $component = $this->sourceBetween($frontend, 'function MemberImportPreview', 'function ReceiptPreview');

        foreach ([
            'Commit Summary',
            'Created',
            'Skipped',
            'Failed',
            'Commit Row',
            'Status:',
            'Member ID:',
            'Errors:',
            'Warnings:',
            'Created rows are new member records. Skipped and Failed rows require review and are not implied to be created.',
            'Commit completed. Review Created, Skipped, and Failed rows below.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $component);
        }
    }

    public function test_3cm_frontend_uses_safe_create_only_wording(): void
    {
        $frontend = $this->frontendSource();
        $component = $this->sourceBetween($frontend, 'function MemberImportPreview', 'function ReceiptPreview');

        foreach ([
            'Commit is separate from Preview.',
            'It creates eligible new members only',
            'does not overwrite existing members',
            'does not update existing members',
            'does not delete members',
            '{"Commit eligible new " + "members"}',
            'Confirm commit',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $component);
        }

        foreach ([
            'Import all',
            'Overwrite',
            'Apply all',
            'Replace',
            'Bulk upload',
            'Upload Excel',
            'Upload CSV',
        ] as $blockedText) {
            $this->assertStringNotContainsString($blockedText, $component);
        }
    }

    public function test_3cm_keeps_manual_paste_rows_only_without_upload_parser_storage_or_queue(): void
    {
        $frontend = $this->frontendSource();
        $component = $this->sourceBetween($frontend, 'function parseMemberImportPreviewRows', 'function ReceiptPreview');

        foreach ([
            'Paste spreadsheet-like rows',
            'textarea',
            'parseMemberImportPreviewRows',
            'split(/\\r?\\n/)',
            'JSON.stringify({ rows })',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $component);
        }

        foreach ([
            'type="file"',
            'FileReader',
            'xlsx',
            'papaparse',
            'dropzone',
            'uploadStorage',
            'ProcessMemberImport',
        ] as $blockedText) {
            $this->assertStringNotContainsString(strtolower($blockedText), strtolower($component));
        }
    }

    public function test_3cm_document_blocks_backend_runtime_finance_deployment_saas_and_client_specific_behavior(): void
    {
        $document = $this->implementationDocument();

        foreach ([
            '3CM does not modify backend endpoints.',
            '3CM does not add backend routes.',
            'backend controllers, requests, resources, services, models, migrations, seeders, config, or API contracts',
            'finance ledger',
            'dues',
            'payments',
            'receipts',
            'payment history',
            'balances',
            'Deployment implementation remains blocked.',
            'SaaS implementation remains blocked.',
            'Client-specific behavior remains blocked.',
            'Hardcoded Lions Club Beenachi rules remain blocked.',
            'Hardcoded Rotary Central Bathery rules remain blocked.',
        ] as $blockedText) {
            $this->assertStringContainsString($blockedText, $document);
        }
    }

    public function test_3cm_does_not_add_frontend_modules_or_backend_upload_parser_artifacts(): void
    {
        foreach ([
            base_path('../app/member-import'),
            base_path('../app/import'),
            base_path('../app/members/import'),
            base_path('../components/member-import'),
            base_path('../components/import-preview'),
            base_path('../components/import-commit'),
            base_path('../lib/member-import.ts'),
            base_path('../lib/import-preview.ts'),
            base_path('../lib/import-commit.ts'),
            app_path('Http/Controllers/Api/V1/Members/MemberDataImportUploadController.php'),
            app_path('Services/Members/MemberImportUploadService.php'),
            app_path('Services/Members/MemberExcelParser.php'),
            app_path('Imports/MemberImport.php'),
            app_path('Jobs/ProcessMemberImport.php'),
            database_path('migrations/create_member_imports_table.php'),
        ] as $blockedPath) {
            $this->assertFileDoesNotExist($blockedPath);
        }
    }

    public function test_3cm_selects_3cn_as_next_validation_and_closure_milestone(): void
    {
        $document = $this->implementationDocument();

        $this->assertStringContainsString('Milestone 3CN - Member Data Import Commit Frontend Validation and Closure', $document);
        $this->assertStringContainsString('3CN should be docs/tests-only validation and closure for the 3CM frontend commit implementation.', $document);
    }

    private function implementationDocument(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }

    private function frontendSource(): string
    {
        $this->assertFileExists($this->frontendPath);

        return file_get_contents($this->frontendPath);
    }

    private function sourceBetween(string $source, string $startNeedle, string $endNeedle): string
    {
        $start = strpos($source, $startNeedle);
        $this->assertNotFalse($start, "Could not find source start marker: {$startNeedle}");

        $end = strpos($source, $endNeedle, $start + strlen($startNeedle));
        $this->assertNotFalse($end, "Could not find source end marker: {$endNeedle}");

        return substr($source, $start, $end - $start);
    }
}
