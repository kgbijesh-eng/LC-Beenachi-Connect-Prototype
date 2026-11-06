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

class Milestone3dcMemberProfileDocumentsCreateUpdateValidationAndClosureTest extends TestCase
{
    use RefreshDatabase;

    private string $docPath = __DIR__ . '/../../docs/milestone_3dc_member_profile_documents_create_update_validation_and_closure.md';
    private string $frontendPath = __DIR__ . '/../../../app/page.tsx';
    private string $routesPath = __DIR__ . '/../../routes/api.php';
    private string $controllerPath = __DIR__ . '/../../app/Http/Controllers/Api/V1/Members/MemberProfileDocumentController.php';
    private string $storeRequestPath = __DIR__ . '/../../app/Http/Requests/Members/StoreMemberProfileDocumentRequest.php';
    private string $updateRequestPath = __DIR__ . '/../../app/Http/Requests/Members/UpdateMemberProfileDocumentRequest.php';
    private string $writeServicePath = __DIR__ . '/../../app/Services/Members/MemberProfileDocumentWriteService.php';
    private string $resourcePath = __DIR__ . '/../../app/Http/Resources/Members/MemberProfileDocumentResource.php';
    private string $modelPath = __DIR__ . '/../../app/Models/MemberProfileDocument.php';
    private string $migrationPath = __DIR__ . '/../../database/migrations/2026_06_13_000001_create_member_profile_documents_table.php';

    public function test_3dc_document_exists_and_is_docs_tests_only_closure(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'Milestone 3DC - Member Profile Documents Create/Update Validation and Closure',
            'Milestone 3DC is a docs/tests-only validation and closure milestone.',
            '3DC validates and closes the 3DB metadata-only Member Profile Documents create/update API lane.',
            '3DC does not add new runtime behavior.',
            '3DC does not change frontend runtime files.',
            '3DC does not change backend routes, controllers, requests, resources, services, policies, middleware, models, migrations, config, or API behavior.',
            'backend/docs/milestone_3dc_member_profile_documents_create_update_validation_and_closure.md',
            'backend/tests/Feature/Milestone3dcMemberProfileDocumentsCreateUpdateValidationAndClosureTest.php',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_relationships_to_3ct_through_3db(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'Milestone 3CT created the Member Profile Documents database foundation.',
            'Milestone 3CU validated and closed the database foundation.',
            'Milestone 3CV scoped the read API lane.',
            'Milestone 3CW authorized the read API implementation.',
            'Milestone 3CX implemented the narrow read API.',
            'Milestone 3CY validated and closed the read API lane.',
            'Milestone 3CZ scoped the future create/update boundary.',
            'Milestone 3DA authorized the future narrow metadata-only create/update implementation lane.',
            'Milestone 3DB implemented the narrow metadata-only create/update API.',
            '3DC validates and closes the 3DB create/update lane.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_foundation_routes_metadata_boundary_and_next_milestone(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'milestone-3db-member-profile-documents-create-update-narrow-implementation',
            'fd176a0cd5edd7af424b21a0113f9b8978d26917',
            'milestone-3dc-member-profile-documents-create-update-validation-closure',
            'member_profile_documents',
            'App\Models\MemberProfileDocument',
            'GET /api/v1/clubs/{club}/members/{member}/profile-documents',
            'GET /api/v1/clubs/{club}/members/{member}/profile-documents/{document}',
            'POST /api/v1/clubs/{club}/members/{member}/profile-documents',
            'PATCH /api/v1/clubs/{club}/members/{member}/profile-documents/{document}',
            'The read API remains unchanged.',
            'The create/update API is metadata-only.',
            'Milestone 3DD - Member Profile Documents Frontend Scope Gate',
            '3DC must not start 3DD.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }

        foreach (array_merge($this->writableMetadataFields(), $this->ownershipAuditFields(), $this->blockedRequestFields()) as $field) {
            $this->assertStringContainsString($field, $document);
        }
    }

    public function test_document_records_authorization_fail_closed_exclusions_guard_repair_and_closure_decision(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'President/Admin can create/update member profile document metadata inside their own club.',
            'Secretary can create/update member profile document metadata inside their own club.',
            'Treasurer remains blocked from create/update.',
            'Normal Member remains blocked from create/update.',
            "Normal Member cannot create/update another member's document metadata.",
            'Cross-club create/update access fails closed.',
            'Member/document mismatch update fails closed.',
            'DELETE remains out of scope.',
            'File upload remains out of scope.',
            'File storage remains out of scope.',
            'Public/signed/download URLs remain out of scope.',
            'OCR/PDF remains out of scope.',
            'Frontend remains out of scope.',
            'Model/migration changes remain out of scope.',
            'Finance/import/deployment/SaaS/client-specific behavior remains out of scope.',
            'Those older guards were updated only so they no longer permanently block 3DB-approved POST/PATCH/store/update behavior.',
            'The repaired older guards still block DELETE, PUT, upload/download routes, storage routes, OCR/PDF routes, model/migration mutation, frontend behavior, and unrelated behavior.',
            'Milestone 3DC closes the Member Profile Documents create/update backend lane delivered by 3DB.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_post_patch_get_routes_exist_and_delete_upload_download_storage_ocr_pdf_are_absent(): void
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

    public function test_president_admin_and_secretary_can_create_and_update_metadata(): void
    {
        foreach (['president-admin', 'secretary'] as $roleSlug) {
            $club = $this->createClub('DOC-3DC-'.$roleSlug.uniqid());
            $officer = $this->createUserWithRole($club, $roleSlug);
            $member = $this->createMember($club, null, 'DOC-3DC-MEMBER-'.$roleSlug.uniqid());

            Sanctum::actingAs($officer);

            config(['instance.organisation_code' => $club->code]);
            $createdId = $this->postJson($this->collectionUrl($club, $member), $this->validPayload([
                'document_type' => $roleSlug.'-document',
            ]))
                ->assertCreated()
                ->assertJsonPath('profile_document.club_id', $club->id)
                ->assertJsonPath('profile_document.member_id', $member->id)
                ->assertJsonPath('profile_document.created_by_user_id', $officer->id)
                ->assertJsonPath('profile_document.updated_by_user_id', $officer->id)
                ->json('profile_document.id');

            $document = MemberProfileDocument::query()->findOrFail($createdId);

            $this->patchJson($this->documentUrl($club, $member, $document), [
                'status' => 'verified',
                'remarks' => 'Closed by 3DC validation.',
            ])
                ->assertOk()
                ->assertJsonPath('profile_document.id', $document->id)
                ->assertJsonPath('profile_document.status', 'verified')
                ->assertJsonPath('profile_document.remarks', 'Closed by 3DC validation.')
                ->assertJsonPath('profile_document.updated_by_user_id', $officer->id);
        }
    }

    public function test_treasurer_and_normal_member_remain_blocked_from_create_and_update(): void
    {
        foreach (['treasurer', 'member'] as $roleSlug) {
            $club = $this->createClub('DOC-3DC-BLOCK-'.$roleSlug.uniqid());
            $user = $this->createUserWithRole($club, $roleSlug);
            $member = $this->createMember($club, $roleSlug === 'member' ? $user : null, 'DOC-3DC-BLOCK-MEMBER-'.$roleSlug.uniqid());
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

    public function test_cross_club_and_member_document_mismatch_fail_closed(): void
    {
        $club = $this->createClub('DOC-3DC-MAIN');
        $otherClub = $this->createClub('DOC-3DC-OTHER');
        $crossClubOfficer = $this->createUserWithRole($otherClub, 'president-admin');
        $member = $this->createMember($club, null, 'DOC-3DC-MAIN-MEMBER');
        $document = $this->createProfileDocument($club, $member);

        Sanctum::actingAs($crossClubOfficer);

        config(['instance.organisation_code' => $club->code]);
        $this->postJson($this->collectionUrl($club, $member), $this->validPayload())
            ->assertForbidden();

        $this->patchJson($this->documentUrl($club, $member, $document), [
            'status' => 'verified',
        ])
            ->assertForbidden();

        $sameClubOfficer = $this->createUserWithRole($club, 'secretary');
        $otherClubMember = $this->createMember($otherClub, null, 'DOC-3DC-OTHER-MEMBER');
        $otherClubDocument = $this->createProfileDocument($otherClub, $otherClubMember);
        $sameClubDifferentMember = $this->createMember($club, null, 'DOC-3DC-DIFFERENT-MEMBER');
        $sameClubDifferentDocument = $this->createProfileDocument($club, $sameClubDifferentMember);

        Sanctum::actingAs($sameClubOfficer);

        $this->postJson($this->collectionUrl($club, $otherClubMember), $this->validPayload())
            ->assertNotFound();

        $this->patchJson($this->documentUrl($club, $member, $otherClubDocument), [
            'status' => 'verified',
        ])
            ->assertNotFound();

        $this->patchJson($this->documentUrl($club, $member, $sameClubDifferentDocument), [
            'status' => 'verified',
        ])
            ->assertNotFound();
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

    public function test_delete_upload_download_storage_ocr_pdf_behavior_is_not_introduced(): void
    {
        $club = $this->createClub();
        $officer = $this->createUserWithRole($club, 'president-admin');
        $member = $this->createMember($club);
        $document = $this->createProfileDocument($club, $member);

        Sanctum::actingAs($officer);

        config(['instance.organisation_code' => $club->code]);
        $this->deleteJson($this->documentUrl($club, $member, $document))
            ->assertStatus(405);

        foreach (['upload', 'download', 'storage', 'ocr', 'pdf'] as $blockedFragment) {
            config(['instance.organisation_code' => $club->code]);
            $this->postJson("/api/v1/clubs/{$club->id}/members/{$member->id}/profile-documents/{$blockedFragment}", [])
                ->assertStatus(405);
        }
    }

    public function test_ownership_audit_file_path_storage_url_ocr_pdf_and_binary_request_fields_are_blocked(): void
    {
        $club = $this->createClub();
        $officer = $this->createUserWithRole($club, 'secretary');
        $member = $this->createMember($club);
        $document = $this->createProfileDocument($club, $member);

        Sanctum::actingAs($officer);

        foreach (array_merge($this->ownershipAuditFields(), $this->blockedRequestFields()) as $field) {
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

    public function test_3dc_file_scope_and_blocked_runtime_files_are_read_only_inputs(): void
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
    }

    private function closureDocument(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }

    private function createClub(string $code = 'DOC-3DC'): Club
    {
        $this->seed(DatabaseSeeder::class);

        return Club::query()->create([
            'name' => 'Document Closure Test Club '.$code,
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

    private function createMember(Club $club, ?User $user = null, string $memberCode = 'DOC-3DC-MEMBER'): Member
    {
        return Member::query()->create([
            'club_id' => $club->id,
            'user_id' => $user?->id,
            'member_code' => $memberCode,
            'full_name' => 'Document Closure Test Member',
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
            'external_reference' => 'MANUAL-REF-3DC',
            'remarks' => 'Metadata-only closure reference.',
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
            'external_reference' => 'MANUAL-3DC',
            'remarks' => 'Metadata-only closure validation.',
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
