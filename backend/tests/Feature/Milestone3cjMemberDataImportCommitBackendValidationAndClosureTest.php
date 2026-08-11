<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3cjMemberDataImportCommitBackendValidationAndClosureTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3cj_member_data_import_commit_backend_validation_and_closure.md';
    private string $implementationDocPath = __DIR__ . '/../../docs/milestone_3ci_member_data_import_commit_backend_narrow_implementation.md';

    public function test_3cj_document_exists_and_is_docs_tests_only_closure(): void
    {
        $document = $this->closureDocument();

        $this->assertStringContainsString('Milestone 3CJ - Member Data Import Commit Backend Validation and Closure', $document);
        $this->assertStringContainsString('docs/tests-only validation and closure milestone', $document);
        $this->assertStringContainsString('It validates and closes the 3CI backend member import commit implementation.', $document);
        $this->assertStringContainsString('It does not change production code.', $document);
        $this->assertStringContainsString('It does not change backend runtime or API behavior.', $document);
        $this->assertStringContainsString('It does not change frontend runtime behavior.', $document);
        $this->assertStringContainsString('It does not change older milestone tests.', $document);
    }

    public function test_3cj_records_base_branch_allowed_files_and_next_milestone(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'Milestone 3CI - Member Data Import Commit Backend Narrow Implementation',
            'milestone-3ci-member-data-import-commit-backend-narrow-implementation',
            'milestone-3cj-member-data-import-commit-backend-validation-and-closure',
            'backend/docs/milestone_3cj_member_data_import_commit_backend_validation_and_closure.md',
            'backend/tests/Feature/Milestone3cjMemberDataImportCommitBackendValidationAndClosureTest.php',
            'Milestone 3CK - Member Data Import Commit Frontend Scope Gate',
            '3CK is only a future scope gate.',
            '3CJ must not start frontend work.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3cj_summarizes_3cf_3cg_3ch_and_3ci_lane_history(): void
    {
        $document = $this->closureDocument();

        foreach ([
            '3CF scope gate summary',
            'Milestone 3CF opened only a docs/tests-only scope gate',
            '3CG authorization summary',
            'Milestone 3CG converted the 3CF scope gate into a future implementation authorization boundary.',
            '3CH scope and authorization closure summary',
            'Milestone 3CH closed the commit backend scope and authorization lane.',
            '3CI backend implementation summary',
            'Milestone 3CI implemented a narrow backend commit path for manual/paste JSON-row member data.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3cj_commit_endpoint_exists_and_is_post_only(): void
    {
        $routes = collect(Route::getRoutes());
        $commitRoutes = $routes->filter(fn ($route) => $route->uri() === 'api/v1/clubs/{club}/members/import-commit');

        $this->assertCount(1, $commitRoutes);

        $methods = $commitRoutes->first()->methods();

        $this->assertContains('POST', $methods);
        $this->assertNotContains('GET', $methods);
        $this->assertNotContains('PUT', $methods);
        $this->assertNotContains('PATCH', $methods);
        $this->assertNotContains('DELETE', $methods);
    }

    public function test_3cj_preview_endpoint_remains_separate(): void
    {
        $routes = collect(Route::getRoutes());
        $previewRoutes = $routes->filter(fn ($route) => $route->uri() === 'api/v1/clubs/{club}/members/import-preview');
        $commitRoutes = $routes->filter(fn ($route) => $route->uri() === 'api/v1/clubs/{club}/members/import-commit');

        $this->assertCount(1, $previewRoutes);
        $this->assertCount(1, $commitRoutes);
        $this->assertNotSame($previewRoutes->first()->getActionName(), $commitRoutes->first()->getActionName());

        $document = $this->closureDocument();
        $this->assertStringContainsString('The existing preview endpoint remains separate and unchanged:', $document);
        $this->assertStringContainsString('POST /api/v1/clubs/{club}/members/import-preview', $document);
    }

    public function test_3cj_commit_controller_request_resource_and_service_files_exist(): void
    {
        foreach ([
            app_path('Http/Controllers/Api/V1/Members/MemberDataImportCommitController.php'),
            app_path('Http/Requests/Members/MemberDataImportCommitRequest.php'),
            app_path('Http/Resources/Members/MemberDataImportCommitResource.php'),
            app_path('Services/Members/MemberDataImportCommitService.php'),
        ] as $requiredFile) {
            $this->assertFileExists($requiredFile);
        }
    }

    public function test_3cj_no_upload_storage_parser_package_queue_or_job_artifacts_exist(): void
    {
        foreach ([
            app_path('Http/Controllers/Api/V1/Members/MemberDataImportUploadController.php'),
            app_path('Services/Members/MemberImportUploadService.php'),
            app_path('Services/Members/MemberExcelParser.php'),
            app_path('Imports/MemberImport.php'),
            app_path('Jobs/ProcessMemberImport.php'),
            app_path('Jobs/CommitMemberImport.php'),
            storage_path('app/member-imports'),
            base_path('../app/member-import/upload'),
            base_path('../components/member-import-upload'),
            base_path('../lib/member-import-upload.ts'),
        ] as $blockedPath) {
            $this->assertFileDoesNotExist($blockedPath);
        }
    }

    public function test_3cj_no_excel_csv_upload_or_parser_routes_exist(): void
    {
        $uris = collect(Route::getRoutes())->map(fn ($route) => $route->uri())->all();

        foreach ($uris as $uri) {
            $this->assertStringNotContainsString('import-upload', $uri);
            $this->assertStringNotContainsString('upload/import', $uri);
            $this->assertStringNotContainsString('xlsx', strtolower($uri));
            $this->assertStringNotContainsString('csv', strtolower($uri));
            $this->assertStringNotContainsString('parser', strtolower($uri));
        }
    }

    public function test_3cj_documents_officer_only_normal_member_club_scope_and_cross_club_fail_closed(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'The commit backend path is officer-only.',
            'President/Admin through existing president or president-admin role flows',
            'Secretary',
            'Treasurer',
            'Normal Member is blocked from the commit endpoint.',
            'Normal Member must not commit member imports.',
            'The commit endpoint operates inside the target club boundary.',
            'Cross-club commit access fails closed.',
            'Unauthenticated users are blocked.',
            'Unauthorized roles are blocked.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3cj_documents_server_side_validation_duplicate_and_existing_match_rechecks(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'Server-side validation runs at commit time.',
            'Frontend-only validation is not trusted.',
            'Required member fields are normalized and validated server-side.',
            'Unsafe or forbidden fields are rejected server-side.',
            'Duplicate rows are re-checked at commit time.',
            'Duplicate rows do not create duplicate members.',
            'Duplicate decisions are not blindly inherited from stale preview results.',
            'Existing-member matches are re-checked at commit time.',
            'Rows matching existing members do not overwrite existing member data.',
            'Existing-member match decisions are not blindly inherited from stale preview results.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3cj_documents_row_level_reporting_and_summary_counts(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'The commit response includes row-level status, errors, and warnings.',
            'The commit response includes created member ID where applicable.',
            'The commit response includes summary counts.',
            'Rows are reported independently.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3cj_documents_create_only_no_update_no_delete_and_no_silent_overwrite(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'Valid eligible rows may create new members.',
            'Invalid rows do not create members.',
            'Duplicate rows do not create duplicate members.',
            'Existing-member match rows do not overwrite existing data.',
            'Existing members are not updated by the 3CI commit path.',
            'Members are not deleted by the 3CI commit path.',
            'The 3CI commit path does not silently overwrite existing member data.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3cj_documents_manual_json_rows_and_blocks_excel_csv_parser_storage_queue(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'Manual/paste JSON rows are the only supported input path.',
            'Excel upload remains blocked.',
            'CSV upload remains blocked.',
            'xlsx upload remains blocked.',
            'Drag-and-drop file upload remains blocked.',
            'xlsx parser/package integration remains blocked.',
            'CSV parser/package integration remains blocked.',
            'File upload/storage flow remains blocked.',
            'Queue/job processing remains blocked.',
            'No background import worker, retry workflow, asynchronous batch import, or queued import processing was added.',
        ] as $blockedText) {
            $this->assertStringContainsString($blockedText, $document);
        }
    }

    public function test_3cj_documents_finance_frontend_deployment_saas_and_client_specific_blocks(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'finance ledger',
            'dues',
            'payments',
            'receipts',
            'payment history',
            'balances',
            'Frontend runtime remains unchanged.',
            'No frontend commit/import/save/apply button was added.',
            'No app/page.tsx change is authorized in 3CJ.',
            '3CI did not add deployment implementation.',
            '3CI did not add SaaS implementation.',
            '3CI did not add client-specific behavior.',
            '3CI did not hardcode Lions Club Beenachi rules.',
            '3CI did not hardcode Rotary Central Bathery rules.',
        ] as $blockedText) {
            $this->assertStringContainsString($blockedText, $document);
        }
    }

    public function test_3cj_documents_narrow_older_milestone_test_compatibility_note(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'Older milestone test adjustments in 3CI were only narrow compatibility updates for the now-approved 3CI commit route and commit controller.',
            'POST api/v1/clubs/{club}/members/import-commit',
            'MemberDataImportCommitController.php',
            'They must continue to block unrelated import routes, upload routes, parser routes, finance behavior, frontend runtime import UI, deployment behavior, SaaS behavior, and client-specific behavior.',
            '3CJ does not edit older milestone tests.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3cj_final_closure_statement_and_next_milestone_are_recorded(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'Milestone 3CJ closes the Member Data Import Commit backend lane.',
            'backend, club-scoped, officer-only, create-only commit endpoint for manual/paste JSON rows',
            'separate from the preview endpoint',
            'server-side commit-time validation and fresh duplicate/existing-member checks',
            'Any future frontend commit UI, Excel/CSV upload, parser package, upload storage, queue/job processing, existing-member update behavior, finance behavior, deployment/SaaS behavior, or client-specific behavior requires a new scope gate.',
            'Milestone 3CK - Member Data Import Commit Frontend Scope Gate',
            '3CJ must not start frontend work.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3cj_references_3ci_implementation_document_claims(): void
    {
        $implementationDocument = $this->implementationDocument();

        foreach ([
            'POST /api/v1/clubs/{club}/members/import-commit',
            'POST /api/v1/clubs/{club}/members/import-preview',
            'Create-Only New Members',
            'No Updates/Deletes',
            'No Finance Mutation',
            'No Excel/CSV file upload or storage backend changes.',
            'No asynchronous job or queue processing.',
            'No frontend runtime changes or additions.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $implementationDocument);
        }
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
}
