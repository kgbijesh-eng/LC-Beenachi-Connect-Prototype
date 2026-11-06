<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubMembership;
use App\Models\Member;
use App\Models\MemberLedgerEntry;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3byMemberDataImportApiNarrowImplementationTest extends TestCase
{
    use RefreshDatabase;

    private string $docPath = __DIR__ . '/../../docs/milestone_3by_member_data_import_api_narrow_implementation.md';

    public function test_3by_document_records_narrow_preview_api_boundary(): void
    {
        $document = $this->implementationDocument();

        $this->assertStringContainsString('Milestone 3BY - Member Data Import API Narrow Implementation', $document);
        $this->assertStringContainsString('narrow backend API implementation milestone', $document);
        $this->assertStringContainsString('It exposes the existing 3BU member data import preview service through a controlled preview endpoint.', $document);
        $this->assertStringContainsString('It is preview/dry-run only.', $document);
        $this->assertStringContainsString('It does not add a commit endpoint.', $document);
        $this->assertStringContainsString('It does not create, update, or delete members.', $document);
        $this->assertStringContainsString('It does not implement file upload.', $document);
        $this->assertStringContainsString('It does not implement xlsx parsing.', $document);
        $this->assertStringContainsString('It does not change frontend runtime behavior.', $document);
    }

    public function test_3by_allows_only_approved_runtime_doc_and_test_files(): void
    {
        $document = $this->implementationDocument();

        foreach ([
            'backend/app/Http/Controllers/Api/V1/Members/MemberDataImportPreviewController.php',
            'backend/app/Http/Requests/Members/MemberDataImportPreviewRequest.php',
            'backend/app/Http/Resources/Members/MemberDataImportPreviewResource.php',
            'backend/routes/api.php',
            'backend/docs/milestone_3by_member_data_import_api_narrow_implementation.md',
            'backend/tests/Feature/Milestone3byMemberDataImportApiNarrowImplementationTest.php',
        ] as $allowedFile) {
            $this->assertStringContainsString($allowedFile, $document);
        }

        foreach ([
            'app/page.tsx',
            'frontend runtime files',
            'backend/app/Services/Members/MemberDataImportPreviewService.php',
            'backend models',
            'backend middleware',
            'backend migrations',
            'backend seeders',
            'backend config',
            'composer.json',
            'composer.lock',
            'file upload files',
            'file storage files',
            'Excel package files',
            'Excel parser integration files',
            'queue files',
            'job files',
            'finance files',
            'payment files',
            'receipt files',
            'payment-history files',
            'ledger files',
            'committee import files',
            'fellowship import files',
            'activity import files',
            'event import files',
            'export files',
            'PDF files',
            'reminder files',
            'deployment files',
            'SaaS files',
            'client-specific hardcoding files',
        ] as $blockedScope) {
            $this->assertStringContainsString($blockedScope, $document);
        }
    }

    public function test_import_preview_route_is_registered_as_single_post_route(): void
    {
        $routes = collect(Route::getRoutes())
            ->filter(fn ($route): bool => str_contains($route->uri(), 'import-preview'))
            ->map(fn ($route): string => implode('|', $route->methods()).' '.$route->uri())
            ->values()
            ->all();

        $this->assertSame([
            'POST api/v1/clubs/{club}/members/import-preview',
        ], $routes);
    }

    public function test_president_admin_secretary_and_treasurer_can_preview_member_import_rows(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();

        foreach (['president-admin', 'secretary', 'treasurer'] as $roleSlug) {
            $user = $this->userForRole($club, $roleSlug);

            config(['instance.organisation_code' => $club->code]);
            $response = $this
                ->withHeader('Authorization', 'Bearer '.$user->createToken('test-token')->plainTextToken)
                ->postJson("/api/v1/clubs/{$club->id}/members/import-preview", [
                    'rows' => [[
                        'Member Code' => 'MEM-3BY-'.$roleSlug,
                        'Member Name' => 'Preview '.$roleSlug,
                        'Mobile' => '+91 98765 43210',
                        'Email Address' => $roleSlug.'-preview@example.test',
                        'Member Status' => 'Active',
                    ]],
                ]);

            $response->assertOk()
                ->assertJsonPath('import_preview.dry_run', true)
                ->assertJsonPath('import_preview.committed', false)
                ->assertJsonPath('import_preview.summary.total_rows', 1)
                ->assertJsonPath('import_preview.summary.accepted_rows', 1)
                ->assertJsonPath('import_preview.rows.0.intent', 'create_preview')
                ->assertJsonPath('import_preview.rows.0.normalized.member_code', 'MEM-3BY-'.strtoupper($roleSlug))
                ->assertJsonPath('import_preview.rows.0.normalized.phone', '+919876543210');
        }
    }

    public function test_normal_member_cannot_access_import_preview_api(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $user = $this->userForRole($club, 'member');

        config(['instance.organisation_code' => $club->code]);
        $response = $this
            ->withHeader('Authorization', 'Bearer '.$user->createToken('test-token')->plainTextToken)
            ->postJson("/api/v1/clubs/{$club->id}/members/import-preview", [
                'rows' => [[
                    'Member Code' => 'MEM-DENIED',
                    'Full Name' => 'Denied Member',
                ]],
            ]);

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_import_preview_api(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();

        config(['instance.organisation_code' => $club->code]);
        $response = $this->postJson("/api/v1/clubs/{$club->id}/members/import-preview", [
            'rows' => [[
                'Member Code' => 'MEM-UNAUTH',
                'Full Name' => 'Unauthenticated Member',
            ]],
        ]);

        $response->assertUnauthorized();
    }

    public function test_cross_club_import_preview_access_fails_closed(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $otherClub = Club::query()->create([
            'name' => 'Other Club',
            'code' => 'OTHER-3BY',
            'district' => '318E',
        ]);
        $user = $this->userForRole($club, 'president-admin');

        config(['instance.organisation_code' => $otherClub->code]);
        $response = $this
            ->withHeader('Authorization', 'Bearer '.$user->createToken('test-token')->plainTextToken)
            ->postJson("/api/v1/clubs/{$otherClub->id}/members/import-preview", [
                'rows' => [[
                    'Member Code' => 'MEM-CROSS',
                    'Full Name' => 'Cross Club Preview',
                ]],
            ]);

        $response->assertForbidden();
    }

    public function test_import_preview_request_validates_json_rows_and_prohibits_mutation_upload_and_finance_controls(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $user = $this->userForRole($club, 'president-admin');

        config(['instance.organisation_code' => $club->code]);
        $response = $this
            ->withHeader('Authorization', 'Bearer '.$user->createToken('test-token')->plainTextToken)
            ->postJson("/api/v1/clubs/{$club->id}/members/import-preview", [
                'rows' => 'not-rows',
                'commit' => true,
                'import' => true,
                'dry_run' => false,
                'file' => 'members.xlsx',
                'upload' => true,
                'xlsx' => true,
                'club_id' => $club->id,
                'created_by_user_id' => $user->id,
                'finance' => [],
                'payments' => [],
                'receipts' => [],
                'ledger' => [],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'rows',
                'commit',
                'import',
                'dry_run',
                'file',
                'upload',
                'xlsx',
                'club_id',
                'created_by_user_id',
                'finance',
                'payments',
                'receipts',
                'ledger',
            ]);
    }

    public function test_import_preview_response_returns_row_level_errors_and_duplicate_warnings(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $user = $this->userForRole($club, 'secretary');

        config(['instance.organisation_code' => $club->code]);
        $response = $this
            ->withHeader('Authorization', 'Bearer '.$user->createToken('test-token')->plainTextToken)
            ->postJson("/api/v1/clubs/{$club->id}/members/import-preview", [
                'rows' => [[
                    'Member Code' => 'MEM-DUP-3BY',
                    'Full Name' => '',
                    'Email' => 'invalid-email',
                ], [
                    'Member Code' => 'mem-dup-3by',
                    'Full Name' => 'Duplicate Member',
                    'Email' => 'duplicate@example.test',
                ]],
            ]);

        $response->assertOk()
            ->assertJsonPath('import_preview.dry_run', true)
            ->assertJsonPath('import_preview.committed', false)
            ->assertJsonPath('import_preview.summary.total_rows', 2)
            ->assertJsonPath('import_preview.summary.rejected_rows', 2)
            ->assertJsonPath('import_preview.summary.duplicate_rows', 2)
            ->assertJsonPath('import_preview.rows.0.intent', 'reject')
            ->assertJsonPath('import_preview.rows.1.intent', 'reject');

        $this->assertContains('The full name field is required.', $response->json('import_preview.rows.0.errors'));
        $this->assertContains('The email field must be a valid email address.', $response->json('import_preview.rows.0.errors'));
        $this->assertContains('Duplicate member_code inside import batch.', $response->json('import_preview.rows.1.errors'));
        $this->assertSame([1], $response->json('import_preview.rows.1.duplicate_matches.batch.0.matched_rows'));
    }

    public function test_import_preview_reports_existing_member_matches_without_mutating_records(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $user = $this->userForRole($club, 'treasurer');
        $existing = Member::query()->create([
            'club_id' => $club->id,
            'member_code' => 'MEM-EXIST-3BY',
            'full_name' => 'Existing Preview Member',
            'phone' => '9000000001',
            'email' => 'existing-3by@example.test',
            'status' => 'active',
        ]);

        $beforeMembers = Member::query()->count();

        config(['instance.organisation_code' => $club->code]);
        $response = $this
            ->withHeader('Authorization', 'Bearer '.$user->createToken('test-token')->plainTextToken)
            ->postJson("/api/v1/clubs/{$club->id}/members/import-preview", [
                'rows' => [[
                    'Member Code' => 'MEM-EXIST-3BY',
                    'Full Name' => 'Incoming Existing Preview',
                    'Phone' => '90000 00001',
                    'Email' => 'existing-3by@example.test',
                ]],
            ]);

        $response->assertOk()
            ->assertJsonPath('import_preview.committed', false)
            ->assertJsonPath('import_preview.summary.possible_existing_match_rows', 1)
            ->assertJsonPath('import_preview.rows.0.intent', 'update_preview')
            ->assertJsonPath('import_preview.rows.0.duplicate_matches.existing.0.member_id', $existing->id)
            ->assertJsonPath('import_preview.rows.0.duplicate_matches.existing.0.member_code', 'MEM-EXIST-3BY');

        $this->assertSame($beforeMembers, Member::query()->count());
        $this->assertDatabaseHas('members', [
            'id' => $existing->id,
            'full_name' => 'Existing Preview Member',
            'phone' => '9000000001',
            'email' => 'existing-3by@example.test',
        ]);
    }

    public function test_import_preview_does_not_mutate_member_finance_payment_receipt_or_ledger_records(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $user = $this->userForRole($club, 'president-admin');

        $before = [
            'members' => Member::query()->count(),
            'ledger' => MemberLedgerEntry::query()->count(),
            'payments' => Payment::query()->count(),
            'receipts' => Receipt::query()->count(),
        ];

        config(['instance.organisation_code' => $club->code]);
        $response = $this
            ->withHeader('Authorization', 'Bearer '.$user->createToken('test-token')->plainTextToken)
            ->postJson("/api/v1/clubs/{$club->id}/members/import-preview", [
                'rows' => [[
                    'Member Code' => 'MEM-NON-MUTATING',
                    'Full Name' => 'Preview Only Member',
                    'Payment Amount' => '9999',
                    'Receipt Number' => 'R-3BY-001',
                    'Ledger Balance' => '8888',
                ]],
            ]);

        $response->assertOk()
            ->assertJsonPath('import_preview.rows.0.intent', 'create_preview');

        $this->assertSame($before['members'], Member::query()->count());
        $this->assertSame($before['ledger'], MemberLedgerEntry::query()->count());
        $this->assertSame($before['payments'], Payment::query()->count());
        $this->assertSame($before['receipts'], Receipt::query()->count());
        $this->assertArrayNotHasKey('Payment Amount', $response->json('import_preview.rows.0.normalized'));
        $this->assertArrayNotHasKey('Receipt Number', $response->json('import_preview.rows.0.normalized'));
        $this->assertArrayNotHasKey('Ledger Balance', $response->json('import_preview.rows.0.normalized'));
    }

    public function test_3by_does_not_add_commit_upload_parser_finance_or_frontend_runtime_files(): void
    {
        foreach ([
            // app_path('Http/Controllers/Api/V1/Members/MemberDataImportCommitController.php'),
            app_path('Http/Controllers/Api/V1/Members/MemberDataImportUploadController.php'),
            app_path('Imports/MemberImport.php'),
            app_path('Jobs/ProcessMemberImport.php'),
            app_path('Models/MemberImport.php'),
            app_path('Services/Members/MemberImportCommitService.php'),
            app_path('Services/Members/MemberImportUploadService.php'),
            app_path('Services/Members/MemberExcelParser.php'),
            database_path('migrations/create_member_imports_table.php'),
            database_path('seeders/MemberImportSeeder.php'),
            base_path('../app/member-import'),
            base_path('../app/import'),
            base_path('../components/member-import'),
            base_path('../lib/member-import.ts'),
        ] as $blockedPath) {
            $this->assertFileDoesNotExist($blockedPath);
        }
    }

    public function test_3by_does_not_add_commit_upload_or_mutation_import_routes(): void
    {
        $routes = collect(Route::getRoutes())
            ->filter(fn ($route): bool => str_contains($route->uri(), 'import'))
            ->map(fn ($route): string => implode('|', $route->methods()).' '.$route->uri())
            ->values()
            ->all();

        $this->assertSame([
            'POST api/v1/clubs/{club}/members/import-preview',
            'POST api/v1/clubs/{club}/members/import-commit',
        ], $routes);
    }

    public function test_3by_selects_3bz_as_next_validation_and_closure_milestone(): void
    {
        $document = $this->implementationDocument();

        $this->assertStringContainsString('Milestone 3BZ - Member Data Import API Validation and Closure', $document);
        $this->assertStringContainsString('3BZ should validate and close only what 3BY implemented.', $document);
    }

    private function implementationDocument(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }

    private function userForRole(Club $club, string $roleSlug): User
    {
        $role = Role::where('slug', $roleSlug)->firstOrFail();
        $user = User::factory()->create([
            'email' => $roleSlug.'-3by@example.test',
        ]);

        ClubMembership::query()->create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return $user;
    }
}
