<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3cyMemberProfileDocumentsReadApiValidationAndClosureTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3cy_member_profile_documents_read_api_validation_and_closure.md';
    private string $frontendPath = __DIR__ . '/../../../app/page.tsx';
    private string $routesPath = __DIR__ . '/../../routes/api.php';
    private string $controllerPath = __DIR__ . '/../../app/Http/Controllers/Api/V1/Members/MemberProfileDocumentController.php';
    private string $resourcePath = __DIR__ . '/../../app/Http/Resources/Members/MemberProfileDocumentResource.php';
    private string $servicePath = __DIR__ . '/../../app/Services/Members/MemberProfileDocumentReadService.php';
    private string $modelPath = __DIR__ . '/../../app/Models/MemberProfileDocument.php';
    private string $migrationPath = __DIR__ . '/../../database/migrations/2026_06_13_000001_create_member_profile_documents_table.php';

    public function test_3cy_document_exists_and_is_docs_tests_only_validation_and_closure(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'Milestone 3CY - Member Profile Documents Read API Validation and Closure',
            'docs/tests-only validation and closure milestone',
            'It validates and closes the 3CX Member Profile Documents metadata-only read API implementation.',
            'It does not change production code.',
            'It does not edit app/page.tsx.',
            'It does not change frontend runtime files.',
            'It does not change backend routes, controllers, resources, or services.',
            'It does not change request, policy, or middleware files.',
            'It does not change the 3CT migration.',
            'It does not change App\Models\MemberProfileDocument.',
            'It does not change older milestone tests.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3cy_records_base_branch_allowed_files_and_next_milestone(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'Milestone 3CX - Member Profile Documents Read API Narrow Implementation',
            'milestone-3cx-member-profile-documents-read-api-narrow-implementation',
            'milestone-3cy-member-profile-documents-read-api-validation-and-closure',
            '2479849a0800a1a453810680fc69452fbd21fd9b',
            'backend/docs/milestone_3cy_member_profile_documents_read_api_validation_and_closure.md',
            'backend/tests/Feature/Milestone3cyMemberProfileDocumentsReadApiValidationAndClosureTest.php',
            'Milestone 3CZ - Member Profile Documents Create/Update Scope Gate',
            '3CZ should be docs/tests-only unless explicitly approved later.',
            '3CY must not start 3CZ.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3cy_allowed_files_exist_and_runtime_files_are_read_only_inputs(): void
    {
        foreach ([
            $this->docPath,
            __FILE__,
            $this->frontendPath,
            $this->routesPath,
            $this->controllerPath,
            $this->resourcePath,
            $this->servicePath,
            $this->modelPath,
            $this->migrationPath,
        ] as $path) {
            $this->assertFileExists($path);
        }
    }

    public function test_document_records_relationship_to_3cv_and_3cw(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'Milestone 3CV scoped the future Member Profile Documents read API as metadata-only, club-scoped, member-scoped, and fail-closed.',
            '3CY confirms that 3CX stayed inside the 3CV read API scope gate.',
            'Milestone 3CW authorized only a future narrow metadata-only read API implementation.',
            '3CY confirms that 3CX implemented only that 3CW-authorized read API.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_3cx_implementation_closure_table_model_and_artifacts(): void
    {
        $document = $this->closureDocument();

        foreach ([
            '3CX delivered a metadata-only read API for member_profile_documents.',
            'member_profile_documents',
            'App\Models\MemberProfileDocument',
            'App\Http\Controllers\Api\V1\Members\MemberProfileDocumentController',
            'App\Http\Resources\Members\MemberProfileDocumentResource',
            'App\Services\Members\MemberProfileDocumentReadService',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_profile_document_routes_remain_metadata_only_after_3db_forward_compatibility_update(): void
    {
        $routes = collect(Route::getRoutes())
            ->filter(fn ($route): bool => str_contains($route->uri(), 'profile-documents'))
            ->map(fn ($route): string => implode('|', $route->methods()).' '.$route->uri())
            ->values()
            ->all();

        $this->assertSame([
            'GET|HEAD api/v1/clubs/{club}/members/{member}/profile-documents',
            'GET|HEAD api/v1/clubs/{club}/members/{member}/profile-documents/{document}',
            'POST api/v1/clubs/{club}/members/{member}/profile-documents',
            'PATCH api/v1/clubs/{club}/members/{member}/profile-documents/{document}',
        ], $routes);
    }

    public function test_profile_document_routes_do_not_include_delete_upload_download_file_ocr_or_pdf_behavior(): void
    {
        $routes = collect(Route::getRoutes())
            ->filter(fn ($route): bool => str_contains($route->uri(), 'profile-documents'));

        $this->assertCount(4, $routes);

        foreach ($routes as $route) {
            $this->assertSame([], array_values(array_intersect($route->methods(), ['PUT', 'DELETE'])));

            foreach (['upload', 'download', 'ocr', 'pdf', 'export', 'storage'] as $blockedFragment) {
                $this->assertStringNotContainsString($blockedFragment, $route->uri());
            }
        }
    }

    public function test_document_records_approved_get_routes_and_blocks_mutation_upload_download_file_ocr_pdf_routes(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'GET /api/v1/clubs/{club}/members/{member}/profile-documents',
            'GET /api/v1/clubs/{club}/members/{member}/profile-documents/{document}',
            'Only GET routes were added.',
            'No POST, PATCH, PUT, DELETE, upload, download, file, OCR, PDF, export, or storage routes were added.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_controller_resource_and_service_are_read_only_metadata_only_artifacts(): void
    {
        $controller = file_get_contents($this->controllerPath);
        $resource = file_get_contents($this->resourcePath);
        $service = file_get_contents($this->servicePath);

        foreach (['function index', 'function show'] as $expectedText) {
            $this->assertStringContainsString($expectedText, $controller);
        }

        foreach (['function destroy', 'function upload', 'function download'] as $blockedText) {
            $this->assertStringNotContainsString($blockedText, $controller);
        }

        foreach ($this->metadataFields() as $field) {
            $this->assertStringContainsString("'{$field}'", $resource);
        }

        foreach ($this->blockedResponseFields() as $field) {
            $this->assertStringNotContainsString("'{$field}'", $resource);
        }

        $this->assertStringContainsString('forMember', $service);

        foreach (['create(', 'update(', 'delete(', 'save(', 'Storage::', 'storeAs(', 'putFile'] as $blockedText) {
            $this->assertStringNotContainsString($blockedText, $service);
        }
    }

    public function test_document_records_read_only_controller_resource_and_service_boundaries(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'The controller is read-only.',
            'The controller has only index and show behavior.',
            'The controller does not have store, update, destroy, upload, download, OCR, PDF, export, or storage behavior.',
            'The resource is metadata-only.',
            'The resource exposes only approved metadata fields.',
            'The service is read-only and metadata-only.',
            'The service reads profile document metadata for a selected club/member pair.',
            'The service does not create, update, delete, upload, download, generate, send, store, or mutate anything.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_access_closure_claims(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'President/Admin can read member profile document metadata within club.',
            'Secretary can read member profile document metadata within club.',
            'Treasurer remains blocked in 3CX.',
            'Normal Member can read only their own document metadata.',
            'Normal Member cannot read another member\'s document metadata.',
            'Unauthenticated users are blocked.',
            'Cross-club users are blocked.',
            'Cross-club member access fails closed.',
            'Cross-club document access fails closed.',
            'Member/document ownership mismatch fails closed.',
            'Document belonging to a different member fails closed.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_metadata_only_response_fields_and_blocks_sensitive_fields(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'Index response is metadata-only.',
            'Show response is metadata-only.',
            'The response includes only:',
            'The response does not include:',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }

        foreach ($this->metadataFields() as $field) {
            $this->assertStringContainsString($field, $document);
        }

        foreach ($this->blockedResponseFields() as $field) {
            $this->assertStringContainsString($field, $document);
        }
    }

    public function test_migration_and_model_remain_metadata_only_without_blocked_storage_fields(): void
    {
        $migration = strtolower(file_get_contents($this->migrationPath));
        $model = strtolower(file_get_contents($this->modelPath));

        foreach ($this->blockedResponseFields() as $field) {
            $this->assertStringNotContainsString($field, $migration);
            $this->assertStringNotContainsString($field, $model);
        }
    }

    public function test_no_request_policy_middleware_upload_storage_ocr_or_pdf_artifacts_are_added(): void
    {
        foreach ([
            app_path('Http/Requests/Members/MemberProfileDocumentRequest.php'),
            app_path('Policies/MemberProfileDocumentPolicy.php'),
            app_path('Http/Middleware/MemberProfileDocumentMiddleware.php'),
            app_path('Services/Members/MemberProfileDocumentUploadService.php'),
            app_path('Services/Members/MemberProfileDocumentStorageService.php'),
            app_path('Services/Members/MemberProfileDocumentOcrService.php'),
            app_path('Services/Members/MemberProfileDocumentPdfService.php'),
        ] as $blockedPath) {
            $this->assertFileDoesNotExist($blockedPath);
        }
    }

    public function test_document_records_runtime_files_unchanged_and_support_artifacts_not_added(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'The migration was not changed.',
            'The 3CT migration was not edited.',
            'App\Models\MemberProfileDocument was not changed.',
            'MemberProfileDocument.php was not edited.',
            'Frontend runtime was not changed.',
            'app/page.tsx was not changed.',
            'No request classes were added.',
            'No policy classes were added.',
            'No middleware classes were added.',
            'No upload service was added.',
            'No storage service was added.',
            'No OCR service was added.',
            'No PDF service was added.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_upload_storage_ocr_pdf_finance_import_deployment_saas_and_client_blocks(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'No file upload was added.',
            'No file storage was added.',
            'No S3 integration was added.',
            'No local disk integration was added.',
            'No public URL behavior was added.',
            'No signed URL behavior was added.',
            'No download URL behavior was added.',
            'No file download endpoint behavior was added.',
            'No OCR behavior was added.',
            'No PDF generation was added.',
            'No finance behavior was added.',
            'No ledger behavior was added.',
            'No payment behavior was added.',
            'No receipt behavior was added.',
            'No payment-history behavior was added.',
            'No member import behavior was changed.',
            'Member import preview remains unchanged.',
            'Member import commit remains unchanged.',
            'No deployment implementation was added.',
            'No SaaS implementation was added.',
            'No client-specific behavior was added.',
            'Hardcoded Lions Club Beenachi behavior remains blocked.',
            'Hardcoded Rotary Central Bathery behavior remains blocked.',
            'Hardcoded future pilot club behavior remains blocked.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_narrow_older_guard_compatibility_updates(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'Older 3CT, 3CU, 3CV, and 3CW stale guard tests were narrowly updated only to allow the approved 3CX read-only GET routes and approved read-only artifacts.',
            'Those older guard tests continue to block mutation routes, upload routes, download routes, OCR routes, PDF routes, request classes, policy classes, middleware classes, upload services, storage services, OCR services, PDF services, and unapproved generic document services.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_final_closure_statement_and_next_milestone_are_recorded(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'Milestone 3CY closes the Member Profile Documents read API lane delivered by 3CX.',
            'The closed read API consists only of metadata-only GET routes for member_profile_documents records through the existing App\Models\MemberProfileDocument model.',
            'The closed read API is club-scoped, member-scoped, read-only, metadata-only, and fail-closed for unauthorized, cross-club, and mismatched member/document access.',
            'The closed read API does not add mutation, upload, download, storage, S3/local disk integration, public/signed/download URL behavior, OCR, PDF, frontend runtime, finance behavior, member import behavior, deployment/SaaS behavior, or client-specific behavior.',
            'Milestone 3CZ - Member Profile Documents Create/Update Scope Gate',
            '3CZ should be docs/tests-only unless explicitly approved later.',
            '3CY must not start 3CZ.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    private function closureDocument(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }

    private function metadataFields(): array
    {
        return [
            'id',
            'club_id',
            'member_id',
            'document_type',
            'status',
            'external_reference',
            'remarks',
            'created_by_user_id',
            'updated_by_user_id',
            'created_at',
            'updated_at',
        ];
    }

    private function blockedResponseFields(): array
    {
        return [
            'file_path',
            'path',
            'disk',
            'storage_disk',
            'file_name',
            'original_file_name',
            'mime_type',
            'file_size',
            'url',
            'public_url',
            'signed_url',
            'download_url',
            'ocr_text',
            'extracted_text',
            'pdf_path',
            'thumbnail_path',
            'uploaded_file',
            'attachment',
            'binary_data',
            'base64_data',
        ];
    }
}
