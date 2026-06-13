<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3dfMemberProfileDocumentsFrontendNarrowImplementationTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3df_member_profile_documents_frontend_narrow_implementation.md';
    private string $frontendPath = __DIR__ . '/../../../app/page.tsx';

    public function test_3df_document_exists_and_records_frontend_narrow_implementation_scope(): void
    {
        $document = $this->implementationDocument();

        foreach ([
            'Milestone 3DF - Member Profile Documents Frontend Narrow Implementation',
            'Milestone 3DF implements the narrow frontend runtime lane authorized by 3DE.',
            '3DF adds a Profile Documents section inside the existing member/member-management UI in app/page.tsx.',
            '3DF is metadata-only.',
            '3DF does not change backend runtime behavior.',
            '3DF does not reopen the backend lane.',
            '3DF must not start 3DG.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3df_records_base_branch_allowed_files_and_next_milestone(): void
    {
        $document = $this->implementationDocument();

        foreach ([
            'milestone-3de-member-profile-documents-frontend-implementation-authorization',
            'b502123f27d33afc113b0702100ae62a0473f78e',
            'milestone-3df-member-profile-documents-frontend-narrow-implementation',
            'app/page.tsx',
            'backend/docs/milestone_3df_member_profile_documents_frontend_narrow_implementation.md',
            'backend/tests/Feature/Milestone3dfMemberProfileDocumentsFrontendNarrowImplementationTest.php',
            'Milestone 3DG - Member Profile Documents Frontend Validation and Closure',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3df_current_file_changes_are_limited_to_approved_files(): void
    {
        $allowed = [
            'app/page.tsx',
            'backend/docs/milestone_3df_member_profile_documents_frontend_narrow_implementation.md',
            'backend/tests/Feature/Milestone3dfMemberProfileDocumentsFrontendNarrowImplementationTest.php',
            'backend/tests/Feature/Milestone3deMemberProfileDocumentsFrontendImplementationAuthorizationTest.php',
        ];

        $repoRoot = dirname(__DIR__, 3);
        $status = shell_exec('git -C '.escapeshellarg($repoRoot).' status --short --untracked-files=all') ?? '';
        $changed = collect(preg_split('/\R/', trim($status)))
            ->filter()
            ->map(function (string $line): string {
                $path = preg_replace('/^[ MARCUD?!]{1,2}\s+/', '', $line) ?? $line;
                $path = trim($path);
                if (str_contains($path, ' -> ')) {
                    $path = trim(substr($path, strrpos($path, ' -> ') + 4));
                }

                return str_replace('\\', '/', $path);
            })
            ->values()
            ->all();

        $unexpected = array_values(array_diff($changed, $allowed));

        $this->assertSame([], $unexpected, '3DF may change only the approved frontend/doc/test files.');
    }

    public function test_document_records_relationships_routes_backend_lane_and_exclusions(): void
    {
        $document = $this->implementationDocument();

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
            'Milestone 3DE authorized this narrow frontend implementation.',
            'GET /api/v1/clubs/{club}/members/{member}/profile-documents',
            'GET /api/v1/clubs/{club}/members/{member}/profile-documents/{document}',
            'POST /api/v1/clubs/{club}/members/{member}/profile-documents',
            'PATCH /api/v1/clubs/{club}/members/{member}/profile-documents/{document}',
            'The backend create/update lane is closed.',
            'Backend authorization remains authoritative.',
            'UI hiding is only convenience.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_metadata_fields_roles_states_refresh_and_ux_copy(): void
    {
        $document = $this->implementationDocument();

        foreach ($this->writableMetadataFields() as $field) {
            $this->assertStringContainsString($field, $document);
        }

        foreach ($this->blockedFields() as $field) {
            $this->assertStringContainsString($field, $document);
        }

        foreach ([
            'President/Admin may see create/edit metadata controls for members inside their own club.',
            'Secretary may see create/edit metadata controls for members inside their own club.',
            'Treasurer must not see create/edit metadata controls.',
            'Normal Member must not see create/edit metadata controls.',
            "Normal Member must not see controls to create/update another member's document metadata.",
            'Loading state.',
            'Empty state.',
            'Safe error state.',
            'Validation error display from backend responses.',
            'Cancel/save behavior.',
            'Refresh after create/update.',
            'Profile Documents',
            'Document metadata only. File upload and document storage are not enabled in this version.',
            'No profile document records added yet.',
            'Add Document Metadata',
            'Edit Metadata',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_frontend_contains_profile_documents_section_and_required_copy(): void
    {
        $frontend = $this->frontendSource();
        $section = $this->profileDocumentsSection();

        foreach ([
            'type ProfileDocumentFormState',
            'type ProfileDocumentMetadataRecord',
            'canManageProfileDocumentMetadata = role === "president" || role === "secretary"',
            'renderProfileDocumentsSection',
            'data-milestone="3DF-profile-documents-frontend-narrow-implementation"',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $frontend);
        }

        foreach ([
            'Profile Documents',
            'Document metadata only. File upload and document storage are not enabled in this version.',
            'No profile document records added yet.',
            'Add Document Metadata',
            'Edit Metadata',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $section);
        }
    }

    public function test_frontend_uses_only_approved_profile_document_routes_and_methods(): void
    {
        $component = $this->memberProfileComponentSource();

        $this->assertStringContainsString('"/clubs/" + currentClubId + "/members/" + currentMemberId + "/profile-documents"', $component);
        $this->assertStringContainsString('"/clubs/" + documentClubId + "/members/" + documentMemberId + "/profile-documents"', $component);
        $this->assertStringContainsString('method: editingDocumentId ? "PATCH" : "POST"', $component);
        $this->assertStringContainsString('Authorization: "Bearer " + currentToken', $component);
        $this->assertStringContainsString('Authorization: "Bearer " + documentToken', $component);
        $this->assertStringNotContainsString('DELETE', $component);
    }

    public function test_frontend_form_state_and_submission_are_metadata_only(): void
    {
        $frontend = $this->frontendSource();
        $component = $this->memberProfileComponentSource();
        $formState = $this->sourceBetween($frontend, 'type ProfileDocumentFormState = {', '};');

        foreach ($this->writableMetadataFields() as $field) {
            $this->assertStringContainsString($field, $formState);
            $this->assertStringContainsString($field . ': documentForm.' . $field, $component);
        }

        foreach ($this->blockedFields() as $field) {
            $this->assertStringNotContainsString($field, $formState);
            $this->assertStringNotContainsString($field . ': documentForm.', $component);
        }
    }

    public function test_frontend_role_controls_hide_create_edit_for_treasurer_and_member(): void
    {
        $section = $this->profileDocumentsSection();

        $this->assertStringContainsString('canManageProfileDocumentMetadata = role === "president" || role === "secretary"', $this->frontendSource());
        $this->assertStringContainsString('{canManageProfileDocumentMetadata && (', $section);
        $this->assertStringContainsString('Add Document Metadata', $section);
        $this->assertStringContainsString('Edit Metadata', $section);
        $this->assertStringContainsString('Backend authorization is required before profile document metadata can be saved.', $this->memberProfileComponentSource());
    }

    public function test_frontend_section_has_states_refresh_and_no_forbidden_file_operation_labels(): void
    {
        $section = $this->profileDocumentsSection();

        foreach ([
            'Loading profile document metadata...',
            'Profile document metadata could not be loaded or saved safely.',
            'No profile document records added yet.',
            'refreshProfileDocuments',
            'Cancel',
            'Save Metadata',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $section);
        }

        $this->assertStringContainsString('Profile document metadata saved and refreshed.', $this->memberProfileComponentSource());

        foreach ([
            'Upload Document',
            'Attach File',
            'Download',
            'Preview File',
            'Open File',
            'Generate PDF',
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

    public function test_document_records_backend_model_finance_import_deployment_and_saas_exclusions(): void
    {
        $document = $this->implementationDocument();

        foreach ([
            'Backend runtime changes.',
            'Route/controller/request/resource/service changes.',
            'Model/migration changes.',
            'Policy/middleware changes.',
            'Package or lock file changes.',
            'Finance behavior.',
            'Member import behavior.',
            'Deployment/SaaS behavior.',
            'Client-specific behavior.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    private function implementationDocument(): string
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
}
