# Milestone 3FB - Member Profile Schema and Import Contract Scope Gate

## Status and purpose

Milestone 3FB is a documentation-and-governance scope gate only.

It defines and formally authorizes the narrow database, model, validation, API-resource, privacy, and import-contract expansion that Milestone 3FC may implement. It does not implement database columns, migrations, model changes, request changes, resource changes, service changes, API changes, frontend changes, or member imports.

Allowed files in 3FB are only:

- `backend/docs/milestone_3fb_member_profile_schema_and_import_contract_scope_gate.md`
- `backend/tests/Feature/Milestone3fbMemberProfileSchemaAndImportContractScopeGateTest.php`

## Verified Milestone 3FA blocker

Milestone 3FA stopped fail-closed because the existing member schema and import contract would silently discard required profile information.

Verified aggregate findings:

- 47 valid source records.
- 47 unique source member IDs.
- Zero duplicate source IDs.
- Existing local member-table count: 3.
- Four role-test users were preserved.
- No import was performed.
- No database changes were made.
- No cleaned workbook was created inside or outside Git.
- No source-code changes were made.
- No commit or push occurred.

Aggregate source capability only:

- Total source members: 47.
- Birth date available: 47.
- Lion Join Date available: 47.
- Occupation available: 15; missing: 32.
- Spouse name available: 4; missing: 43.
- Wedding anniversary available: 0.
- Personal email available: 23.
- Mobile available: 30.
- Birth dates recorded as 1 January and requiring later verification: 12.
- Placeholder-value artefacts require cleaning.
- `_x000D_` formatting artefacts require cleaning.

No private source values belong in this document, tests, logs, fixtures, or source control.

## Existing member and import contract

The current member schema and importer support only:

- `club_id` / club ownership.
- `member_code`.
- `full_name`.
- `phone`.
- `email`.
- `status`.
- `joined_at` for Lion Join Date.
- `blood_group`.
- `blood_donor_consent`.

The current import preview validates spreadsheet-like rows, reports batch duplicates and possible existing matches, and does not write. The current import commit creates eligible new members and skips existing matches; it does not provide the required safe update contract for matching members. Both flows are club-scoped and officer-authorized through existing request authorization.

The existing contract cannot persist birth date, occupation, spouse name, wedding anniversary, gender, membership classification, membership sponsor, additional phone fields, or structured address data. A 47-member import through the current contract would therefore be lossy and remains prohibited.

## Required target member-profile fields

3FC may preserve the existing fields and add these nullable semantic fields:

### Identity and name

- `salutation`.
- `first_name`.
- `middle_name`.
- `last_name`.
- Existing `full_name` as the compatible normalized display name.

### Personal profile

- `birth_date`.
- `occupation`.
- `spouse_name`.
- `wedding_anniversary`.
- `gender`.

Wedding anniversary remains nullable. The current source contains no usable wedding-anniversary dates, and no date may be inferred.

### Membership

- `membership_type`.
- `membership_category`.
- `membership_program`.
- `membership_sponsor_name`.
- Existing `joined_at` as Lion Join Date.
- Existing `status` as membership status where its normalized values are compatible.

The contract must represent a discounted Family Member without coercing all members to one membership type.

### Contact

- Existing `phone` as primary mobile for compatibility.
- `home_phone`.
- `alternate_phone`.
- Existing `email` as personal email for compatibility.

Missing contact data is valid and remains null.

## Explicit identity decision

**Decision: reuse `member_code` as the Lions Member ID.**

The existing schema already stores member code as text and enforces the composite uniqueness boundary `club_id + member_code`. This safely preserves leading zeros and permits the same external identifier in different clubs. A second external-member-ID column would duplicate identity semantics without a demonstrated safety benefit.

3FC must keep `member_code` required for imported Lions members, normalize it as text, and match imported members by `club_id + member_code`. Name, phone, email, role, or similarity must never be the primary identity key.

## Explicit address-storage decision

**Decision: use structured nullable address fields.**

3FC may add:

- `address_line_1`.
- `address_line_2`.
- `city_locality`.
- `district`.
- `state`.
- `postal_code`.
- `country`.

Structured storage is proportionate because the source already separates address components, supports safe restricted resource exposure, and avoids irreversible concatenation. Every field is nullable. Normalization must trim whitespace, remove trailing separators caused by blank components, convert placeholder artefacts to null, and remove `_x000D_` without discarding supplied address information.

## Database and migration contract for 3FC

3FC may add one additive migration for the authorized fields.

- Every new extended-profile column must be nullable.
- Existing members, seed members, and test members must remain valid.
- Existing columns and constraints must not be removed or renamed.
- Unknown values must not be backfilled with fake defaults.
- `birth_date`, `wedding_anniversary`, and existing `joined_at` use appropriate date types.
- Address lines may use nullable strings; a text type is allowed only where source length requires it.
- Phone, postal-code, and identifier fields remain text so formatting or leading zeros are not lost.
- Current age must not be stored. Age is calculated from `birth_date` only when authorized and needed.
- Member-code uniqueness remains composite and club-scoped: `club_id + member_code`.
- Global cross-club member-code uniqueness is not authorized.
- Rollback removes only columns or constraints introduced by the new migration.
- Migration naming and order must follow existing project conventions.

No source member data may appear in the migration.

## Member model contract

3FC may update `App\Models\Member` narrowly to include authorized fields in fillable or guarded handling, add date casts for `birth_date` and `wedding_anniversary`, preserve the existing `joined_at` date cast and donor-consent boolean cast, and handle nullable fields consistently.

`full_name` remains compatible with existing API and UI behavior. A normalized display-name helper is allowed only if required to construct or present the display name consistently. The model must not store current age or infer gender, occupation, spouse, anniversary, membership classification, or address values.

Existing relationships, `user_id`, authentication, and club ownership remain unchanged.

## Validation and normalization contract

The same semantic rules must govern member create, member update, import preview, and import commit:

- `member_code` is required for imported Lions members and preserved as text.
- `full_name` is required.
- `birth_date`, `wedding_anniversary`, and `joined_at` are valid dates when present.
- Occupation and spouse name are optional.
- Membership fields are optional unless present in the source.
- Gender is optional and restricted to explicitly supported normalized values.
- Email is optional and valid when present.
- All phone and address fields are optional.
- Missing optional values must not reject a member.
- Literal placeholder values in textual/contact/profile fields become null before validation.
- `_x000D_` must not survive normalization.
- Fixed `Current Age` input is ignored/rejected as persisted profile data.
- No missing value may be guessed or inferred.

Validation errors must identify row number, member code, field name, and reason without exposing unnecessary private values. Logs and responses must mask private values not needed to resolve the error.

## Import preview contract

3FC may expand the existing preview contract to support every authorized field. Preview must:

- Accept the approved spreadsheet-like import template.
- Normalize every source row before validation.
- Process all rows before returning a result.
- Identify creates, updates, unchanged/skipped rows, invalid rows, duplicate source member codes, and conflicts.
- Match existing records only by `club_id + member_code`.
- Report projected created, updated, skipped, and failed counts.
- Remain read-only and write no member or partial import data.
- Never silently drop a supported field or skip a row.
- Avoid unnecessary exposure of private values.
- Preserve the semantic distinction between blank, invalid, unchanged, and intentionally cleared.

Preview errors and update diffs must be reviewable before commit. 3FC must not import the current 47-member workbook.

## Import commit contract

3FC may expand the commit capability but must not execute the current member import. Future commit behavior must:

- Consume a reviewed preview result or approved preview token and revalidate server-side.
- Remain club-scoped.
- Match only by `club_id + member_code`.
- Create missing members and safely update matching members.
- Reject duplicate source member codes and unresolved conflicts.
- Preserve existing audit behavior and identify actor, club, operation, and affected rows.
- Execute atomically according to existing transaction conventions.
- Report created, updated, skipped, and failed counts.
- Never use ad-hoc SQL or bypass validation.
- Never delete members or mutate finance, fellowship, committee, activity, event, receipt, payment, or ledger data.

### Safe blank-update rule

A blank source value must not overwrite a populated existing value by default. Explicit clearing requires a separately represented, intentional supported mechanism. Placeholder artefacts are always null/absent input and must never overwrite valid data. Existing manually corrected values must not be erased silently. This rule must be enforced independently in preview and commit tests.

## Role-test account separation

- President/Admin, Secretary, Treasurer, and Normal Member test logins remain usable.
- Test users are not deleted, disabled, or converted into imported members.
- Authentication behavior and existing `user_id` relationships remain unchanged.
- Test users are not counted as imported genuine members unless separately linked through an explicitly authorized workflow.
- Test users do not automatically enter Fellowship Sponsor Rotation, Celebrations, or genuine-member selectors.
- Automatic identity matching by similar name, email, or role is prohibited.

## Privacy and authorization contract

Sensitive fields include full birth date/year, phone numbers, email, spouse name, wedding anniversary, and residential address.

- Existing role permissions remain authoritative and must be inspected before 3FC implementation.
- President/Admin, Secretary, and other currently authorized PST roles receive only the access already justified by the role matrix.
- A Normal Member may access their own authorized full profile but must not receive unrestricted sensitive data for other members.
- Restricted directory responses must not expose full birth year or residential address by default.
- Celebration output should use day/month when the year is unnecessary.
- Age is calculated only in an authorized context.
- Import preview/errors and logs mask unnecessary private values.
- Tests use synthetic values only.
- API responses explicitly map allowed fields; blind model serialization is prohibited.

## API and resource exposure boundaries

3FC must distinguish:

1. Full authorized officer profile response.
2. Restricted member-directory/list response.
3. Own-profile response.
4. Import preview metadata and masked row errors.

No response may expose every new column by default. `MemberController::memberPayload` currently serves both list and detail contexts; 3FC must split or parameterize exposure so restricted lists do not inherit sensitive profile fields. Existing club and permission middleware remains authoritative.

## Expected 3FC implementation surface

3FC may modify only the minimum justified backend runtime surface:

- One new additive migration under `backend/database/migrations/` for member extended-profile columns.
- `backend/app/Models/Member.php`.
- `backend/app/Http/Controllers/Api/V1/Member/MemberController.php`, or narrowly introduced member request/resource classes if needed to separate validation and exposure safely.
- `backend/app/Services/Members/MemberDataImportPreviewService.php`.
- `backend/app/Services/Members/MemberDataImportCommitService.php`.
- `backend/app/Http/Resources/Members/MemberDataImportPreviewResource.php` only if masked metadata shaping requires change.
- `backend/app/Http/Resources/Members/MemberDataImportCommitResource.php` only if masked metadata shaping requires change.
- `backend/app/Http/Requests/Members/MemberDataImportPreviewRequest.php` and `MemberDataImportCommitRequest.php` only if the row-envelope contract requires change.
- New or updated synthetic backend tests and one 3FC implementation record.

3FC must not change routes unless existing endpoints cannot expose the authorized behavior; any route change requires an explicit stop-and-report decision before implementation. No frontend file, seed data, authentication logic, real workbook, or real member value is authorized.

## Required 3FC tests

3FC tests must cover:

- Migration up/down safety and nullable existing-row compatibility.
- Club-scoped member-code uniqueness and leading-zero preservation.
- Every authorized field's create/update normalization and validation.
- Optional missing fields accepted.
- Date validation and no stored current age.
- Placeholder and `_x000D_` cleaning.
- Preview create/update/skipped/failed classification and zero writes.
- Duplicate source IDs and conflict rejection.
- Safe blank-update preservation.
- Atomic commit, audit behavior, and created/updated/skipped/failed counts.
- Role-test account separation.
- Officer/full-profile, own-profile, and restricted-directory privacy boundaries.
- Cross-club and unauthorized access failures.
- No real member values in tests or fixtures.

## Out of scope and milestone sequence

3FB does not implement or authorize current-milestone runtime changes. Actual member import is prohibited in both 3FB and 3FC.

Deferred work:

- Replacing hard-coded member lists in `app/page.tsx`.
- Fellowship Sponsor Rotation using imported members.
- Celebrations using imported dates.
- Board/Committee selectors using imported members.
- Member Directory rendering all new fields.
- Imported-member Payments identity.
- Dynamic fellowship-month lifecycle.
- File upload/storage or workbook persistence in Git.

Planned sequence:

- 3FB - scope gate.
- 3FC - backend schema and import-contract implementation only.
- 3FD - schema/import validation and closure.
- 3FE - current 47-member import execution.
- 3FF - imported-member frontend integration.
- 3FG - fellowship month lifecycle and dynamic continuation.

3FB must not begin 3FC, 3FE, 3FF, or 3FG.

## Final verdict

**GO - Milestone 3FC may implement the authorized schema and import-contract expansion.**

This verdict authorizes 3FC only. It does not authorize importing the current 47-member workbook in 3FC, changing frontend behavior, or beginning later milestones.
