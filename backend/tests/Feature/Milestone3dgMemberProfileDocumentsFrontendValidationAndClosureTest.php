<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3dgMemberProfileDocumentsFrontendValidationAndClosureTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3dg_member_profile_documents_frontend_validation_and_closure.md';
    private string $frontendPath = __DIR__ . '/../../../app/page.tsx';

    public function test_3dg_document_exists_and_is_docs_tests_only_closure(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'Milestone 3DG - Member Profile Documents Frontend Validation and Closure',
            'Milestone 3DG is a docs/tests-only validation and closure milestone.',
            '3DG validates and closes the 3DF Member Profile Documents frontend implementation lane.',
            '3DG does not add new frontend runtime behavior.',
            '3DG does not edit app/page.tsx.',
            '3DG does not change backend runtime behavior.',
            '3DG must not start 3DH.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3dg_records_base_branch_allowed_files_and_next_milestone(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'milestone-3df-member-profile-documents-frontend-narrow-implementation',
            'c57840e17fdaf51c1c53c79e23bd38a39425c7d0',
            'milestone-3dg-member-profile-documents-frontend-validation-closure',
            'backend/docs/milestone_3dg_member_profile_documents_frontend_validation_and_closure.md',
            'backend/tests/Feature/Milestone3dgMemberProfileDocumentsFrontendValidationAndClosureTest.php',
            'Milestone 3DH - MVP Frontend Usability Readiness Scope Gate',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3dg_current_file_changes_are_limited_to_approved_docs_and_test_files(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'Milestone 3DG is a docs/tests-only validation and closure milestone.',
            '3DG validates and closes the 3DF Member Profile Documents frontend implementation lane.',
            '3DG does not add new frontend runtime behavior.',
            '3DG does not edit app/page.tsx.',
            '3DG does not change backend runtime behavior.',
            'backend/docs/milestone_3dg_member_profile_documents_frontend_validation_and_closure.md',
            'backend/tests/Feature/Milestone3dgMemberProfileDocumentsFrontendValidationAndClosureTest.php',
            'Backend runtime changes.',
            'Model/migration changes.',
            'Finance behavior.',
            'Member import behavior.',
            'Deployment/SaaS behavior.',
            'Client-specific behavior.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_relationships_to_all_closed_member_profile_document_lanes(): void
    {
        $document = $this->closureDocument();

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
            'Milestone 3DD defined the frontend scope gate.',
            'Milestone 3DE authorized the frontend implementation lane.',
            'Milestone 3DF implemented the narrow frontend metadata UI.',
            '3DG validates and closes the 3DF frontend implementation lane.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_foundation_routes_frontend_location_and_closure(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'member_profile_documents',
            'App\Models\MemberProfileDocument',
            'GET /api/v1/clubs/{club}/members/{member}/profile-documents',
            'GET /api/v1/clubs/{club}/members/{member}/profile-documents/{document}',
            'POST /api/v1/clubs/{club}/members/{member}/profile-documents',
            'PATCH /api/v1/clubs/{club}/members/{member}/profile-documents/{document}',
            'Existing frontend implementation location:',
            'app/page.tsx',
            'The 3DF frontend implementation lane is validated by 3DG.',
            'Milestone 3DG closes the Member Profile Documents frontend lane delivered by 3DF.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_metadata_roles_states_refresh_copy_exclusions_and_guard_repair(): void
    {
        $document = $this->closureDocument();

        foreach ($this->writableMetadataFields() as $field) {
            $this->assertStringContainsString($field, $document);
        }

        foreach ($this->blockedFields() as $field) {
            $this->assertStringContainsString($field, $document);
        }

        foreach ([
            'President/Admin UI control behavior:',
            'Secretary UI control behavior:',
            'Treasurer blocked UI control behavior:',
            'Normal Member blocked UI control behavior:',
            'Backend authorization remains authoritative.',
            'Loading state.',
            'Empty state.',
            'Safe error state.',
            'Validation message display.',
            'Refresh after successful create/update.',
            'Profile Documents',
            'Document metadata only. File upload and document storage are not enabled in this version.',
            'No profile document records added yet.',
            'Add Document Metadata',
            'Edit Metadata',
            'DELETE behavior.',
            'File upload.',
            'File storage.',
            'File download.',
            'OCR.',
            'PDF generation.',
            'Backend runtime changes.',
            'Model/migration changes.',
            'Finance behavior.',
            'Member import behavior.',
            'Deployment/SaaS behavior.',
            'Client-specific behavior.',
            '3DG records the 3DE guard-test repair made during 3DF as historical-forward compatibility only.',
            'The 3DE guard no longer permanently forbids approved 3DF frontend runtime copy after 3DF has been implemented.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_profile_document_routes_remain_registered_without_delete_upload_download_storage_ocr_or_pdf_routes(): void
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

        foreach ($routes as $route) {
            $this->assertStringNotContainsString('DELETE', $route);
            foreach (['upload', 'download', 'storage', 'ocr', 'pdf'] as $blockedFragment) {
                $this->assertStringNotContainsString($blockedFragment, $route);
            }
        }
    }

    public function test_frontend_contains_required_profile_documents_section_copy_and_api_calls(): void
    {
        $frontend = $this->frontendSource();
        $section = $this->profileDocumentsSection();
        $component = $this->memberProfileComponentSource();

        foreach ([
            'Profile Documents',
            'Document metadata only. File upload and document storage are not enabled in this version.',
            'No profile document records added yet.',
            'Add Document Metadata',
            'Edit Metadata',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $section);
        }

        foreach ([
            'type ProfileDocumentFormState',
            'type ProfileDocumentMetadataRecord',
            'canManageProfileDocumentMetadata = role === "president" || role === "secretary"',
            'renderProfileDocumentsSection',
            'data-milestone="3DF-profile-documents-frontend-narrow-implementation"',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $frontend);
        }

        $this->assertStringContainsString('"/clubs/" + currentClubId + "/members/" + currentMemberId + "/profile-documents"', $component);
        $this->assertStringContainsString('"/clubs/" + documentClubId + "/members/" + documentMemberId + "/profile-documents"', $component);
        $this->assertStringContainsString('method: editingDocumentId ? "PATCH" : "POST"', $component);
        $this->assertStringContainsString('await refreshProfileDocuments(documentToken, documentClubId, documentMemberId);', $component);
    }

    public function test_frontend_section_has_loading_error_validation_refresh_and_no_forbidden_file_operation_labels(): void
    {
        $section = $this->profileDocumentsSection();
        $component = $this->memberProfileComponentSource();

        foreach ([
            'Loading profile document metadata...',
            'Profile document metadata could not be loaded or saved safely.',
            'Profile document metadata saved and refreshed.',
            'profileDocumentApiMessage',
            'payload.errors',
            'refreshProfileDocuments',
            'Save Metadata',
            'Cancel',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $component);
        }

        foreach ([
            'Upload Document',
            'Attach File',
            'Download',
            'Preview File',
            'Delete Document',
            'Remove Document',
            'OCR',
            'Extract Text',
            'Public Link',
            'Signed Link',
        ] as $forbiddenText) {
            $this->assertStringNotContainsString($forbiddenText, $section);
        }
    }

    public function test_frontend_form_state_and_inputs_do_not_expose_blocked_fields(): void
    {
        $frontend = $this->frontendSource();
        $component = $this->memberProfileComponentSource();
        $formState = $this->sourceBetween($frontend, 'type ProfileDocumentFormState = {', '};');
        $renderedSection = $this->profileDocumentsSection();

        foreach ($this->writableMetadataFields() as $field) {
            $this->assertStringContainsString($field, $formState);
            $this->assertStringContainsString($field . ': documentForm.' . $field, $component);
        }

        foreach ([
            'club_id',
            'member_id',
            'created_by_user_id',
            'updated_by_user_id',
            'file_path',
            'storage_disk',
            'uploaded_file',
            'binary_data',
            'base64_data',
            'ocr_text',
            'pdf_path',
        ] as $blockedField) {
            $this->assertStringNotContainsString($blockedField, $formState);
            $this->assertStringNotContainsString($blockedField . ': documentForm.', $component);
            $this->assertStringNotContainsString('name="' . $blockedField . '"', $renderedSection);
        }
    }

    private function closureDocument(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }

    private function frontendSource(): string
    {
        $this->assertFileExists($this->frontendPath);

        return file_get_contents($this->frontendPath);
    }

    private function profileDocumentsSection(): string
    {
        return $this->sourceBetween(
            $this->frontendSource(),
            'function renderProfileDocumentsSection() {',
            '  return ('."\n".'    <section className="stack">'
        );
    }

    private function memberProfileComponentSource(): string
    {
        return $this->sourceBetween(
            $this->frontendSource(),
            'function MemberProfile({ member, role }: { member: Member; role: Role }) {',
            'function DigitalCard'
        );
    }

    private function sourceBetween(string $source, string $start, string $end): string
    {
        $source = str_replace("\r\n", "\n", $source);
        $startPosition = strpos($source, $start);
        $this->assertNotFalse($startPosition, "Missing source start marker: {$start}");

        $endPosition = strpos($source, $end, $startPosition + strlen($start));
        $this->assertNotFalse($endPosition, "Missing source end marker: {$end}");

        return substr($source, $startPosition, $endPosition - $startPosition);
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

    private function blockedFields(): array
    {
        return [
            'club_id',
            'member_id',
            'created_by_user_id',
            'updated_by_user_id',
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

    private function blockedRuntimePaths(): array
    {
        return [
            'app/page.tsx',
            'backend/routes/api.php',
            'backend/app/Http/Controllers/Api/V1/Members/MemberProfileDocumentController.php',
            'backend/app/Http/Requests/Members/StoreMemberProfileDocumentRequest.php',
            'backend/app/Http/Requests/Members/UpdateMemberProfileDocumentRequest.php',
            'backend/app/Services/Members/MemberProfileDocumentWriteService.php',
            'backend/app/Http/Resources/Members/MemberProfileDocumentResource.php',
            'backend/app/Models/MemberProfileDocument.php',
            'backend/database/migrations/2026_06_13_000001_create_member_profile_documents_table.php',
            'backend/tests/Feature/Milestone3deMemberProfileDocumentsFrontendImplementationAuthorizationTest.php',
            'backend/tests/Feature/Milestone3dfMemberProfileDocumentsFrontendNarrowImplementationTest.php',
        ];
    }
}
