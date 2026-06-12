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

class Milestone3ciMemberDataImportCommitBackendNarrowImplementationTest extends TestCase
{
    use RefreshDatabase;

    private string $docPath = __DIR__ . '/../../docs/milestone_3ci_member_data_import_commit_backend_narrow_implementation.md';

    public function test_3ci_document_records_narrow_commit_api_boundary(): void
    {
        $document = $this->implementationDocument();

        $this->assertStringContainsString('Milestone 3CI — Member Data Import Commit Backend Narrow Implementation', $document);
        $this->assertStringContainsString('Purpose of 3CI', $document);
        $this->assertStringContainsString('POST /api/v1/clubs/{club}/members/import-commit', $document);
        $this->assertStringContainsString('Relationship to Existing Preview Endpoint', $document);
        $this->assertStringContainsString('Officer-Only Access', $document);
        $this->assertStringContainsString('Normal Member Exclusion', $document);
        $this->assertStringContainsString('Cross-Club Fail-Closed Behavior', $document);
        $this->assertStringContainsString('Create-Only New Members', $document);
        $this->assertStringContainsString('No Updates/Deletes', $document);
        $this->assertStringContainsString('No Finance Mutation', $document);
    }

    public function test_3ci_allows_only_approved_runtime_doc_and_test_files(): void
    {
        $document = $this->implementationDocument();

        foreach ([
            'backend/routes/api.php',
            'backend/app/Http/Controllers/Api/V1/Members/MemberDataImportCommitController.php',
            'backend/app/Http/Requests/Members/MemberDataImportCommitRequest.php',
            'backend/app/Http/Resources/Members/MemberDataImportCommitResource.php',
            'backend/app/Services/Members/MemberDataImportCommitService.php',
            'backend/docs/milestone_3ci_member_data_import_commit_backend_narrow_implementation.md',
            'backend/tests/Feature/Milestone3ciMemberDataImportCommitBackendNarrowImplementationTest.php',
        ] as $allowedFile) {
            $this->assertStringContainsString($allowedFile, $document);
        }
    }

    public function test_import_commit_route_is_registered_correctly(): void
    {
        $routes = collect(Route::getRoutes())
            ->filter(fn ($route): bool => str_contains($route->uri(), 'import-commit'))
            ->map(fn ($route): string => implode('|', $route->methods()).' '.$route->uri())
            ->values()
            ->all();

        $this->assertSame([
            'POST api/v1/clubs/{club}/members/import-commit',
        ], $routes);
    }

    public function test_existing_preview_route_remains_separate(): void
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

    public function test_officers_can_commit_eligible_new_member_rows(): void
    {
        $this->seed(DatabaseSeeder::class);
        $club = Club::where('code', 'LCB')->firstOrFail();

        foreach (['president-admin', 'secretary', 'treasurer'] as $index => $roleSlug) {
            $user = $this->userForRole($club, $roleSlug);
            $initialCount = Member::query()->count();

            $response = $this
                ->withHeader('Authorization', 'Bearer '.$user->createToken('test-token')->plainTextToken)
                ->postJson("/api/v1/clubs/{$club->id}/members/import-commit", [
                    'rows' => [[
                        'Member Code' => 'MEM-3CI-'.$roleSlug,
                        'Member Name' => 'New Member '.$roleSlug,
                        'Mobile' => '987654321'.$index,
                        'Email Address' => $roleSlug.'-commit@example.test',
                        'Member Status' => 'Active',
                    ]],
                ]);

            $response->assertOk()
                ->assertJsonPath('import_commit.dry_run', false)
                ->assertJsonPath('import_commit.committed', true)
                ->assertJsonPath('import_commit.summary.total_rows', 1)
                ->assertJsonPath('import_commit.summary.created_rows', 1)
                ->assertJsonPath('import_commit.summary.skipped_rows', 0)
                ->assertJsonPath('import_commit.summary.failed_rows', 0)
                ->assertJsonPath('import_commit.rows.0.status', 'created')
                ->assertJsonPath('import_commit.rows.0.normalized.member_code', 'MEM-3CI-'.strtoupper($roleSlug));

            $this->assertNotNull($response->json('import_commit.rows.0.member_id'));
            $this->assertSame($initialCount + 1, Member::query()->count());

            $this->assertDatabaseHas('members', [
                'id' => $response->json('import_commit.rows.0.member_id'),
                'member_code' => 'MEM-3CI-'.strtoupper($roleSlug),
                'full_name' => 'New Member '.$roleSlug,
                'club_id' => $club->id,
            ]);
        }
    }

    public function test_normal_member_cannot_commit_members(): void
    {
        $this->seed(DatabaseSeeder::class);
        $club = Club::where('code', 'LCB')->firstOrFail();
        $user = $this->userForRole($club, 'member');

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$user->createToken('test-token')->plainTextToken)
            ->postJson("/api/v1/clubs/{$club->id}/members/import-commit", [
                'rows' => [[
                    'Member Code' => 'MEM-DENIED-3CI',
                    'Full Name' => 'Denied Commit Member',
                ]],
            ]);

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_commit_members(): void
    {
        $this->seed(DatabaseSeeder::class);
        $club = Club::where('code', 'LCB')->firstOrFail();

        $response = $this->postJson("/api/v1/clubs/{$club->id}/members/import-commit", [
            'rows' => [[
                'Member Code' => 'MEM-UNAUTH-3CI',
                'Full Name' => 'Unauth Commit Member',
            ]],
        ]);

        $response->assertUnauthorized();
    }

    public function test_cross_club_import_commit_fails_closed(): void
    {
        $this->seed(DatabaseSeeder::class);
        $club = Club::where('code', 'LCB')->firstOrFail();
        $otherClub = Club::query()->create([
            'name' => 'Other Club 3CI',
            'code' => 'OTH-3CI',
            'district' => '318E',
        ]);
        $user = $this->userForRole($club, 'president-admin');

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$user->createToken('test-token')->plainTextToken)
            ->postJson("/api/v1/clubs/{$otherClub->id}/members/import-commit", [
                'rows' => [[
                    'Member Code' => 'MEM-CROSS-3CI',
                    'Full Name' => 'Cross Commit Member',
                ]],
            ]);

        $response->assertForbidden();
    }

    public function test_invalid_rows_fail_commit_and_are_not_created(): void
    {
        $this->seed(DatabaseSeeder::class);
        $club = Club::where('code', 'LCB')->firstOrFail();
        $user = $this->userForRole($club, 'president-admin');

        $initialCount = Member::query()->count();

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$user->createToken('test-token')->plainTextToken)
            ->postJson("/api/v1/clubs/{$club->id}/members/import-commit", [
                'rows' => [
                    [
                        'Member Code' => '', // Required code is empty
                        'Full Name' => 'Invalid Code Member',
                    ],
                    [
                        'Member Code' => 'MEM-3CI-VALID',
                        'Full Name' => '', // Required name is empty
                    ]
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('import_commit.summary.total_rows', 2)
            ->assertJsonPath('import_commit.summary.created_rows', 0)
            ->assertJsonPath('import_commit.summary.failed_rows', 2)
            ->assertJsonPath('import_commit.rows.0.status', 'failed')
            ->assertJsonPath('import_commit.rows.1.status', 'failed');

        $this->assertSame($initialCount, Member::query()->count());
    }

    public function test_duplicate_rows_in_batch_are_skipped_or_failed_and_not_created(): void
    {
        $this->seed(DatabaseSeeder::class);
        $club = Club::where('code', 'LCB')->firstOrFail();
        $user = $this->userForRole($club, 'secretary');

        $initialCount = Member::query()->count();

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$user->createToken('test-token')->plainTextToken)
            ->postJson("/api/v1/clubs/{$club->id}/members/import-commit", [
                'rows' => [
                    [
                        'Member Code' => 'MEM-DUP-BATCH',
                        'Full Name' => 'Duplicate One',
                    ],
                    [
                        'Member Code' => 'MEM-DUP-BATCH', // Duplicate member code
                        'Full Name' => 'Duplicate Two',
                    ]
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('import_commit.summary.total_rows', 2)
            ->assertJsonPath('import_commit.summary.created_rows', 0)
            ->assertJsonPath('import_commit.summary.failed_rows', 2)
            ->assertJsonPath('import_commit.rows.0.status', 'failed')
            ->assertJsonPath('import_commit.rows.1.status', 'failed');

        $this->assertContains('Duplicate member_code inside import batch.', $response->json('import_commit.rows.0.errors'));
        $this->assertContains('Duplicate member_code inside import batch.', $response->json('import_commit.rows.1.errors'));

        $this->assertSame($initialCount, Member::query()->count());
    }

    public function test_existing_member_matches_are_skipped_and_never_updated_or_overwritten(): void
    {
        $this->seed(DatabaseSeeder::class);
        $club = Club::where('code', 'LCB')->firstOrFail();
        $user = $this->userForRole($club, 'treasurer');

        $existing = Member::query()->create([
            'club_id' => $club->id,
            'member_code' => 'MEM-EXIST-3CI',
            'full_name' => 'Original Member Name',
            'phone' => '1234567890',
            'email' => 'original@example.test',
        ]);

        $initialCount = Member::query()->count();

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$user->createToken('test-token')->plainTextToken)
            ->postJson("/api/v1/clubs/{$club->id}/members/import-commit", [
                'rows' => [[
                    'Member Code' => 'MEM-EXIST-3CI',
                    'Full Name' => 'Attempted Overwrite Name',
                    'Phone' => '1234567890',
                ]],
            ]);

        $response->assertOk()
            ->assertJsonPath('import_commit.summary.total_rows', 1)
            ->assertJsonPath('import_commit.summary.created_rows', 0)
            ->assertJsonPath('import_commit.summary.skipped_rows', 1)
            ->assertJsonPath('import_commit.rows.0.status', 'skipped');

        $this->assertContains("Existing member match found: member_code matches member ID {$existing->id}.", $response->json('import_commit.rows.0.warnings'));

        $this->assertSame($initialCount, Member::query()->count());

        $this->assertDatabaseHas('members', [
            'id' => $existing->id,
            'full_name' => 'Original Member Name',
            'member_code' => 'MEM-EXIST-3CI',
        ]);
    }

    public function test_import_commit_does_not_mutate_member_finance_payment_receipt_or_ledger_records(): void
    {
        $this->seed(DatabaseSeeder::class);
        $club = Club::where('code', 'LCB')->firstOrFail();
        $user = $this->userForRole($club, 'president-admin');

        $before = [
            'ledger' => MemberLedgerEntry::query()->count(),
            'payments' => Payment::query()->count(),
            'receipts' => Receipt::query()->count(),
        ];

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$user->createToken('test-token')->plainTextToken)
            ->postJson("/api/v1/clubs/{$club->id}/members/import-commit", [
                'rows' => [[
                    'Member Code' => 'MEM-NO-FINANCE-3CI',
                    'Full Name' => 'New Import Member',
                    'Payment Amount' => '12500',
                    'Receipt Number' => 'R-3CI-999',
                    'Ledger Balance' => '10000',
                ]],
            ]);

        $response->assertOk()
            ->assertJsonPath('import_commit.summary.created_rows', 1);

        $this->assertSame($before['ledger'], MemberLedgerEntry::query()->count());
        $this->assertSame($before['payments'], Payment::query()->count());
        $this->assertSame($before['receipts'], Receipt::query()->count());
        $this->assertArrayNotHasKey('Payment Amount', $response->json('import_commit.rows.0.normalized'));
        $this->assertArrayNotHasKey('Receipt Number', $response->json('import_commit.rows.0.normalized'));
        $this->assertArrayNotHasKey('Ledger Balance', $response->json('import_commit.rows.0.normalized'));
    }

    public function test_3ci_does_not_add_blocked_files(): void
    {
        foreach ([
            app_path('Http/Controllers/Api/V1/Members/MemberDataImportUploadController.php'),
            app_path('Imports/MemberImport.php'),
            app_path('Jobs/ProcessMemberImport.php'),
            app_path('Models/MemberImport.php'),
            app_path('Services/Members/MemberExcelParser.php'),
            database_path('migrations/create_member_imports_table.php'),
            database_path('seeders/MemberImportSeeder.php'),
            base_path('../app/member-import'),
            base_path('../components/member-import'),
        ] as $blockedPath) {
            $this->assertFileDoesNotExist($blockedPath);
        }
    }

    public function test_3ci_selects_3cj_as_next_validation_and_closure_milestone(): void
    {
        $document = $this->implementationDocument();

        $this->assertStringContainsString('Milestone 3CJ — Member Data Import Commit Backend Validation and Closure', $document);
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
            'email' => $roleSlug.'-3ci@example.test',
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
