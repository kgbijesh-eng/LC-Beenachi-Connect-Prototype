<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3clMemberDataImportCommitFrontendImplementationAuthorizationTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3cl_member_data_import_commit_frontend_implementation_authorization.md';
    private string $scopeGateDocPath = __DIR__ . '/../../docs/milestone_3ck_member_data_import_commit_frontend_scope_gate.md';

    public function test_3cl_document_exists_and_is_docs_tests_only_frontend_authorization(): void
    {
        $document = $this->authorizationDocument();

        $this->assertStringContainsString('Milestone 3CL - Member Data Import Commit Frontend Implementation Authorization', $document);
        $this->assertStringContainsString('docs/tests-only frontend implementation authorization milestone', $document);
        $this->assertStringContainsString('It authorizes only a future narrow frontend commit implementation in Milestone 3CM.', $document);
        $this->assertStringContainsString('It does not implement frontend commit UI.', $document);
        $this->assertStringContainsString('It does not edit app/page.tsx.', $document);
        $this->assertStringContainsString('It does not change frontend runtime behavior.', $document);
        $this->assertStringContainsString('It does not change backend runtime behavior.', $document);
        $this->assertStringContainsString('It does not change backend API behavior.', $document);
    }

    public function test_3cl_records_base_branch_allowed_files_and_next_milestone(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'Milestone 3CK - Member Data Import Commit Frontend Scope Gate',
            'milestone-3ck-member-data-import-commit-frontend-scope-gate',
            'milestone-3cl-member-data-import-commit-frontend-implementation-authorization',
            'backend/docs/milestone_3cl_member_data_import_commit_frontend_implementation_authorization.md',
            'backend/tests/Feature/Milestone3clMemberDataImportCommitFrontendImplementationAuthorizationTest.php',
            'Milestone 3CM - Member Data Import Commit Frontend Narrow Implementation',
            '3CM may implement a narrow frontend commit UI only if explicitly approved later.',
            '3CL must not start implementation.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3cl_authorizes_only_future_narrow_frontend_commit_lane(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            '3CL authorizes a later milestone to implement only a narrow frontend commit UI.',
            'The future implementation must stay inside the 3CK scope gate.',
            'The future implementation must use existing backend endpoints only.',
            'The future implementation must not be treated as a bulk Excel import system.',
            'The future implementation must not bypass backend server-side validation.',
            'The future implementation must show backend row-level results instead of assuming success.',
            'The future implementation must prefer fail-closed messaging over uncertain commit behavior.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3cl_records_relationship_to_preview_frontend_backend_commit_and_3ck(): void
    {
        $document = $this->authorizationDocument();
        $scopeGate = $this->scopeGateDocument();

        foreach ([
            'The member import preview frontend lane is closed.',
            'The existing preview UI must remain a preview/dry-run workflow.',
            '3CL does not alter the existing preview UI.',
            'The backend commit lane is closed through Milestone 3CJ.',
            'The backend commit path remains club-scoped, officer-only, create-only, non-financial, and manual/paste-row only.',
            '3CL does not alter the backend commit implementation.',
            'Milestone 3CK opened the frontend commit scope gate.',
            '3CL converts that boundary into future implementation authorization for 3CM only.',
            '3CL does not broaden 3CK.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }

        $this->assertStringContainsString('Milestone 3CK - Member Data Import Commit Frontend Scope Gate', $scopeGate);
        $this->assertStringContainsString('Milestone 3CL - Member Data Import Commit Frontend Implementation Authorization', $scopeGate);
    }

    public function test_3cl_references_existing_preview_and_commit_endpoints_without_modifying_them(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'POST /api/v1/clubs/{club}/members/import-preview',
            'POST /api/v1/clubs/{club}/members/import-commit',
            '3CL must not modify either endpoint.',
            '3CL must not add new API routes.',
            '3CL must not change API response contracts.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }

        $routes = collect(Route::getRoutes());

        $this->assertCount(1, $routes->filter(fn ($route) => $route->uri() === 'api/v1/clubs/{club}/members/import-preview'));
        $this->assertCount(1, $routes->filter(fn ($route) => $route->uri() === 'api/v1/clubs/{club}/members/import-commit'));
    }

    public function test_3cl_requires_future_club_scoped_officer_only_frontend_and_normal_member_exclusion(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'Future frontend commit behavior must be club-scoped.',
            'Future frontend commit behavior must use the active authorized club context.',
            'Future frontend commit behavior must not show or submit commit rows for another club.',
            'Cross-club behavior must fail closed through backend authorization.',
            'Future frontend commit behavior must be officer-only.',
            'President/Admin through existing president or president-admin role flows',
            'Secretary',
            'Treasurer',
            'Backend authorization remains the source of truth.',
            'Normal Member must not see commit behavior.',
            'Normal Member must not see commit controls.',
            'Normal Member must not trigger commit API calls.',
            'Normal Member must not load commit data into hidden frontend state.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3cl_requires_preview_first_workflow_and_explicit_confirmation(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'Future frontend commit behavior must preserve the existing preview-first workflow.',
            'Preview must be run before commit is offered.',
            'Preview and Commit must be clearly separated.',
            'Future frontend commit behavior must not treat a stale preview as final authorization to commit.',
            'Future frontend commit behavior must not bypass backend commit-time validation.',
            'Future frontend commit behavior must require explicit user confirmation before calling the commit endpoint.',
            'The confirmation must make clear that commit creates eligible new member records only.',
            'The confirmation must not imply that all rows will be imported.',
            'The confirmation must not imply that existing records will be overwritten.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3cl_requires_row_level_eligibility_errors_warnings_and_results(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'Future frontend commit behavior must display row-level eligibility before commit.',
            'Rows with validation errors must not be presented as eligible.',
            'Rows with duplicate warnings must not be silently committed.',
            'Rows with existing-member match warnings must not overwrite or update existing member data.',
            'row-level validation errors',
            'duplicate warnings',
            'existing-member match warnings',
            'backend-provided ineligible row reasons',
            'Future frontend commit behavior must not invent row eligibility.',
            'Future frontend commit behavior must not invent duplicate decisions.',
            'Future frontend commit behavior must not invent existing-member match decisions.',
            'row-level created results',
            'row-level skipped results',
            'row-level failed results',
            'backend summary counts',
            'Summary counts must not hide row-level failures.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3cl_requires_create_only_messaging_and_blocks_update_delete_overwrite(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'Future frontend commit behavior must clearly state that commit creates eligible new members only.',
            'Future frontend commit behavior may use safe wording such as Commit eligible new members.',
            'Future frontend commit behavior must avoid dangerous wording such as Import all, Overwrite, Apply all, or Replace.',
            'Existing-member update remains blocked.',
            'Future frontend commit behavior must clearly state that existing members will not be overwritten.',
            'Future frontend commit behavior must clearly state that existing members will not be updated.',
            'Existing-member matches must remain skipped or blocked unless a later scope gate explicitly authorizes matched-row update behavior.',
            'Member deletion remains blocked.',
            'Future frontend commit behavior must clearly state that members will not be deleted.',
            'Silent overwrite remains blocked.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3cl_keeps_manual_paste_rows_only_and_blocks_upload_parser_storage_queue(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'Manual/paste JSON or spreadsheet-like rows remain the only currently authorized input path.',
            'Future frontend commit behavior must not add a bulk Excel import system.',
            'Future frontend commit behavior must not add package-based spreadsheet parsing.',
            'Excel upload remains blocked.',
            'CSV upload remains blocked.',
            'xlsx upload remains blocked.',
            'Drag-and-drop file upload remains blocked.',
            'xlsx parser/package integration remains blocked.',
            'CSV parser/package integration remains blocked.',
            'File upload/storage flow remains blocked.',
            'Queue/job processing remains blocked.',
            'No background import worker, retry workflow, asynchronous batch import, or queued import processing is authorized.',
        ] as $blockedText) {
            $this->assertStringContainsString($blockedText, $document);
        }
    }

    public function test_3cl_keeps_finance_adjacent_deployment_saas_and_client_specific_behavior_blocked(): void
    {
        $document = $this->authorizationDocument();

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

    public function test_3cl_explicitly_does_not_authorize_runtime_or_import_button_changes(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'frontend commit UI implementation in 3CL',
            'app/page.tsx changes',
            'frontend runtime file changes',
            'backend route changes',
            'backend controller changes',
            'backend request changes',
            'backend resource changes',
            'backend service changes',
            'backend model changes',
            'backend migration changes',
            'backend config changes',
            'preview endpoint changes',
            'commit endpoint changes',
            'import button implementation in 3CL',
            'commit button implementation in 3CL',
            'save button implementation in 3CL',
            'existing-member update',
            'member deletion',
            'silent overwrite',
        ] as $blockedText) {
            $this->assertStringContainsString($blockedText, $document);
        }
    }

    public function test_3cl_records_blocked_file_boundary(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'app/page.tsx',
            'frontend runtime files',
            'backend/routes/api.php',
            'backend/app/Http/Controllers/Api/V1/Members/MemberDataImportCommitController.php',
            'backend/app/Http/Requests/Members/MemberDataImportCommitRequest.php',
            'backend/app/Http/Resources/Members/MemberDataImportCommitResource.php',
            'backend/app/Services/Members/MemberDataImportCommitService.php',
            'backend/app/Http/Controllers/Api/V1/Members/MemberDataImportPreviewController.php',
            'backend/app/Http/Requests/Members/MemberDataImportPreviewRequest.php',
            'backend/app/Http/Resources/Members/MemberDataImportPreviewResource.php',
            'backend/app/Services/Members/MemberDataImportPreviewService.php',
            'backend existing member controllers/requests/resources/services',
            'backend models',
            'backend middleware',
            'backend migrations',
            'backend seeders',
            'backend config',
            'composer.json',
            'composer.lock',
            'package.json',
            'package-lock.json',
            'older milestone tests',
        ] as $blockedText) {
            $this->assertStringContainsString($blockedText, $document);
        }
    }

    public function test_3cl_does_not_add_commit_ui_to_app_page(): void
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
            'Apply all',
            'Replace',
        ] as $blockedText) {
            $this->assertStringNotContainsString($blockedText, $appPage);
        }
    }

    public function test_3cl_selects_3cm_as_next_narrow_frontend_implementation_milestone(): void
    {
        $document = $this->authorizationDocument();

        $this->assertStringContainsString('Milestone 3CM - Member Data Import Commit Frontend Narrow Implementation', $document);
        $this->assertStringContainsString('3CM may implement a narrow frontend commit UI only if explicitly approved later.', $document);
        $this->assertStringContainsString('3CL must not start implementation.', $document);
    }

    private function authorizationDocument(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }

    private function scopeGateDocument(): string
    {
        $this->assertFileExists($this->scopeGateDocPath);

        return file_get_contents($this->scopeGateDocPath);
    }
}
