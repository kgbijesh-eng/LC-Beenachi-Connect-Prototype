<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Member;
use App\Services\Members\MemberDataImportPreviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3bvMemberDataImportExcelOnboardingValidationAndClosureTest extends TestCase
{
    use RefreshDatabase;

    private string $docPath = __DIR__ . '/../../docs/milestone_3bv_member_data_import_excel_onboarding_validation_and_closure.md';

    public function test_3bv_document_exists_and_is_docs_tests_only_closure(): void
    {
        $document = $this->closureDocument();

        $this->assertStringContainsString('Milestone 3BV - Member Data Import / Excel Onboarding Validation and Closure', $document);
        $this->assertStringContainsString('docs/tests-only validation and closure milestone', $document);
        $this->assertStringContainsString('It validates and closes Milestone 3BU.', $document);
        $this->assertStringContainsString('It does not change frontend runtime behavior.', $document);
        $this->assertStringContainsString('It does not change backend runtime behavior.', $document);
        $this->assertStringContainsString('It does not change API behavior.', $document);
    }

    public function test_3bv_allows_only_closure_doc_and_validation_test(): void
    {
        $document = $this->closureDocument();

        $this->assertStringContainsString('backend/docs/milestone_3bv_member_data_import_excel_onboarding_validation_and_closure.md', $document);
        $this->assertStringContainsString('backend/tests/Feature/Milestone3bvMemberDataImportExcelOnboardingValidationAndClosureTest.php', $document);

        foreach ([
            'app/page.tsx',
            'frontend runtime files',
            'backend/app/Services/Members/MemberDataImportPreviewService.php',
            'backend routes',
            'backend controllers',
            'backend requests',
            'backend resources',
            'backend models',
            'backend middleware',
            'backend migrations',
            'backend seeders',
            'backend config',
            'composer.json',
            'composer.lock',
            'API behavior',
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

    public function test_3bu_artifacts_remain_present_for_closure(): void
    {
        foreach ([
            base_path('docs/milestone_3bu_member_data_import_excel_onboarding_narrow_implementation.md'),
            base_path('tests/Feature/Milestone3buMemberDataImportExcelOnboardingNarrowImplementationTest.php'),
            app_path('Services/Members/MemberDataImportPreviewService.php'),
        ] as $requiredPath) {
            $this->assertFileExists($requiredPath);
        }
    }

    public function test_preview_service_remains_dry_run_array_based_and_non_committing(): void
    {
        $club = $this->createClub();

        $preview = $this->service()->preview($club->id, [[
            'Member Code' => 'MEM-3BV-001',
            'Member Name' => 'Closure Preview Member',
            'Mobile' => '+91 90000 00001',
            'Email Address' => 'CLOSURE@EXAMPLE.TEST',
            'Member Status' => 'Active',
            'Joined Date' => '2026-06-12',
            'Blood Group' => 'a+',
            'Donor Consent' => 'yes',
            'Payment Opening Balance' => '1000',
            'Ledger Notes' => 'must be ignored',
        ]]);

        $this->assertTrue($preview['dry_run']);
        $this->assertFalse($preview['committed']);
        $this->assertSame(1, $preview['summary']['total_rows']);
        $this->assertSame(1, $preview['summary']['accepted_rows']);
        $this->assertSame('create_preview', $preview['rows'][0]['intent']);
        $this->assertSame('MEM-3BV-001', $preview['rows'][0]['normalized']['member_code']);
        $this->assertSame('Closure Preview Member', $preview['rows'][0]['normalized']['full_name']);
        $this->assertSame('+919000000001', $preview['rows'][0]['normalized']['phone']);
        $this->assertSame('closure@example.test', $preview['rows'][0]['normalized']['email']);
        $this->assertSame('active', $preview['rows'][0]['normalized']['status']);
        $this->assertSame('2026-06-12', $preview['rows'][0]['normalized']['joined_at']);
        $this->assertSame('A+', $preview['rows'][0]['normalized']['blood_group']);
        $this->assertTrue($preview['rows'][0]['normalized']['blood_donor_consent']);
        $this->assertArrayNotHasKey('Payment Opening Balance', $preview['rows'][0]['normalized']);
        $this->assertArrayNotHasKey('Ledger Notes', $preview['rows'][0]['normalized']);
        $this->assertSame(0, Member::query()->count());
    }

    public function test_preview_service_reports_row_level_validation_errors_and_reject_intent(): void
    {
        $club = $this->createClub();

        $preview = $this->service()->preview($club->id, [[
            'Member Code' => '',
            'Full Name' => '',
            'Email' => 'invalid-email',
            'Status' => 'retired',
            'Joined Date' => 'not-a-date',
            'Blood Donor Consent' => 'unknown',
        ]]);

        $this->assertSame(1, $preview['summary']['rejected_rows']);
        $this->assertFalse($preview['rows'][0]['valid']);
        $this->assertSame('reject', $preview['rows'][0]['intent']);

        foreach ([
            'The member code field is required.',
            'The full name field is required.',
            'The email field must be a valid email address.',
            'The selected status is invalid.',
            'The joined at field must be a valid date.',
            'The blood donor consent field must be true or false.',
        ] as $expectedError) {
            $this->assertContains($expectedError, $preview['rows'][0]['errors']);
        }
    }

    public function test_preview_service_detects_batch_duplicates_by_code_phone_and_email(): void
    {
        $club = $this->createClub();

        $preview = $this->service()->preview($club->id, [
            [
                'Member Code' => 'MEM-DUP-3BV',
                'Full Name' => 'First Duplicate',
                'Phone' => '9000000002',
                'Email' => 'duplicate@example.test',
            ],
            [
                'Code' => 'mem-dup-3bv',
                'Name' => 'Second Duplicate',
                'Phone Number' => '90000 00002',
                'Email Address' => 'DUPLICATE@EXAMPLE.TEST',
            ],
        ]);

        $this->assertSame(2, $preview['summary']['duplicate_rows']);
        $this->assertSame(2, $preview['summary']['rejected_rows']);

        foreach ([
            'Duplicate member_code inside import batch.',
            'Duplicate phone inside import batch.',
            'Duplicate email inside import batch.',
        ] as $expectedError) {
            $this->assertContains($expectedError, $preview['rows'][0]['errors']);
            $this->assertContains($expectedError, $preview['rows'][1]['errors']);
        }
    }

    public function test_preview_service_detects_existing_member_matches_inside_same_club_as_preview_only(): void
    {
        $club = $this->createClub();
        $otherClub = $this->createClub('LCB-3BV-OTHER');
        $existing = $this->createMember($club, 'MEM-3BV-EXISTING', 'Existing Closure Member', '9000000003', 'existing-3bv@example.test');
        $this->createMember($otherClub, 'MEM-3BV-CROSS', 'Cross Club Member', '9000000004', 'cross-3bv@example.test');

        $beforeMembers = Member::query()->count();

        $preview = $this->service()->preview($club->id, [[
            'Member Code' => 'MEM-3BV-EXISTING',
            'Full Name' => 'Incoming Existing Member',
            'Phone' => '90000 00003',
            'Email' => 'existing-3bv@example.test',
        ], [
            'Member Code' => 'MEM-3BV-CROSS',
            'Full Name' => 'Incoming Cross Club Member',
            'Phone' => '9000000004',
            'Email' => 'cross-3bv@example.test',
        ]]);

        $this->assertSame($beforeMembers, Member::query()->count());
        $this->assertSame(1, $preview['summary']['possible_existing_match_rows']);
        $this->assertSame('update_preview', $preview['rows'][0]['intent']);
        $this->assertSame($existing->id, $preview['rows'][0]['duplicate_matches']['existing'][0]['member_id']);
        $this->assertSame('MEM-3BV-EXISTING', $preview['rows'][0]['duplicate_matches']['existing'][0]['member_code']);
        $this->assertSame([], $preview['rows'][1]['duplicate_matches']['existing']);
        $this->assertSame('create_preview', $preview['rows'][1]['intent']);
    }

    public function test_service_source_remains_preview_only_without_mutation_upload_parser_or_finance_behavior(): void
    {
        $service = $this->serviceSource();

        foreach ([
            'does not upload files',
            'parse xlsx files',
            'create members',
            'update members',
            'queue jobs',
            'store import state',
            'touch finance records',
            'dry_run',
            'committed',
            'existingMemberLookup',
            'batchDuplicateLookup',
        ] as $requiredText) {
            $this->assertStringContainsString($requiredText, $service);
        }

        foreach ([
            'DB::transaction',
            'Member::query()->create',
            '->update(',
            '->delete(',
            'Payment::',
            'Receipt::',
            'MemberLedgerEntry::',
            'Storage::',
            'Excel::',
            'dispatch(',
            'Bus::',
        ] as $blockedText) {
            $this->assertStringNotContainsString($blockedText, $service);
        }
    }

    public function test_3bv_confirms_no_import_routes_controllers_upload_parser_queue_or_frontend_files_exist(): void
    {
        foreach ([
            app_path('Http/Controllers/Api/V1/MemberImportController.php'),
            app_path('Http/Controllers/Api/V1/Members/MemberImportController.php'),
            app_path('Http/Requests/MemberImportRequest.php'),
            app_path('Http/Requests/Members/MemberImportRequest.php'),
            app_path('Http/Resources/MemberImportResource.php'),
            app_path('Imports/MemberImport.php'),
            app_path('Jobs/ProcessMemberImport.php'),
            app_path('Models/MemberImport.php'),
            app_path('Services/Members/MemberImportCommitService.php'),
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

    public function test_3bv_confirms_only_the_later_authorized_3by_preview_route_is_registered(): void
    {
        $memberImportRoutes = collect(Route::getRoutes())
            ->filter(fn ($route): bool => str_contains($route->uri(), 'import'))
            ->map(fn ($route): string => implode('|', $route->methods()).' '.$route->uri())
            ->values()
            ->all();

        $this->assertSame([
            'POST api/v1/clubs/{club}/members/import-preview',
            'POST api/v1/clubs/{club}/members/import-commit',
        ], $memberImportRoutes);
    }

    public function test_3bv_document_records_all_closure_claims(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'Pure backend preview/validation service only.',
            'Accepts spreadsheet-like row arrays.',
            'Normalizes supported member columns.',
            'Validates required member fields.',
            'Reports row-level validation errors.',
            'Detects duplicate rows inside the import batch.',
            'Detects club-scoped existing-member matches by member code, phone, or email.',
            'Existing-member matches are preview-only.',
            'No silent overwrite behavior.',
            'No database mutation.',
            'No member create/update behavior.',
            'No file upload.',
            'No `.xlsx` parser or package dependency.',
            'No routes/controllers/API behavior.',
            'No frontend upload UI.',
            'No dues, ledger, payment, receipt, payment-history, committee, fellowship, activity, event, or finance import.',
            'No queue/job/migration/package/deployment/SaaS/client-specific behavior.',
        ] as $closureClaim) {
            $this->assertStringContainsString($closureClaim, $document);
        }
    }

    public function test_3bv_records_3bw_as_future_scope_gate_candidate_only(): void
    {
        $document = $this->closureDocument();

        $this->assertStringContainsString('The expected next milestone is recorded only as a future decision candidate:', $document);
        $this->assertStringContainsString('Milestone 3BW - Member Data Import API Scope Gate', $document);
        $this->assertStringContainsString('3BW should be a scope-gate milestone only.', $document);
        $this->assertStringContainsString('3BW must not implement routes or controllers unless a later human approval changes the milestone type.', $document);
        $this->assertStringContainsString('No next milestone is started by 3BV.', $document);
    }

    private function closureDocument(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }

    private function service(): MemberDataImportPreviewService
    {
        return new MemberDataImportPreviewService();
    }

    private function serviceSource(): string
    {
        $path = app_path('Services/Members/MemberDataImportPreviewService.php');

        $this->assertFileExists($path);

        return file_get_contents($path);
    }

    private function createClub(string $code = 'LCB-3BV'): Club
    {
        return Club::query()->create([
            'name' => 'Lions Club Beenachi',
            'code' => $code,
            'district' => '318E',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function createMember(
        Club $club,
        string $memberCode,
        string $fullName,
        ?string $phone = null,
        ?string $email = null
    ): Member {
        return Member::query()->create([
            'club_id' => $club->id,
            'member_code' => $memberCode,
            'full_name' => $fullName,
            'phone' => $phone,
            'email' => $email,
            'status' => 'active',
            'joined_at' => '2026-06-01',
            'blood_group' => 'O+',
            'blood_donor_consent' => true,
        ]);
    }
}
