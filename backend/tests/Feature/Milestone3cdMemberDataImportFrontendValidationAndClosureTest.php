<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3cdMemberDataImportFrontendValidationAndClosureTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3cd_member_data_import_frontend_validation_and_closure.md';
    private string $frontendPath = __DIR__ . '/../../../app/page.tsx';
    private string $implementationDocPath = __DIR__ . '/../../docs/milestone_3cc_member_data_import_frontend_narrow_implementation.md';

    public function test_3cd_document_exists_and_is_docs_tests_only_closure(): void
    {
        $document = $this->closureDocument();

        $this->assertStringContainsString('Milestone 3CD - Member Data Import Frontend Validation and Closure', $document);
        $this->assertStringContainsString('docs/tests-only validation and closure milestone', $document);
        $this->assertStringContainsString('It does not edit app/page.tsx.', $document);
        $this->assertStringContainsString('It does not implement frontend runtime changes.', $document);
        $this->assertStringContainsString('It does not change backend runtime files or API behavior.', $document);
    }

    public function test_3cd_records_base_branch_allowed_files_and_next_milestone(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'Milestone 3CC - Member Data Import Frontend Narrow Implementation',
            'milestone-3cc-member-data-import-frontend-narrow-implementation',
            'milestone-3cd-member-data-import-frontend-validation-and-closure',
            'backend/docs/milestone_3cd_member_data_import_frontend_validation_and_closure.md',
            'backend/tests/Feature/Milestone3cdMemberDataImportFrontendValidationAndClosureTest.php',
            'Milestone 3CE - Member Data Import Preview Module Closure Review',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3cd_summarizes_3ca_3cb_and_3cc_lane_history(): void
    {
        $document = $this->closureDocument();

        foreach ([
            '3CA scope gate summary',
            'Milestone 3CA defined the frontend preview lane as a future scope gate only.',
            '3CB authorization summary',
            'Milestone 3CB authorized the future 3CC frontend implementation only inside the 3CA scope gate.',
            '3CC frontend implementation summary',
            'Milestone 3CC added a Member Import Preview screen to app/page.tsx.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3cd_validates_existing_3cc_frontend_screen_without_changing_runtime_scope(): void
    {
        $frontend = $this->frontendSource();
        $document = $this->closureDocument();
        $implementationDocument = $this->implementationDocument();

        $this->assertStringContainsString('function MemberImportPreview', $frontend);
        $this->assertStringContainsString('screen === "member-import-preview" && <MemberImportPreview role={role} />', $frontend);
        $this->assertStringContainsString('Milestone 3CC added a Member Import Preview screen to app/page.tsx.', $document);
        $this->assertStringContainsString('3CC adds a Member Import Preview screen to the existing prototype frontend.', $implementationDocument);
    }

    public function test_3cd_closes_officer_only_and_normal_member_exclusion_claims(): void
    {
        $document = $this->closureDocument();
        $frontend = $this->frontendSource();
        $component = $this->sourceBetween($frontend, 'function MemberImportPreview', 'function ReceiptPreview');
        $memberNavigationBlock = $this->sourceBetween($frontend, 'member: [', '];', strpos($frontend, 'member: ['));

        foreach ([
            'President/Admin through the existing president role flow',
            'Secretary',
            'Treasurer',
            'Normal Member must not see the member import preview UI.',
            'Normal Member must not trigger member import preview API calls.',
            'Normal Member must not load import preview data into hidden frontend state.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }

        $this->assertStringContainsString('role === "president" || role === "secretary" || role === "treasurer"', $component);
        $this->assertStringContainsString('if (!canUseMemberImportPreview)', $component);
        $this->assertStringContainsString('setPreviewToken(null);', $component);
        $this->assertStringContainsString('setPreviewClubId(null);', $component);
        $this->assertStringContainsString('setPreview(null);', $component);
        $this->assertStringNotContainsString('member-import-preview', $memberNavigationBlock);
    }

    public function test_3cd_closes_club_scoped_existing_endpoint_only_behavior(): void
    {
        $document = $this->closureDocument();
        $frontend = $this->frontendSource();

        foreach ([
            'POST /api/v1/clubs/{club}/members/import-preview',
            'The UI is club-scoped through the active authenticated club context.',
            'Preview rows are sent only to the active club endpoint.',
            'No new endpoint is authorized by 3CD.',
            'No backend API response contract change is authorized by 3CD.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }

        $this->assertStringContainsString('API_BASE_URL + "/clubs/" + previewClubId + "/members/import-preview"', $frontend);
        $this->assertStringNotContainsString('/members/import-preview/commit', $frontend);
        $this->assertStringNotContainsString('/members/import/upload', $frontend);
        $this->assertStringNotContainsString('/members/import/save', $frontend);
    }

    public function test_3cd_closes_preview_dry_run_only_and_manual_paste_input_behavior(): void
    {
        $document = $this->closureDocument();
        $frontend = $this->frontendSource();
        $component = $this->sourceBetween($frontend, 'function parseMemberImportPreviewRows', 'function ReceiptPreview');

        foreach ([
            'The member import frontend preview lane is closed as preview/dry-run-only.',
            'Preview does not save changes.',
            'Preview does not create members.',
            'Preview does not update members.',
            'Preview does not delete members.',
            'Preview does not commit imports.',
            'The closed 3CC frontend supports manual/paste spreadsheet-like rows only.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }

        $this->assertStringContainsString('Paste spreadsheet-like rows', $component);
        $this->assertStringContainsString('textarea', $component);
        $this->assertStringContainsString('Run preview', $component);
        $this->assertStringContainsString('JSON.stringify({ rows })', $component);
        $this->assertStringNotContainsString('type="file"', $component);
    }

    public function test_3cd_closes_row_error_duplicate_and_existing_match_warning_display(): void
    {
        $document = $this->closureDocument();
        $frontend = $this->frontendSource();
        $component = $this->sourceBetween($frontend, 'function MemberImportPreview', 'function ReceiptPreview');

        foreach ([
            'Row-level validation display closure claim',
            'Duplicate warning display closure claim',
            'Existing-member match warning display closure claim',
            'The UI displays row-level validation errors returned by the backend.',
            'The UI displays duplicate warnings returned by the backend.',
            'The UI displays existing-member match warnings returned by the backend.',
            'The frontend must not silently overwrite existing member data.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }

        foreach ([
            'Validation Errors',
            'Duplicate Warnings',
            'Existing-Member Match Warnings',
            'Existing-member matches are preview information only.',
            'Unavailable',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $component);
        }
    }

    public function test_3cd_keeps_excel_upload_parser_package_and_storage_blocked(): void
    {
        $document = $this->closureDocument();
        $frontend = strtolower($this->sourceBetween($this->frontendSource(), 'function MemberImportPreview', 'function ReceiptPreview'));

        foreach ([
            'Excel upload',
            'xlsx upload',
            'CSV upload',
            'drag-and-drop file upload',
            'browser-side xlsx parser',
            'browser-side CSV parser',
            'parser package',
            'upload storage',
            'file persistence',
            'package.json changes',
            'package-lock.json changes',
            'composer.json changes',
            'composer.lock changes',
        ] as $blockedText) {
            $this->assertStringContainsString($blockedText, $document);
        }

        $this->assertStringNotContainsString('type="file"', $frontend);
        $this->assertStringNotContainsString('filereader', $frontend);
        $this->assertStringNotContainsString('xlsx', $frontend);
        $this->assertStringNotContainsString('papaparse', $frontend);
        $this->assertStringNotContainsString('dropzone', $frontend);
    }

    public function test_3cd_keeps_import_save_commit_and_member_mutation_blocked(): void
    {
        $document = $this->closureDocument();
        $component = $this->sourceBetween($this->frontendSource(), 'function MemberImportPreview', 'function ReceiptPreview');

        foreach ([
            'import save button',
            'import commit button',
            'import/save/commit behavior',
            'member import commit endpoint',
            'member create behavior',
            'member update behavior',
            'member delete behavior',
            'silent overwrite behavior',
            'upload/import job behavior',
            'queue/job behavior',
        ] as $blockedText) {
            $this->assertStringContainsString($blockedText, $document);
        }

        foreach ([
            '>Save<',
            '>Commit<',
            '>Import<',
            'createMember',
            'updateMember',
            'deleteMember',
            'commitImport',
            'saveImport',
        ] as $blockedText) {
            $this->assertStringNotContainsString($blockedText, $component);
        }
    }

    public function test_3cd_keeps_backend_runtime_api_finance_and_adjacent_module_changes_blocked(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'backend route changes',
            'backend controller changes',
            'backend request changes',
            'backend resource changes',
            'backend service changes',
            'backend model changes',
            'backend middleware changes',
            'backend migration changes',
            'backend seeder changes',
            'backend config changes',
            'API behavior changes',
            'API response contract changes',
            'finance ledger mutation',
            'dues import',
            'payment mutation',
            'receipt mutation',
            'payment-history mutation',
            'balance mutation',
            'committee import',
            'fellowship import',
            'activity import',
            'event import',
            'export',
            'PDF generation',
            'reminder sending',
            'notification sending',
        ] as $blockedText) {
            $this->assertStringContainsString($blockedText, $document);
        }
    }

    public function test_3cd_keeps_deployment_saas_and_client_specific_behavior_blocked(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'deployment implementation',
            'SaaS implementation',
            'client-specific behavior',
            'hardcoded Lions Club Beenachi rules',
            'hardcoded Rotary Central Bathery rules',
            'hardcoded future pilot club rules',
            'Any future upload, parser, import commit, member mutation, backend API expansion, finance import, deployment, SaaS, or client-specific behavior requires a new scope gate.',
        ] as $blockedText) {
            $this->assertStringContainsString($blockedText, $document);
        }
    }

    public function test_3cd_does_not_add_disallowed_frontend_backend_or_import_artifacts(): void
    {
        foreach ([
            base_path('../app/member-import'),
            base_path('../app/import'),
            base_path('../app/members/import'),
            base_path('../components/member-import'),
            base_path('../components/import-preview'),
            base_path('../lib/member-import.ts'),
            base_path('../lib/import-preview.ts'),
            app_path('Http/Controllers/Api/V1/Members/MemberDataImportCommitController.php'),
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

    public function test_3cd_final_closure_statement_is_recorded(): void
    {
        $document = $this->closureDocument();

        $this->assertStringContainsString('Milestone 3CD closes the Member Data Import frontend preview lane.', $document);
        $this->assertStringContainsString('officer-only, club-scoped, preview/dry-run frontend', $document);
        $this->assertStringContainsString('existing 3BY preview API', $document);
        $this->assertStringContainsString('3CE should be a docs/tests-only closure review and next-roadmap decision milestone.', $document);
    }

    private function closureDocument(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }

    private function implementationDocument(): string
    {
        $this->assertFileExists($this->implementationDocPath);

        return file_get_contents($this->implementationDocPath);
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
