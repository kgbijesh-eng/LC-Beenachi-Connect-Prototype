<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3fbMemberProfileSchemaAndImportContractScopeGateTest extends TestCase
{
    private string $docPath = __DIR__.'/../../docs/milestone_3fb_member_profile_schema_and_import_contract_scope_gate.md';

    public function test_3fb_document_exists_and_is_docs_tests_only_scope_gate(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Milestone 3FB - Member Profile Schema and Import Contract Scope Gate',
            'documentation-and-governance scope gate only',
            'It does not implement database columns, migrations, model changes, request changes, resource changes, service changes, API changes, frontend changes, or member imports.',
            'backend/docs/milestone_3fb_member_profile_schema_and_import_contract_scope_gate.md',
            'backend/tests/Feature/Milestone3fbMemberProfileSchemaAndImportContractScopeGateTest.php',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fb_records_3fa_fail_closed_findings_without_private_values(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            '47 valid source records.',
            '47 unique source member IDs.',
            'Zero duplicate source IDs.',
            'Existing local member-table count: 3.',
            'Four role-test users were preserved.',
            'No import was performed.',
            'No database changes were made.',
            'No cleaned workbook was created inside or outside Git.',
            'No source-code changes were made.',
            'No commit or push occurred.',
            'Birth date available: 47.',
            'Lion Join Date available: 47.',
            'Occupation available: 15; missing: 32.',
            'Spouse name available: 4; missing: 43.',
            'Wedding anniversary available: 0.',
            'Personal email available: 23.',
            'Mobile available: 30.',
            'Birth dates recorded as 1 January and requiring later verification: 12.',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }

        $this->assertDoesNotMatchRegularExpression('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $document);
        $this->assertDoesNotMatchRegularExpression('/\b\d{4}-\d{2}-\d{2}\b/', $document);
    }

    public function test_3fb_documents_existing_and_target_profile_fields(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'member_code', 'full_name', 'phone', 'email', 'status', 'joined_at', 'blood_group', 'blood_donor_consent',
            'salutation', 'first_name', 'middle_name', 'last_name', 'birth_date', 'occupation', 'spouse_name',
            'wedding_anniversary', 'gender', 'membership_type', 'membership_category', 'membership_program',
            'membership_sponsor_name', 'home_phone', 'alternate_phone',
        ] as $field) {
            $this->assertStringContainsString($field, $document);
        }

        $this->assertStringContainsString('Wedding anniversary remains nullable.', $document);
        $this->assertStringContainsString('Missing contact data is valid and remains null.', $document);
        $this->assertStringContainsString('discounted Family Member', $document);
    }

    public function test_3fb_makes_explicit_identity_and_address_decisions(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Decision: reuse `member_code` as the Lions Member ID.',
            'club_id + member_code',
            'Global cross-club member-code uniqueness is not authorized.',
            'Decision: use structured nullable address fields.',
            'address_line_1', 'address_line_2', 'city_locality', 'district', 'state', 'postal_code', 'country',
            'Every field is nullable.',
            'remove `_x000D_` without discarding supplied address information',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fb_defines_additive_nullable_migration_and_model_contract(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Every new extended-profile column must be nullable.',
            'Existing members, seed members, and test members must remain valid.',
            'Existing columns and constraints must not be removed or renamed.',
            'Unknown values must not be backfilled with fake defaults.',
            'Current age must not be stored.',
            'Member-code uniqueness remains composite and club-scoped',
            'Rollback removes only columns or constraints introduced by the new migration.',
            'App\\Models\\Member',
            'date casts for `birth_date` and `wedding_anniversary`',
            'Existing relationships, `user_id`, authentication, and club ownership remain unchanged.',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fb_defines_validation_and_cleaning_contract(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'member code, field name, and reason',
            '`member_code` is required for imported Lions members and preserved as text.',
            '`full_name` is required.',
            '`birth_date`, `wedding_anniversary`, and `joined_at` are valid dates when present.',
            'Occupation and spouse name are optional.',
            'Email is optional and valid when present.',
            'All phone and address fields are optional.',
            'Missing optional values must not reject a member.',
            'Literal placeholder values in textual/contact/profile fields become null before validation.',
            '`_x000D_` must not survive normalization.',
            'Fixed `Current Age` input is ignored/rejected as persisted profile data.',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fb_defines_preview_commit_and_safe_blank_update_contract(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Identify creates, updates, unchanged/skipped rows, invalid rows, duplicate source member codes, and conflicts.',
            'Match existing records only by `club_id + member_code`.',
            'Report projected created, updated, skipped, and failed counts.',
            'Remain read-only and write no member or partial import data.',
            'Never silently drop a supported field or skip a row.',
            'Preserve the semantic distinction between blank, invalid, unchanged, and intentionally cleared.',
            'Consume a reviewed preview result or approved preview token and revalidate server-side.',
            'Create missing members and safely update matching members.',
            'Execute atomically according to existing transaction conventions.',
            'A blank source value must not overwrite a populated existing value by default.',
            'Explicit clearing requires a separately represented, intentional supported mechanism.',
            'Existing manually corrected values must not be erased silently.',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fb_defines_role_test_separation_and_privacy_resource_boundaries(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'President/Admin, Secretary, Treasurer, and Normal Member test logins remain usable.',
            'Test users are not deleted, disabled, or converted into imported members.',
            'Test users do not automatically enter Fellowship Sponsor Rotation, Celebrations, or genuine-member selectors.',
            'Automatic identity matching by similar name, email, or role is prohibited.',
            'A Normal Member may access their own authorized full profile but must not receive unrestricted sensitive data for other members.',
            'Restricted directory responses must not expose full birth year or residential address by default.',
            'API responses explicitly map allowed fields; blind model serialization is prohibited.',
            'Full authorized officer profile response.',
            'Restricted member-directory/list response.',
            'Own-profile response.',
            'Import preview metadata and masked row errors.',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fb_records_expected_3fc_surface_tests_and_frontend_deferral(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'One new additive migration under `backend/database/migrations/`',
            'backend/app/Models/Member.php',
            'backend/app/Http/Controllers/Api/V1/Member/MemberController.php',
            'backend/app/Services/Members/MemberDataImportPreviewService.php',
            'backend/app/Services/Members/MemberDataImportCommitService.php',
            'Migration up/down safety and nullable existing-row compatibility.',
            'Safe blank-update preservation.',
            'Role-test account separation.',
            'Replacing hard-coded member lists in `app/page.tsx`.',
            'Fellowship Sponsor Rotation using imported members.',
            'Celebrations using imported dates.',
            'Board/Committee selectors using imported members.',
            'Imported-member Payments identity.',
            'Dynamic fellowship-month lifecycle.',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fb_scope_guard_blocks_current_runtime_work_and_real_import(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            '3FB does not implement or authorize current-milestone runtime changes.',
            'Actual member import is prohibited in both 3FB and 3FC.',
            '3FB must not begin 3FC, 3FE, 3FF, or 3FG.',
            'No frontend file, seed data, authentication logic, real workbook, or real member value is authorized.',
        ] as $blockedBoundary) {
            $this->assertStringContainsString($blockedBoundary, $document);
        }

        $this->assertEmpty(glob(database_path('migrations/*3fb*')) ?: []);
        $this->assertFileDoesNotExist(app_path('Services/Members/Milestone3fbMemberImportService.php'));
    }

    public function test_3fb_documents_sequence_and_authorizes_3fc_only(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            '3FB - scope gate.',
            '3FC - backend schema and import-contract implementation only.',
            '3FD - schema/import validation and closure.',
            '3FE - current 47-member import execution.',
            '3FF - imported-member frontend integration.',
            '3FG - fellowship month lifecycle and dynamic continuation.',
            'GO - Milestone 3FC may implement the authorized schema and import-contract expansion.',
            'This verdict authorizes 3FC only.',
            'It does not authorize importing the current 47-member workbook in 3FC',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    private function scopeDocument(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }
}
