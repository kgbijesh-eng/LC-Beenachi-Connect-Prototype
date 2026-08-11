<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3ddMemberProfileDocumentsFrontendScopeGateTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3dd_member_profile_documents_frontend_scope_gate.md';
    private string $frontendPath = __DIR__ . '/../../../app/page.tsx';
    private string $routesPath = __DIR__ . '/../../routes/api.php';
    private string $controllerPath = __DIR__ . '/../../app/Http/Controllers/Api/V1/Members/MemberProfileDocumentController.php';
    private string $storeRequestPath = __DIR__ . '/../../app/Http/Requests/Members/StoreMemberProfileDocumentRequest.php';
    private string $updateRequestPath = __DIR__ . '/../../app/Http/Requests/Members/UpdateMemberProfileDocumentRequest.php';
    private string $writeServicePath = __DIR__ . '/../../app/Services/Members/MemberProfileDocumentWriteService.php';
    private string $resourcePath = __DIR__ . '/../../app/Http/Resources/Members/MemberProfileDocumentResource.php';
    private string $modelPath = __DIR__ . '/../../app/Models/MemberProfileDocument.php';
    private string $migrationPath = __DIR__ . '/../../database/migrations/2026_06_13_000001_create_member_profile_documents_table.php';

    public function test_3dd_document_exists_and_is_docs_tests_only_frontend_scope_gate(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Milestone 3DD - Member Profile Documents Frontend Scope Gate',
            'Milestone 3DD is a docs/tests-only frontend scope gate milestone.',
            '3DD defines the future frontend boundary for Member Profile Documents.',
            '3DD does not implement frontend runtime.',
            '3DD does not edit app/page.tsx.',
            '3DD does not change backend runtime behavior.',
            '3DD does not add routes, controllers, requests, resources, services, models, migrations, policies, middleware, seeders, factories, config, package files, or lock files.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3dd_records_base_branch_allowed_files_and_next_milestone(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'milestone-3dc-member-profile-documents-create-update-validation-closure',
            '2c5b8a05677647b14f56b969018966605cb31de3',
            'milestone-3dd-member-profile-documents-frontend-scope-gate',
            'backend/docs/milestone_3dd_member_profile_documents_frontend_scope_gate.md',
            'backend/tests/Feature/Milestone3ddMemberProfileDocumentsFrontendScopeGateTest.php',
            'Milestone 3DE - Member Profile Documents Frontend Implementation Authorization',
            '3DE may only authorize a future narrow frontend implementation if explicitly approved later.',
            '3DD must not start 3DE.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3dd_allowed_files_exist_and_runtime_files_are_read_only_inputs(): void
    {
        foreach ([
            $this->docPath,
            __FILE__,
            $this->frontendPath,
            $this->routesPath,
            $this->controllerPath,
            $this->storeRequestPath,
            $this->updateRequestPath,
            $this->writeServicePath,
            $this->resourcePath,
            $this->modelPath,
            $this->migrationPath,
        ] as $path) {
            $this->assertFileExists($path);
        }
    }

    public function test_document_records_relationships_to_closed_backend_lanes(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Milestone 3CT created the Member Profile Documents database foundation.',
            'Milestone 3CU validated and closed the database foundation.',
            'Milestone 3CV scoped the read API lane.',
            'Milestone 3CW authorized the read API implementation.',
            'Milestone 3CX implemented the narrow read API.',
            'Milestone 3CY validated and closed the read API lane.',
            'Milestone 3CZ scoped the create/update API boundary.',
            'Milestone 3DA authorized the narrow metadata-only create/update implementation lane.',
            'Milestone 3DB implemented the narrow metadata-only create/update API.',
            'Milestone 3DC validated and closed the create/update API lane.',
            '3DD confirms that the backend create/update lane is closed before any future frontend scope is considered.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_existing_foundation_and_backend_route_boundaries(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'member_profile_documents',
            'App\Models\MemberProfileDocument',
            'GET /api/v1/clubs/{club}/members/{member}/profile-documents',
            'GET /api/v1/clubs/{club}/members/{member}/profile-documents/{document}',
            'POST /api/v1/clubs/{club}/members/{member}/profile-documents',
            'PATCH /api/v1/clubs/{club}/members/{member}/profile-documents/{document}',
            'DELETE remains out of scope.',
            'File upload/download/storage/OCR/PDF remains out of scope.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }

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

    public function test_document_records_future_frontend_section_and_api_call_boundary(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'A future frontend implementation may include only a Member Profile Documents section inside the existing member/member-management UI.',
            'Profile Documents section title.',
            'Document list/table/card display.',
            'Single document detail display if useful.',
            'Metadata-only create form.',
            'Metadata-only edit form.',
            'Role-aware UI controls.',
            '3DD does not implement this frontend section.',
            'The future frontend may call only these existing backend routes:',
            'The future frontend must not call or assume:',
            'DELETE /api/v1/clubs/{club}/members/{member}/profile-documents/{document}',
            'upload endpoint',
            'download endpoint',
            'public URL endpoint',
            'signed URL endpoint',
            'OCR endpoint',
            'PDF endpoint',
            'storage endpoint',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_future_writable_and_blocked_metadata_boundaries(): void
    {
        $document = $this->scopeDocument();

        foreach ($this->writableMetadataFields() as $field) {
            $this->assertStringContainsString($field, $document);
        }

        foreach ($this->ownershipAuditFields() as $field) {
            $this->assertStringContainsString($field, $document);
        }

        foreach ($this->blockedFileFields() as $field) {
            $this->assertStringContainsString($field, $document);
        }

        foreach ([
            'Club/member/user ownership must remain backend-derived.',
            'Frontend must not pretend to enforce security.',
            'Backend authorization remains authoritative.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_future_role_control_boundary(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'President/Admin may see create/edit metadata controls for members inside their own club.',
            'Secretary may see create/edit metadata controls for members inside their own club.',
            'Treasurer must not see create/edit metadata controls unless later explicitly authorized.',
            'Normal Member must not see create/edit metadata controls unless later explicitly authorized.',
            "Normal Member must not see controls to create/update another member's document metadata.",
            'Cross-club behavior must fail closed through backend authorization.',
            'UI hiding is convenience only; backend remains the enforcement layer.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_future_ui_states_and_forbidden_ui_behavior(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Loading state.',
            'Empty state.',
            'Safe error state.',
            'Validation message display.',
            'Add metadata button for authorized roles only.',
            'Edit metadata button for authorized roles only.',
            'Cancel/save behavior.',
            'file upload',
            'drag-and-drop upload',
            'attachment picker',
            'file preview',
            'file download',
            'public link',
            'signed link',
            'delete button',
            'OCR extraction',
            'PDF generation',
            'WhatsApp/SMS/email/reminder action',
            'finance mutation',
            'member import',
            'deployment/SaaS/client-specific behavior',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_explicit_non_authorization_and_blocked_files_scope(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Frontend runtime implementation.',
            'app/page.tsx changes.',
            'Backend runtime changes.',
            'Route/controller/request/resource/service changes.',
            'Model/migration changes.',
            'Policy/middleware changes.',
            'Package or lock file changes.',
            'DELETE behavior.',
            'File upload.',
            'File storage.',
            'File download.',
            'Public URLs.',
            'Signed URLs.',
            'OCR.',
            'PDF generation.',
            'Finance/import/deployment/SaaS/client-specific behavior.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    private function scopeDocument(): string
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

    private function ownershipAuditFields(): array
    {
        return [
            'club_id',
            'member_id',
            'created_by_user_id',
            'updated_by_user_id',
        ];
    }

    private function blockedFileFields(): array
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
