<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Member;
use App\Services\Members\MemberDataImportPreviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Milestone3buMemberDataImportExcelOnboardingNarrowImplementationTest extends TestCase
{
    use RefreshDatabase;

    private string $docPath = __DIR__ . '/../../docs/milestone_3bu_member_data_import_excel_onboarding_narrow_implementation.md';

    public function test_3bu_document_records_narrow_backend_preview_foundation_boundary(): void
    {
        $document = $this->implementationDocument();

        $this->assertStringContainsString('Milestone 3BU - Member Data Import / Excel Onboarding Narrow Implementation', $document);
        $this->assertStringContainsString('narrow backend implementation milestone', $document);
        $this->assertStringContainsString('It implements a pure member data import preview and validation foundation service.', $document);
        $this->assertStringContainsString('It does not implement Excel file upload.', $document);
        $this->assertStringContainsString('It does not implement xlsx parsing.', $document);
        $this->assertStringContainsString('It does not implement API routes or controllers.', $document);
        $this->assertStringContainsString('It does not implement frontend upload UI.', $document);
        $this->assertStringContainsString('It does not mutate database records.', $document);
    }

    public function test_3bu_allows_only_service_doc_and_focused_test(): void
    {
        $document = $this->implementationDocument();

        foreach ([
            'backend/app/Services/Members/MemberDataImportPreviewService.php',
            'backend/docs/milestone_3bu_member_data_import_excel_onboarding_narrow_implementation.md',
            'backend/tests/Feature/Milestone3buMemberDataImportExcelOnboardingNarrowImplementationTest.php',
        ] as $allowedFile) {
            $this->assertStringContainsString($allowedFile, $document);
        }

        foreach ([
            'app/page.tsx',
            'frontend runtime files',
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

    public function test_preview_service_normalizes_supported_column_names_and_returns_dry_run_result(): void
    {
        $club = $this->createClub();
        $preview = $this->service()->preview($club->id, [[
            'Member Code' => ' mem-001 ',
            'Member Name' => ' Lion   Import Member ',
            'Mobile' => '+91 98765 43210',
            'Email Address' => 'IMPORT@LCBEENACHI.TEST',
            'Member Status' => 'Active',
            'Joined Date' => '2026-06-12',
            'Blood Group' => ' o+ ',
            'Donor Consent' => 'yes',
            'Ignored Finance Opening Balance' => '999999',
        ]]);

        $this->assertTrue($preview['dry_run']);
        $this->assertFalse($preview['committed']);
        $this->assertSame([
            'total_rows' => 1,
            'accepted_rows' => 1,
            'rejected_rows' => 0,
            'duplicate_rows' => 0,
            'possible_existing_match_rows' => 0,
            'created_rows' => 1,
            'updated_rows' => 0,
            'skipped_rows' => 0,
            'failed_rows' => 0,
        ], $preview['summary']);
        $this->assertSame('create_preview', $preview['rows'][0]['intent']);
        $this->assertSame('MEM-001', $preview['rows'][0]['normalized']['member_code']);
        $this->assertSame('Lion Import Member', $preview['rows'][0]['normalized']['full_name']);
        $this->assertSame('+919876543210', $preview['rows'][0]['normalized']['phone']);
        $this->assertSame('import@lcbeenachi.test', $preview['rows'][0]['normalized']['email']);
        $this->assertSame('active', $preview['rows'][0]['normalized']['status']);
        $this->assertSame('2026-06-12', $preview['rows'][0]['normalized']['joined_at']);
        $this->assertSame('O+', $preview['rows'][0]['normalized']['blood_group']);
        $this->assertTrue($preview['rows'][0]['normalized']['blood_donor_consent']);
        $this->assertArrayNotHasKey('Ignored Finance Opening Balance', $preview['rows'][0]['normalized']);
    }

    public function test_preview_service_reports_row_level_validation_errors(): void
    {
        $club = $this->createClub();
        $preview = $this->service()->preview($club->id, [[
            'Member Code' => '',
            'Full Name' => '',
            'Email' => 'not-an-email',
            'Status' => 'archived',
            'Joined Date' => 'not-a-date',
            'Blood Donor Consent' => 'maybe',
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

    public function test_preview_service_detects_duplicate_rows_inside_import_batch(): void
    {
        $club = $this->createClub();
        $preview = $this->service()->preview($club->id, [
            [
                'Member Code' => 'MEM-DUP',
                'Full Name' => 'First Duplicate',
                'Phone' => '9999999999',
                'Email' => 'first@example.test',
            ],
            [
                'Code' => 'mem-dup',
                'Name' => 'Second Duplicate',
                'Phone Number' => '99999 99999',
                'Email' => 'second@example.test',
            ],
        ]);

        $this->assertSame(2, $preview['summary']['duplicate_rows']);
        $this->assertSame(2, $preview['summary']['rejected_rows']);
        $this->assertSame([2], $preview['rows'][0]['duplicate_matches']['batch'][0]['matched_rows']);
        $this->assertSame([1], $preview['rows'][1]['duplicate_matches']['batch'][0]['matched_rows']);
        $this->assertContains('Duplicate member_code inside import batch.', $preview['rows'][0]['errors']);
        $this->assertContains('Duplicate phone inside import batch.', $preview['rows'][1]['errors']);
    }

    public function test_preview_service_detects_existing_member_matches_inside_same_club_only(): void
    {
        $club = $this->createClub();
        $otherClub = $this->createClub('LCB-3BU-OTHER');
        $existing = $this->createMember($club, 'MEM-EXISTING', 'Existing Member', '8888888888', 'existing@example.test');
        $this->createMember($otherClub, 'MEM-CROSS', 'Cross Club Member', '7777777777', 'cross@example.test');

        $preview = $this->service()->preview($club->id, [[
            'Member Code' => 'MEM-EXISTING',
            'Full Name' => 'Incoming Existing Member',
            'Phone' => '88888 88888',
            'Email' => 'existing@example.test',
        ], [
            'Member Code' => 'MEM-CROSS',
            'Full Name' => 'Incoming Cross Club Member',
            'Phone' => '7777777777',
            'Email' => 'cross@example.test',
        ]]);

        $this->assertSame(1, $preview['summary']['possible_existing_match_rows']);
        $this->assertTrue($preview['rows'][0]['valid']);
        $this->assertSame('update_preview', $preview['rows'][0]['intent']);
        $this->assertSame($existing->id, $preview['rows'][0]['duplicate_matches']['existing'][0]['member_id']);
        $this->assertSame('MEM-EXISTING', $preview['rows'][0]['duplicate_matches']['existing'][0]['member_code']);
        $this->assertSame([], $preview['rows'][1]['duplicate_matches']['existing']);
        $this->assertSame('create_preview', $preview['rows'][1]['intent']);
    }

    public function test_preview_service_does_not_mutate_members_or_finance_records(): void
    {
        $club = $this->createClub();
        $this->createMember($club, 'MEM-UNCHANGED', 'Unchanged Member', '9111111111', 'unchanged@example.test');

        $beforeMembers = Member::query()->count();

        $preview = $this->service()->preview($club->id, [[
            'Member Code' => 'MEM-NEW',
            'Full Name' => 'New Preview Only',
            'Phone' => '9222222222',
            'Email' => 'new@example.test',
        ], [
            'Member Code' => 'MEM-UNCHANGED',
            'Full Name' => 'Changed Name Preview Only',
            'Phone' => '9111111111',
            'Email' => 'unchanged@example.test',
        ]]);

        $this->assertFalse($preview['committed']);
        $this->assertSame($beforeMembers, Member::query()->count());
        $this->assertDatabaseHas('members', [
            'member_code' => 'MEM-UNCHANGED',
            'full_name' => 'Unchanged Member',
            'phone' => '9111111111',
            'email' => 'unchanged@example.test',
        ]);
    }

    public function test_3bu_does_not_add_routes_controllers_upload_parser_queue_or_frontend_files(): void
    {
        foreach ([
            base_path('routes/api.php.3bu'),
            app_path('Http/Controllers/Api/V1/MemberImportController.php'),
            app_path('Http/Requests/MemberImportRequest.php'),
            app_path('Http/Resources/MemberImportResource.php'),
            app_path('Imports/MemberImport.php'),
            app_path('Jobs/ProcessMemberImport.php'),
            app_path('Models/MemberImport.php'),
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

    public function test_3bu_service_source_blocks_upload_parser_persistence_and_finance_behavior(): void
    {
        $service = file_get_contents(app_path('Services/Members/MemberDataImportPreviewService.php'));

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
        ] as $requiredText) {
            $this->assertStringContainsString($requiredText, $service);
        }

        foreach ([
            'DB::transaction',
            'Member::query()->create',
            '->update(',
            'Payment::',
            'Receipt::',
            'MemberLedgerEntry::',
            'Storage::',
            'Excel::',
            'dispatch(',
        ] as $blockedText) {
            $this->assertStringNotContainsString($blockedText, $service);
        }
    }

    public function test_3bu_selects_3bv_as_next_validation_and_closure_milestone(): void
    {
        $document = $this->implementationDocument();

        $this->assertStringContainsString('Milestone 3BV - Member Data Import / Excel Onboarding Validation and Closure', $document);
        $this->assertStringContainsString('3BV should validate and close only what 3BU implemented.', $document);
    }

    private function implementationDocument(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }

    private function service(): MemberDataImportPreviewService
    {
        return new MemberDataImportPreviewService();
    }

    private function createClub(string $code = 'LCB-3BU'): Club
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
