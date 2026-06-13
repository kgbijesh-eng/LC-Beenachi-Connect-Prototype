<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3daMemberProfileDocumentsCreateUpdateImplementationAuthorizationTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3da_member_profile_documents_create_update_implementation_authorization.md';
    private string $routesPath = __DIR__ . '/../../routes/api.php';
    private string $controllerPath = __DIR__ . '/../../app/Http/Controllers/Api/V1/Members/MemberProfileDocumentController.php';
    private string $resourcePath = __DIR__ . '/../../app/Http/Resources/Members/MemberProfileDocumentResource.php';
    private string $servicePath = __DIR__ . '/../../app/Services/Members/MemberProfileDocumentReadService.php';
    private string $modelPath = __DIR__ . '/../../app/Models/MemberProfileDocument.php';
    private string $migrationPath = __DIR__ . '/../../database/migrations/2026_06_13_000001_create_member_profile_documents_table.php';
    private string $frontendPath = __DIR__ . '/../../../app/page.tsx';

    public function test_3da_document_exists_and_is_docs_tests_only_authorization(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'Milestone 3DA - Member Profile Documents Create/Update Implementation Authorization',
            'Milestone 3DA is a docs/tests-only implementation authorization milestone.',
            'It converts the 3CZ Member Profile Documents Create/Update scope gate into a precise future implementation authorization boundary.',
            'It does not implement create/update APIs.',
            'It does not change production code.',
            'It does not edit app/page.tsx.',
            'It does not change frontend runtime files.',
            'It does not change backend routes, controllers, requests, resources, services, policies, middleware, models, migrations, config, or API behavior.',
            'It does not change the 3CT migration.',
            'It does not change App\Models\MemberProfileDocument.',
            'It does not change older milestone tests.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3da_records_base_branch_created_branch_allowed_files_and_next_milestone(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'milestone-3cz-member-profile-documents-create-update-scope-gate',
            '82138475e3cb84b81f7f38dee963335700a948c9',
            'milestone-3da-member-profile-documents-create-update-implementation-authorization',
            'backend/docs/milestone_3da_member_profile_documents_create_update_implementation_authorization.md',
            'backend/tests/Feature/Milestone3daMemberProfileDocumentsCreateUpdateImplementationAuthorizationTest.php',
            'Milestone 3DB - Member Profile Documents Create/Update Narrow Implementation',
            '3DB may implement only the narrow metadata-only create/update API if explicitly approved later.',
            '3DA must not start 3DB.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3da_allowed_files_exist_and_runtime_files_are_read_only_inputs(): void
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

    public function test_document_records_relationship_to_database_foundation_read_api_and_3cz(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'Milestone 3CT created the database foundation and model for Member Profile Documents.',
            'Milestone 3CU validated and closed that foundation.',
            '3DA records that the 3CT and 3CU database foundation lane is already closed.',
            '3DA keeps the database and model foundation unchanged.',
            '3DA does not authorize database migration or model changes.',
            'Milestone 3CV scoped the read API.',
            'Milestone 3CW authorized the read API implementation.',
            'Milestone 3CX implemented the narrow read API.',
            'Milestone 3CY validated and closed the read API lane.',
            '3DA records that the read API lane remains closed.',
            'Existing read routes and implementation remain completely unchanged.',
            'Milestone 3CZ scoped the future Member Profile Documents Create/Update API boundary.',
            '3DA converts the 3CZ create/update scope gate into a future implementation authorization boundary.',
            '3DA preserves the 3CZ metadata-only, officer-managed, club-scoped, member-scoped, non-file, non-storage, non-OCR, non-PDF, non-finance, non-SaaS, and non-client-specific boundaries.',
            '3DA does not implement the 3CZ candidate routes.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_existing_foundation_and_read_routes(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'member_profile_documents',
            'App\Models\MemberProfileDocument',
            'GET /api/v1/clubs/{club}/members/{member}/profile-documents',
            'GET /api/v1/clubs/{club}/members/{member}/profile-documents/{document}',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_future_create_update_authorization_and_candidate_routes(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            '3DA may authorize a future narrow metadata-only create/update API implementation.',
            'POST /api/v1/clubs/{club}/members/{member}/profile-documents',
            'PATCH /api/v1/clubs/{club}/members/{member}/profile-documents/{document}',
            'These routes are future-authorized candidates only.',
            '3DA must not add these routes.',
            'DELETE remains out of scope unless explicitly authorized in a later separate milestone.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_future_read_write_separation_and_implementation_boundary(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'The closed read API lane remains read-only and unchanged.',
            'The future create/update API lane must be separate from the read API lane.',
            'Future read/write separation must preserve metadata-only response and request boundaries.',
            'POST route for creating metadata-only member profile document records.',
            'PATCH route for updating metadata-only member profile document records.',
            'Controller create/update behavior.',
            'Request validation for metadata-only fields.',
            'Resource reuse or extension if metadata-only.',
            'Service layer if needed.',
            'Feature tests for authorized create/update access.',
            'Club-scoped access checks.',
            'Member-scoped access checks.',
            'Document ownership checks.',
            'Cross-club fail-closed checks.',
            'Server-derived ownership and audit fields.',
            'The future create/update API must remain metadata-only.',
            'The future create/update API must not upload, store, download, generate, OCR, send, import, export, or mutate finance behavior.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_future_access_boundaries(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'Future create/update API should be club-scoped.',
            'Future create/update API should be member-scoped.',
            'Future create/update API should be metadata-only.',
            'Future create/update API should be officer-managed.',
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

    public function test_document_records_future_writable_metadata_and_server_derived_fields(): void
    {
        $document = $this->authorizationDocument();

        foreach ($this->writableMetadataFields() as $field) {
            $this->assertStringContainsString($field, $document);
        }

        foreach ($this->serverDerivedFields() as $field) {
            $this->assertStringContainsString($field, $document);
        }

        foreach ([
            'The following ownership and audit fields must be server-derived and not user-controlled:',
            'Future create/update request must not accept user-controlled ownership or audit fields:',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_blocked_file_path_storage_url_ocr_pdf_and_binary_request_fields(): void
    {
        $document = $this->authorizationDocument();

        foreach ($this->blockedRequestFields() as $field) {
            $this->assertStringContainsString($field, $document);
        }
    }

    public function test_only_read_only_profile_document_routes_exist_in_3da(): void
    {
        $approvedRoutes = [
            'api/v1/clubs/{club}/members/{member}/profile-documents',
            'api/v1/clubs/{club}/members/{member}/profile-documents/{document}',
        ];

        $routes = collect(Route::getRoutes())
            ->filter(fn ($route): bool => str_contains($route->uri(), 'profile-documents'));

        $this->assertSame($approvedRoutes, $routes->map(fn ($route): string => $route->uri())->values()->all());

        $routes->each(function ($route): void {
            $this->assertSame(['GET', 'HEAD'], $route->methods());
            $this->assertNotContains('POST', $route->methods());
            $this->assertNotContains('PATCH', $route->methods());
            $this->assertNotContains('PUT', $route->methods());
            $this->assertNotContains('DELETE', $route->methods());
        });
    }

    public function test_runtime_files_remain_without_create_update_delete_or_blocked_fields(): void
    {
        $controller = file_get_contents($this->controllerPath);
        $model = file_get_contents($this->modelPath);
        $migration = file_get_contents($this->migrationPath);

        foreach (['store', 'update', 'destroy'] as $method) {
            $this->assertStringNotContainsString("public function {$method}", $controller);
        }

        foreach ($this->blockedRequestFields() as $field) {
            $this->assertStringNotContainsString($field, $model);
            $this->assertStringNotContainsString($field, $migration);
        }
    }

    public function test_no_unapproved_create_update_upload_storage_ocr_pdf_artifacts_exist(): void
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

    public function test_document_records_runtime_backend_policy_frontend_migration_and_model_blocks(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'Runtime implementation in 3DA is blocked.',
            'Backend route implementation in 3DA is blocked.',
            'Controller implementation in 3DA is blocked.',
            'Request implementation in 3DA is blocked.',
            'Resource implementation in 3DA is blocked.',
            'Service implementation in 3DA is blocked.',
            'Policy implementation in 3DA is blocked.',
            'Middleware implementation in 3DA is blocked.',
            'Frontend implementation in 3DA is blocked.',
            'Migration/table changes are blocked.',
            'Model changes are blocked.',
            'Any production behavior change is blocked in 3DA.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_file_storage_url_ocr_pdf_messaging_finance_import_and_deployment_blocks(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'File upload is blocked.',
            'File storage is blocked.',
            'S3 integration is blocked.',
            'Local disk integration is blocked.',
            'S3/local disk integration is blocked.',
            'Public file URL behavior is blocked.',
            'Signed file URL behavior is blocked.',
            'Download URL behavior is blocked.',
            'Public/signed/download URL behavior is blocked.',
            'File download endpoint behavior is blocked.',
            'OCR is blocked.',
            'PDF generation is blocked.',
            'WhatsApp sending is blocked.',
            'SMS sending is blocked.',
            'Email sending is blocked.',
            'WhatsApp/SMS/email sending is blocked.',
            'Reminder behavior is blocked.',
            'WhatsApp/SMS/email/reminder behavior is blocked.',
            'Finance mutation is blocked.',
            'Ledger mutation is blocked.',
            'Payment mutation is blocked.',
            'Receipt mutation is blocked.',
            'Payment-history changes are blocked.',
            'Finance/ledger/payment/receipt/payment-history changes are blocked.',
            'Member import changes are blocked.',
            'Member import behavior is blocked.',
            'Deployment implementation is blocked.',
            'SaaS implementation is blocked.',
            'Deployment/SaaS behavior is blocked.',
            'Client-specific behavior is blocked.',
            'Deployment/SaaS/client-specific behavior is blocked.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_explicit_non_authorization_and_final_authorization_statement(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            '3DA does not authorize:',
            'Runtime implementation in 3DA.',
            'Backend route implementation in 3DA.',
            'Controller/request/resource/service implementation in 3DA.',
            'Policy/middleware implementation in 3DA.',
            'Frontend implementation in 3DA.',
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
            'Milestone 3DA authorizes only a future narrow metadata-only Member Profile Documents create/update API lane, if later approved.',
            '3DA does not implement routes, controllers, requests, resources, services, policies, middleware, frontend runtime, migration/model changes, upload/storage behavior, public/signed/download URLs, OCR, PDF, messaging, reminders, finance mutation, member import changes, deployment/SaaS behavior, client-specific behavior, DELETE behavior, or any production behavior change.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    private function authorizationDocument(): string
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
