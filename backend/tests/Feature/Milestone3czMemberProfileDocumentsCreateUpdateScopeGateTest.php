<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3czMemberProfileDocumentsCreateUpdateScopeGateTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3cz_member_profile_documents_create_update_scope_gate.md';
    private string $routesPath = __DIR__ . '/../../routes/api.php';
    private string $controllerPath = __DIR__ . '/../../app/Http/Controllers/Api/V1/Members/MemberProfileDocumentController.php';
    private string $resourcePath = __DIR__ . '/../../app/Http/Resources/Members/MemberProfileDocumentResource.php';
    private string $servicePath = __DIR__ . '/../../app/Services/Members/MemberProfileDocumentReadService.php';
    private string $modelPath = __DIR__ . '/../../app/Models/MemberProfileDocument.php';
    private string $migrationPath = __DIR__ . '/../../database/migrations/2026_06_13_000001_create_member_profile_documents_table.php';
    private string $frontendPath = __DIR__ . '/../../../app/page.tsx';

    public function test_3cz_document_exists_and_is_docs_tests_only_create_update_scope_gate(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'Milestone 3CZ - Member Profile Documents Create/Update Scope Gate',
            'Milestone 3CZ is a docs/tests-only create/update scope gate milestone.',
            'It defines the future Member Profile Documents Create/Update API boundary only.',
            'It does not implement create/update APIs.',
            'It does not change production code.',
            'It does not edit app/page.tsx.',
            'It does not change frontend runtime files.',
            'It does not change backend routes, controllers, requests, resources, services, policies, middleware, or config.',
            'It does not change the 3CT migration.',
            'It does not change App\Models\MemberProfileDocument.',
            'It does not change older milestone tests.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3cz_records_base_branch_allowed_files_and_verified_base_hash(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'milestone-3cy-member-profile-documents-read-api-validation-and-closure',
            '0b57a138e7f8799a273e77270e00d9c7a6487da9',
            'milestone-3cz-member-profile-documents-create-update-scope-gate',
            'backend/docs/milestone_3cz_member_profile_documents_create_update_scope_gate.md',
            'backend/tests/Feature/Milestone3czMemberProfileDocumentsCreateUpdateScopeGateTest.php',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3cz_allowed_files_exist_and_runtime_files_are_read_only_inputs(): void
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

    public function test_document_records_relationship_to_foundation_and_read_api(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'Milestone 3CT created the database foundation and model for Member Profile Documents.',
            'Milestone 3CU validated and closed that foundation.',
            '3CZ confirms that the database and model foundation remains closed and unchanged.',
            '3CZ does not authorize database migration or model changes.',
            'Milestone 3CV scoped the read API, 3CW authorized the read API implementation, 3CX implemented the narrow read API, and 3CY validated and closed the read API lane.',
            '3CZ confirms that the read API lane remains closed.',
            'Existing read routes and implementation remain completely unchanged.',
            'member_profile_documents',
            'App\Models\MemberProfileDocument',
            'GET /api/v1/clubs/{club}/members/{member}/profile-documents',
            'GET /api/v1/clubs/{club}/members/{member}/profile-documents/{document}',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_future_create_update_api_concept_and_candidate_routes(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'Future create/update API may later allow officers to create or update metadata-only member profile document records.',
            'POST /api/v1/clubs/{club}/members/{member}/profile-documents',
            'PATCH /api/v1/clubs/{club}/members/{member}/profile-documents/{document}',
            'These routes are only future candidates. 3CZ does not add them.',
            'DELETE remains out of scope unless explicitly authorized in a later separate milestone.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_future_access_boundaries(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'Future create/update API should be:',
            'Club-scoped.',
            'Member-scoped.',
            'Metadata-only.',
            'Officer-managed.',
            'President/Admin may create/update member profile document metadata within their club.',
            'Secretary may create/update member profile document metadata within their club.',
            'Treasurer must remain blocked from create/update unless later explicitly authorized.',
            'Normal Member must remain blocked from create/update unless later explicitly authorized.',
            "Normal Member must not create/update another member's document metadata.",
            'Cross-club access must fail closed.',
            'Member/document ownership mismatch must fail closed.',
            'Sensitive/private metadata must be protected.',
            'Create/update API must never accept file paths, storage disks, URLs, OCR text, binary data, or physical file data.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_future_writable_metadata_boundary(): void
    {
        $document = $this->scopeGateDocument();

        foreach ($this->writableMetadataFields() as $field) {
            $this->assertStringContainsString($field, $document);
        }

        foreach ($this->serverDerivedFields() as $field) {
            $this->assertStringContainsString($field, $document);
        }

        foreach ($this->blockedRequestFields() as $field) {
            $this->assertStringContainsString($field, $document);
        }
    }

    public function test_document_records_explicit_non_authorization(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            '3CZ does not authorize:',
            'Runtime implementation.',
            'Backend route implementation.',
            'Controller/request/resource/service implementation.',
            'Policy/middleware implementation.',
            'Frontend implementation.',
            'Migration/table changes.',
            'Model changes.',
            'DELETE behavior.',
            'File upload.',
            'File storage.',
            'S3/local disk integration.',
            'Public file URLs.',
            'Signed file URLs.',
            'File download endpoints.',
            'OCR.',
            'PDF generation.',
            'WhatsApp/SMS/email sending.',
            'Reminder behavior.',
            'Member import changes.',
            'Finance/ledger/payment/receipt/payment-history changes.',
            'Committee/fellowship/activity/event changes.',
            'Deployment/SaaS/client-specific behavior.',
            'Any production behavior change.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_expected_next_milestone(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'Milestone 3DA — Member Profile Documents Create/Update Implementation Authorization',
            '3DA should remain docs/tests-only unless explicitly approved later.',
            '3CZ must not start 3DA.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_profile_document_routes_remain_metadata_only_after_3db_forward_compatibility_update(): void
    {
        $routes = collect(Route::getRoutes())
            ->filter(fn ($route): bool => str_contains($route->uri(), 'profile-documents'));

        $this->assertCount(4, $routes);

        foreach ($routes as $route) {
            $this->assertNotContains('PUT', $route->methods());
            $this->assertNotContains('DELETE', $route->methods());

            foreach (['upload', 'download', 'ocr', 'pdf', 'storage'] as $blockedFragment) {
                $this->assertStringNotContainsString($blockedFragment, $route->uri());
            }
        }
    }

    public function test_runtime_files_keep_blocking_delete_file_storage_ocr_pdf_and_model_migration_mutation(): void
    {
        $controller = file_get_contents($this->controllerPath);
        $model = file_get_contents($this->modelPath);
        $migration = file_get_contents($this->migrationPath);

        foreach (['destroy', 'upload', 'download'] as $method) {
            $this->assertStringNotContainsString("public function {$method}", $controller);
        }

        foreach ($this->blockedRequestFields() as $field) {
            $this->assertStringNotContainsString($field, $model);
            $this->assertStringNotContainsString($field, $migration);
        }
    }

    public function test_no_unapproved_create_update_artifacts_exist_for_profile_documents(): void
    {
        foreach ([
            app_path('Http/Requests/Members/MemberProfileDocumentCreateRequest.php'),
            app_path('Http/Requests/Members/MemberProfileDocumentUpdateRequest.php'),
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

    private function scopeGateDocument(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }

    private function writableMetadataFields(): array
    {
        return [
            'document_type',
            'status',
            'external_reference',
            'remarks',
        ];
    }

    private function serverDerivedFields(): array
    {
        return [
            'club_id',
            'member_id',
            'created_by_user_id',
            'updated_by_user_id',
        ];
    }

    private function blockedRequestFields(): array
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
