<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubMembership;
use App\Models\Member;
use App\Models\MemberProfileDocument;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Milestone3dbMemberProfileDocumentsCreateUpdateNarrowImplementationTest extends TestCase
{
    use RefreshDatabase;

    private string $docPath = __DIR__ . '/../../docs/milestone_3db_member_profile_documents_create_update_narrow_implementation.md';
    private string $controllerPath = __DIR__ . '/../../app/Http/Controllers/Api/V1/Members/MemberProfileDocumentController.php';
    private string $storeRequestPath = __DIR__ . '/../../app/Http/Requests/Members/StoreMemberProfileDocumentRequest.php';
    private string $updateRequestPath = __DIR__ . '/../../app/Http/Requests/Members/UpdateMemberProfileDocumentRequest.php';
    private string $writeServicePath = __DIR__ . '/../../app/Services/Members/MemberProfileDocumentWriteService.php';
    private string $resourcePath = __DIR__ . '/../../app/Http/Resources/Members/MemberProfileDocumentResource.php';
    private string $modelPath = __DIR__ . '/../../app/Models/MemberProfileDocument.php';
    private string $migrationPath = __DIR__ . '/../../database/migrations/2026_06_13_000001_create_member_profile_documents_table.php';
    private string $frontendPath = __DIR__ . '/../../../app/page.tsx';

    public function test_3db_adds_post_and_patch_routes_without_delete_upload_or_download_routes(): void
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
            $this->assertStringNotContainsString('upload', $route);
            $this->assertStringNotContainsString('download', $route);
            $this->assertStringNotContainsString('ocr', $route);
            $this->assertStringNotContainsString('pdf', $route);
        }
    }

    public function test_president_admin_can_create_metadata_within_own_club(): void
    {
        $club = $this->createClub();
        $president = $this->createUserWithRole($club, 'president-admin');
        $member = $this->createMember($club);

        Sanctum::actingAs($president);

        config(['instance.organisation_code' => $club->code]);
        $this->postJson($this->collectionUrl($club, $member), $this->validPayload([
            'club_id' => 9999,
            'member_id' => 9999,
            'created_by_user_id' => 9999,
            'updated_by_user_id' => 9999,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'club_id',
                'member_id',
                'created_by_user_id',
                'updated_by_user_id',
            ]);

        $response = $this->postJson($this->collectionUrl($club, $member), $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('profile_document.club_id', $club->id)
            ->assertJsonPath('profile_document.member_id', $member->id)
            ->assertJsonPath('profile_document.document_type', 'id_proof')
            ->assertJsonPath('profile_document.status', 'verified')
            ->assertJsonPath('profile_document.external_reference', 'MANUAL-3DB')
            ->assertJsonPath('profile_document.remarks', 'Metadata-only document reference.')
            ->assertJsonPath('profile_document.created_by_user_id', $president->id)
            ->assertJsonPath('profile_document.updated_by_user_id', $president->id);

        $this->assertDatabaseHas('member_profile_documents', [
            'id' => $response->json('profile_document.id'),
            'club_id' => $club->id,
            'member_id' => $member->id,
            'created_by_user_id' => $president->id,
            'updated_by_user_id' => $president->id,
        ]);
    }

    public function test_president_admin_can_update_metadata_within_own_club(): void
    {
        $club = $this->createClub();
        $president = $this->createUserWithRole($club, 'president-admin');
        $member = $this->createMember($club);
        $document = $this->createProfileDocument($club, $member, [
            'created_by_user_id' => null,
            'updated_by_user_id' => null,
        ]);

        Sanctum::actingAs($president);

        config(['instance.organisation_code' => $club->code]);
        $this->patchJson($this->documentUrl($club, $member, $document), [
            'status' => 'rejected',
            'remarks' => 'Needs a newer metadata reference.',
        ])
            ->assertOk()
            ->assertJsonPath('profile_document.id', $document->id)
            ->assertJsonPath('profile_document.document_type', 'profile_photo')
            ->assertJsonPath('profile_document.status', 'rejected')
            ->assertJsonPath('profile_document.remarks', 'Needs a newer metadata reference.')
            ->assertJsonPath('profile_document.updated_by_user_id', $president->id);

        $this->assertDatabaseHas('member_profile_documents', [
            'id' => $document->id,
            'club_id' => $club->id,
            'member_id' => $member->id,
            'document_type' => 'profile_photo',
            'status' => 'rejected',
            'updated_by_user_id' => $president->id,
        ]);
    }

    public function test_secretary_can_create_and_update_metadata_within_own_club(): void
    {
        $club = $this->createClub();
        $secretary = $this->createUserWithRole($club, 'secretary');
        $member = $this->createMember($club);

        Sanctum::actingAs($secretary);

        config(['instance.organisation_code' => $club->code]);
        $created = $this->postJson($this->collectionUrl($club, $member), $this->validPayload([
            'document_type' => 'membership_form',
            'status' => 'pending',
        ]))
            ->assertCreated()
            ->assertJsonPath('profile_document.created_by_user_id', $secretary->id)
            ->json('profile_document.id');

        $document = MemberProfileDocument::query()->findOrFail($created);

        $this->patchJson($this->documentUrl($club, $member, $document), [
            'status' => 'verified',
            'external_reference' => 'SECRETARY-UPDATED',
        ])
            ->assertOk()
            ->assertJsonPath('profile_document.status', 'verified')
            ->assertJsonPath('profile_document.external_reference', 'SECRETARY-UPDATED')
            ->assertJsonPath('profile_document.updated_by_user_id', $secretary->id);
    }

    public function test_treasurer_and_normal_member_are_blocked_from_create_and_update(): void
    {
        foreach (['treasurer', 'member'] as $roleSlug) {
            $club = $this->createClub('DOC-BLOCK-'.$roleSlug.uniqid());
            $user = $this->createUserWithRole($club, $roleSlug);
            $member = $this->createMember($club, $roleSlug === 'member' ? $user : null, 'DOC-BLOCK-MEMBER-'.$roleSlug.uniqid());
            $document = $this->createProfileDocument($club, $member);

            Sanctum::actingAs($user);

            config(['instance.organisation_code' => $club->code]);
            $this->postJson($this->collectionUrl($club, $member), $this->validPayload())
                ->assertForbidden();

            $this->patchJson($this->documentUrl($club, $member, $document), [
                'status' => 'verified',
            ])
                ->assertForbidden();
        }
    }

    public function test_normal_member_cannot_create_or_update_another_members_document_metadata(): void
    {
        $club = $this->createClub();
        $normalUser = $this->createUserWithRole($club, 'member');
        $otherMember = $this->createMember($club, null, 'DOC-OTHER-NORMAL');
        $document = $this->createProfileDocument($club, $otherMember);

        Sanctum::actingAs($normalUser);

        config(['instance.organisation_code' => $club->code]);
        $this->postJson($this->collectionUrl($club, $otherMember), $this->validPayload())
            ->assertForbidden();

        $this->patchJson($this->documentUrl($club, $otherMember, $document), [
            'status' => 'verified',
        ])
            ->assertForbidden();
    }

    public function test_cross_club_create_and_update_fail_closed(): void
    {
        $club = $this->createClub('DOC-MAIN');
        $otherClub = $this->createClub('DOC-OTHER');
        $crossClubOfficer = $this->createUserWithRole($otherClub, 'president-admin');
        $member = $this->createMember($club, null, 'DOC-MAIN-MEMBER');
        $document = $this->createProfileDocument($club, $member);

        Sanctum::actingAs($crossClubOfficer);

        config(['instance.organisation_code' => $club->code]);
        $this->postJson($this->collectionUrl($club, $member), $this->validPayload())
            ->assertForbidden();

        $this->patchJson($this->documentUrl($club, $member, $document), [
            'status' => 'verified',
        ])
            ->assertForbidden();

        $mainOfficer = $this->createUserWithRole($club, 'secretary');
        $otherMember = $this->createMember($otherClub, null, 'DOC-OTHER-MEMBER');
        $otherDocument = $this->createProfileDocument($otherClub, $otherMember);

        Sanctum::actingAs($mainOfficer);

        $this->postJson($this->collectionUrl($club, $otherMember), $this->validPayload())
            ->assertNotFound();

        $this->patchJson($this->documentUrl($club, $member, $otherDocument), [
            'status' => 'verified',
        ])
            ->assertNotFound();
    }

    public function test_member_document_mismatch_update_fails_closed(): void
    {
        $club = $this->createClub();
        $officer = $this->createUserWithRole($club, 'president-admin');
        $member = $this->createMember($club, null, 'DOC-SELECTED');
        $otherMember = $this->createMember($club, null, 'DOC-DIFFERENT');
        $otherDocument = $this->createProfileDocument($club, $otherMember);

        Sanctum::actingAs($officer);

        config(['instance.organisation_code' => $club->code]);
        $this->patchJson($this->documentUrl($club, $member, $otherDocument), [
            'status' => 'verified',
        ])
            ->assertNotFound();
    }

    public function test_blocked_file_path_storage_url_ocr_pdf_and_binary_fields_are_rejected(): void
    {
        $club = $this->createClub();
        $officer = $this->createUserWithRole($club, 'secretary');
        $member = $this->createMember($club);
        $document = $this->createProfileDocument($club, $member);

        Sanctum::actingAs($officer);

        foreach ($this->blockedRequestFields() as $field) {
            config(['instance.organisation_code' => $club->code]);
            $this->postJson($this->collectionUrl($club, $member), $this->validPayload([
                $field => 'blocked-value',
            ]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors([$field]);

            $this->patchJson($this->documentUrl($club, $member, $document), [
                $field => 'blocked-value',
            ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors([$field]);
        }
    }

    public function test_existing_get_collection_and_single_document_routes_still_work(): void
    {
        $club = $this->createClub();
        $officer = $this->createUserWithRole($club, 'president-admin');
        $member = $this->createMember($club);
        $document = $this->createProfileDocument($club, $member, [
            'document_type' => 'id_proof',
            'status' => 'verified',
        ]);

        Sanctum::actingAs($officer);

        config(['instance.organisation_code' => $club->code]);
        $this->getJson($this->collectionUrl($club, $member))
            ->assertOk()
            ->assertJsonPath('profile_documents.0.id', $document->id)
            ->assertJsonPath('profile_documents.0.document_type', 'id_proof');

        $this->getJson($this->documentUrl($club, $member, $document))
            ->assertOk()
            ->assertJsonPath('profile_document.id', $document->id)
            ->assertJsonPath('profile_document.status', 'verified');
    }

    public function test_delete_is_not_allowed_and_upload_download_behavior_is_not_introduced(): void
    {
        $club = $this->createClub();
        $officer = $this->createUserWithRole($club, 'president-admin');
        $member = $this->createMember($club);
        $document = $this->createProfileDocument($club, $member);

        Sanctum::actingAs($officer);

        config(['instance.organisation_code' => $club->code]);
        $this->deleteJson($this->documentUrl($club, $member, $document))
            ->assertStatus(405);

        foreach (['upload', 'download', 'ocr', 'pdf'] as $blockedFragment) {
            config(['instance.organisation_code' => $club->code]);
            $this->postJson("/api/v1/clubs/{$club->id}/members/{$member->id}/profile-documents/{$blockedFragment}", [])
                ->assertStatus(405);
        }
    }

    public function test_only_approved_3db_files_exist_and_blocked_runtime_areas_are_unchanged(): void
    {
        foreach ([
            $this->docPath,
            __FILE__,
            $this->controllerPath,
            $this->storeRequestPath,
            $this->updateRequestPath,
            $this->writeServicePath,
            $this->resourcePath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $this->assertFileExists($this->frontendPath);
        $this->assertFileExists($this->modelPath);
        $this->assertFileExists($this->migrationPath);

        foreach ([
            app_path('Policies/MemberProfileDocumentPolicy.php'),
            app_path('Http/Middleware/MemberProfileDocumentMiddleware.php'),
            app_path('Services/Members/MemberProfileDocumentUploadService.php'),
            app_path('Services/Members/MemberProfileDocumentStorageService.php'),
            app_path('Services/Members/MemberProfileDocumentOcrService.php'),
            app_path('Services/Members/MemberProfileDocumentPdfService.php'),
        ] as $blockedPath) {
            $this->assertFileDoesNotExist($blockedPath);
        }

        $model = strtolower(file_get_contents($this->modelPath));
        $migration = strtolower(file_get_contents($this->migrationPath));

        foreach ($this->blockedRequestFields() as $field) {
            $this->assertStringNotContainsString($field, $model);
            $this->assertStringNotContainsString($field, $migration);
        }
    }

    public function test_documentation_records_3db_implementation_boundary_and_next_milestone(): void
    {
        $document = $this->implementationDocument();

        foreach ([
            'Milestone 3DB implements the narrow backend runtime lane for metadata-only Member Profile Documents create/update API behavior.',
            'milestone-3da-member-profile-documents-create-update-implementation-authorization',
            '141be8b68633d0e6bc5e602cb5477d8905450f94',
            'milestone-3db-member-profile-documents-create-update-narrow-implementation',
            'Milestone 3CT created the Member Profile Documents database foundation.',
            'Milestone 3CU validated and closed the database foundation.',
            'Milestone 3CV scoped the read API lane.',
            'Milestone 3CW authorized the read API implementation.',
            'Milestone 3CX implemented the narrow read API.',
            'Milestone 3CY validated and closed the read API lane.',
            'Milestone 3CZ scoped the future create/update boundary.',
            'Milestone 3DA authorized the future narrow metadata-only create/update implementation lane.',
            'member_profile_documents',
            'App\Models\MemberProfileDocument',
            'GET /api/v1/clubs/{club}/members/{member}/profile-documents',
            'GET /api/v1/clubs/{club}/members/{member}/profile-documents/{document}',
            'POST /api/v1/clubs/{club}/members/{member}/profile-documents',
            'PATCH /api/v1/clubs/{club}/members/{member}/profile-documents/{document}',
            'DELETE remains out of scope.',
            'File upload remains out of scope.',
            'File storage remains out of scope.',
            'Public/signed/download URLs remain out of scope.',
            'OCR/PDF remains out of scope.',
            'President/Admin may create/update member profile document metadata within their own club.',
            'Secretary may create/update member profile document metadata within their own club.',
            'Treasurer remains blocked from create/update.',
            'Normal Member remains blocked from create/update.',
            "Normal Member cannot create/update another member's document metadata.",
            'Cross-club create fails closed.',
            'Cross-club update fails closed.',
            'Member/document ownership mismatch update fails closed.',
            'No frontend changes were made.',
            'No model changes were made.',
            'No migration changes were made.',
            'No finance behavior was added.',
            'No member import behavior was added.',
            'No deployment/SaaS behavior was added.',
            'No client-specific behavior was added.',
            'Milestone 3DC - Member Profile Documents Create/Update Validation and Closure',
            '3DB must not start 3DC.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }

        foreach (array_merge($this->writableMetadataFields(), $this->ownershipAuditFields(), $this->blockedRequestFields()) as $field) {
            $this->assertStringContainsString($field, $document);
        }
    }

    private function implementationDocument(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }

    private function createClub(string $code = 'DOC-3DB'): Club
    {
        $this->seed(DatabaseSeeder::class);

        return Club::query()->create([
            'name' => 'Document Test Club '.$code,
            'code' => $code,
            'is_active' => true,
        ]);
    }

    private function createUserWithRole(Club $club, string $roleSlug): User
    {
        $role = Role::where('slug', $roleSlug)->firstOrFail();
        $user = User::factory()->create();

        ClubMembership::query()->create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return $user;
    }

    private function createMember(Club $club, ?User $user = null, string $memberCode = 'DOC-3DB-MEMBER'): Member
    {
        return Member::query()->create([
            'club_id' => $club->id,
            'user_id' => $user?->id,
            'member_code' => $memberCode,
            'full_name' => 'Document Test Member',
            'phone' => '9999990000',
            'email' => strtolower($memberCode).'@example.test',
            'status' => 'active',
            'blood_donor_consent' => false,
        ]);
    }

    private function createProfileDocument(Club $club, Member $member, array $overrides = []): MemberProfileDocument
    {
        return MemberProfileDocument::query()->create(array_merge([
            'club_id' => $club->id,
            'member_id' => $member->id,
            'document_type' => 'profile_photo',
            'status' => 'pending',
            'external_reference' => 'MANUAL-REF-001',
            'remarks' => 'Metadata-only reference.',
            'created_by_user_id' => null,
            'updated_by_user_id' => null,
        ], $overrides));
    }

    private function collectionUrl(Club $club, Member $member): string
    {
        return "/api/v1/clubs/{$club->id}/members/{$member->id}/profile-documents";
    }

    private function documentUrl(Club $club, Member $member, MemberProfileDocument $document): string
    {
        return "/api/v1/clubs/{$club->id}/members/{$member->id}/profile-documents/{$document->id}";
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'document_type' => 'id_proof',
            'status' => 'verified',
            'external_reference' => 'MANUAL-3DB',
            'remarks' => 'Metadata-only document reference.',
        ], $overrides);
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
