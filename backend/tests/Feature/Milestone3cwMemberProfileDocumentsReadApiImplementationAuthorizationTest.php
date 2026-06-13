<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3cwMemberProfileDocumentsReadApiImplementationAuthorizationTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3cw_member_profile_documents_read_api_implementation_authorization.md';
    private string $modelPath = __DIR__ . '/../../app/Models/MemberProfileDocument.php';
    private string $migrationPath = __DIR__ . '/../../database/migrations/2026_06_13_000001_create_member_profile_documents_table.php';
    private string $frontendPath = __DIR__ . '/../../../app/page.tsx';

    public function test_3cw_document_exists_and_is_docs_tests_only_authorization(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'Milestone 3CW - Member Profile Documents Read API Implementation Authorization',
            'docs/tests-only implementation authorization milestone',
            'It converts the 3CV Member Profile Documents Read API scope gate into a precise future implementation authorization boundary.',
            'It does not implement the read API.',
            'It does not change production code.',
            'It does not edit app/page.tsx.',
            'It does not change frontend runtime files.',
            'It does not change backend runtime files or API behavior.',
            'It does not change the 3CT migration.',
            'It does not change MemberProfileDocument.php.',
            'It does not change older milestone tests.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3cw_records_base_branch_allowed_files_and_next_milestone(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'Milestone 3CV - Member Profile Documents Read API Scope Gate',
            'milestone-3cv-member-profile-documents-read-api-scope-gate',
            'milestone-3cw-member-profile-documents-read-api-implementation-authorization',
            '570dfb832674fa155954ca7f9cfea401d670c505',
            'backend/docs/milestone_3cw_member_profile_documents_read_api_implementation_authorization.md',
            'backend/tests/Feature/Milestone3cwMemberProfileDocumentsReadApiImplementationAuthorizationTest.php',
            'Milestone 3CX - Member Profile Documents Read API Narrow Implementation',
            '3CX may implement only the narrow metadata-only read API if explicitly approved later.',
            '3CW must not start 3CX.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3cw_allowed_files_exist_and_foundation_files_are_read_only_inputs(): void
    {
        $this->assertFileExists($this->docPath);
        $this->assertFileExists(__FILE__);
        $this->assertFileExists($this->migrationPath);
        $this->assertFileExists($this->modelPath);
        $this->assertFileExists($this->frontendPath);
    }

    public function test_document_records_relationship_to_3cr_3cs_3ct_3cu_and_3cv(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'Milestone 3CR scoped Member Profile Documents as a future metadata-only, club-scoped, member-scoped, officer-managed module.',
            '3CW stays inside that metadata-only scope gate.',
            'Milestone 3CS authorized only a future narrow database foundation lane for member document metadata.',
            '3CW builds on that authorization by authorizing future metadata-only reads only.',
            'Milestone 3CT created the metadata-only database/model foundation.',
            'Milestone 3CU validated and closed the 3CT database/model foundation.',
            'Milestone 3CV scoped the future Member Profile Documents Read API.',
            '3CW converts that scope gate into a future implementation authorization boundary.',
            '3CW preserves the 3CV metadata-only response, access, and non-file boundaries.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_existing_foundation_and_member_import_closure(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'member_profile_documents',
            'App\Models\MemberProfileDocument',
            '3CW keeps that foundation unchanged.',
            'The Member Data Import module remains closed.',
            'Member import preview remains closed and unchanged.',
            'Member import commit remains closed and unchanged.',
            '3CW does not change member import behavior.',
            '3CW does not add Excel import, CSV import, upload, parser, storage, queue, or import mutation behavior.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_future_metadata_only_read_api_authorization_and_candidate_routes(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            '3CW may authorize a future narrow metadata-only read API implementation.',
            'GET /api/v1/clubs/{club}/members/{member}/profile-documents',
            'GET /api/v1/clubs/{club}/members/{member}/profile-documents/{document}',
            'These routes are future-authorized candidates only.',
            '3CW must not add these routes.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_only_3cx_read_only_candidate_profile_document_routes_are_registered_after_stale_guard_update(): void
    {
        $approvedRoutes = [
            'api/v1/clubs/{club}/members/{member}/profile-documents',
            'api/v1/clubs/{club}/members/{member}/profile-documents/{document}',
        ];

        $routes = collect(Route::getRoutes())
            ->filter(fn ($route): bool => in_array($route->uri(), $approvedRoutes, true));

        $this->assertSame($approvedRoutes, $routes->map(fn ($route): string => $route->uri())->values()->all());

        $routes->each(function ($route): void {
            $this->assertSame(['GET', 'HEAD'], $route->methods());
        });

        $blockedRoutes = collect(Route::getRoutes())
            ->filter(fn ($route): bool => str_contains($route->uri(), 'profile-documents'))
            ->filter(function ($route): bool {
                $uri = $route->uri();

                return ! in_array($uri, [
                    'api/v1/clubs/{club}/members/{member}/profile-documents',
                    'api/v1/clubs/{club}/members/{member}/profile-documents/{document}',
                ], true)
                    || count(array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE'])) > 0
                    || str_contains($uri, 'upload')
                    || str_contains($uri, 'download')
                    || str_contains($uri, 'ocr')
                    || str_contains($uri, 'pdf');
            })
            ->values()
            ->all();

        $this->assertSame([], $blockedRoutes);
    }

    public function test_document_records_future_read_only_implementation_boundary(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'Read-only route definitions',
            'Read-only controller',
            'Read-only resource',
            'Read-only query/service if needed',
            'Feature tests for authorized read access',
            'Metadata-only response',
            'Club-scoped access checks',
            'Member-scoped access checks',
            'Cross-club fail-closed checks',
            'The future read API must remain read-only.',
            'The future read API must not create, update, delete, upload, download, generate, send, or mutate anything.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_future_access_boundaries(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'Future read API should be club-scoped.',
            'Future read API should be member-scoped.',
            'Future read API should be metadata-only.',
            'Future read API should be read-only.',
            'President/Admin and Secretary may read member profile document metadata in a later implementation authorization.',
            'Treasurer access must remain restricted and should be read-only only if explicitly authorized later.',
            'Normal Member may read only their own allowed document metadata if later authorized.',
            "Normal Member must not read other members' document metadata.",
            'Cross-club access must fail closed.',
            'Sensitive/private metadata must be protected.',
            'Read API must never expose file paths, storage disks, URLs, OCR text, binary data, or physical file data.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_future_metadata_only_response_fields(): void
    {
        $document = $this->authorizationDocument();

        foreach ($this->metadataResponseFields() as $field) {
            $this->assertStringContainsString($field, $document);
        }
    }

    public function test_document_records_blocked_file_path_storage_url_ocr_pdf_and_binary_response_fields(): void
    {
        $document = $this->authorizationDocument();

        foreach ($this->blockedResponseFields() as $field) {
            $this->assertStringContainsString($field, $document);
        }
    }

    public function test_model_and_migration_remain_metadata_only_without_blocked_fields(): void
    {
        $model = strtolower(file_get_contents($this->modelPath));
        $migration = strtolower(file_get_contents($this->migrationPath));

        foreach ($this->blockedResponseFields() as $field) {
            $this->assertStringNotContainsString($field, $model);
            $this->assertStringNotContainsString($field, $migration);
        }
    }

    public function test_document_records_runtime_api_policy_middleware_frontend_migration_and_model_blocks_in_3cw(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'Runtime implementation in 3CW is blocked.',
            'Backend route implementation in 3CW is blocked.',
            'Controller implementation in 3CW is blocked.',
            'Request implementation in 3CW is blocked.',
            'Resource implementation in 3CW is blocked.',
            'Service implementation in 3CW is blocked.',
            'Policy implementation in 3CW is blocked.',
            'Middleware implementation in 3CW is blocked.',
            'Frontend implementation in 3CW is blocked.',
            'Migration/table changes are blocked.',
            'Model changes are blocked.',
            'Any production behavior change is blocked in 3CW.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_only_3cx_read_only_runtime_artifacts_exist_for_profile_documents_after_stale_guard_update(): void
    {
        foreach ([
            app_path('Http/Controllers/Api/V1/Members/MemberProfileDocumentController.php'),
            app_path('Http/Resources/Members/MemberProfileDocumentResource.php'),
            app_path('Services/Members/MemberProfileDocumentReadService.php'),
        ] as $approvedPath) {
            $this->assertFileExists($approvedPath);
        }

        foreach ([
            app_path('Http/Requests/Members/MemberProfileDocumentRequest.php'),
            app_path('Services/Members/MemberProfileDocumentService.php'),
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

    public function test_no_unapproved_runtime_read_api_artifacts_exist_for_profile_documents(): void
    {
        foreach ([
            app_path('Http/Requests/Members/MemberProfileDocumentRequest.php'),
            app_path('Services/Members/MemberProfileDocumentService.php'),
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

    public function test_document_records_file_upload_storage_s3_local_disk_and_url_blocks(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'File upload is blocked.',
            'File storage is blocked.',
            'S3 integration is blocked.',
            'Local disk integration is blocked.',
            'Public file URL behavior is blocked.',
            'Signed file URL behavior is blocked.',
            'Download URL behavior is blocked.',
            'File download endpoint behavior is blocked.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_ocr_pdf_messaging_finance_import_deployment_and_client_blocks(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'OCR is blocked.',
            'PDF generation is blocked.',
            'WhatsApp sending is blocked.',
            'SMS sending is blocked.',
            'Email sending is blocked.',
            'Reminder behavior is blocked.',
            'Notification behavior is blocked.',
            'Finance mutation is blocked.',
            'Ledger mutation is blocked.',
            'Payment mutation is blocked.',
            'Receipt mutation is blocked.',
            'Payment-history changes are blocked.',
            'Member import changes are blocked.',
            'Committee changes are blocked.',
            'Fellowship changes are blocked.',
            'Activity changes are blocked.',
            'Event changes are blocked.',
            'Deployment implementation is blocked.',
            'SaaS implementation is blocked.',
            'Client-specific behavior is blocked.',
            'Hardcoded Lions Club Beenachi behavior is blocked.',
            'Hardcoded Rotary Central Bathery behavior is blocked.',
            'Hardcoded future pilot club behavior is blocked.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_explicit_non_authorization_and_final_authorization_statement_are_recorded(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            '3CW does not authorize:',
            'Runtime implementation in 3CW',
            'Backend route implementation in 3CW',
            'Controller implementation in 3CW',
            'Request implementation in 3CW',
            'Resource implementation in 3CW',
            'Service implementation in 3CW',
            'Policy implementation in 3CW',
            'Middleware implementation in 3CW',
            'Frontend implementation in 3CW',
            'Migration/table changes',
            'Model changes',
            'File upload',
            'File storage',
            'S3/local disk integration',
            'Public file URLs',
            'Signed file URLs',
            'File download endpoints',
            'OCR',
            'PDF generation',
            'WhatsApp/SMS/email sending',
            'Reminder behavior',
            'Member import changes',
            'Finance/ledger/payment/receipt/payment-history changes',
            'Deployment/SaaS/client-specific behavior',
            'Any production behavior change',
            'Milestone 3CW authorizes only a future narrow metadata-only Member Profile Documents read API lane, if later approved.',
            '3CW does not implement routes, controllers, requests, resources, services, policies, middleware, frontend runtime, migration/model changes, upload/storage behavior, public/signed/download URLs, OCR, PDF, messaging, finance mutation, member import changes, deployment/SaaS behavior, client-specific behavior, or any production behavior change.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_expected_next_milestone_is_recorded(): void
    {
        $document = $this->authorizationDocument();

        foreach ([
            'Milestone 3CX - Member Profile Documents Read API Narrow Implementation',
            '3CX may implement only the narrow metadata-only read API if explicitly approved later.',
            '3CW must not start 3CX.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    private function authorizationDocument(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }

    private function metadataResponseFields(): array
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
