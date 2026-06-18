<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubMembership;
use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use App\Services\Members\MemberDataImportCommitService;
use App\Services\Members\MemberDataImportPreviewService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Milestone3fcMemberProfileSchemaAndImportContractImplementationTest extends TestCase
{
    use RefreshDatabase;

    private const EXTENDED_COLUMNS = [
        'salutation', 'first_name', 'middle_name', 'last_name', 'birth_date', 'occupation',
        'spouse_name', 'wedding_anniversary', 'gender', 'membership_type',
        'membership_category', 'membership_program', 'membership_sponsor_name', 'home_phone',
        'alternate_phone', 'address_line_1', 'address_line_2', 'city_locality', 'district',
        'state', 'postal_code', 'country',
    ];

    private string $docPath = __DIR__.'/../../docs/milestone_3fc_member_profile_schema_and_import_contract_implementation.md';

    public function test_schema_adds_only_nullable_extended_profile_columns_without_current_age(): void
    {
        foreach (self::EXTENDED_COLUMNS as $column) {
            $this->assertTrue(Schema::hasColumn('members', $column), $column.' must exist.');
        }

        $this->assertFalse(Schema::hasColumn('members', 'current_age'));

        $columns = collect(DB::select("PRAGMA table_info('members')"))->keyBy('name');
        foreach (self::EXTENDED_COLUMNS as $column) {
            $this->assertSame(0, (int) $columns[$column]->notnull, $column.' must remain nullable.');
        }

        $this->assertSame('date', Schema::getColumnType('members', 'birth_date'));
        $this->assertSame('date', Schema::getColumnType('members', 'wedding_anniversary'));
    }

    public function test_club_scoped_member_code_uniqueness_and_leading_zero_text_are_preserved(): void
    {
        $firstClub = Club::query()->create(['name' => 'Synthetic Club One', 'code' => 'SYN-ONE']);
        $secondClub = Club::query()->create(['name' => 'Synthetic Club Two', 'code' => 'SYN-TWO']);

        Member::query()->create([
            'club_id' => $firstClub->id,
            'member_code' => '00052',
            'full_name' => 'Synthetic Alpha',
        ]);
        Member::query()->create([
            'club_id' => $secondClub->id,
            'member_code' => '00052',
            'full_name' => 'Synthetic Beta',
        ]);

        $this->assertSame('00052', Member::query()->where('club_id', $firstClub->id)->value('member_code'));

        $this->expectException(QueryException::class);
        Member::query()->create([
            'club_id' => $firstClub->id,
            'member_code' => '00052',
            'full_name' => 'Synthetic Duplicate',
        ]);
    }

    public function test_migration_rollback_is_additive_and_removes_only_3fc_columns(): void
    {
        $migration = file_get_contents(database_path(
            'migrations/2026_06_18_000001_add_extended_profile_fields_to_members_table.php'
        ));

        $this->assertStringContainsString("Schema::table('members'", $migration);
        $this->assertStringContainsString('$table->dropColumn([', $migration);
        $this->assertStringNotContainsString("Schema::dropIfExists('members')", $migration);

        foreach (self::EXTENDED_COLUMNS as $column) {
            $this->assertStringContainsString("'{$column}'", $migration);
        }
    }

    public function test_model_assigns_extended_fields_and_casts_profile_dates_without_inference(): void
    {
        $club = Club::query()->create(['name' => 'Synthetic Model Club', 'code' => 'SYN-MOD']);
        $member = Member::query()->create([
            'club_id' => $club->id,
            'member_code' => 'SYN-MODEL-1',
            'full_name' => 'Synthetic Model Member',
            'first_name' => 'Synthetic',
            'birth_date' => '1990-04-03',
            'wedding_anniversary' => '2015-05-06',
            'postal_code' => '005201',
        ]);

        $this->assertSame('Synthetic Model Member', $member->full_name);
        $this->assertSame('1990-04-03', $member->birth_date->toDateString());
        $this->assertSame('2015-05-06', $member->wedding_anniversary->toDateString());
        $this->assertSame('005201', $member->postal_code);
        $this->assertNull($member->gender);
        $this->assertNull($member->occupation);
        $this->assertArrayNotHasKey('current_age', $member->getAttributes());
    }

    public function test_create_and_update_accept_extended_optional_fields_and_normalize_gender(): void
    {
        [$club, $user] = $this->seededOfficer('president-admin');
        $token = $user->createToken('3fc-test')->plainTextToken;

        $create = $this->withToken($token)->postJson("/api/v1/clubs/{$club->id}/members", [
            'member_code' => ' syn-api-001 ',
            'full_name' => ' Synthetic   API Member ',
            'first_name' => ' Synthetic ',
            'last_name' => ' Member ',
            'birth_date' => '1992-02-03',
            'gender' => 'F',
            'membership_type' => 'Family Member',
            'postal_code' => '005201',
        ]);

        $create->assertCreated()
            ->assertJsonPath('member.member_code', 'SYN-API-001')
            ->assertJsonPath('member.full_name', 'Synthetic API Member')
            ->assertJsonPath('member.gender', 'female')
            ->assertJsonPath('member.birth_date', '1992-02-03')
            ->assertJsonPath('member.postal_code', '005201');

        $memberId = $create->json('member.id');
        $this->withToken($token)
            ->patchJson("/api/v1/clubs/{$club->id}/members/{$memberId}", [
                'occupation' => 'Synthetic Analyst',
                'wedding_anniversary' => '2019-06-07',
            ])
            ->assertOk()
            ->assertJsonPath('member.occupation', 'Synthetic Analyst')
            ->assertJsonPath('member.wedding_anniversary', '2019-06-07');
    }

    public function test_create_and_update_reject_invalid_extended_values_and_client_scoping(): void
    {
        [$club, $user] = $this->seededOfficer('president-admin');
        $token = $user->createToken('3fc-invalid')->plainTextToken;

        $this->withToken($token)->postJson("/api/v1/clubs/{$club->id}/members", [
            'club_id' => $club->id,
            'member_code' => 'SYN-INVALID',
            'full_name' => 'Synthetic Invalid Member',
            'birth_date' => 'not-a-date',
            'wedding_anniversary' => 'also-not-a-date',
            'email' => 'invalid-email',
            'gender' => 'unsupported',
            'current_age' => 44,
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'club_id', 'birth_date', 'wedding_anniversary', 'email', 'gender', 'current_age',
        ]);
    }

    public function test_import_normalization_is_field_aware_and_ignores_current_age(): void
    {
        $club = Club::query()->create(['name' => 'Synthetic Preview Club', 'code' => 'SYN-PRV']);
        $preview = app(MemberDataImportPreviewService::class)->preview($club->id, [[
            'Lions Member ID' => ' 00052 ',
            'First Name' => ' Synthetic ',
            'Middle Name' => ' Middle ',
            'Last Name' => ' Member ',
            'Occupation' => '52',
            'Address Line 1' => " 12_x000D_ Test   Road ",
            'Postal Code' => '005201',
            'Mobile' => '+91 90000 00000',
            'Personal Email' => ' SYNTHETIC @EXAMPLE.TEST ',
            'Gender' => 'M',
            'Current Age' => '52',
        ]]);

        $normalized = $preview['rows'][0]['normalized'];
        $this->assertSame('00052', $normalized['member_code']);
        $this->assertSame('Synthetic Middle Member', $normalized['full_name']);
        $this->assertNull($normalized['occupation']);
        $this->assertSame('12 Test Road', $normalized['address_line_1']);
        $this->assertSame('005201', $normalized['postal_code']);
        $this->assertSame('+919000000000', $normalized['phone']);
        $this->assertSame('synthetic@example.test', $normalized['email']);
        $this->assertSame('male', $normalized['gender']);
        $this->assertArrayNotHasKey('current_age', $normalized);
    }

    public function test_preview_classifies_create_update_unchanged_and_failed_without_writes(): void
    {
        $club = Club::query()->create(['name' => 'Synthetic Classification Club', 'code' => 'SYN-CLS']);
        Member::query()->create([
            'club_id' => $club->id,
            'member_code' => 'SYN-UPD',
            'full_name' => 'Synthetic Old Name',
            'occupation' => 'Existing Occupation',
        ]);
        Member::query()->create([
            'club_id' => $club->id,
            'member_code' => 'SYN-SAME',
            'full_name' => 'Synthetic Same Name',
        ]);
        $before = Member::query()->count();

        $preview = app(MemberDataImportPreviewService::class)->preview($club->id, [
            ['Member Code' => 'SYN-NEW', 'Full Name' => 'Synthetic New'],
            ['Member Code' => 'SYN-UPD', 'Full Name' => 'Synthetic Updated', 'Occupation' => ''],
            ['Member Code' => 'SYN-SAME', 'Full Name' => 'Synthetic Same Name'],
            ['Member Code' => 'SYN-BAD', 'Full Name' => 'Synthetic Invalid', 'Birth Date' => 'invalid'],
        ]);

        $this->assertSame(['create_preview', 'update_preview', 'unchanged_preview', 'reject'], array_column($preview['rows'], 'intent'));
        $this->assertSame(1, $preview['summary']['created_rows']);
        $this->assertSame(1, $preview['summary']['updated_rows']);
        $this->assertSame(1, $preview['summary']['skipped_rows']);
        $this->assertSame(1, $preview['summary']['failed_rows']);
        $this->assertSame('birth_date', $preview['rows'][3]['error_details'][0]['field']);
        $this->assertSame(4, $preview['rows'][3]['error_details'][0]['row_number']);
        $this->assertSame($before, Member::query()->count());
    }

    public function test_duplicate_source_member_codes_fail_without_creating_members(): void
    {
        $club = Club::query()->create(['name' => 'Synthetic Duplicate Club', 'code' => 'SYN-DUP']);
        $rows = [
            ['Member Code' => 'SYN-DUP-1', 'Full Name' => 'Synthetic One'],
            ['Member Code' => 'syn-dup-1', 'Full Name' => 'Synthetic Two'],
        ];

        $result = app(MemberDataImportCommitService::class)->commit($club->id, $rows);

        $this->assertSame(0, $result['summary']['created_rows']);
        $this->assertSame(2, $result['summary']['failed_rows']);
        $this->assertSame(0, Member::query()->where('club_id', $club->id)->count());
    }

    public function test_commit_creates_and_safely_updates_with_blank_and_placeholder_preservation(): void
    {
        $club = Club::query()->create(['name' => 'Synthetic Commit Club', 'code' => 'SYN-CMT']);
        $existing = Member::query()->create([
            'club_id' => $club->id,
            'member_code' => 'SYN-EXIST',
            'full_name' => 'Synthetic Existing',
            'occupation' => 'Protected Occupation',
            'spouse_name' => 'Protected Spouse',
        ]);

        $result = app(MemberDataImportCommitService::class)->commit($club->id, [
            [
                'Member Code' => 'SYN-NEW',
                'Full Name' => 'Synthetic Created',
                'Birth Date' => '1995-08-09',
                'Address Line 1' => '10 Synthetic Lane',
            ],
            [
                'Member Code' => 'SYN-EXIST',
                'Full Name' => 'Synthetic Updated',
                'Occupation' => '52',
                'Spouse Name' => '',
                'Membership Program' => 'Synthetic Program',
            ],
        ]);

        $this->assertSame(1, $result['summary']['created_rows']);
        $this->assertSame(1, $result['summary']['updated_rows']);
        $this->assertSame('created', $result['rows'][0]['status']);
        $this->assertSame('updated', $result['rows'][1]['status']);

        $existing->refresh();
        $this->assertSame('Synthetic Updated', $existing->full_name);
        $this->assertSame('Protected Occupation', $existing->occupation);
        $this->assertSame('Protected Spouse', $existing->spouse_name);
        $this->assertSame('Synthetic Program', $existing->membership_program);
        $created = Member::query()
            ->where('club_id', $club->id)
            ->where('member_code', 'SYN-NEW')
            ->firstOrFail();
        $this->assertSame('1995-08-09', $created->birth_date->toDateString());
    }

    public function test_commit_matching_remains_club_scoped(): void
    {
        $firstClub = Club::query()->create(['name' => 'Synthetic Scope One', 'code' => 'SYN-SCP1']);
        $secondClub = Club::query()->create(['name' => 'Synthetic Scope Two', 'code' => 'SYN-SCP2']);
        Member::query()->create([
            'club_id' => $firstClub->id,
            'member_code' => 'SHARED-001',
            'full_name' => 'First Club Member',
        ]);

        $result = app(MemberDataImportCommitService::class)->commit($secondClub->id, [[
            'Member Code' => 'SHARED-001',
            'Full Name' => 'Second Club Member',
        ]]);

        $this->assertSame(1, $result['summary']['created_rows']);
        $this->assertDatabaseHas('members', ['club_id' => $firstClub->id, 'full_name' => 'First Club Member']);
        $this->assertDatabaseHas('members', ['club_id' => $secondClub->id, 'full_name' => 'Second Club Member']);
    }

    public function test_directory_and_other_member_responses_do_not_expose_sensitive_profile_fields(): void
    {
        [$club, $normalUser] = $this->seededOfficer('member');
        $other = Member::query()->create([
            'club_id' => $club->id,
            'member_code' => 'SYN-PRIVATE',
            'full_name' => 'Synthetic Private Member',
            'birth_date' => '1980-01-02',
            'phone' => '+910000000000',
            'email' => 'private@example.test',
            'spouse_name' => 'Synthetic Spouse',
            'address_line_1' => 'Private Synthetic Address',
        ]);
        $token = $normalUser->createToken('3fc-normal')->plainTextToken;

        $listMember = $this->withToken($token)->getJson("/api/v1/clubs/{$club->id}/members")
            ->assertOk()
            ->json('members.0');
        $detail = $this->withToken($token)->getJson("/api/v1/clubs/{$club->id}/members/{$other->id}")
            ->assertOk()
            ->json('member');

        foreach (['birth_date', 'phone', 'email', 'spouse_name', 'wedding_anniversary', 'address_line_1'] as $field) {
            $this->assertArrayNotHasKey($field, $listMember);
            $this->assertArrayNotHasKey($field, $detail);
        }
    }

    public function test_own_profile_and_pst_profile_access_preserve_authorized_sensitive_visibility(): void
    {
        [$club, $normalUser] = $this->seededOfficer('member');
        $ownMember = Member::query()->create([
            'club_id' => $club->id,
            'user_id' => $normalUser->id,
            'member_code' => 'SYN-OWN',
            'full_name' => 'Synthetic Own Member',
            'birth_date' => '1982-03-04',
            'email' => 'own@example.test',
            'address_line_1' => 'Own Synthetic Address',
        ]);
        $normalToken = $normalUser->createToken('3fc-own')->plainTextToken;

        $this->withToken($normalToken)
            ->getJson("/api/v1/clubs/{$club->id}/members/{$ownMember->id}")
            ->assertOk()
            ->assertJsonPath('member.birth_date', '1982-03-04')
            ->assertJsonPath('member.email', 'own@example.test')
            ->assertJsonPath('member.address_line_1', 'Own Synthetic Address');

        $pstUser = $this->userForRole($club, 'secretary');
        $this->withToken($pstUser->createToken('3fc-pst')->plainTextToken)
            ->getJson("/api/v1/clubs/{$club->id}/members/{$ownMember->id}")
            ->assertOk()
            ->assertJsonPath('member.birth_date', '1982-03-04')
            ->assertJsonPath('member.email', 'own@example.test');
    }

    public function test_role_test_accounts_remain_present_and_are_not_transformed_by_import_services(): void
    {
        $this->seed(DatabaseSeeder::class);
        $club = Club::query()->where('code', 'LCB')->firstOrFail();
        $emails = [
            'president@lcbeenachi.test',
            'secretary@lcbeenachi.test',
            'treasurer@lcbeenachi.test',
            'local.member@lcbeenachi.test',
        ];
        $beforeLinks = User::query()->whereIn('email', $emails)->pluck('id', 'email');

        app(MemberDataImportPreviewService::class)->preview($club->id, [[
            'Member Code' => 'SYN-ROLE-SAFE',
            'Full Name' => 'Synthetic Role Safe Member',
        ]]);

        $this->assertCount(4, $beforeLinks);
        foreach ($beforeLinks as $email => $id) {
            $this->assertSame($id, User::query()->where('email', $email)->value('id'));
        }
        $this->assertDatabaseMissing('members', ['member_code' => 'SYN-ROLE-SAFE']);
    }

    public function test_implementation_document_records_scope_and_3fd_handoff(): void
    {
        $document = file_get_contents($this->docPath);

        foreach ([
            'Milestone 3FC - Member Profile Schema and Import Contract Implementation',
            'club_id + member_code',
            'Safe blank-update rule',
            'No real member import was performed.',
            'Frontend integration remains deferred to Milestone 3FF.',
            'IMPLEMENTED — ready for Milestone 3FD validation and closure.',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    /** @return array{Club, User} */
    private function seededOfficer(string $roleSlug): array
    {
        $this->seed(DatabaseSeeder::class);
        $club = Club::query()->where('code', 'LCB')->firstOrFail();

        return [$club, $this->userForRole($club, $roleSlug)];
    }

    private function userForRole(Club $club, string $roleSlug): User
    {
        $role = Role::query()->where('slug', $roleSlug)->firstOrFail();
        $user = User::factory()->create([
            'email' => 'synthetic-'.$roleSlug.'-3fc-'.uniqid().'@example.test',
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
