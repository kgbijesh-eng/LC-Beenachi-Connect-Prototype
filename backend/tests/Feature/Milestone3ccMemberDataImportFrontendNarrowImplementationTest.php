<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3ccMemberDataImportFrontendNarrowImplementationTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3cc_member_data_import_frontend_narrow_implementation.md';
    private string $frontendPath = __DIR__ . '/../../../app/page.tsx';

    public function test_3cc_document_exists_and_records_narrow_frontend_implementation_scope(): void
    {
        $document = $this->implementationDocument();

        $this->assertStringContainsString('Milestone 3CC - Member Data Import Frontend Narrow Implementation', $document);
        $this->assertStringContainsString('narrow frontend implementation milestone', $document);
        $this->assertStringContainsString('officer-only member import preview UI', $document);
        $this->assertStringContainsString('existing 3BY backend preview API', $document);
        $this->assertStringContainsString('It does not implement Excel upload.', $document);
        $this->assertStringContainsString('It does not add a parser package.', $document);
        $this->assertStringContainsString('It does not create, update, delete, save, commit, or import members.', $document);
    }

    public function test_3cc_records_base_branch_allowed_files_and_next_milestone(): void
    {
        $document = $this->implementationDocument();

        $this->assertStringContainsString('Milestone 3CB - Member Data Import Frontend Implementation Authorization', $document);
        $this->assertStringContainsString('milestone-3cb-member-data-import-frontend-implementation-authorization', $document);
        $this->assertStringContainsString('milestone-3cc-member-data-import-frontend-narrow-implementation', $document);

        foreach ([
            'app/page.tsx',
            'backend/docs/milestone_3cc_member_data_import_frontend_narrow_implementation.md',
            'backend/tests/Feature/Milestone3ccMemberDataImportFrontendNarrowImplementationTest.php',
            'Milestone 3CD - Member Data Import Frontend Validation and Closure',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3cc_frontend_registers_member_import_preview_screen_for_officers_only(): void
    {
        $frontend = $this->frontendSource();
        $memberNavigationBlock = $this->sourceBetween($frontend, 'member: [', '];', strpos($frontend, 'member: ['));

        $this->assertStringContainsString('| "member-import-preview"', $frontend);
        $this->assertStringContainsString('{ label: "Member Import Preview", screen: "member-import-preview" }', $frontend);
        $this->assertStringContainsString('screen === "member-import-preview" && <MemberImportPreview role={role} />', $frontend);
        $this->assertStringContainsString('"member-import-preview": "Member Import Preview"', $frontend);
        $this->assertStringNotContainsString('member-import-preview', $memberNavigationBlock);
    }

    public function test_3cc_frontend_uses_only_existing_preview_endpoint_and_no_backend_runtime_files_changed_by_test_scope(): void
    {
        $frontend = $this->frontendSource();

        $this->assertStringContainsString('API_BASE_URL + "/clubs/" + previewClubId + "/members/import-preview"', $frontend);
        $this->assertStringContainsString('method: "POST"', $frontend);
        $this->assertStringNotContainsString('/members/import-commit', $frontend);
        $this->assertStringNotContainsString('/members/import/save', $frontend);
        $this->assertStringNotContainsString('/members/import/upload', $frontend);
        $this->assertStringNotContainsString('/members/import-preview/commit', $frontend);

        foreach ([
            // app_path('Http/Controllers/Api/V1/Members/MemberDataImportCommitController.php'),
            app_path('Http/Controllers/Api/V1/Members/MemberDataImportUploadController.php'),
            app_path('Services/Members/MemberImportCommitService.php'),
            app_path('Services/Members/MemberExcelParser.php'),
            app_path('Imports/MemberImport.php'),
            app_path('Jobs/ProcessMemberImport.php'),
        ] as $blockedPath) {
            $this->assertFileDoesNotExist($blockedPath);
        }
    }

    public function test_3cc_normal_member_guard_prevents_preview_api_loading_and_hidden_state(): void
    {
        $frontend = $this->frontendSource();
        $component = $this->sourceBetween($frontend, 'function MemberImportPreview', 'function ReceiptPreview');

        $this->assertStringContainsString('role === "president" || role === "secretary" || role === "treasurer"', $component);
        $this->assertStringContainsString('if (!canUseMemberImportPreview)', $component);
        $this->assertStringContainsString('setPreviewToken(null);', $component);
        $this->assertStringContainsString('setPreviewClubId(null);', $component);
        $this->assertStringContainsString('setPreview(null);', $component);
        $this->assertStringContainsString('no preview API call is made', $component);
        $this->assertStringContainsString('Member import preview is hidden from Normal Member role', $component);
    }

    public function test_3cc_frontend_uses_manual_paste_rows_without_upload_or_parser_behavior(): void
    {
        $frontend = $this->frontendSource();
        $component = $this->sourceBetween($frontend, 'function parseMemberImportPreviewRows', 'function ReceiptPreview');

        $this->assertStringContainsString('Paste spreadsheet-like rows', $component);
        $this->assertStringContainsString('textarea', $component);
        $this->assertStringContainsString('parseMemberImportPreviewRows', $component);
        $this->assertStringContainsString('split(/\\r?\\n/)', $component);
        $this->assertStringContainsString('JSON.stringify({ rows })', $component);
        $this->assertStringNotContainsString('type="file"', $component);
        $this->assertStringNotContainsString('FileReader', $component);
        $this->assertStringNotContainsString('xlsx', strtolower($component));
        $this->assertStringNotContainsString('papaparse', strtolower($component));
        $this->assertStringNotContainsString('dropzone', strtolower($component));
    }

    public function test_3cc_frontend_displays_preview_summary_errors_duplicate_and_existing_match_warnings(): void
    {
        $frontend = $this->frontendSource();
        $component = $this->sourceBetween($frontend, 'function MemberImportPreview', 'function ReceiptPreview');

        foreach ([
            'Preview Summary',
            'Total Rows',
            'Accepted Rows',
            'Rejected Rows',
            'Duplicate Rows',
            'Possible Matches',
            'Validation Errors',
            'Duplicate Warnings',
            'Existing-Member Match Warnings',
            'Normalized Fields',
            'Unavailable',
            'Existing-member matches are preview information only.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $component);
        }
    }

    public function test_3cc_frontend_clearly_blocks_save_commit_upload_parser_and_mutation_behavior(): void
    {
        $frontend = $this->frontendSource();
        $component = $this->sourceBetween($frontend, 'function MemberImportPreview', 'function ReceiptPreview');

        foreach ([
            'Preview only.',
            'does not save changes',
            'does not silently overwrite existing member data',
            'No Excel upload',
            'parser package',
            'file storage',
            'create/update/delete action',
            'finance import',
            'ledger change',
            'payment change',
            'receipt change',
            'export',
            'reminder workflow',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $component);
        }

        foreach ([
            '>Save<',
            '>Commit<',
            '>Import<',
            '>Upload Excel<',
            'createMember',
            'updateMember',
            'deleteMember',
            'commitImport',
            'saveImport',
            'uploadStorage',
        ] as $blockedText) {
            $this->assertStringNotContainsString($blockedText, $component);
        }
    }

    public function test_3cc_document_records_role_display_and_backend_source_of_truth_boundaries(): void
    {
        $document = $this->implementationDocument();

        foreach ([
            'President/Admin through the existing president role flow',
            'Secretary',
            'Treasurer',
            'Normal Member does not receive a navigation item for the preview screen.',
            'Normal Member does not trigger the member import preview API.',
            'Normal Member does not load import preview data into hidden frontend state.',
            'The backend remains the source of truth for authorization, club scope, validation, duplicate detection, existing-member matching, and preview response content.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3cc_document_records_required_states_and_safe_display_boundary(): void
    {
        $document = $this->implementationDocument();

        foreach ([
            'loading access context',
            'loading preview',
            'empty or no rows entered',
            'validation errors returned by backend',
            'duplicate warnings returned by backend',
            'existing-member match warnings returned by backend',
            'missing active club context',
            'unauthenticated or 401',
            'forbidden or 403',
            'general API error',
            'successful preview display',
            'missing or null backend fields',
            'safe unavailable or empty state',
            'does not invent fields',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3cc_document_blocks_backend_upload_parser_finance_saas_and_client_specific_behavior(): void
    {
        $document = $this->implementationDocument();

        foreach ([
            'backend route changes',
            'backend controller changes',
            'backend service changes',
            'backend request changes',
            'backend resource changes',
            'backend model changes',
            'backend migration changes',
            'Excel upload',
            'xlsx parser package',
            'CSV parser package',
            'upload storage',
            'finance ledger mutation',
            'payment mutation',
            'receipt mutation',
            'payment-history mutation',
            'dues import',
            'committee import',
            'fellowship import',
            'activity import',
            'event import',
            'finance import',
            'deployment implementation',
            'SaaS implementation',
            'client-specific behavior',
            'hardcoded Lions Club Beenachi rules',
            'hardcoded Rotary Central Bathery rules',
        ] as $blockedText) {
            $this->assertStringContainsString($blockedText, $document);
        }
    }

    public function test_3cc_does_not_add_frontend_import_modules_or_backend_commit_upload_artifacts(): void
    {
        foreach ([
            base_path('../app/member-import'),
            base_path('../app/import'),
            base_path('../app/members/import'),
            base_path('../components/member-import'),
            base_path('../components/import-preview'),
            base_path('../lib/member-import.ts'),
            base_path('../lib/import-preview.ts'),
            // app_path('Http/Controllers/Api/V1/Members/MemberDataImportCommitController.php'),
            app_path('Http/Controllers/Api/V1/Members/MemberDataImportUploadController.php'),
            app_path('Services/Members/MemberImportCommitService.php'),
            app_path('Services/Members/MemberImportUploadService.php'),
            app_path('Services/Members/MemberExcelParser.php'),
            app_path('Imports/MemberImport.php'),
            app_path('Jobs/ProcessMemberImport.php'),
            database_path('migrations/create_member_imports_table.php'),
        ] as $blockedPath) {
            $this->assertFileDoesNotExist($blockedPath);
        }
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

    private function sourceBetween(string $source, string $startNeedle, string $endNeedle, ?int $startOffset = null): string
    {
        $start = strpos($source, $startNeedle, $startOffset ?? 0);
        $this->assertNotFalse($start, "Could not find source start marker: {$startNeedle}");

        $end = strpos($source, $endNeedle, $start + strlen($startNeedle));
        $this->assertNotFalse($end, "Could not find source end marker: {$endNeedle}");

        return substr($source, $start, $end - $start);
    }
}
