<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3cvMemberProfileDocumentsReadApiScopeGateTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3cv_member_profile_documents_read_api_scope_gate.md';
    private string $modelPath = __DIR__ . '/../../app/Models/MemberProfileDocument.php';
    private string $migrationPath = __DIR__ . '/../../database/migrations/2026_06_13_000001_create_member_profile_documents_table.php';
    private string $frontendPath = __DIR__ . '/../../../app/page.tsx';

    public function test_3cv_document_exists_and_is_docs_tests_only_scope_gate(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'Milestone 3CV - Member Profile Documents Read API Scope Gate',
            'docs/tests-only read API scope gate milestone',
            'It defines the future Member Profile Documents Read API boundary only.',
            'It does not implement read APIs.',
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

    public function test_3cv_records_base_branch_allowed_files_and_next_milestone(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'Milestone 3CU - Member Profile Documents Database Foundation Validation and Closure',
            'milestone-3cu-member-profile-documents-database-foundation-validation-and-closure',
            'milestone-3cv-member-profile-documents-read-api-scope-gate',
            '709188f2a9fe0d342e9bb34afccefa297324b826',
            'backend/docs/milestone_3cv_member_profile_documents_read_api_scope_gate.md',
            'backend/tests/Feature/Milestone3cvMemberProfileDocumentsReadApiScopeGateTest.php',
            'Milestone 3CW - Member Profile Documents Read API Implementation Authorization',
            '3CW should remain docs/tests-only unless explicitly approved otherwise.',
            '3CV must not start 3CW.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3cv_allowed_files_exist_and_foundation_files_are_read_only_inputs(): void
    {
        $this->assertFileExists($this->docPath);
        $this->assertFileExists(__FILE__);
        $this->assertFileExists($this->migrationPath);
        $this->assertFileExists($this->modelPath);
        $this->assertFileExists($this->frontendPath);
    }

    public function test_document_records_relationship_to_3cr_3cs_3ct_and_3cu(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'Milestone 3CR scoped Member Profile Documents as a future metadata-only, club-scoped, member-scoped, officer-managed module.',
            '3CV stays inside that metadata-only scope gate.',
            'Milestone 3CS authorized only a future narrow database foundation lane for member document metadata.',
            '3CV builds on that authorization by scoping future reads only.',
            'Milestone 3CT created the metadata-only database/model foundation.',
            'Milestone 3CU validated and closed the 3CT database/model foundation.',
            '3CV records that the database foundation is closed before read API authorization begins.',
            '3CV does not reopen the database foundation.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_existing_table_model_and_member_import_closure(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'member_profile_documents',
            'App\Models\MemberProfileDocument',
            '3CV keeps that foundation unchanged.',
            'The Member Data Import module remains closed.',
            'Member import preview remains closed and unchanged.',
            'Member import commit remains closed and unchanged.',
            '3CV does not change member import behavior.',
            '3CV does not add Excel import, CSV import, upload, parser, storage, queue, or import mutation behavior.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_future_read_api_metadata_only_concept_and_candidate_routes(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'Future read API may later expose metadata-only document records linked to members.',
            'Future read API must be metadata-only.',
            'Future read API must not expose physical files.',
            'GET /api/v1/clubs/{club}/members/{member}/profile-documents',
            'GET /api/v1/clubs/{club}/members/{member}/profile-documents/{document}',
            'These routes are only future candidates.',
            '3CV does not add these routes.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_candidate_profile_document_routes_are_not_registered_yet(): void
    {
        $registeredUris = collect(Route::getRoutes())
            ->map(fn ($route): string => $route->uri())
            ->values();

        $this->assertFalse($registeredUris->contains('api/v1/clubs/{club}/members/{member}/profile-documents'));
        $this->assertFalse($registeredUris->contains('api/v1/clubs/{club}/members/{member}/profile-documents/{document}'));
    }

    public function test_document_records_future_access_boundaries(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'Future read API should be club-scoped.',
            'Future read API should be member-scoped.',
            'Future read API should be metadata-only.',
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
        $document = $this->scopeGateDocument();

        foreach ($this->metadataResponseFields() as $field) {
            $this->assertStringContainsString($field, $document);
        }
    }

    public function test_document_records_blocked_file_path_storage_url_ocr_pdf_and_binary_response_fields(): void
    {
        $document = $this->scopeGateDocument();

        foreach ($this->blockedResponseFields() as $field) {
            $this->assertStringContainsString($field, $document);
        }
    }

    public function test_model_and_migration_remain_metadata_only_without_blocked_fields(): void
    {
        $model = strtolower(file_get_contents($this->modelPath));
        $migration = strtolower(file_get_contents($this->migrationPath));

        foreach ($this->metadataResponseFields() as $field) {
            if ($field === 'id' || $field === 'created_at' || $field === 'updated_at') {
                continue;
            }

            $this->assertStringContainsString($field, $model.$migration);
        }

        foreach ($this->blockedResponseFields() as $field) {
            $this->assertStringNotContainsString($field, $model);
            $this->assertStringNotContainsString($field, $migration);
        }
    }

    public function test_document_records_runtime_api_policy_middleware_frontend_migration_and_model_blocks(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'Runtime implementation is blocked in 3CV.',
            'Backend route implementation is blocked in 3CV.',
            'Controller implementation is blocked in 3CV.',
            'Request implementation is blocked in 3CV.',
            'Resource implementation is blocked in 3CV.',
            'Service implementation is blocked in 3CV.',
            'Policy implementation is blocked in 3CV.',
            'Middleware implementation is blocked in 3CV.',
            'Frontend implementation is blocked in 3CV.',
            'Migration/table changes are blocked in 3CV.',
            'Model changes are blocked in 3CV.',
            'Any production behavior change is blocked in 3CV.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_no_runtime_read_api_artifacts_exist_for_profile_documents(): void
    {
        foreach ([
            app_path('Http/Controllers/Api/V1/Members/MemberProfileDocumentController.php'),
            app_path('Http/Requests/Members/MemberProfileDocumentRequest.php'),
            app_path('Http/Resources/Members/MemberProfileDocumentResource.php'),
            app_path('Services/Members/MemberProfileDocumentService.php'),
            app_path('Policies/MemberProfileDocumentPolicy.php'),
        ] as $blockedPath) {
            $this->assertFileDoesNotExist($blockedPath);
        }
    }

    public function test_document_records_file_upload_storage_s3_local_disk_and_url_blocks(): void
    {
        $document = $this->scopeGateDocument();

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
        $document = $this->scopeGateDocument();

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

    public function test_explicit_non_authorization_and_final_scope_statement_are_recorded(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            '3CV does not authorize:',
            'Runtime implementation',
            'Backend route implementation',
            'Controller implementation',
            'Request implementation',
            'Resource implementation',
            'Service implementation',
            'Policy implementation',
            'Middleware implementation',
            'Frontend implementation',
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
            'Milestone 3CV scopes a future Member Profile Documents read API only.',
            'The future API must be club-scoped, member-scoped, metadata-only, privacy-protective, and fail closed across clubs.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_expected_next_milestone_is_recorded(): void
    {
        $document = $this->scopeGateDocument();

        foreach ([
            'Milestone 3CW - Member Profile Documents Read API Implementation Authorization',
            '3CW should remain docs/tests-only unless explicitly approved otherwise.',
            '3CV must not start 3CW.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    private function scopeGateDocument(): string
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
