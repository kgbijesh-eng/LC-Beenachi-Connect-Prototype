<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3ceMemberDataImportPreviewModuleClosureReviewTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3ce_member_data_import_preview_module_closure_review.md';
    private string $apiClosureDocPath = __DIR__ . '/../../docs/milestone_3bz_member_data_import_api_validation_and_closure.md';
    private string $frontendClosureDocPath = __DIR__ . '/../../docs/milestone_3cd_member_data_import_frontend_validation_and_closure.md';
    private string $frontendPath = __DIR__ . '/../../../app/page.tsx';

    public function test_3ce_document_exists_and_is_docs_tests_only_module_closure_review(): void
    {
        $document = $this->closureReviewDocument();

        $this->assertStringContainsString('Milestone 3CE - Member Data Import Preview Module Closure Review', $document);
        $this->assertStringContainsString('docs/tests-only module closure review', $document);
        $this->assertStringContainsString('It reviews and closes the complete Member Data Import Preview Module lane.', $document);
        $this->assertStringContainsString('It does not edit app/page.tsx.', $document);
        $this->assertStringContainsString('It does not implement frontend runtime changes.', $document);
        $this->assertStringContainsString('It does not change backend runtime files or API behavior.', $document);
        $this->assertStringContainsString('It does not authorize import save or commit behavior.', $document);
    }

    public function test_3ce_records_base_branch_allowed_files_and_next_milestone(): void
    {
        $document = $this->closureReviewDocument();

        foreach ([
            'Milestone 3CD - Member Data Import Frontend Validation and Closure',
            'milestone-3cd-member-data-import-frontend-validation-and-closure',
            'milestone-3ce-member-data-import-preview-module-closure-review',
            'backend/docs/milestone_3ce_member_data_import_preview_module_closure_review.md',
            'backend/tests/Feature/Milestone3ceMemberDataImportPreviewModuleClosureReviewTest.php',
            'Milestone 3CF - Member Data Import Commit Scope Gate',
            '3CE does not start or authorize import commit/save behavior.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3ce_reviews_backend_api_and_frontend_preview_lanes_together(): void
    {
        $document = $this->closureReviewDocument();

        foreach ([
            '3BV - Member Data Import / Excel Onboarding Validation and Closure',
            '3BW - Member Data Import API Scope Gate',
            '3BX - Member Data Import API Implementation Authorization',
            '3BY - Member Data Import API Narrow Implementation',
            '3BZ - Member Data Import API Validation and Closure',
            '3CA - Member Data Import Frontend Scope Gate',
            '3CB - Member Data Import Frontend Implementation Authorization',
            '3CC - Member Data Import Frontend Narrow Implementation',
            '3CD - Member Data Import Frontend Validation and Closure',
            'The closure review confirms that both backend preview API and frontend preview UI remain inside the approved preview-only boundary.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3ce_closes_existing_preview_endpoint_as_the_only_authorized_endpoint(): void
    {
        $document = $this->closureReviewDocument();
        $apiClosure = $this->apiClosureDocument();
        $frontend = $this->frontendSource();

        foreach ([
            'POST /api/v1/clubs/{club}/members/import-preview',
            'No other member import endpoint is authorized by 3CE.',
            'No generic import endpoint is authorized by 3CE.',
            'No upload endpoint is authorized by 3CE.',
            'No parser endpoint is authorized by 3CE.',
            'No import commit endpoint is authorized by 3CE.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }

        $this->assertStringContainsString('POST api/v1/clubs/{club}/members/import-preview', $apiClosure);
        $this->assertStringContainsString('API_BASE_URL + "/clubs/" + previewClubId + "/members/import-preview"', $frontend);
        $this->assertStringNotContainsString('/members/import-preview/commit', $frontend);
        $this->assertStringNotContainsString('/members/import/upload', $frontend);
        $this->assertStringNotContainsString('/members/import/save', $frontend);
    }

    public function test_3ce_preserves_officer_only_access_and_normal_member_exclusion(): void
    {
        $document = $this->closureReviewDocument();
        $frontend = $this->frontendSource();
        $component = $this->sourceBetween($frontend, 'function MemberImportPreview', 'function ReceiptPreview');
        $memberNavigationBlock = $this->sourceBetween($frontend, 'member: [', '];', strpos($frontend, 'member: ['));

        foreach ([
            'President/Admin through existing president or president-admin role flows',
            'Secretary',
            'Treasurer',
            'Normal Member must not access the member import preview API.',
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

    public function test_3ce_preserves_club_scoped_behavior_for_backend_and_frontend(): void
    {
        $document = $this->closureReviewDocument();
        $apiClosure = $this->apiClosureDocument();
        $frontendClosure = $this->frontendClosureDocument();

        foreach ([
            'The backend preview API is club-scoped.',
            'The frontend preview UI is club-scoped.',
            'The preview module uses the active authenticated club context.',
            'Cross-club preview behavior remains fail-closed and is not authorized by frontend assumptions.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }

        $this->assertStringContainsString('club-scoped authorization', $apiClosure);
        $this->assertStringContainsString('The UI is club-scoped through the active authenticated club context.', $frontendClosure);
    }

    public function test_3ce_preserves_preview_dry_run_only_and_non_mutating_behavior(): void
    {
        $document = $this->closureReviewDocument();

        foreach ([
            'The Member Data Import Preview Module is closed as preview/dry-run-only.',
            'The module does not create members.',
            'The module does not update members.',
            'The module does not delete members.',
            'The module does not import members.',
            'The module does not save imports.',
            'The module does not commit imports.',
            'The module does not silently overwrite existing member data.',
            'Any future import commit or save behavior requires a new scope gate.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3ce_preserves_manual_paste_rows_only_frontend_boundary(): void
    {
        $document = $this->closureReviewDocument();
        $frontend = $this->frontendSource();
        $component = $this->sourceBetween($frontend, 'function parseMemberImportPreviewRows', 'function ReceiptPreview');

        foreach ([
            'The frontend preview UI supports manual/paste spreadsheet-like rows only.',
            'The frontend may convert pasted rows to JSON for the existing preview API.',
            'The module does not authorize Excel upload, CSV upload, drag-and-drop upload, browser-side xlsx parser packages, browser-side CSV parser packages, upload storage, template download, or sample file generation.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }

        $this->assertStringContainsString('Paste spreadsheet-like rows', $component);
        $this->assertStringContainsString('textarea', $component);
        $this->assertStringContainsString('Run preview', $component);
        $this->assertStringContainsString('JSON.stringify({ rows })', $component);
        $this->assertStringNotContainsString('type="file"', $component);
    }

    public function test_3ce_preserves_row_errors_duplicate_warnings_and_existing_match_warnings(): void
    {
        $document = $this->closureReviewDocument();
        $frontend = $this->frontendSource();
        $component = $this->sourceBetween($frontend, 'function MemberImportPreview', 'function ReceiptPreview');

        foreach ([
            'Row-level validation display final closure claim',
            'Duplicate warning display final closure claim',
            'Existing-member match warning display final closure claim',
            'The preview module displays row-level validation errors returned by the backend.',
            'The preview module displays duplicate warnings returned by the backend.',
            'The preview module displays existing-member match warnings returned by the backend.',
            'The preview module must not silently overwrite existing member data.',
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

    public function test_3ce_keeps_excel_upload_parser_package_and_storage_blocked(): void
    {
        $document = $this->closureReviewDocument();
        $component = strtolower($this->sourceBetween($this->frontendSource(), 'function MemberImportPreview', 'function ReceiptPreview'));

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
            'template download',
            'sample file generation',
            'composer.json changes',
            'composer.lock changes',
            'package.json changes',
            'package-lock.json changes',
            'pnpm-lock.yaml changes',
            'yarn.lock changes',
        ] as $blockedText) {
            $this->assertStringContainsString($blockedText, $document);
        }

        $this->assertStringNotContainsString('type="file"', $component);
        $this->assertStringNotContainsString('filereader', $component);
        $this->assertStringNotContainsString('xlsx', $component);
        $this->assertStringNotContainsString('papaparse', $component);
        $this->assertStringNotContainsString('dropzone', $component);
    }

    public function test_3ce_keeps_import_save_commit_and_member_mutation_blocked(): void
    {
        $document = $this->closureReviewDocument();
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

    public function test_3ce_blocks_backend_and_frontend_runtime_changes_inside_3ce(): void
    {
        $document = $this->closureReviewDocument();

        foreach ([
            'backend/routes/api.php',
            'backend/app/Http/Controllers/Api/V1/Members/MemberDataImportPreviewController.php',
            'backend/app/Http/Requests/Members/MemberDataImportPreviewRequest.php',
            'backend/app/Http/Resources/Members/MemberDataImportPreviewResource.php',
            'backend/app/Services/Members/MemberDataImportPreviewService.php',
            'backend models',
            'backend middleware',
            'backend migrations',
            'backend seeders',
            'backend config',
            'API behavior',
            'API response contracts',
            'app/page.tsx',
            'frontend runtime files',
            'package.json',
            'package-lock.json',
        ] as $blockedText) {
            $this->assertStringContainsString($blockedText, $document);
        }
    }

    public function test_3ce_keeps_finance_adjacent_deployment_saas_and_client_specific_behavior_blocked(): void
    {
        $document = $this->closureReviewDocument();

        foreach ([
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
            'deployment implementation',
            'SaaS implementation',
            'client-specific behavior',
            'hardcoded Lions Club Beenachi rules',
            'hardcoded Rotary Central Bathery rules',
            'hardcoded future pilot club rules',
        ] as $blockedText) {
            $this->assertStringContainsString($blockedText, $document);
        }
    }

    public function test_3ce_does_not_add_disallowed_import_commit_upload_or_runtime_artifacts(): void
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

    public function test_3ce_final_module_closure_statement_is_recorded(): void
    {
        $document = $this->closureReviewDocument();

        foreach ([
            'Milestone 3CE closes the Member Data Import Preview Module.',
            'club-scoped, officer-only backend preview API',
            'club-scoped, officer-only frontend preview UI',
            'The module is strictly preview/dry-run only',
            'Any future import commit/save lane must begin with a new scope gate.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    private function closureReviewDocument(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }

    private function apiClosureDocument(): string
    {
        $this->assertFileExists($this->apiClosureDocPath);

        return file_get_contents($this->apiClosureDocPath);
    }

    private function frontendClosureDocument(): string
    {
        $this->assertFileExists($this->frontendClosureDocPath);

        return file_get_contents($this->frontendClosureDocPath);
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
