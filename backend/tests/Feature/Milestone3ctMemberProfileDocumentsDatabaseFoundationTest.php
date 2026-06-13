<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Member;
use App\Models\MemberProfileDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Milestone3ctMemberProfileDocumentsDatabaseFoundationTest extends TestCase
{
    use RefreshDatabase;

    private string $docPath = __DIR__ . '/../../docs/milestone_3ct_member_profile_documents_database_foundation.md';
    private string $modelPath = __DIR__ . '/../../app/Models/MemberProfileDocument.php';
    private string $migrationPath = __DIR__ . '/../../database/migrations/2026_06_13_000001_create_member_profile_documents_table.php';
    private string $frontendPath = __DIR__ . '/../../../app/page.tsx';

    public function test_3ct_document_exists_and_records_narrow_database_foundation_scope(): void
    {
        $document = $this->foundationDocument();

        foreach ([
            'Milestone 3CT - Member Profile Documents Database Foundation',
            'narrow backend database foundation implementation',
            'It creates only a metadata-only table and model for future Member Profile Documents behavior.',
            'It does not add frontend runtime behavior.',
            'It does not add backend API routes, controllers, requests, resources, or services.',
            'It does not add file upload, file storage, S3/local disk integration, OCR, PDF generation, finance behavior, deployment/SaaS behavior, or client-specific behavior.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3ct_records_base_branch_allowed_files_and_next_milestone(): void
    {
        $document = $this->foundationDocument();

        foreach ([
            'Milestone 3CS - Member Profile Documents Implementation Authorization',
            'milestone-3cs-member-profile-documents-implementation-authorization',
            'milestone-3ct-member-profile-documents-database-foundation',
            '065dd8dfe7f70aa165d6e9d4cf9667baa725eb00',
            'backend/database/migrations/2026_06_13_000001_create_member_profile_documents_table.php',
            'backend/app/Models/MemberProfileDocument.php',
            'backend/docs/milestone_3ct_member_profile_documents_database_foundation.md',
            'backend/tests/Feature/Milestone3ctMemberProfileDocumentsDatabaseFoundationTest.php',
            'Only one new migration file is allowed.',
            'Milestone 3CU - Member Profile Documents Database Foundation Validation and Closure',
            '3CT must not start 3CU.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3ct_approved_files_exist(): void
    {
        $this->assertFileExists($this->migrationPath);
        $this->assertFileExists($this->modelPath);
        $this->assertFileExists($this->docPath);
        $this->assertFileExists(__FILE__);
    }

    public function test_member_profile_documents_table_can_be_migrated_with_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('member_profile_documents'));

        foreach ([
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
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('member_profile_documents', $column), "Missing column [{$column}].");
        }
    }

    public function test_nullable_metadata_and_audit_columns_accept_null_values(): void
    {
        $club = Club::query()->create([
            'name' => 'Test Club',
            'code' => 'DOC-CLUB',
            'status' => 'active',
        ]);

        $member = Member::query()->create([
            'club_id' => $club->id,
            'member_code' => 'DOC-MEMBER-001',
            'full_name' => 'Document Test Member',
            'status' => 'active',
            'blood_donor_consent' => false,
        ]);

        $document = MemberProfileDocument::query()->create([
            'club_id' => $club->id,
            'member_id' => $member->id,
            'document_type' => 'id_proof',
            'status' => 'pending',
            'external_reference' => null,
            'remarks' => null,
            'created_by_user_id' => null,
            'updated_by_user_id' => null,
        ]);

        $this->assertDatabaseHas('member_profile_documents', [
            'id' => $document->id,
            'club_id' => $club->id,
            'member_id' => $member->id,
            'document_type' => 'id_proof',
            'status' => 'pending',
            'external_reference' => null,
            'remarks' => null,
            'created_by_user_id' => null,
            'updated_by_user_id' => null,
        ]);
    }

    public function test_member_profile_documents_table_has_no_upload_storage_url_ocr_or_pdf_columns(): void
    {
        $columns = Schema::getColumnListing('member_profile_documents');

        foreach ($this->blockedStorageColumns() as $blockedColumn) {
            $this->assertNotContains($blockedColumn, $columns, "Blocked column [{$blockedColumn}] must not exist.");
        }
    }

    public function test_member_profile_document_model_contains_metadata_fillable_fields(): void
    {
        $model = new MemberProfileDocument();

        $this->assertSame([
            'club_id',
            'member_id',
            'document_type',
            'status',
            'external_reference',
            'remarks',
            'created_by_user_id',
            'updated_by_user_id',
        ], $model->getFillable());
    }

    public function test_member_profile_document_model_relationships_are_metadata_only(): void
    {
        $club = Club::query()->create([
            'name' => 'Relationship Club',
            'code' => 'DOC-REL',
            'status' => 'active',
        ]);
        $member = Member::query()->create([
            'club_id' => $club->id,
            'member_code' => 'DOC-REL-001',
            'full_name' => 'Relationship Member',
            'status' => 'active',
            'blood_donor_consent' => false,
        ]);
        $creator = User::factory()->create();
        $updater = User::factory()->create();

        $document = MemberProfileDocument::query()->create([
            'club_id' => $club->id,
            'member_id' => $member->id,
            'document_type' => 'membership_form',
            'status' => 'verified',
            'external_reference' => 'FORM-001',
            'remarks' => 'Manual reference verified.',
            'created_by_user_id' => $creator->id,
            'updated_by_user_id' => $updater->id,
        ]);

        $this->assertTrue($document->club->is($club));
        $this->assertTrue($document->member->is($member));
        $this->assertTrue($document->creator->is($creator));
        $this->assertTrue($document->updater->is($updater));
    }

    public function test_model_source_does_not_contain_upload_storage_url_ocr_or_pdf_behavior(): void
    {
        $source = strtolower($this->modelSource());

        foreach ([
            'upload',
            'storage',
            'file_path',
            'public_url',
            'signed_url',
            'download',
            'ocr',
            'pdf',
            'base64',
            'binary',
        ] as $blockedText) {
            $this->assertStringNotContainsString($blockedText, $source);
        }
    }

    public function test_no_backend_document_routes_controllers_requests_resources_or_services_are_added(): void
    {
        $routes = collect(Route::getRoutes())
            ->map(fn ($route): string => $route->uri())
            ->filter(fn (string $uri): bool => str_contains($uri, 'document'))
            ->values()
            ->all();

        $this->assertSame([], $routes);

        foreach ([
            app_path('Http/Controllers/Api/V1/Members/MemberProfileDocumentController.php'),
            app_path('Http/Requests/Members/MemberProfileDocumentRequest.php'),
            app_path('Http/Resources/Members/MemberProfileDocumentResource.php'),
            app_path('Services/Members/MemberProfileDocumentService.php'),
        ] as $blockedPath) {
            $this->assertFileDoesNotExist($blockedPath);
        }
    }

    public function test_no_frontend_runtime_seeders_factories_config_or_package_files_are_changed_by_scope(): void
    {
        $document = $this->foundationDocument();

        foreach ([
            'No frontend runtime implementation is added in 3CT.',
            'app/page.tsx is not edited in 3CT.',
            'No deployment implementation is added.',
            'No SaaS implementation is added.',
            'No client-specific behavior is added.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }

        $this->assertFileExists($this->frontendPath);
    }

    public function test_document_records_metadata_only_table_model_and_relationship_boundaries(): void
    {
        $document = $this->foundationDocument();

        foreach ([
            'member_profile_documents',
            'The table is metadata-only.',
            'The table is club-scoped.',
            'The table is member-scoped.',
            'document_type is a short string suitable for values such as profile_photo, membership_form, id_proof, address_proof, consent_form, or general.',
            'status is a short string suitable for values such as pending, verified, rejected, or archived.',
            'No enum constraint is added for document_type or status in 3CT.',
            'App\Models\MemberProfileDocument',
            'The MemberProfileDocument model fillable fields are metadata-only:',
            'The model does not include file upload, file storage, URL generation, OCR, PDF, or download behavior.',
            'The MemberProfileDocument model may define relationships to:',
            'No file, storage, upload, OCR, PDF, finance, member import, committee, fellowship, activity, or event relationship is added.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_blocked_upload_storage_path_url_ocr_and_pdf_columns(): void
    {
        $document = $this->foundationDocument();

        foreach ($this->blockedStorageColumns() as $blockedColumn) {
            $this->assertStringContainsString($blockedColumn, $document);
        }

        $this->assertStringContainsString('No physical file storage is allowed in 3CT.', $document);
    }

    public function test_document_records_api_frontend_file_storage_ocr_pdf_finance_import_saas_and_client_blocks(): void
    {
        $document = $this->foundationDocument();

        foreach ([
            'No API route is added in 3CT.',
            'No controller is added in 3CT.',
            'No request is added in 3CT.',
            'No resource is added in 3CT.',
            'No service is added in 3CT.',
            'No frontend runtime implementation is added in 3CT.',
            'No file upload is added.',
            'No file storage is added.',
            'No S3 integration is added.',
            'No local disk integration is added.',
            'No public URL behavior is added.',
            'No signed URL behavior is added.',
            'No download URL behavior is added.',
            'No file download endpoint behavior is added.',
            'No OCR is added.',
            'No PDF generation is added.',
            'No WhatsApp sending is added.',
            'No SMS sending is added.',
            'No email sending is added.',
            'No reminder behavior is added.',
            'No finance behavior is added.',
            'No ledger behavior is added.',
            'No payment behavior is added.',
            'No receipt behavior is added.',
            'No payment-history behavior is added.',
            'No member import behavior is added.',
            'No deployment implementation is added.',
            'No SaaS implementation is added.',
            'No client-specific behavior is added.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_final_foundation_statement_and_next_milestone_are_recorded(): void
    {
        $document = $this->foundationDocument();

        foreach ([
            'Milestone 3CT creates only the Member Profile Documents database/model foundation.',
            'The foundation consists of one metadata-only member_profile_documents table and one MemberProfileDocument model.',
            'The foundation is club-scoped, member-scoped, audit-friendly, and limited to document metadata fields.',
            'The foundation does not add APIs, frontend screens, upload, storage, OCR, PDF, messaging, reminders, finance behavior, member import behavior, deployment/SaaS behavior, or client-specific behavior.',
            'Milestone 3CU - Member Profile Documents Database Foundation Validation and Closure',
            '3CU should be docs/tests-only validation and closure unless explicitly approved later.',
            '3CT must not start 3CU.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    private function foundationDocument(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }

    private function modelSource(): string
    {
        $this->assertFileExists($this->modelPath);

        return file_get_contents($this->modelPath);
    }

    private function blockedStorageColumns(): array
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
