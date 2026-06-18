# Milestone 3FC - Member Profile Schema and Import Contract Implementation

## 1. Milestone purpose

Milestone 3FC implements the backend-only member-profile schema and import contract authorized by Milestone 3FB. It expands storage, model assignment and casts, create/update validation, privacy-aware member responses, import normalization, preview classification, and safe commit updates.

No real member import was performed.

## 2. Controlling 3FB decisions

- `member_code` remains the Lions Member ID.
- Identity and import matching remain club-scoped through `club_id + member_code`.
- Address data uses nullable structured fields.
- Optional missing values remain valid.
- Blank or placeholder source values do not erase populated values.
- Role-test accounts remain separate from genuine imported members.
- Member responses explicitly control sensitive-field exposure.
- Frontend integration remains deferred to Milestone 3FF.
- Dynamic fellowship-month lifecycle remains deferred to Milestone 3FG.

## 3. Migration fields

One additive migration adds these nullable columns to `members`:

- Identity: `salutation`, `first_name`, `middle_name`, `last_name`.
- Personal profile: `birth_date`, `occupation`, `spouse_name`, `wedding_anniversary`, `gender`.
- Membership: `membership_type`, `membership_category`, `membership_program`, `membership_sponsor_name`.
- Contact: `home_phone`, `alternate_phone`.
- Structured address: `address_line_1`, `address_line_2`, `city_locality`, `district`, `state`, `postal_code`, `country`.

`birth_date` and `wedding_anniversary` use database date columns. Phone and postal-code values remain strings. The migration has no backfill, fake defaults, destructive changes, or real member data. Rollback removes only these columns.

No `current_age` column was added. Age remains derived from `birth_date` only when an authorized future context requires it.

## 4. Existing-field reuse

The implementation reuses:

- `club_id` for ownership and scoping.
- `member_code` for Lions Member ID.
- `full_name` for backward-compatible display name.
- `phone` for primary mobile.
- `email` for personal email.
- `status` for member status.
- `joined_at` for Lion Join Date.
- `blood_group` and `blood_donor_consent` without semantic duplication.

When an import row omits `full_name` but supplies name components, normalization composes a display name from nonblank first, middle, and last names. It does not infer missing name components.

## 5. Member-code uniqueness

The original members-table migration already contains the composite unique index on `club_id + member_code`. 3FC does not add a duplicate index. Member codes remain strings, retain leading zeros, and may repeat across different clubs.

Create/update validation scopes uniqueness to the route club and correctly ignores the current record during update. Import preview and commit match only within the supplied club.

## 6. Model changes

`App\Models\Member` permits safe assignment of every authorized extended field. It casts:

- `birth_date` as a date;
- `wedding_anniversary` as a date;
- existing `joined_at` as a date;
- existing `blood_donor_consent` as a boolean.

Relationships, user linkage, club ownership, finance relationships, and existing display-name behavior remain intact. The model does not infer private values or link imported rows to users.

## 7. Create/update validation

The existing member controller now validates the extended contract while preserving its existing authorization middleware:

- `member_code` and `full_name` retain current required behavior.
- Dates are nullable and validated when present.
- Email is nullable and validated when present.
- Gender normalizes supported variants to `male`, `female`, or `other`.
- Extended text, contact, and address fields are nullable strings.
- `postal_code` remains text.
- `club_id` and `current_age` are prohibited client inputs.
- Update uniqueness remains club-scoped and ignores the current member.

Whitespace and `_x000D_` artefacts are cleaned before validation. The controller does not generate missing values.

## 8. Import normalization

`MemberDataImportPreviewService` is the single normalization authority used by both preview and commit. It:

- maps established and extended heading aliases;
- trims surrounding whitespace and safely collapses repeated internal whitespace;
- converts blank optional values to null;
- removes `_x000D_` artefacts;
- normalizes email casing and spacing;
- preserves a meaningful leading `+` and phone digits;
- preserves member codes and postal codes as text;
- normalizes valid dates without hiding invalid nonblank dates;
- normalizes supported gender values;
- ignores unsupported `Current Age` input;
- never infers absent values.

The literal placeholder `52` becomes null only for the approved optional text/contact/address fields where it represents an export artefact. It remains valid in identity fields, and text such as `00052` is preserved.

## 9. Preview behavior

Preview remains read-only. It normalizes and validates all rows, finds batch duplicates, finds same-club existing matches, and classifies rows as:

- `create_preview`;
- `update_preview`;
- `unchanged_preview`;
- compatibility `matched_preview` for a non-identity contact conflict;
- `reject`.

The summary reports projected created, updated, skipped, and failed counts while retaining legacy preview counts. Each error includes row number, member code where available, field, and reason. No database write occurs.

The current architecture uses reviewed rows rather than a persisted preview-token table. Commit therefore runs the exact preview service again server-side before applying any mutation, preserving normalization and validation equivalence without introducing a second importer or public endpoint.

## 10. Commit behavior

`MemberDataImportCommitService` consumes the reviewed row envelope accepted by the existing endpoint and re-runs the shared preview contract. Inside the existing database transaction it:

- creates valid missing members;
- updates valid same-club `member_code` matches;
- skips unchanged rows and contact conflicts;
- rejects invalid or duplicate-source rows;
- reports created, updated, skipped, and failed counts;
- performs no finance, payment, receipt, ledger, fellowship, or user mutation.

No ad-hoc SQL, delete operation, route addition, upload parser, workbook storage, or parallel importer was added.

## 11. Safe blank-update rule

Blank and placeholder-normalized source fields are excluded from update changes. They do not overwrite populated existing values. Valid nonblank changed fields update normally. Explicit clearing is not implemented in 3FC.

This preserves manually corrected occupation, spouse, contact, membership, and address values when a later source row is blank or contains an approved artefact.

## 12. Privacy and resource boundaries

Member directory/list responses use an explicitly restricted payload and omit full birth date, phone numbers, email, spouse name, wedding anniversary, membership sponsor, and residential address.

Selected-member detail responses expose sensitive fields only when:

- the authenticated user is linked to that member as their own profile; or
- the user's active club role is President/Admin, Secretary, or Treasurer.

A Normal Member does not gain unrestricted sensitive access to another member. Existing club permission middleware remains authoritative. Import endpoints remain officer-authorized. Import resources explicitly shape output and do not serialize the `Member` model blindly.

## 13. Role-test account protection

The seeded President/Admin, Secretary, Treasurer, and Normal Member accounts remain unchanged and usable. Import normalization does not match names, emails, or roles to user accounts. 3FC does not convert, delete, disable, or automatically link role-test users, and it does not place anyone into rotations or celebrations.

## 14. Exact runtime files changed

- `backend/database/migrations/2026_06_18_000001_add_extended_profile_fields_to_members_table.php`
- `backend/app/Models/Member.php`
- `backend/app/Http/Controllers/Api/V1/Member/MemberController.php`
- `backend/app/Services/Members/MemberDataImportPreviewService.php`
- `backend/app/Services/Members/MemberDataImportCommitService.php`
- `backend/app/Http/Resources/Members/MemberDataImportPreviewResource.php`
- `backend/app/Http/Resources/Members/MemberDataImportCommitResource.php`

No route, request, authentication, seed, frontend, finance, deployment, or dependency file changed.

## 15. Tests added or updated

Added:

- `backend/tests/Feature/Milestone3fcMemberProfileSchemaAndImportContractImplementationTest.php`

Narrow compatibility assertions updated because 3FC intentionally supersedes their earlier preview-only/create-only assumptions:

- `backend/tests/Feature/Milestone3buMemberDataImportExcelOnboardingNarrowImplementationTest.php`
- `backend/tests/Feature/Milestone3bvMemberDataImportExcelOnboardingValidationAndClosureTest.php`
- `backend/tests/Feature/Milestone3byMemberDataImportApiNarrowImplementationTest.php`
- `backend/tests/Feature/Milestone3bzMemberDataImportApiValidationAndClosureTest.php`
- `backend/tests/Feature/Milestone3ciMemberDataImportCommitBackendNarrowImplementationTest.php`

All test values are synthetic.

## 16. Real-data and frontend boundary

No real member import was performed.

No real workbook or private member value was read into the implementation, tests, documentation, fixtures, seeders, or Git.

Frontend integration remains deferred to Milestone 3FF.

## 17. Validation results

Validation covers migration structure, nullable columns, composite uniqueness, leading-zero preservation, model assignment/casts, create/update validation, shared normalization, preview classifications, safe commit updates, blank preservation, cross-club behavior, privacy, and role-test protection.

Focused validation completed successfully:

- 3FC implementation contract: 15 tests, 163 assertions.
- Protected member create/update/read routes: 16 tests, 58 assertions.
- Import preview compatibility: 36 tests, 409 assertions.
- Import commit compatibility: 14 tests, 104 assertions.
- Import API closure compatibility: 14 tests, 187 assertions.

The established full backend command, `php -d memory_limit=512M vendor/bin/phpunit`, passed against the clean candidate commit: 1,519 tests and 19,356 assertions.

## 18. Final verdict

**IMPLEMENTED — ready for Milestone 3FD validation and closure.**

This verdict does not begin 3FD, authorize the real 47-member import, or authorize frontend integration.
