<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3bwMemberDataImportApiScopeGateTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3bw_member_data_import_api_scope_gate.md';

    public function test_3bw_document_exists_and_is_docs_tests_only_scope_gate(): void
    {
        $document = $this->scopeGateDocument();

        $this->assertStringContainsString('Milestone 3BW - Member Data Import API Scope Gate', $document);
        $this->assertStringContainsString('docs/tests-only scope gate', $document);
        $this->assertStringContainsString('It does not implement a member import API.', $document);
        $this->assertStringContainsString('It does not add routes.', $document);
        $this->assertStringContainsString('It does not add controllers.', $document);
        $this->assertStringContainsString('It does not add requests or resources.', $document);
        $this->assertStringContainsString('It does not change frontend runtime behavior.', $document);
        $this->assertStringContainsString('It does not change backend runtime behavior.', $document);
        $this->assertStringContainsString('It does not change API behavior.', $document);
    }

    public function test_3bw_records_base_branch_and_allowed_files(): void
    {
        $document = $this->scopeGateDocument();

        $this->assertStringContainsString('Milestone 3BV - Member Data Import / Excel Onboarding Validation and Closure', $document);
        $this->assertStringContainsString('milestone-3bv-member-data-import-excel-onboarding-validation-and-closure', $document);
        $this->assertStringContainsString('milestone-3bw-member-data-import-api-scope-gate', $document);
        $this->assertStringContainsString('backend/docs/milestone_3bw_member_data_import_api_scope_gate.md', $document);
        $this->assertStringContainsString('backend/tests/Feature/Milestone3bwMemberDataImportApiScopeGateTest.php', $document);
    }

    public function test_3bw_blocks_runtime_frontend_backend_api_import_file_queue_and_finance_changes(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'app/page.tsx',
            'frontend runtime files',
            'backend routes',
            'backend controllers',
            'backend requests',
            'backend resources',
            'backend models',
            'backend middleware',
            'backend migrations',
            'backend seeders',
            'backend config',
            'backend/app/Services/Members/MemberDataImportPreviewService.php',
            'composer.json',
            'composer.lock',
            'API behavior',
            'file upload files',
            'file storage files',
            'Excel package files',
            'Excel parser integration files',
            'queue files',
            'job files',
            'finance files',
            'payment files',
            'receipt files',
            'payment-history files',
            'ledger files',
            'committee import files',
            'fellowship import files',
            'activity import files',
            'event import files',
            'export files',
            'PDF files',
            'reminder files',
            'deployment files',
            'SaaS files',
            'client-specific hardcoding files',
            '3BW must not add routes.',
            '3BW must not add controllers.',
            '3BW must not add request classes.',
            '3BW must not add resource classes.',
            '3BW must not change the 3BU preview service.',
            '3BW must not implement file upload.',
            '3BW must not implement Excel parsing.',
            '3BW must not mutate member data.',
        ] as $blockedScope) {
            $this->assertStringContainsString($blockedScope, $document);
        }
    }

    public function test_3bw_allows_future_api_only_for_controlled_preview_service_exposure(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'Future API may expose the existing 3BU preview service through a controlled endpoint.',
            'Scope must remain preview/dry-run first.',
            'No commit endpoint is authorized yet.',
            'No import mutation endpoint is authorized yet.',
            'No member create behavior is authorized yet.',
            'No member update behavior is authorized yet.',
            'No member delete behavior is authorized yet.',
            'No actual file upload is authorized yet unless separately scoped.',
            'No xlsx parser or package is authorized yet.',
            'API must be club-scoped.',
            'API must be officer-only.',
            'Normal Member must not access import preview APIs.',
            'Backend authorization must remain the source of truth.',
            'Cross-club import preview access must fail closed.',
        ] as $decision) {
            $this->assertStringContainsString($decision, $document);
        }
    }

    public function test_3bw_requires_preview_response_validation_errors_and_warnings_without_overwrite_or_finance_mutation(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'API must return row-level validation errors.',
            'API must return duplicate warnings as preview-only information.',
            'API must return existing-member match warnings as preview-only information.',
            'API must not silently overwrite existing member data.',
            'API must not mutate finance ledger, payments, receipts, or payment history.',
            'API must not import dues, committee, fellowship, activity, event, or finance data.',
            'No frontend upload UI is authorized yet.',
            'No deployment, SaaS, or client-specific behavior is authorized.',
        ] as $boundary) {
            $this->assertStringContainsString($boundary, $document);
        }
    }

    public function test_3bw_requires_future_authorization_to_answer_api_shape_questions(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'What exact preview route URI is allowed?',
            'What request payload shape is allowed?',
            'What maximum row count is allowed?',
            'What maximum payload size is allowed?',
            'Which officer roles may access the preview endpoint?',
            'Whether President/Admin, Secretary, and Treasurer all receive the same preview capability.',
            'Whether request validation should live in a dedicated request class.',
            'Whether the response should use a dedicated resource class.',
            'How validation errors are represented.',
            'How duplicate and existing-match warnings are represented.',
            'How unauthorized and cross-club access fail closed.',
            'Whether a sample template endpoint is needed later.',
            'Whether upload and parser support should remain a separate later lane.',
        ] as $question) {
            $this->assertStringContainsString($question, $document);
        }
    }

    public function test_3bw_defines_future_preview_api_as_json_rows_using_existing_service_only(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'If later authorized, the first API implementation may accept spreadsheet-like rows in JSON.',
            'App\\Services\\Members\\MemberDataImportPreviewService::preview',
            'The first API implementation must return a dry-run preview result.',
            'The first API implementation must not persist import state.',
            'The first API implementation must not store raw uploaded files.',
            'The first API implementation must not parse xlsx files.',
            'The first API implementation must not create, update, delete, merge, or overwrite member records.',
            'The first API implementation must not touch finance records.',
        ] as $futureBehavior) {
            $this->assertStringContainsString($futureBehavior, $document);
        }
    }

    public function test_3bw_defines_role_and_club_boundary_for_future_api(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'Normal Member:',
            'Must not access member import preview APIs.',
            'Must not preview imports.',
            'Must not upload data.',
            'Must not commit imports.',
            "Must not see other members' import data.",
            'President/Admin, Secretary, and Treasurer:',
            'May be considered for future officer-only import preview API access.',
            'Must remain scoped to their active club.',
            'Must not preview imports for another club.',
            'Must not commit imports in the preview-only lane.',
            'Backend authorization remains the source of truth for all future import API access.',
        ] as $roleBoundary) {
            $this->assertStringContainsString($roleBoundary, $document);
        }
    }

    public function test_3bw_defines_preview_response_safety_boundary(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'row number',
            'row validity',
            'normalized member fields allowed by 3BU',
            'row-level validation errors',
            'duplicate row warnings',
            'club-scoped existing-member match warnings',
            'dry-run summary counts',
            'create_preview, matched_preview, or reject intent',
        ] as $safeField) {
            $this->assertStringContainsString($safeField, $document);
        }

        foreach ([
            'raw uploaded file contents beyond the submitted row preview',
            'hidden columns',
            'finance fields',
            'ledger fields',
            'payment fields',
            'receipt fields',
            'payment-history fields',
            'internal cancellation or reversal fields',
            'unrelated member private data',
            'global cross-club member matches',
        ] as $blockedField) {
            $this->assertStringContainsString($blockedField, $document);
        }
    }

    public function test_3bw_blocks_commit_upload_parser_package_queue_frontend_and_non_member_import_behavior(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'commit/import mutation endpoint',
            'member create endpoint for import',
            'member update endpoint for import',
            'member delete endpoint for import',
            'silent overwrite behavior',
            'file upload implementation',
            'file storage implementation',
            'xlsx parser integration',
            'composer package changes',
            'queue implementation',
            'job implementation',
            'migration',
            'seeder',
            'frontend upload UI',
            'dues import',
            'ledger import',
            'payment import',
            'receipt import',
            'payment-history import',
            'committee import',
            'fellowship import',
            'activity import',
            'event import',
            'finance import',
            'export',
            'PDF',
            'reminder',
            'deployment implementation',
            'SaaS implementation',
            'client-specific behavior',
            'hardcoded Lions Club Beenachi rules',
            'hardcoded Rotary Central Bathery rules',
            'hardcoded future pilot club rules',
        ] as $blockedBehavior) {
            $this->assertStringContainsString($blockedBehavior, $document);
        }
    }

    public function test_3bw_context_models_services_and_apis_are_review_only_not_changed(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'MemberDataImportPreviewService',
            'Member',
            'Club',
            'ClubMembership',
            'Role',
            'User',
            'auth/me',
            'club/me',
            'member list',
            'member create',
            'member update',
            'may be reviewed for context only and must not be changed in 3BW',
            '3BW confirms there is no member import API implementation in this milestone.',
            'Future member import API implementation must be authorized separately before implementation.',
        ] as $contextText) {
            $this->assertStringContainsString($contextText, $document);
        }
    }

    public function test_3bw_does_not_add_member_import_runtime_files_or_routes(): void
    {
        foreach ([
            app_path('Http/Controllers/Api/V1/MemberImportController.php'),
            app_path('Http/Controllers/Api/V1/Members/MemberImportController.php'),
            app_path('Http/Requests/MemberImportRequest.php'),
            app_path('Http/Requests/Members/MemberImportRequest.php'),
            app_path('Http/Resources/MemberImportResource.php'),
            app_path('Imports/MemberImport.php'),
            app_path('Jobs/ProcessMemberImport.php'),
            app_path('Models/MemberImport.php'),
            app_path('Services/Members/MemberImportCommitService.php'),
            database_path('migrations/create_member_imports_table.php'),
            database_path('seeders/MemberImportSeeder.php'),
            base_path('../app/member-import'),
            base_path('../app/import'),
            base_path('../components/member-import'),
            base_path('../lib/member-import.ts'),
        ] as $blockedPath) {
            $this->assertFileDoesNotExist($blockedPath);
        }

        $memberImportRoutes = collect(Route::getRoutes())
            ->filter(fn ($route): bool => str_contains($route->uri(), 'import'))
            ->map(fn ($route): string => implode('|', $route->methods()).' '.$route->uri())
            ->values()
            ->all();

        $this->assertSame([
            'POST api/v1/clubs/{club}/members/import-preview',
            'POST api/v1/clubs/{club}/members/import-commit',
        ], $memberImportRoutes);
    }

    public function test_3bw_selects_3bx_as_next_implementation_authorization_milestone(): void
    {
        $document = $this->scopeGateDocument();

        $this->assertStringContainsString('Milestone 3BX - Member Data Import API Implementation Authorization', $document);
        $this->assertStringContainsString('3BX should remain docs/tests-only unless the human explicitly authorizes a different scope.', $document);
        $this->assertStringContainsString('3BX may authorize a future narrow preview API implementation only after it confirms 3BW scope boundaries.', $document);
    }

    private function scopeGateDocument(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }
}
