<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3cpMemberDataImportModuleClosureReviewTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3cp_member_data_import_module_closure_review.md';
    private string $frontendPath = __DIR__ . '/../../../app/page.tsx';

    public function test_3cp_document_exists_and_is_docs_tests_only_final_module_closure_review(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'Milestone 3CP - Member Data Import Module Closure Review',
            'docs/tests-only final module closure review milestone',
            'It reviews and closes the complete Member Data Import module across preview, commit, backend API, and frontend lanes.',
            'It does not change production code.',
            'It does not edit app/page.tsx.',
            'It does not change frontend runtime behavior.',
            'It does not change backend runtime files or API behavior.',
            'It does not change old milestone tests.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3cp_records_base_branch_allowed_files_and_next_milestone(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'Milestone 3CO - Member Data Import Commit Flow Closure Review',
            'milestone-3co-member-data-import-commit-flow-closure-review',
            'milestone-3cp-member-data-import-module-closure-review',
            'backend/docs/milestone_3cp_member_data_import_module_closure_review.md',
            'backend/tests/Feature/Milestone3cpMemberDataImportModuleClosureReviewTest.php',
            'Milestone 3CQ - Post Member Data Import Module Next Scope Decision',
            '3CQ should be a docs/tests-only decision milestone unless explicitly approved otherwise.',
            '3CP must not start 3CQ.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3cp_reviews_full_3bv_through_3co_member_import_chain(): void
    {
        $document = $this->closureDocument();

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
            '3CE - Member Data Import Preview Module Closure Review',
            '3CF - Member Data Import Commit Scope Gate',
            '3CG - Member Data Import Commit Implementation Authorization',
            '3CH - Member Data Import Commit Backend Scope and Authorization Closure',
            '3CI - Member Data Import Commit Backend Narrow Implementation',
            '3CJ - Member Data Import Commit Backend Validation and Closure',
            '3CK - Member Data Import Commit Frontend Scope Gate',
            '3CL - Member Data Import Commit Frontend Implementation Authorization',
            '3CM - Member Data Import Commit Frontend Narrow Implementation',
            '3CN - Member Data Import Commit Frontend Validation and Closure',
            '3CO - Member Data Import Commit Flow Closure Review',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3cp_records_existing_preview_and_commit_endpoints_and_they_remain_registered(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'POST /api/v1/clubs/{club}/members/import-preview',
            'POST /api/v1/clubs/{club}/members/import-commit',
            'Preview and commit remain separate.',
            'The preview endpoint remains:',
            'The commit endpoint remains:',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }

        $routes = collect(Route::getRoutes());

        $this->assertCount(1, $routes->filter(fn ($route) => $route->uri() === 'api/v1/clubs/{club}/members/import-preview'));
        $this->assertCount(1, $routes->filter(fn ($route) => $route->uri() === 'api/v1/clubs/{club}/members/import-commit'));
    }

    public function test_3cp_records_preview_api_frontend_and_module_closure(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'The preview API lane is closed.',
            'The preview frontend lane is closed.',
            'The preview module closure is closed.',
            'Preview remains dry-run behavior.',
            'Preview returns row-level validation errors.',
            'Preview returns duplicate warnings.',
            'Preview returns existing-member match warnings as preview-only information.',
            'Preview does not save, create, update, delete, or import member records.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3cp_records_commit_backend_frontend_and_flow_closure(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'The commit backend lane is closed.',
            'The commit frontend lane is closed.',
            'The commit flow closure is closed.',
            'Commit remains separate from preview.',
            'Commit is available only after successful preview.',
            'Commit requires explicit confirmation before the frontend calls the backend commit endpoint.',
            'Commit creates eligible new members only.',
            'Commit does not update existing members.',
            'Commit does not delete members.',
            'Commit does not silently overwrite existing member data.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3cp_frontend_still_contains_manual_preview_and_confirmed_commit_flow(): void
    {
        $component = $this->sourceBetween($this->frontendSource(), 'function MemberImportPreview', 'function ReceiptPreview');

        foreach ([
            'Paste spreadsheet-like rows',
            'textarea',
            'Run preview',
            'JSON.stringify({ rows })',
            'Run preview successfully before commit is offered.',
            'Confirm commit before calling the backend commit endpoint.',
            'checked={commitConfirmed}',
            'disabled={!canOfferCommit || !commitConfirmed}',
            '{"Commit eligible new " + "members"}',
            'Commit Summary',
            'Created',
            'Skipped',
            'Failed',
            'Commit Row',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $component);
        }

        $this->assertStringNotContainsString('type="file"', $component);
    }

    public function test_3cp_documents_manual_paste_rows_only_and_upload_blocks(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'The module supports manual/paste-row based preview.',
            'The module supports manual/paste-row based commit of eligible new members.',
            'Manual/paste JSON or spreadsheet-like rows remain the only input path.',
            'The frontend uses textarea input for member import rows.',
            'The backend accepts JSON row payloads.',
            'Excel upload remains blocked.',
            'CSV upload remains blocked.',
            'File input, drag-and-drop upload, upload storage, and file persistence remain blocked.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3cp_documents_officer_only_normal_member_exclusion_and_club_scope(): void
    {
        $document = $this->closureDocument();
        $component = $this->sourceBetween($this->frontendSource(), 'function MemberImportPreview', 'function ReceiptPreview');
        $memberNavigationBlock = $this->sourceBetween($this->frontendSource(), 'member: [', '];', strpos($this->frontendSource(), 'member: ['));

        foreach ([
            'The module remains officer-only.',
            'President/Admin through existing president or president-admin role flows',
            'Secretary',
            'Treasurer',
            'Normal Member remains excluded.',
            'Normal Member must not see the member import preview or commit UI.',
            'Normal Member must not trigger preview or commit API calls.',
            'Normal Member must not load import preview or commit data into hidden frontend state.',
            'The module remains club-scoped.',
            'Cross-club access remains fail-closed.',
            'Unauthenticated users are blocked.',
            'Unauthorized roles are blocked.',
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

    public function test_3cp_documents_preview_first_backend_authoritative_validation_and_fresh_rechecks(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'Preview-first workflow is preserved.',
            'Preview must run successfully before commit is offered.',
            'Changing pasted rows clears preview and commit state.',
            'The frontend does not treat stale preview as final authorization to commit.',
            'The backend duplicate re-check at commit time is authoritative.',
            'The backend existing-member match re-check at commit time is authoritative.',
            'Backend validation is authoritative.',
            'Frontend-only validation is not trusted.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3cp_documents_duplicate_existing_member_create_only_and_no_overwrite_rules(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'Duplicate detection remains active.',
            'Existing-member match detection remains active.',
            'Duplicate rows are not created.',
            'Existing-member match rows do not overwrite existing data.',
            'Invalid rows are not created.',
            'Valid eligible rows may create new members.',
            'Existing members are not updated.',
            'Members are not deleted.',
            'Silent overwrite is blocked.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3cp_documents_summary_row_errors_warnings_and_commit_results_display(): void
    {
        $document = $this->closureDocument();
        $component = $this->sourceBetween($this->frontendSource(), 'function MemberImportPreview', 'function ReceiptPreview');

        foreach ([
            'Backend summary counts are displayed.',
            'Row-level validation errors are displayed.',
            'Row-level warnings are displayed.',
            'Row-level Created, Skipped, and Failed results are displayed.',
            'Summary counts do not hide row-level failures.',
            'The frontend wording states that preview does not save changes and commit creates eligible new members only.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }

        foreach ([
            'Validation Errors',
            'Duplicate Warnings',
            'Existing-Member Match Warnings',
            'Total Rows',
            'Created',
            'Skipped',
            'Failed',
            'Commit Row',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $component);
        }
    }

    public function test_3cp_documents_no_backend_api_or_frontend_runtime_changes_in_3cp(): void
    {
        $document = $this->closureDocument();

        foreach ([
            '3CP does not modify backend APIs.',
            '3CP does not add backend routes.',
            '3CP does not change backend controllers, requests, resources, services, models, middleware, migrations, seeders, config, or API contracts.',
            '3CP does not change preview or commit implementation files.',
            '3CP does not edit app/page.tsx.',
            '3CP does not add frontend runtime files.',
            '3CP does not change package files.',
            '3CP does not add a new frontend screen.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3cp_documents_parser_package_storage_queue_and_background_processing_blocks(): void
    {
        $document = $this->closureDocument();

        foreach ([
            '.xlsx parser/package integration remains blocked.',
            'CSV parser/package integration remains blocked.',
            'composer.json changes remain blocked.',
            'composer.lock changes remain blocked.',
            'package.json changes remain blocked.',
            'package-lock.json changes remain blocked.',
            'Queue/job processing remains blocked.',
            'Background import processing remains blocked.',
            'No asynchronous import worker, retry workflow, or queued import processing was added.',
        ] as $blockedText) {
            $this->assertStringContainsString($blockedText, $document);
        }
    }

    public function test_3cp_documents_finance_adjacent_export_pdf_reminder_deployment_saas_and_client_specific_blocks(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'finance ledger',
            'dues',
            'payments',
            'receipts',
            'payment history',
            'balances',
            'committee data',
            'fellowship data',
            'activity data',
            'event data',
            'No dues import is authorized.',
            'No committee import is authorized.',
            'No fellowship import is authorized.',
            'No activity import is authorized.',
            'No event import is authorized.',
            'No finance import is authorized.',
            'Export behavior remains blocked.',
            'PDF behavior remains blocked.',
            'Reminder behavior remains blocked.',
            'Deployment implementation remains blocked.',
            'SaaS implementation remains blocked.',
            'Client-specific behavior remains blocked.',
            'Hardcoded Lions Club Beenachi rules remain blocked.',
            'Hardcoded Rotary Central Bathery rules remain blocked.',
            'Hardcoded future pilot club rules remain blocked.',
        ] as $blockedText) {
            $this->assertStringContainsString($blockedText, $document);
        }
    }

    public function test_3cp_final_module_closure_statement_is_recorded(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'Milestone 3CP closes the complete Member Data Import module.',
            'spans the 3BV through 3CO chain',
            'club-scoped, officer-only, manual/paste-row workflow',
            'using the existing preview endpoint and existing commit endpoint',
            'dry-run preview and explicitly confirmed commit of eligible new members only',
            'Preview and commit remain separate.',
            'Backend validation remains authoritative.',
            'Commit-time duplicate and existing-member match re-checks remain authoritative.',
            'Existing members are not updated or deleted.',
            'Invalid, duplicate, and existing-member match rows are not created.',
            'Backend summary counts, row-level validation errors, row-level warnings, and row-level Created, Skipped, and Failed results are displayed.',
            'Any future Excel/CSV upload, file storage, parser package, queued import processing, matched-row update behavior, member deletion, finance import, adjacent module import, export/PDF/reminder behavior, deployment/SaaS behavior, or client-specific behavior requires a new scope gate.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3cp_does_not_add_disallowed_import_upload_parser_queue_or_adjacent_artifacts(): void
    {
        foreach ([
            base_path('../app/member-import/upload'),
            base_path('../app/members/import/upload'),
            base_path('../components/member-import-upload'),
            base_path('../lib/member-import-upload.ts'),
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

    private function sourceBetween(string $source, string $startNeedle, string $endNeedle, ?int $startOffset = null): string
    {
        $start = strpos($source, $startNeedle, $startOffset ?? 0);
        $this->assertNotFalse($start, "Could not find source start marker: {$startNeedle}");

        $end = strpos($source, $endNeedle, $start + strlen($startNeedle));
        $this->assertNotFalse($end, "Could not find source end marker: {$endNeedle}");

        return substr($source, $start, $end - $start);
    }
}
