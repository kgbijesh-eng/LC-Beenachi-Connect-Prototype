<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Member;
use App\Models\User;
use App\Services\Members\MemberDataImportCommitService;
use App\Services\Members\MemberDataImportPreviewService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Milestone3fdMemberProfileSchemaAndImportContractValidationAndClosureTest extends TestCase
{
    use RefreshDatabase;

    private const BASE_COMMIT = '56cefa321786e7d1f934a724b96a531f4d2fdfbc';

    private const EXTENDED_COLUMNS = [
        'salutation', 'first_name', 'middle_name', 'last_name', 'birth_date', 'occupation',
        'spouse_name', 'wedding_anniversary', 'gender', 'membership_type',
        'membership_category', 'membership_program', 'membership_sponsor_name', 'home_phone',
        'alternate_phone', 'address_line_1', 'address_line_2', 'city_locality', 'district',
        'state', 'postal_code', 'country',
    ];

    private string $docPath = __DIR__.'/../../docs/milestone_3fd_member_profile_schema_and_import_contract_validation_and_closure.md';

    public function test_3fd_document_exists_and_records_validation_only_closure(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'Milestone 3FD - Member Profile Schema and Import Contract Validation and Closure',
            'docs/tests-only validation and closure milestone',
            'It does not change runtime behavior',
            'It does not begin Milestone 3FE',
            'No closure-blocking defect was found.',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fd_records_base_commit_and_3fb_to_3fc_traceability(): void
    {
        $document = $this->closureDocument();

        foreach ([
            self::BASE_COMMIT,
            '3FB-to-3FC authorization traceability',
            'one additive member-profile migration',
            'existing member create/update validation',
            'existing preview and commit services',
            'existing preview and commit resources',
            'Every runtime file belongs to the 3FB-authorized surface.',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fc_commit_surface_matches_the_authorized_runtime_and_test_files(): void
    {
        $expected = [
            'backend/app/Http/Controllers/Api/V1/Member/MemberController.php',
            'backend/app/Http/Resources/Members/MemberDataImportCommitResource.php',
            'backend/app/Http/Resources/Members/MemberDataImportPreviewResource.php',
            'backend/app/Models/Member.php',
            'backend/app/Services/Members/MemberDataImportCommitService.php',
            'backend/app/Services/Members/MemberDataImportPreviewService.php',
            'backend/database/migrations/2026_06_18_000001_add_extended_profile_fields_to_members_table.php',
            'backend/docs/milestone_3fc_member_profile_schema_and_import_contract_implementation.md',
            'backend/tests/Feature/Milestone3buMemberDataImportExcelOnboardingNarrowImplementationTest.php',
            'backend/tests/Feature/Milestone3bvMemberDataImportExcelOnboardingValidationAndClosureTest.php',
            'backend/tests/Feature/Milestone3byMemberDataImportApiNarrowImplementationTest.php',
            'backend/tests/Feature/Milestone3bzMemberDataImportApiValidationAndClosureTest.php',
            'backend/tests/Feature/Milestone3ciMemberDataImportCommitBackendNarrowImplementationTest.php',
            'backend/tests/Feature/Milestone3fcMemberProfileSchemaAndImportContractImplementationTest.php',
        ];

        $repoRoot = dirname(__DIR__, 3);
        $output = shell_exec(
            'git -C '.escapeshellarg($repoRoot).' diff --name-only '
            .'67efed4a2d93eb82d3031e1787879d2bbde13513..'.self::BASE_COMMIT
        ) ?? '';
        $actual = collect(preg_split('/\R/', trim($output)))
            ->filter()
            ->map(fn (string $path): string => str_replace('\\', '/', trim($path)))
            ->sort()
            ->values()
            ->all();

        sort($expected);
        $this->assertSame($expected, $actual);

        foreach (['app/', 'backend/routes/', 'backend/database/seeders/', 'package.json', 'backend/composer.json'] as $blocked) {
            $this->assertFalse(collect($actual)->contains(fn (string $path): bool => str_starts_with($path, $blocked)));
        }
    }

    public function test_3fd_current_changes_are_limited_to_two_validation_files(): void
    {
        $repoRoot = dirname(__DIR__, 3);
        $branch = trim(shell_exec('git -C '.escapeshellarg($repoRoot).' rev-parse --abbrev-ref HEAD') ?? '');
        if ($branch !== 'milestone-3fd-member-profile-schema-import-contract-validation-and-closure') {
            $this->assertTrue(true);
            return;
        }

        $allowed = [
            'backend/docs/milestone_3fd_member_profile_schema_and_import_contract_validation_and_closure.md',
            'backend/tests/Feature/Milestone3fdMemberProfileSchemaAndImportContractValidationAndClosureTest.php',
        ];
        $status = shell_exec('git -C '.escapeshellarg($repoRoot).' status --short --untracked-files=all') ?? '';
        $changed = collect(preg_split('/\R/', trim($status)))
            ->filter()
            ->map(function (string $line): string {
                $path = preg_replace('/^[ MARCUD?!]{1,2}\s+/', '', $line) ?? $line;

                return str_replace('\\', '/', trim($path));
            })
            ->values()
            ->all();

        $this->assertSame([], array_values(array_diff($changed, $allowed)));
    }

    public function test_schema_has_exact_nullable_extended_contract_and_no_persisted_age(): void
    {
        $this->assertCount(22, self::EXTENDED_COLUMNS);
        $columns = collect(DB::select("PRAGMA table_info('members')"))->keyBy('name');

        foreach (self::EXTENDED_COLUMNS as $column) {
            $this->assertTrue(Schema::hasColumn('members', $column));
            $this->assertSame(0, (int) $columns[$column]->notnull, $column.' must remain nullable.');
        }

        $this->assertSame('date', Schema::getColumnType('members', 'birth_date'));
        $this->assertSame('date', Schema::getColumnType('members', 'wedding_anniversary'));
        $this->assertFalse(Schema::hasColumn('members', 'current_age'));

        $migration = file_get_contents(database_path('migrations/2026_06_18_000001_add_extended_profile_fields_to_members_table.php'));
        $this->assertStringContainsString('$table->dropColumn([', $migration);
        $this->assertStringNotContainsString("Schema::dropIfExists('members')", $migration);
    }

    public function test_club_scoped_member_code_uniqueness_is_effective_without_duplicate_3fc_index(): void
    {
        $clubOne = Club::query()->create(['name' => 'Synthetic Validation One', 'code' => 'SYN-FD1']);
        $clubTwo = Club::query()->create(['name' => 'Synthetic Validation Two', 'code' => 'SYN-FD2']);

        Member::query()->create(['club_id' => $clubOne->id, 'member_code' => '00052', 'full_name' => 'Synthetic Alpha']);
        Member::query()->create(['club_id' => $clubTwo->id, 'member_code' => '00052', 'full_name' => 'Synthetic Beta']);

        $this->assertSame('00052', Member::query()->where('club_id', $clubOne->id)->value('member_code'));

        $this->expectException(QueryException::class);
        Member::query()->create(['club_id' => $clubOne->id, 'member_code' => '00052', 'full_name' => 'Synthetic Duplicate']);
    }

    public function test_member_model_assignment_casts_relationships_and_no_inference_are_validated(): void
    {
        $member = new Member;

        foreach (self::EXTENDED_COLUMNS as $field) {
            $this->assertContains($field, $member->getFillable());
        }

        $casts = $member->getCasts();
        $this->assertSame('date', $casts['birth_date']);
        $this->assertSame('date', $casts['wedding_anniversary']);
        $this->assertSame('date', $casts['joined_at']);
        $this->assertSame('boolean', $casts['blood_donor_consent']);
        $this->assertArrayNotHasKey('current_age', $member->getFillable());
        $this->assertInstanceOf(BelongsTo::class, $member->club());
        $this->assertInstanceOf(BelongsTo::class, $member->user());
        $this->assertInstanceOf(HasMany::class, $member->ledgerEntries());
    }

    public function test_create_update_validation_and_privacy_rules_are_present_in_runtime(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Api/V1/Member/MemberController.php'));

        foreach ([
            "'club_id' => ['prohibited']",
            "'current_age' => ['prohibited']",
            "'birth_date'",
            "'wedding_anniversary'",
            "Rule::in(['male', 'female', 'other'])",
            "Rule::unique('members', 'member_code')",
            "->where('club_id', \$club->id)",
            "->ignore(\$member->id)",
            'canViewSensitiveProfile',
            'SENSITIVE_PROFILE_ROLE_SLUGS',
        ] as $expected) {
            $this->assertStringContainsString($expected, $controller);
        }

        $this->assertStringNotContainsString('return $member->toArray()', $controller);
    }

    public function test_normalization_is_field_aware_and_preview_is_read_only_with_structured_errors(): void
    {
        $club = Club::query()->create(['name' => 'Synthetic Preview Validation', 'code' => 'SYN-FDP']);
        $before = Member::query()->count();
        $preview = app(MemberDataImportPreviewService::class)->preview($club->id, [
            [
                'Lions Member ID' => ' 00052 ',
                'First Name' => ' Synthetic ',
                'Last Name' => ' Member ',
                'Occupation' => '52',
                'Address Line 1' => ' 12_x000D_ Test   Road ',
                'Postal Code' => '005201',
                'Mobile' => '+91 90000 00000',
                'Personal Email' => ' SYNTHETIC @EXAMPLE.TEST ',
                'Gender' => 'F',
                'Current Age' => '52',
            ],
            [
                'Member Code' => 'SYN-BAD-DATE',
                'Full Name' => 'Synthetic Invalid Date',
                'Birth Date' => 'not-a-date',
            ],
        ]);

        $normalized = $preview['rows'][0]['normalized'];
        $this->assertSame('00052', $normalized['member_code']);
        $this->assertSame('Synthetic Member', $normalized['full_name']);
        $this->assertNull($normalized['occupation']);
        $this->assertSame('12 Test Road', $normalized['address_line_1']);
        $this->assertSame('005201', $normalized['postal_code']);
        $this->assertSame('+919000000000', $normalized['phone']);
        $this->assertSame('synthetic@example.test', $normalized['email']);
        $this->assertSame('female', $normalized['gender']);
        $this->assertArrayNotHasKey('current_age', $normalized);
        $this->assertSame('reject', $preview['rows'][1]['intent']);
        $this->assertSame(2, $preview['rows'][1]['error_details'][0]['row_number']);
        $this->assertSame('SYN-BAD-DATE', $preview['rows'][1]['error_details'][0]['member_code']);
        $this->assertSame('birth_date', $preview['rows'][1]['error_details'][0]['field']);
        $this->assertNotSame('', $preview['rows'][1]['error_details'][0]['reason']);
        $this->assertSame($before, Member::query()->count());
    }

    public function test_preview_classification_counts_and_duplicate_source_failure_are_validated(): void
    {
        $club = Club::query()->create(['name' => 'Synthetic Classification Validation', 'code' => 'SYN-FDC']);
        Member::query()->create(['club_id' => $club->id, 'member_code' => 'SYN-UPD', 'full_name' => 'Synthetic Old']);
        Member::query()->create(['club_id' => $club->id, 'member_code' => 'SYN-SAME', 'full_name' => 'Synthetic Same']);

        $preview = app(MemberDataImportPreviewService::class)->preview($club->id, [
            ['Member Code' => 'SYN-NEW', 'Full Name' => 'Synthetic New'],
            ['Member Code' => 'SYN-UPD', 'Full Name' => 'Synthetic Updated'],
            ['Member Code' => 'SYN-SAME', 'Full Name' => 'Synthetic Same'],
            ['Member Code' => 'SYN-INVALID', 'Full Name' => 'Synthetic Invalid', 'Birth Date' => 'invalid'],
        ]);

        $this->assertSame(['create_preview', 'update_preview', 'unchanged_preview', 'reject'], array_column($preview['rows'], 'intent'));
        $this->assertSame(1, $preview['summary']['created_rows']);
        $this->assertSame(1, $preview['summary']['updated_rows']);
        $this->assertSame(1, $preview['summary']['skipped_rows']);
        $this->assertSame(1, $preview['summary']['failed_rows']);

        $duplicates = app(MemberDataImportPreviewService::class)->preview($club->id, [
            ['Member Code' => 'SYN-DUP', 'Full Name' => 'Synthetic Duplicate One'],
            ['Member Code' => 'syn-dup', 'Full Name' => 'Synthetic Duplicate Two'],
        ]);
        $this->assertSame(2, $duplicates['summary']['failed_rows']);
        $this->assertSame('member_code', $duplicates['rows'][0]['error_details'][0]['field']);
    }

    public function test_commit_reuses_preview_and_preserves_blank_placeholder_and_manual_values(): void
    {
        $club = Club::query()->create(['name' => 'Synthetic Commit Validation', 'code' => 'SYN-FDM']);
        $existing = Member::query()->create([
            'club_id' => $club->id,
            'member_code' => 'SYN-EXIST',
            'full_name' => 'Synthetic Existing',
            'occupation' => 'Protected Occupation',
            'spouse_name' => 'Protected Spouse',
        ]);
        $rows = [
            ['Member Code' => 'SYN-NEW', 'Full Name' => 'Synthetic New', 'Occupation' => ''],
            [
                'Member Code' => 'SYN-EXIST',
                'Full Name' => 'Synthetic Updated',
                'Occupation' => '52',
                'Spouse Name' => '',
                'Membership Program' => 'Synthetic Program',
            ],
            ['Member Code' => 'SYN-BAD', 'Full Name' => 'Synthetic Bad', 'Birth Date' => 'invalid'],
        ];

        $preview = app(MemberDataImportPreviewService::class)->preview($club->id, $rows);
        $commit = app(MemberDataImportCommitService::class)->commit($club->id, $rows);

        $this->assertSame($preview['rows'][0]['normalized'], $commit['rows'][0]['normalized']);
        $this->assertSame($preview['rows'][1]['normalized'], $commit['rows'][1]['normalized']);
        $this->assertSame(1, $commit['summary']['created_rows']);
        $this->assertSame(1, $commit['summary']['updated_rows']);
        $this->assertSame(0, $commit['summary']['skipped_rows']);
        $this->assertSame(1, $commit['summary']['failed_rows']);
        $this->assertSame(['created', 'updated', 'failed'], array_column($commit['rows'], 'status'));

        $existing->refresh();
        $this->assertSame('Synthetic Updated', $existing->full_name);
        $this->assertSame('Protected Occupation', $existing->occupation);
        $this->assertSame('Protected Spouse', $existing->spouse_name);
        $this->assertSame('Synthetic Program', $existing->membership_program);
        $this->assertNull(Member::query()->where('member_code', 'SYN-NEW')->firstOrFail()->occupation);
        $this->assertDatabaseMissing('members', ['member_code' => 'SYN-BAD']);

        $commitSource = file_get_contents(app_path('Services/Members/MemberDataImportCommitService.php'));
        $this->assertStringContainsString('$this->previewService->preview($clubId, $rows)', $commitSource);
        $this->assertStringContainsString('DB::transaction', $commitSource);
    }

    public function test_actual_member_response_privacy_preserves_own_and_pst_access(): void
    {
        $this->seed(DatabaseSeeder::class);
        $club = Club::query()->where('code', 'LCB')->firstOrFail();
        $normalUser = User::query()->where('email', 'local.member@lcbeenachi.test')->firstOrFail();
        $secretary = User::query()->where('email', 'secretary@lcbeenachi.test')->firstOrFail();
        $own = Member::query()->create([
            'club_id' => $club->id,
            'user_id' => $normalUser->id,
            'member_code' => 'SYN-OWN-FD',
            'full_name' => 'Synthetic Own Profile',
            'birth_date' => '1988-03-04',
            'email' => 'synthetic-own@example.test',
            'address_line_1' => 'Synthetic Own Address',
        ]);
        $other = Member::query()->create([
            'club_id' => $club->id,
            'member_code' => 'SYN-OTHER-FD',
            'full_name' => 'Synthetic Other Profile',
            'birth_date' => '1989-04-05',
            'email' => 'synthetic-other@example.test',
            'address_line_1' => 'Synthetic Other Address',
        ]);

        Sanctum::actingAs($normalUser);
        config(['instance.organisation_code' => $club->code]);
        $this->getJson("/api/v1/clubs/{$club->id}/members/{$own->id}")
            ->assertOk()
            ->assertJsonPath('member.birth_date', '1988-03-04')
            ->assertJsonPath('member.email', 'synthetic-own@example.test');
        $restricted = $this->getJson("/api/v1/clubs/{$club->id}/members/{$other->id}")
            ->assertOk()
            ->json('member');

        foreach (['birth_date', 'phone', 'email', 'spouse_name', 'wedding_anniversary', 'membership_sponsor_name', 'address_line_1'] as $field) {
            $this->assertArrayNotHasKey($field, $restricted);
        }

        Sanctum::actingAs($secretary);
        config(['instance.organisation_code' => $club->code]);
        $this->getJson("/api/v1/clubs/{$club->id}/members/{$other->id}")
            ->assertOk()
            ->assertJsonPath('member.birth_date', '1989-04-05')
            ->assertJsonPath('member.email', 'synthetic-other@example.test');
    }

    public function test_role_test_accounts_remain_usable_separate_and_unmodified_by_preview(): void
    {
        $this->seed(DatabaseSeeder::class);
        $club = Club::query()->where('code', 'LCB')->firstOrFail();
        $emails = [
            'president@lcbeenachi.test',
            'secretary@lcbeenachi.test',
            'treasurer@lcbeenachi.test',
            'local.member@lcbeenachi.test',
        ];
        $before = User::query()->whereIn('email', $emails)->pluck('id', 'email');

        app(MemberDataImportPreviewService::class)->preview($club->id, [[
            'Member Code' => 'SYN-ROLE-FD',
            'Full Name' => 'Synthetic Role Separation',
        ]]);

        $this->assertCount(4, $before);
        foreach ($before as $email => $id) {
            $this->assertSame($id, User::query()->where('email', $email)->value('id'));
        }
        $this->assertDatabaseMissing('members', ['member_code' => 'SYN-ROLE-FD']);
    }

    public function test_resources_are_explicit_and_do_not_blindly_serialize_member_models(): void
    {
        foreach ([
            app_path('Http/Resources/Members/MemberDataImportPreviewResource.php'),
            app_path('Http/Resources/Members/MemberDataImportCommitResource.php'),
        ] as $resourcePath) {
            $resource = file_get_contents($resourcePath);
            $this->assertStringContainsString("'row_number'", $resource);
            $this->assertStringContainsString("'errors'", $resource);
            $this->assertStringContainsString("'error_details'", $resource);
            $this->assertStringNotContainsString('parent::toArray', $resource);
            $this->assertStringNotContainsString('->toArray()', $resource);
        }
    }

    public function test_real_data_deferrals_and_successful_closure_verdict_are_explicit(): void
    {
        $document = $this->closureDocument();

        foreach ([
            'No real member workbook was imported.',
            'No real member private data was added to Git.',
            'Milestone 3FE: real 47-member import execution.',
            'Milestone 3FF: imported-member frontend integration.',
            'Milestone 3FG: fellowship month lifecycle and dynamic continuation.',
            'CLOSED — Milestone 3FC member profile schema and import-contract implementation is validated and ready for Milestone 3FE current-member import execution.',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fd_contains_no_runtime_implementation_authorization(): void
    {
        $document = $this->closureDocument();

        foreach ([
            '3FD contains validation evidence only.',
            'It does not authorize or implement runtime changes',
            '3FD does not begin or implement any deferred milestone.',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }

        foreach (['3FD authorizes runtime', '3FD implements migration', '3FD imports the real workbook'] as $blocked) {
            $this->assertStringNotContainsString($blocked, $document);
        }
    }

    private function closureDocument(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }
}
