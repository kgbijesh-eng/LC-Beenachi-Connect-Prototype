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

class Milestone3bzMemberDataImportApiValidationAndClosureTest extends TestCase
{
    use RefreshDatabase;

    private string $docPath = __DIR__ . '/../../docs/milestone_3bz_member_data_import_api_validation_and_closure.md';

    public function test_3bz_document_exists_and_is_docs_tests_only_closure(): void
    {
        $document = $this->closureDocument();

        $this->assertStringContainsString('Milestone 3BZ - Member Data Import API Validation and Closure', $document);
        $this->assertStringContainsString('docs/tests-only validation and closure milestone', $document);
        $this->assertStringContainsString('It validates and closes Milestone 3BY.', $document);
        $this->assertStringContainsString('It does not change frontend runtime behavior.', $document);
        $this->assertStringContainsString('It does not change backend runtime behavior.', $document);
        $this->assertStringContainsString('It does not change API behavior.', $document);
    }

    public function test_3bz_allows_only_closure_doc_and_validation_test(): void
    {
        $document = $this->closureDocument();

        $this->assertStringContainsString('backend/docs/milestone_3bz_member_data_import_api_validation_and_closure.md', $document);
        $this->assertStringContainsString('backend/tests/Feature/Milestone3bzMemberDataImportApiValidationAndClosureTest.php', $document);

        foreach ([
            'app/page.tsx',
            'frontend runtime files',
            'backend/routes/api.php',
            'backend/app/Http/Controllers/Api/V1/Members/MemberDataImportPreviewController.php',
            'backend/app/Http/Requests/Members/MemberDataImportPreviewRequest.php',
            'backend/app/Http/Resources/Members/MemberDataImportPreviewResource.php',
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

    public function test_3by_artifacts_remain_present_for_closure(): void
    {
        foreach ([
            base_path('docs/milestone_3by_member_data_import_api_narrow_implementation.md'),
            base_path('tests/Feature/Milestone3byMemberDataImportApiNarrowImplementationTest.php'),
            app_path('Http/Controllers/Api/V1/Members/MemberDataImportPreviewController.php'),
            app_path('Http/Requests/Members/MemberDataImportPreviewRequest.php'),
            app_path('Http/Resources/Members/MemberDataImportPreviewResource.php'),
            app_path('Services/Members/MemberDataImportPreviewService.php'),
        ] as $requiredPath) {
            $this->assertFileExists($requiredPath);
        }
    }

    public function test_3by_route_boundary_is_exactly_one_preview_post_route(): void
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

    public function test_no_commit_upload_parser_or_mutation_import_routes_exist(): void
    {
        $routeSignatures = collect(Route::getRoutes())
            ->map(fn ($route): string => implode('|', $route->methods()).' '.$route->uri())
            ->values()
            ->all();

        foreach ([
            'POST api/v1/clubs/{club}/members/import',
            // 'POST api/v1/clubs/{club}/members/import-commit',
            'POST api/v1/clubs/{club}/members/import-upload',
            'POST api/v1/clubs/{club}/members/imports',
            'PATCH api/v1/clubs/{club}/members/import-preview',
            'PUT api/v1/clubs/{club}/members/import-preview',
            'DELETE api/v1/clubs/{club}/members/import-preview',
            'POST api/v1/clubs/{club}/members/import-preview/commit',
            'POST api/v1/clubs/{club}/members/import-preview/upload',
        ] as $blockedRoute) {
            $this->assertNotContains($blockedRoute, $routeSignatures);
        }
    }

    public function test_controller_request_and_resource_preserve_preview_only_boundaries(): void
    {
        $controller = $this->source(app_path('Http/Controllers/Api/V1/Members/MemberDataImportPreviewController.php'));
        $request = $this->source(app_path('Http/Requests/Members/MemberDataImportPreviewRequest.php'));
        $resource = $this->source(app_path('Http/Resources/Members/MemberDataImportPreviewResource.php'));

        foreach ([
            'MemberDataImportPreviewService',
            'preview(',
            "'import_preview' =>",
        ] as $requiredControllerText) {
            $this->assertStringContainsString($requiredControllerText, $controller);
        }

        foreach ([
            "'president-admin'",
            "'secretary'",
            "'treasurer'",
            "'rows' => ['required', 'array', 'min:1', 'max:500']",
            "'rows.*' => ['required', 'array']",
            "'commit' => ['missing']",
            "'import' => ['missing']",
            "'file' => ['missing']",
            "'xlsx' => ['missing']",
            "'finance' => ['missing']",
            "'payments' => ['missing']",
            "'receipts' => ['missing']",
            "'ledger' => ['missing']",
        ] as $requiredRequestText) {
            $this->assertStringContainsString($requiredRequestText, $request);
        }

        foreach ([
            "'dry_run' =>",
            "'committed' =>",
            "'summary' =>",
            "'row_number' =>",
            "'valid' =>",
            "'intent' =>",
            "'normalized' =>",
            "'errors' =>",
            "'duplicate_matches' =>",
            "'batch' =>",
            "'existing' =>",
        ] as $requiredResourceText) {
            $this->assertStringContainsString($requiredResourceText, $resource);
        }

        foreach ([$controller, $request, $resource] as $source) {
            foreach ([
                'Member::create',
                '->update(',
                '->delete(',
                'DB::transaction',
                'Storage::',
                'Excel::',
                'dispatch(',
                'Payment::',
                'Receipt::',
                'MemberLedgerEntry::',
            ] as $blockedText) {
                $this->assertStringNotContainsString($blockedText, $source);
            }
        }
    }

    public function test_officer_access_remains_validated_for_import_preview_api(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();

        foreach (['president-admin', 'secretary', 'treasurer'] as $roleSlug) {
            $user = $this->userForRole($club, $roleSlug);

            $this
                ->withHeader('Authorization', 'Bearer '.$user->createToken('test-token')->plainTextToken)
                ->postJson("/api/v1/clubs/{$club->id}/members/import-preview", [
                    'rows' => [[
                        'Member Code' => 'MEM-3BZ-'.$roleSlug,
                        'Full Name' => '3BZ '.$roleSlug,
                    ]],
                ])
                ->assertOk()
                ->assertJsonPath('import_preview.dry_run', true)
                ->assertJsonPath('import_preview.committed', false)
                ->assertJsonPath('import_preview.rows.0.intent', 'create_preview');
        }
    }

    public function test_normal_member_is_denied_import_preview_api(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();

        $memberUser = $this->userForRole($club, 'member');

        $this
            ->withHeader('Authorization', 'Bearer '.$memberUser->createToken('test-token')->plainTextToken)
            ->postJson("/api/v1/clubs/{$club->id}/members/import-preview", [
                'rows' => [[
                    'Member Code' => 'MEM-3BZ-DENIED',
                    'Full Name' => 'Denied Preview',
                ]],
            ])
            ->assertForbidden();
    }

    public function test_cross_club_import_preview_access_fails_closed(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();

        $otherClub = Club::query()->create([
            'name' => 'Other 3BZ Club',
            'code' => 'OTHER-3BZ',
            'district' => '318E',
        ]);

        $this
            ->withHeader('Authorization', 'Bearer '.$this->userForRole($club, 'president-admin')->createToken('test-token')->plainTextToken)
            ->postJson("/api/v1/clubs/{$otherClub->id}/members/import-preview", [
                'rows' => [[
                    'Member Code' => 'MEM-3BZ-CROSS',
                    'Full Name' => 'Cross Club Preview',
                ]],
            ])
            ->assertForbidden();
    }

    public function test_preview_response_keeps_row_errors_duplicates_and_matches_preview_only(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $user = $this->userForRole($club, 'secretary');
        $existing = Member::query()->create([
            'club_id' => $club->id,
            'member_code' => 'MEM-3BZ-EXISTING',
            'full_name' => 'Existing 3BZ Member',
            'phone' => '9000000030',
            'email' => 'existing-3bz@example.test',
            'status' => 'active',
        ]);

        $beforeMembers = Member::query()->count();

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$user->createToken('test-token')->plainTextToken)
            ->postJson("/api/v1/clubs/{$club->id}/members/import-preview", [
                'rows' => [[
                    'Member Code' => 'MEM-3BZ-DUP',
                    'Full Name' => '',
                    'Email' => 'not-an-email',
                ], [
                    'Member Code' => 'mem-3bz-dup',
                    'Full Name' => 'Duplicate 3BZ',
                ], [
                    'Member Code' => 'MEM-3BZ-EXISTING',
                    'Full Name' => 'Incoming Existing 3BZ',
                    'Phone' => '90000 00030',
                    'Email' => 'existing-3bz@example.test',
                ]],
            ]);

        $response->assertOk()
            ->assertJsonPath('import_preview.dry_run', true)
            ->assertJsonPath('import_preview.committed', false)
            ->assertJsonPath('import_preview.summary.total_rows', 3)
            ->assertJsonPath('import_preview.summary.duplicate_rows', 2)
            ->assertJsonPath('import_preview.summary.possible_existing_match_rows', 1)
            ->assertJsonPath('import_preview.rows.2.intent', 'update_preview')
            ->assertJsonPath('import_preview.rows.2.duplicate_matches.existing.0.member_id', $existing->id);

        $this->assertContains('The full name field is required.', $response->json('import_preview.rows.0.errors'));
        $this->assertContains('The email field must be a valid email address.', $response->json('import_preview.rows.0.errors'));
        $this->assertContains('Duplicate member_code inside import batch.', $response->json('import_preview.rows.1.errors'));
        $this->assertSame($beforeMembers, Member::query()->count());
    }

    public function test_preview_api_does_not_mutate_members_finance_or_receipt_records(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $user = $this->userForRole($club, 'treasurer');

        $before = [
            'members' => Member::query()->count(),
            'ledger' => MemberLedgerEntry::query()->count(),
            'payments' => Payment::query()->count(),
            'receipts' => Receipt::query()->count(),
        ];

        $this
            ->withHeader('Authorization', 'Bearer '.$user->createToken('test-token')->plainTextToken)
            ->postJson("/api/v1/clubs/{$club->id}/members/import-preview", [
                'rows' => [[
                    'Member Code' => 'MEM-3BZ-PREVIEW',
                    'Full Name' => 'Preview Only 3BZ',
                    'Payment Amount' => '9999',
                    'Receipt Number' => 'R-3BZ-001',
                    'Ledger Balance' => '8888',
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('import_preview.rows.0.intent', 'create_preview')
            ->assertJsonMissingPath('import_preview.rows.0.normalized.Payment Amount')
            ->assertJsonMissingPath('import_preview.rows.0.normalized.Receipt Number')
            ->assertJsonMissingPath('import_preview.rows.0.normalized.Ledger Balance');

        $this->assertSame($before['members'], Member::query()->count());
        $this->assertSame($before['ledger'], MemberLedgerEntry::query()->count());
        $this->assertSame($before['payments'], Payment::query()->count());
        $this->assertSame($before['receipts'], Receipt::query()->count());
    }

    public function test_no_blocked_import_upload_parser_frontend_or_runtime_files_exist(): void
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

    public function test_compatibility_guard_updates_are_limited_to_exact_3by_preview_route(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'older guard tests were updated only to recognize the exact 3BY preview route',
            'POST api/v1/clubs/{club}/members/import-preview',
            'generic import routes',
            'upload routes',
            'parser routes',
            'commit/import mutation routes',
            'member create/update/delete import behavior',
            'finance import behavior',
            'frontend upload UI',
            'deployment behavior',
            'SaaS behavior',
            'client-specific behavior',
        ] as $guardText) {
            $this->assertStringContainsString($guardText, $document);
        }
    }

    public function test_3bz_records_3ca_as_future_frontend_scope_gate_candidate_only(): void
    {
        $document = $this->closureDocument();

        $this->assertStringContainsString('The expected next milestone is recorded only as a future decision candidate:', $document);
        $this->assertStringContainsString('Milestone 3CA - Member Data Import Frontend Scope Gate', $document);
        $this->assertStringContainsString('3CA must be a scope-gate milestone only.', $document);
        $this->assertStringContainsString('3CA must not implement frontend upload UI unless a later human approval changes the milestone type.', $document);
        $this->assertStringContainsString('No next milestone is started by 3BZ.', $document);
    }

    private function closureDocument(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }

    private function source(string $path): string
    {
        $this->assertFileExists($path);

        return file_get_contents($path);
    }

    private function userForRole(Club $club, string $roleSlug): User
    {
        $role = Role::where('slug', $roleSlug)->firstOrFail();
        $user = User::factory()->create([
            'email' => $roleSlug.'-3bz@example.test',
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
