<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3bxMemberDataImportApiImplementationAuthorizationTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3bx_member_data_import_api_implementation_authorization.md';

    public function test_3bx_document_exists_and_is_docs_tests_only_implementation_authorization(): void
    {
        $document = $this->authorizationDocument();

        $this->assertStringContainsString('Milestone 3BX - Member Data Import API Implementation Authorization', $document);
        $this->assertStringContainsString('docs/tests-only implementation authorization milestone', $document);
        $this->assertStringContainsString('It authorizes a future narrow API implementation in Milestone 3BY.', $document);
        $this->assertStringContainsString('It does not implement a member import API.', $document);
        $this->assertStringContainsString('It does not add routes.', $document);
        $this->assertStringContainsString('It does not add controllers.', $document);
        $this->assertStringContainsString('It does not add requests or resources.', $document);
        $this->assertStringContainsString('It does not implement file upload.', $document);
        $this->assertStringContainsString('It does not implement xlsx parsing.', $document);
        $this->assertStringContainsString('It does not change frontend runtime behavior.', $document);
        $this->assertStringContainsString('It does not change backend runtime behavior.', $document);
        $this->assertStringContainsString('It does not change API behavior.', $document);
    }

    public function test_3bx_records_base_branch_and_allowed_files(): void
    {
        $document = $this->authorizationDocument();

        $this->assertStringContainsString('Milestone 3BW - Member Data Import API Scope Gate', $document);
        $this->assertStringContainsString('milestone-3bw-member-data-import-api-scope-gate', $document);
        $this->assertStringContainsString('milestone-3bx-member-data-import-api-implementation-authorization', $document);
        $this->assertStringContainsString('backend/docs/milestone_3bx_member_data_import_api_implementation_authorization.md', $document);
        $this->assertStringContainsString('backend/tests/Feature/Milestone3bxMemberDataImportApiImplementationAuthorizationTest.php', $document);
    }

    public function test_3bx_blocks_runtime_frontend_backend_api_import_file_queue_and_finance_changes(): void
    {
        $document = $this->authorizationDocument();

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
            '3BX must not add routes.',
            '3BX must not add controllers.',
            '3BX must not add request classes.',
            '3BX must not add resource classes.',
            '3BX must not change the 3BU preview service.',
            '3BX must not implement file upload.',
            '3BX must not implement Excel parsing.',
            '3BX must not mutate member data.',
        ] as $blockedScope) {
            $this->assertStringContainsString($blockedScope, $document);
        }
    }

    public function test_3bx_authorizes_only_future_narrow_preview_api_implementation(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'Milestone 3BY may implement only a narrow backend preview API for member data import.',
            'Future 3BY may expose the existing 3BU preview service through a controlled preview endpoint.',
            'Future 3BY must remain preview/dry-run only.',
            'Future 3BY must not add a commit endpoint.',
            'Future 3BY must not add an import mutation endpoint.',
            'Future 3BY must not create members.',
            'Future 3BY must not update members.',
            'Future 3BY must not delete members.',
            'Future 3BY must not implement file upload.',
            'Future 3BY must not add an xlsx parser or package.',
            'Future 3BY must be club-scoped only.',
            'Future 3BY must be officer-only.',
            'Normal Member must not access the import preview API.',
            'Backend authorization must remain the source of truth.',
            'Cross-club import preview access must fail closed.',
        ] as $authorizationText) {
            $this->assertStringContainsString($authorizationText, $document);
        }
    }

    public function test_3bx_limits_future_3by_candidates_and_requires_stop_for_surface_expansion(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'one controlled preview route',
            'one controller action for preview',
            'request validation only if explicitly included in the approved 3BY file boundary',
            'response resource only if explicitly included in the approved 3BY file boundary',
            'focused implementation documentation',
            'focused backend tests',
            'Future 3BY must stop and request approval before adding:',
            'file upload handling',
            'storage persistence',
            'xlsx parser integration',
            'composer package changes',
            'import commit endpoint',
            'member create/update/delete import behavior',
            'model changes',
            'migrations',
            'seeders',
            'queues',
            'jobs',
            'frontend upload UI',
            'finance import behavior',
            'Those files and behaviors are not pre-authorized by 3BX unless a later 3BY approval explicitly names them.',
        ] as $candidateText) {
            $this->assertStringContainsString($candidateText, $document);
        }
    }

    public function test_3bx_defines_role_and_club_authorization_boundary(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'Normal Member must not access import preview APIs.',
            'Normal Member must not preview imports.',
            'Normal Member must not upload data.',
            'Normal Member must not commit imports.',
            "Normal Member must not see other members' import data.",
            'President/Admin, Secretary, and Treasurer may be considered for officer-only import preview API access.',
            'Officers must remain scoped to their active club.',
            'Officers must not preview imports for another club.',
            'Cross-club import preview access must fail closed.',
            'Backend authorization remains the source of truth.',
        ] as $roleText) {
            $this->assertStringContainsString($roleText, $document);
        }
    }

    public function test_3bx_defines_future_preview_behavior_boundary(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'accept spreadsheet-like rows in JSON only.',
            'call the existing MemberDataImportPreviewService preview behavior.',
            'return a dry-run preview result.',
            'return row-level validation errors.',
            'return duplicate warnings as preview-only information.',
            'return existing-member match warnings as preview-only information.',
            'keep existing-member matches preview-only.',
            'avoid silent overwrites.',
            'avoid silent merges.',
            'avoid global cross-club duplicate matching.',
            'use club-scoped member code, phone, or email matching as preview information only.',
            'silently overwrite existing member data.',
            'mutate existing members.',
            'create members.',
            'update members.',
            'delete members.',
            'persist import state.',
            'store uploaded files.',
            'parse xlsx files.',
            'expose raw uploaded data to unauthorized users.',
        ] as $behaviorText) {
            $this->assertStringContainsString($behaviorText, $document);
        }
    }

    public function test_3bx_defines_future_response_boundary(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'dry_run',
            'committed',
            'summary',
            'row number',
            'row validity',
            'normalized member fields allowed by 3BU',
            'row-level validation errors',
            'duplicate row warnings',
            'club-scoped existing-member match warnings',
            'create_preview, matched_preview, or reject intent',
            'hidden columns',
            'finance fields',
            'ledger fields',
            'payment fields',
            'receipt fields',
            'payment-history fields',
            'internal cancellation fields',
            'internal reversal fields',
            'unrelated member private data',
            'global cross-club member matches',
        ] as $responseText) {
            $this->assertStringContainsString($responseText, $document);
        }
    }

    public function test_3bx_blocks_non_member_finance_and_related_imports(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'dues import',
            'ledger import',
            'payment import',
            'receipt import',
            'payment-history import',
            'finance import',
            'committee import',
            'board role import',
            'fellowship import',
            'activity import',
            'event import',
            'project import',
            'document import',
            'donor consent bulk override unless explicitly authorized later',
        ] as $blockedImport) {
            $this->assertStringContainsString($blockedImport, $document);
        }
    }

    public function test_3bx_blocks_commit_upload_parser_package_queue_frontend_mutation_export_deployment_saas_and_client_behavior(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'commit/import mutation endpoint',
            'member create endpoint for import',
            'member update endpoint for import',
            'member delete endpoint for import',
            'file upload implementation',
            'file storage implementation',
            'xlsx parser integration',
            'composer package changes',
            'queue implementation',
            'job implementation',
            'migration',
            'seeder',
            'frontend upload UI',
            'payment mutation',
            'receipt mutation',
            'finance ledger mutation',
            'payment history mutation',
            'dues creation',
            'balance calculation',
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
        ] as $blockedBehavior) {
            $this->assertStringContainsString($blockedBehavior, $document);
        }
    }

    public function test_3bx_preserves_backend_source_of_truth_for_import_preview_api(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'Backend validation and authorization must remain the source of truth',
            'club scope',
            'role access',
            'preview endpoint authorization',
            'row payload validation',
            'required member fields',
            'duplicate detection',
            'row-level validation',
            'dry-run result',
            'existing-member match warnings',
            'Frontend code may not infer import permissions or commit behavior unless separately authorized.',
        ] as $sourceOfTruthText) {
            $this->assertStringContainsString($sourceOfTruthText, $document);
        }
    }

    public function test_3bx_defines_expected_future_3by_validation_scope(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'the member import preview API is present only if authorized by 3BX.',
            'the preview API is club-scoped.',
            'the preview API is officer-only.',
            'Normal Member cannot access the preview API.',
            'cross-club preview access fails closed.',
            'row-level validation errors are returned safely.',
            'duplicate and existing-member match warnings remain preview-only.',
            'no commit/import mutation endpoint is added.',
            'no member create/update/delete behavior is added.',
            'no file upload or xlsx parser is added.',
            'no finance, payment, receipt, ledger, or payment-history records are created.',
            'no frontend upload UI is added.',
            'no export, PDF, reminder, deployment, SaaS, or client-specific behavior is added.',
        ] as $validationText) {
            $this->assertStringContainsString($validationText, $document);
        }
    }

    public function test_3bx_does_not_add_member_import_runtime_files_or_routes(): void
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

    public function test_3bx_selects_3by_as_next_narrow_implementation_milestone(): void
    {
        $document = $this->authorizationDocument();

        $this->assertStringContainsString('Milestone 3BY - Member Data Import API Narrow Implementation', $document);
        $this->assertStringContainsString('3BY may implement only the narrow member import preview API authorized by this document.', $document);
    }

    private function authorizationDocument(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }
}
