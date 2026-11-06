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

class Milestone3cxMemberProfileDocumentsReadApiNarrowImplementationTest extends TestCase
{
    use RefreshDatabase;

    private string $docPath = __DIR__ . '/../../docs/milestone_3cx_member_profile_documents_read_api_narrow_implementation.md';
    private string $controllerPath = __DIR__ . '/../../app/Http/Controllers/Api/V1/Members/MemberProfileDocumentController.php';
    private string $resourcePath = __DIR__ . '/../../app/Http/Resources/Members/MemberProfileDocumentResource.php';
    private string $servicePath = __DIR__ . '/../../app/Services/Members/MemberProfileDocumentReadService.php';
    private string $modelPath = __DIR__ . '/../../app/Models/MemberProfileDocument.php';
    private string $migrationPath = __DIR__ . '/../../database/migrations/2026_06_13_000001_create_member_profile_documents_table.php';
    private string $frontendPath = __DIR__ . '/../../../app/page.tsx';

    public function test_3cx_document_records_narrow_read_api_implementation_boundary(): void
    {
        $document = $this->implementationDocument();

        foreach ([
            'Milestone 3CX - Member Profile Documents Read API Narrow Implementation',
            'narrow backend read API implementation',
            'It implements only metadata-only GET routes for Member Profile Documents.',
            'It does not change frontend runtime files.',
            'It does not change the 3CT migration.',
            'It does not change App\Models\MemberProfileDocument.',
            'It does not add request classes, policy classes, middleware classes, upload behavior, storage behavior, OCR, PDF, finance behavior, member import behavior, deployment/SaaS behavior, or client-specific behavior.',
            'Milestone 3CY - Member Profile Documents Read API Validation and Closure',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_profile_document_routes_remain_metadata_only_after_3db_forward_compatibility_update(): void
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
    }

    public function test_profile_document_routes_do_not_include_delete_upload_download_ocr_or_pdf_methods(): void
    {
        $routes = collect(Route::getRoutes())
            ->filter(fn ($route): bool => str_contains($route->uri(), 'profile-documents'));

        $this->assertCount(4, $routes);

        foreach ($routes as $route) {
            $this->assertSame([], array_values(array_intersect($route->methods(), ['PUT', 'DELETE'])));

            foreach (['upload', 'download', 'ocr', 'pdf', 'export'] as $blockedFragment) {
                $this->assertStringNotContainsString($blockedFragment, $route->uri());
            }
        }
    }

    public function test_president_admin_and_secretary_can_read_member_profile_document_metadata_within_club(): void
    {
        foreach (['president-admin', 'secretary'] as $roleSlug) {
            $club = $this->createClub('DOC-READ-'.strtoupper($roleSlug).uniqid());
            $reader = $this->createUserWithRole($club, $roleSlug);
            $member = $this->createMember($club, null, 'DOC-MEMBER-'.strtoupper($roleSlug).uniqid());
            $document = $this->createProfileDocument($club, $member, [
                'document_type' => 'id_proof',
                'status' => 'verified',
                'external_reference' => 'REF-'.$roleSlug,
                'remarks' => 'Metadata only.',
                'created_by_user_id' => $reader->id,
                'updated_by_user_id' => $reader->id,
            ]);

            Sanctum::actingAs($reader);

            $this->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/profile-documents")
                ->assertOk()
                ->assertJsonPath('profile_documents.0.id', $document->id)
                ->assertJsonPath('profile_documents.0.club_id', $club->id)
                ->assertJsonPath('profile_documents.0.member_id', $member->id)
                ->assertJsonPath('profile_documents.0.document_type', 'id_proof')
                ->assertJsonPath('profile_documents.0.status', 'verified')
                ->assertJsonPath('profile_documents.0.external_reference', 'REF-'.$roleSlug)
                ->assertJsonPath('profile_documents.0.remarks', 'Metadata only.')
                ->assertJsonPath('profile_documents.0.created_by_user_id', $reader->id)
                ->assertJsonPath('profile_documents.0.updated_by_user_id', $reader->id);

            $this->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/profile-documents/{$document->id}")
                ->assertOk()
                ->assertJsonPath('profile_document.id', $document->id)
                ->assertJsonPath('profile_document.document_type', 'id_proof');
        }
    }

    public function test_treasurer_is_blocked_in_3cx(): void
    {
        $club = $this->createClub();
        $treasurer = $this->createUserWithRole($club, 'treasurer');
        $member = $this->createMember($club);
        $document = $this->createProfileDocument($club, $member);

        Sanctum::actingAs($treasurer);

        $this->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/profile-documents")
            ->assertForbidden();

        $this->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/profile-documents/{$document->id}")
            ->assertForbidden();
    }

    public function test_normal_member_can_read_only_own_profile_document_metadata(): void
    {
        $club = $this->createClub();
        $normalUser = $this->createUserWithRole($club, 'member');
        $ownMember = $this->createMember($club, $normalUser, 'DOC-OWN');
        $document = $this->createProfileDocument($club, $ownMember, [
            'document_type' => 'membership_form',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($normalUser);

        $this->getJson("/api/v1/clubs/{$club->id}/members/{$ownMember->id}/profile-documents")
            ->assertOk()
            ->assertJsonPath('profile_documents.0.id', $document->id)
            ->assertJsonPath('profile_documents.0.document_type', 'membership_form');

        $this->getJson("/api/v1/clubs/{$club->id}/members/{$ownMember->id}/profile-documents/{$document->id}")
            ->assertOk()
            ->assertJsonPath('profile_document.id', $document->id);
    }

    public function test_normal_member_cannot_read_another_members_profile_document_metadata(): void
    {
        $club = $this->createClub();
        $normalUser = $this->createUserWithRole($club, 'member');
        $ownMember = $this->createMember($club, $normalUser, 'DOC-NORMAL');
        $otherMember = $this->createMember($club, null, 'DOC-OTHER');
        $document = $this->createProfileDocument($club, $otherMember);

        Sanctum::actingAs($normalUser);

        $this->getJson("/api/v1/clubs/{$club->id}/members/{$ownMember->id}/profile-documents")
            ->assertOk();

        $this->getJson("/api/v1/clubs/{$club->id}/members/{$otherMember->id}/profile-documents")
            ->assertForbidden();

        $this->getJson("/api/v1/clubs/{$club->id}/members/{$otherMember->id}/profile-documents/{$document->id}")
            ->assertForbidden();
    }

    public function test_unauthenticated_user_is_blocked(): void
    {
        $club = $this->createClub();
        $member = $this->createMember($club);
        $document = $this->createProfileDocument($club, $member);

        $this->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/profile-documents")
            ->assertUnauthorized();

        $this->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/profile-documents/{$document->id}")
            ->assertUnauthorized();
    }

    public function test_cross_club_user_is_blocked(): void
    {
        $club = $this->createClub('DOC-A');
        $otherClub = $this->createClub('DOC-B');
        $crossClubUser = $this->createUserWithRole($otherClub, 'president-admin');
        $member = $this->createMember($club);
        $document = $this->createProfileDocument($club, $member);

        Sanctum::actingAs($crossClubUser);

        // This is now a foreign-instance URL, rejected before club permissions.
        $this->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/profile-documents")
            ->assertNotFound();

        $this->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/profile-documents/{$document->id}")
            ->assertNotFound();
    }

    public function test_cross_club_member_and_document_mismatches_fail_closed(): void
    {
        $club = $this->createClub('DOC-MAIN');
        $otherClub = $this->createClub('DOC-OTHER');
        $officer = $this->createUserWithRole($club, 'president-admin');
        $member = $this->createMember($club, null, 'DOC-MAIN-MEMBER');
        $otherMember = $this->createMember($otherClub, null, 'DOC-OTHER-MEMBER');
        $otherDocument = $this->createProfileDocument($otherClub, $otherMember);

        Sanctum::actingAs($officer);

        $this->getJson("/api/v1/clubs/{$club->id}/members/{$otherMember->id}/profile-documents")
            ->assertNotFound();

        $this->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/profile-documents/{$otherDocument->id}")
            ->assertNotFound();
    }

    public function test_document_belonging_to_different_member_fails_closed(): void
    {
        $club = $this->createClub();
        $officer = $this->createUserWithRole($club, 'secretary');
        $member = $this->createMember($club, null, 'DOC-SELECTED');
        $otherMember = $this->createMember($club, null, 'DOC-DIFFERENT');
        $otherDocument = $this->createProfileDocument($club, $otherMember);

        Sanctum::actingAs($officer);

        $this->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/profile-documents/{$otherDocument->id}")
            ->assertNotFound();
    }

    public function test_index_and_show_responses_are_metadata_only(): void
    {
        $club = $this->createClub();
        $officer = $this->createUserWithRole($club, 'president-admin');
        $member = $this->createMember($club);
        $document = $this->createProfileDocument($club, $member);

        Sanctum::actingAs($officer);

        $indexResponse = $this->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/profile-documents")
            ->assertOk();

        $showResponse = $this->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/profile-documents/{$document->id}")
            ->assertOk();

        $indexPayload = $indexResponse->json('profile_documents.0');
        $showPayload = $showResponse->json('profile_document');

        $this->assertSame($this->metadataFields(), array_keys($indexPayload));
        $this->assertSame($this->metadataFields(), array_keys($showPayload));

        foreach ($this->blockedResponseFields() as $field) {
            $this->assertArrayNotHasKey($field, $indexPayload);
            $this->assertArrayNotHasKey($field, $showPayload);
        }
    }

    public function test_migration_and_model_are_not_edited_for_file_storage_or_url_behavior(): void
    {
        $model = strtolower(file_get_contents($this->modelPath));
        $migration = strtolower(file_get_contents($this->migrationPath));

        foreach ($this->blockedResponseFields() as $field) {
            $this->assertStringNotContainsString($field, $model);
            $this->assertStringNotContainsString($field, $migration);
        }
    }

    public function test_no_frontend_request_policy_middleware_upload_storage_ocr_or_pdf_artifacts_are_added(): void
    {
        $this->assertFileExists($this->frontendPath);

        foreach ([
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

    public function test_documentation_records_implementation_boundaries(): void
    {
        $document = $this->implementationDocument();

        foreach ([
            'GET /api/v1/clubs/{club}/members/{member}/profile-documents',
            'GET /api/v1/clubs/{club}/members/{member}/profile-documents/{document}',
            'Only GET routes were added.',
            'President/Admin may read member profile document metadata within their club.',
            'Secretary may read member profile document metadata within their club.',
            'Treasurer is blocked in 3CX.',
            'Normal Member may read only their own allowed profile document metadata.',
            'Normal Member must not read another member\'s profile document metadata.',
            'Unauthenticated access is blocked.',
            'Cross-club access fails closed.',
            'The selected member must belong to the selected club.',
            'The selected document must belong to the selected club.',
            'The selected document must belong to the selected member.',
            'The response includes only:',
            'The response does not include:',
            'The migration was not changed.',
            'App\Models\MemberProfileDocument was not changed.',
            'Frontend runtime was not changed.',
            'No file upload was added.',
            'No file storage was added.',
            'No OCR behavior was added.',
            'No PDF generation was added.',
            'No finance behavior was added.',
            'No member import behavior was added.',
            'No deployment implementation was added.',
            'No SaaS implementation was added.',
            'No client-specific behavior was added.',
            'Milestone 3CY - Member Profile Documents Read API Validation and Closure',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    private function implementationDocument(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }

    private function createClub(string $code = 'DOC-CLUB'): Club
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
        config(['instance.organisation_code' => $club->code]);
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

    private function createMember(Club $club, ?User $user = null, string $memberCode = 'DOC-MEMBER'): Member
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

    private function metadataFields(): array
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
