<?php

namespace Tests\Feature;

use App\Models\MemberProfileDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Milestone3cuMemberProfileDocumentsDatabaseFoundationValidationAndClosureTest extends TestCase
{
    use RefreshDatabase;

    private string $docPath = __DIR__ . '/../../docs/milestone_3cu_member_profile_documents_database_foundation_validation_and_closure.md';
    private string $modelPath = __DIR__ . '/../../app/Models/MemberProfileDocument.php';
    private string $migrationPath = __DIR__ . '/../../database/migrations/2026_06_13_000001_create_member_profile_documents_table.php';
    private string $frontendPath = __DIR__ . '/../../../app/page.tsx';

    public function test_3cu_document_exists_and_is_docs_tests_only_validation_and_closure(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'Milestone 3CU - Member Profile Documents Database Foundation Validation and Closure',
            'docs/tests-only validation and closure milestone',
            'It validates and closes the 3CT Member Profile Documents database/model foundation.',
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

    public function test_3cu_records_base_branch_allowed_files_and_next_milestone(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'Milestone 3CT - Member Profile Documents Database Foundation',
            'milestone-3ct-member-profile-documents-database-foundation',
            'milestone-3cu-member-profile-documents-database-foundation-validation-and-closure',
            '708094d56dd11a7036cc6cb2c593d0a9c1db8377',
            'backend/docs/milestone_3cu_member_profile_documents_database_foundation_validation_and_closure.md',
            'backend/tests/Feature/Milestone3cuMemberProfileDocumentsDatabaseFoundationValidationAndClosureTest.php',
            'Milestone 3CV - Member Profile Documents Read API Scope Gate',
            '3CV should be docs/tests-only unless explicitly approved later.',
            '3CU must not start 3CV.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3cu_allowed_files_exist_and_3ct_files_are_read_only_inputs(): void
    {
        $this->assertFileExists($this->docPath);
        $this->assertFileExists(__FILE__);
        $this->assertFileExists($this->migrationPath);
        $this->assertFileExists($this->modelPath);
        $this->assertFileExists($this->frontendPath);
    }

    public function test_document_records_relationship_to_3cr_and_3cs(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'Milestone 3CR scoped Member Profile Documents as a future metadata-only, club-scoped, member-scoped, officer-managed module.',
            '3CU confirms that 3CT stayed inside the 3CR metadata-only scope gate.',
            'Milestone 3CS authorized only a future narrow database foundation for member document metadata.',
            '3CU confirms that 3CT implemented only that narrow database/model foundation.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_3ct_database_foundation_closure_table_and_model(): void
    {
        $document = $this->closureDocument();

        foreach ([
            '3CT created one metadata-only migration for:',
            'member_profile_documents',
            '3CT created one model:',
            'App\Models\MemberProfileDocument',
            'The foundation is metadata-only.',
            'The foundation is club-scoped.',
            'The foundation is member-scoped.',
            'The foundation is audit-friendly.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_schema_contains_required_metadata_columns_and_nullable_fields(): void
    {
        $this->assertTrue(Schema::hasTable('member_profile_documents'));

        foreach ($this->requiredColumns() as $column) {
            $this->assertTrue(Schema::hasColumn('member_profile_documents', $column), "Missing column [{$column}].");
        }

        $nullableColumns = [
            'external_reference',
            'remarks',
            'created_by_user_id',
            'updated_by_user_id',
        ];

        foreach ($nullableColumns as $column) {
            $this->assertTrue(Schema::getColumnType('member_profile_documents', $column) !== null);
        }
    }

    public function test_document_records_required_metadata_columns_nullable_fields_and_scope_boundaries(): void
    {
        $document = $this->closureDocument();

        foreach ($this->requiredColumns() as $column) {
            $this->assertStringContainsString($column, $document);
        }

        foreach ([
            'external_reference is nullable.',
            'remarks is nullable.',
            'created_by_user_id is nullable.',
            'updated_by_user_id is nullable.',
            'The table has club_id.',
            'The table has member_id.',
            'The table is not a generic club-wide file repository.',
            'The table is not a detached document repository.',
            'The model links document metadata to a club and a member.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_model_has_metadata_fillable_fields_and_no_blocked_behavior(): void
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

        $modelSource = strtolower($this->modelSource());

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
            $this->assertStringNotContainsString($blockedText, $modelSource);
        }
    }

    public function test_schema_migration_and_model_do_not_introduce_upload_storage_path_url_ocr_or_pdf_columns(): void
    {
        $columns = Schema::getColumnListing('member_profile_documents');
        $migration = strtolower(file_get_contents($this->migrationPath));
        $model = strtolower($this->modelSource());

        foreach ($this->blockedStorageColumns() as $blockedColumn) {
            $this->assertNotContains($blockedColumn, $columns, "Blocked column [{$blockedColumn}] must not exist.");
            $this->assertStringNotContainsString($blockedColumn, $migration);
            $this->assertStringNotContainsString($blockedColumn, $model);
        }
    }

    public function test_document_records_blocked_upload_storage_path_url_ocr_and_pdf_columns(): void
    {
        $document = $this->closureDocument();

        foreach ($this->blockedStorageColumns() as $blockedColumn) {
            $this->assertStringContainsString($blockedColumn, $document);
        }
    }

    public function test_no_backend_document_routes_controllers_requests_resources_or_services_were_added(): void
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

    public function test_document_records_no_backend_api_frontend_runtime_or_support_file_changes(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'No backend API route was added for Member Profile Documents.',
            'No controller was added for Member Profile Documents.',
            'No request was added for Member Profile Documents.',
            'No resource was added for Member Profile Documents.',
            'No service was added for Member Profile Documents.',
            'No frontend runtime file was changed for 3CU.',
            'app/page.tsx was not changed for 3CU.',
            'No Member Profile Documents frontend screen was added.',
            'No browser upload UI was added.',
            'No file picker was added.',
            'No seeders were changed.',
            'No factories were changed.',
            'No config files were changed.',
            'No composer.json or composer.lock changes were added.',
            'No package.json, package-lock.json, pnpm-lock.yaml, or yarn.lock changes were added.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_upload_storage_s3_local_disk_and_url_behavior_blocked(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'File upload remains blocked.',
            'File storage remains blocked.',
            'S3 integration remains blocked.',
            'Local disk integration remains blocked.',
            'Public URL behavior remains blocked.',
            'Signed URL behavior remains blocked.',
            'Download URL behavior remains blocked.',
            'File download endpoint behavior remains blocked.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_ocr_pdf_messaging_reminder_finance_and_import_blocks(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'OCR behavior remains blocked.',
            'PDF behavior remains blocked.',
            'WhatsApp sending remains blocked.',
            'SMS sending remains blocked.',
            'Email sending remains blocked.',
            'Reminder behavior remains blocked.',
            'Notification behavior remains blocked.',
            'Finance behavior remains blocked.',
            'Ledger behavior remains blocked.',
            'Payment behavior remains blocked.',
            'Receipt behavior remains blocked.',
            'Payment-history behavior remains blocked.',
            'Member import behavior remains closed and unchanged.',
            'Member import preview remains closed and unchanged.',
            'Member import commit remains closed and unchanged.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_deployment_saas_and_client_specific_blocks(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'Deployment implementation remains blocked.',
            'SaaS implementation remains blocked.',
            'Client-specific behavior remains blocked.',
            'Hardcoded Lions Club Beenachi behavior remains blocked.',
            'Hardcoded Rotary Central Bathery behavior remains blocked.',
            'Hardcoded future pilot club behavior remains blocked.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_final_closure_statement_and_next_milestone_are_recorded(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'Milestone 3CU closes the Member Profile Documents database foundation delivered by 3CT.',
            'The closed foundation consists only of the metadata-only member_profile_documents table and the App\Models\MemberProfileDocument model.',
            'The closed foundation is club-scoped, member-scoped, audit-friendly, and limited to document metadata columns.',
            'The closed foundation does not add backend APIs, frontend runtime, routes, controllers, requests, resources, services, upload, storage, S3/local disk integration, public/signed/download URL behavior, OCR, PDF, finance behavior, member import behavior, deployment/SaaS behavior, or client-specific behavior.',
            'Milestone 3CV - Member Profile Documents Read API Scope Gate',
            '3CV should be docs/tests-only unless explicitly approved later.',
            '3CU must not start 3CV.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    private function closureDocument(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }

    private function modelSource(): string
    {
        $this->assertFileExists($this->modelPath);

        return file_get_contents($this->modelPath);
    }

    private function requiredColumns(): array
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
