<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3ckMemberDataImportCommitFrontendScopeGateTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3ck_member_data_import_commit_frontend_scope_gate.md';
    private string $backendClosureDocPath = __DIR__ . '/../../docs/milestone_3cj_member_data_import_commit_backend_validation_and_closure.md';

    public function test_3ck_document_exists_and_is_docs_tests_only_frontend_scope_gate(): void
    {
        $document = $this->scopeDocument();

        $this->assertStringContainsString('Milestone 3CK - Member Data Import Commit Frontend Scope Gate', $document);
        $this->assertStringContainsString('docs/tests-only frontend scope gate', $document);
        $this->assertStringContainsString('It does not implement frontend commit UI.', $document);
        $this->assertStringContainsString('It does not edit app/page.tsx.', $document);
        $this->assertStringContainsString('It does not change backend runtime or API behavior.', $document);
        $this->assertStringContainsString('It does not alter the preview UI.', $document);
        $this->assertStringContainsString('It does not alter the preview or commit endpoints.', $document);
    }

    public function test_3ck_records_base_branch_allowed_files_and_next_milestone(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Milestone 3CJ - Member Data Import Commit Backend Validation and Closure',
            'milestone-3cj-member-data-import-commit-backend-validation-and-closure',
            'milestone-3ck-member-data-import-commit-frontend-scope-gate',
            'backend/docs/milestone_3ck_member_data_import_commit_frontend_scope_gate.md',
            'backend/tests/Feature/Milestone3ckMemberDataImportCommitFrontendScopeGateTest.php',
            'Milestone 3CL - Member Data Import Commit Frontend Implementation Authorization',
            '3CL may only authorize a later frontend implementation.',
            '3CK does not start frontend work.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3ck_references_only_existing_preview_and_commit_endpoints(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'POST /api/v1/clubs/{club}/members/import-preview',
            'POST /api/v1/clubs/{club}/members/import-commit',
            '3CK does not change either endpoint.',
            '3CK does not add new API routes.',
            '3CK does not change API response contracts.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }

        $routes = collect(Route::getRoutes());

        $this->assertCount(1, $routes->filter(fn ($route) => $route->uri() === 'api/v1/clubs/{club}/members/import-preview'));
        $this->assertCount(1, $routes->filter(fn ($route) => $route->uri() === 'api/v1/clubs/{club}/members/import-commit'));
    }

    public function test_3ck_defines_future_club_scoped_officer_only_frontend_boundary(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'The future lane must remain club-scoped.',
            'The future lane must remain officer-only.',
            'President/Admin through existing president or president-admin role flows',
            'Secretary',
            'Treasurer',
            'Normal Member must not see commit behavior.',
            'Normal Member must not trigger commit API calls.',
            'Normal Member must not load commit data into hidden frontend state.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3ck_requires_preview_first_workflow_and_separates_preview_from_commit(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Future frontend commit behavior must preserve a preview-first workflow.',
            'Preview must be run before commit is offered.',
            'Preview and Commit must be clearly separated.',
            'The frontend must not treat a stale preview as final authorization to commit.',
            'The backend remains the source of truth for commit-time validation, duplicate checks, existing-member matching, and row outcomes.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3ck_requires_safe_commit_warning_and_wording(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Future frontend commit behavior must warn that commit creates eligible new members.',
            'Future frontend commit behavior must state that existing members are not overwritten.',
            'Future frontend commit behavior must state that existing members are not updated.',
            'Future frontend commit behavior must state that members are not deleted.',
            'Future frontend commit behavior must avoid wording such as Import all or Overwrite.',
            'Future frontend commit behavior may use safe wording such as Commit eligible new members only if later authorized.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3ck_requires_row_level_pre_commit_display(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'row-level eligibility',
            'row-level validation errors',
            'duplicate warnings',
            'existing-member match warnings',
            'Rows with validation errors must not be presented as eligible.',
            'Rows with duplicate warnings must not be silently committed.',
            'Rows with existing-member match warnings must not overwrite or update existing member data.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3ck_requires_explicit_confirmation_before_commit_call(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Future frontend commit behavior must require explicit confirmation before calling the commit endpoint.',
            'The confirmation must make clear that the operation creates eligible new member records.',
            'The confirmation must not imply that all rows will be imported.',
            'The confirmation must not imply that existing records will be overwritten.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3ck_requires_post_commit_row_level_results_and_summary_counts(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'row-level created results',
            'row-level skipped results',
            'row-level failed results',
            'summary counts',
            'Summary counts must not hide row-level failures.',
            'Created rows must be distinct from skipped or failed rows.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3ck_keeps_manual_rows_only_and_blocks_upload_parser_storage_queue(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'manual/paste JSON or spreadsheet-like rows',
            'No Excel upload is authorized.',
            'No CSV upload is authorized.',
            'No file upload is authorized.',
            'No upload storage is authorized.',
            'No xlsx parser package is authorized.',
            'No CSV parser package is authorized.',
            'No queue or job workflow is authorized.',
        ] as $blockedText) {
            $this->assertStringContainsString($blockedText, $document);
        }
    }

    public function test_3ck_keeps_finance_adjacent_deployment_saas_and_client_specific_behavior_blocked(): void
    {
        $document = $this->scopeDocument();

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

    public function test_3ck_explicitly_does_not_authorize_runtime_or_commit_ui_changes(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'frontend commit UI implementation',
            'app/page.tsx changes',
            'frontend runtime changes',
            'backend runtime changes',
            'API behavior changes',
            'preview endpoint changes',
            'commit endpoint changes',
            'import button implementation',
            'commit button implementation',
            'save button implementation',
            'apply button implementation',
            'existing-member update',
            'member deletion',
            'silent overwrite',
        ] as $blockedText) {
            $this->assertStringContainsString($blockedText, $document);
        }
    }

    public function test_3ck_blocks_runtime_file_boundaries_and_older_milestone_test_edits(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'app/page.tsx',
            'frontend runtime files',
            'backend/routes/api.php',
            'backend controllers',
            'backend requests',
            'backend resources',
            'backend services',
            'backend models',
            'backend middleware',
            'backend migrations',
            'backend seeders',
            'backend config',
            'API behavior',
            '3CI implementation files',
            'older milestone tests',
            'package files',
        ] as $blockedText) {
            $this->assertStringContainsString($blockedText, $document);
        }
    }

    public function test_3ck_does_not_add_frontend_commit_endpoint_call_or_commit_wording_to_app_page(): void
    {
        $appPagePath = base_path('../app/page.tsx');

        $this->assertFileExists($appPagePath);

        $appPage = file_get_contents($appPagePath);

        foreach ([
            '/members/import-commit',
            'import-commit',
            'Commit eligible new members',
            'Import all',
            'Overwrite',
        ] as $blockedText) {
            $this->assertStringNotContainsString($blockedText, $appPage);
        }
    }

    public function test_3ck_relationship_to_3cj_and_next_milestone_are_recorded(): void
    {
        $document = $this->scopeDocument();
        $backendClosureDocument = $this->backendClosureDocument();

        foreach ([
            'Milestone 3CJ closed the backend commit lane.',
            'The backend commit lane remains club-scoped, officer-only, create-only, non-financial, and manual/paste-row only.',
            '3CK does not reopen 3CJ.',
            '3CK does not alter 3CI runtime implementation.',
            '3CK only records the frontend scope gate required before any future frontend commit implementation authorization.',
            'Milestone 3CL - Member Data Import Commit Frontend Implementation Authorization',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }

        $this->assertStringContainsString('Milestone 3CJ closes the Member Data Import Commit backend lane.', $backendClosureDocument);
        $this->assertStringContainsString('Milestone 3CK - Member Data Import Commit Frontend Scope Gate', $backendClosureDocument);
    }

    private function scopeDocument(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }

    private function backendClosureDocument(): string
    {
        $this->assertFileExists($this->backendClosureDocPath);

        return file_get_contents($this->backendClosureDocPath);
    }
}
