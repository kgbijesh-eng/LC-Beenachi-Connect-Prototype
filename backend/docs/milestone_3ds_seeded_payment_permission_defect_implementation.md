# Milestone 3DS - Seeded Payment Permission Defect Implementation

## Status

Milestone 3DS implements the narrow DN-031 seed/permission fix authorized by 3DR.

3DS fixes DN-031 only.

3DS does not fix DN-020.

3DS does not directly fix DN-047.

3DS does not edit frontend runtime code.

3DS does not edit app/page.tsx.

3DS does not edit payment controller, payment service, payment middleware, routes, requests, resources, models, migrations, package files, or lock files.

3DS does not deploy the app.

3DS does not use production data.

3DS does not use real club financial records.

3DS does not perform real payment collection.

## Base and Branch

Base branch:

- milestone-3dr-seeded-payment-permission-defect-scope-gate

Verified base hash:

- 4503b7d22c4e90404b4e950355cb065a8f00a4a1

Created branch:

- milestone-3ds-seeded-payment-permission-defect-implementation

## Root Cause

DN-031 failed because the seeded local President/Admin persona used by internal MVP manual acceptance did not inherit payments.record.

The payment API route already requires club.permission:payments.record,club.

PaymentRecordingService already requires payments.record through ClubPermissionService.

EnsureClubPermission correctly returns HTTP 403 when the active role lacks the required permission.

The defect was therefore in seeded local persona permission readiness, not payment runtime behavior.

## Implementation

3DS updates only the local/testing persona seed path.

LocalRoleTestUsersSeeder now creates the payments.record permission in local and testing environments.

LocalRoleTestUsersSeeder now attaches payments.record to the seeded president-admin role.

The seeded President/Admin user remains president@lcbeenachi.test.

The seeded Normal Member user remains local.member@lcbeenachi.test.

Normal Member does not receive payments.record.

DefaultRolesAndPermissionsSeeder is not changed in 3DS.

The default foundation role matrix is not broadened in 3DS.

No runtime authorization checks are weakened.

## Files Changed

Approved 3DS implementation files:

- backend/database/seeders/LocalRoleTestUsersSeeder.php
- backend/docs/milestone_3ds_seeded_payment_permission_defect_implementation.md
- backend/tests/Feature/Milestone3dsSeededPaymentPermissionDefectImplementationTest.php

Existing test expectation updates required by the new seeded local President/Admin permission:

- backend/tests/Feature/AuthMeRoleAwareResponseTest.php
- backend/tests/Feature/ClubPermissionServiceTest.php
- backend/tests/Feature/ProtectedClubFoundationRoutesTest.php

## Narrow test-maintenance exception

3DS required narrow compatibility updates to existing backend feature tests because the seeded President/Admin permission list intentionally gained one new permission: `payments.record`.

The following older tests were updated only to reflect the corrected seeded permission contract:

- `backend/tests/Feature/AuthMeRoleAwareResponseTest.php`
- `backend/tests/Feature/ClubPermissionServiceTest.php`
- `backend/tests/Feature/ProtectedClubFoundationRoutesTest.php`

These changes do not alter runtime behavior. They only update expected permission fragments/counts from the previous President/Admin seeded permission set to the corrected set that now includes `payments.record`.

Normal Member payment authorization remains denied with HTTP 403.

3DR lifecycle guard exception:

- Not used.

## Validation Expectations

3DS focused validation must prove:

- payments.record exists after local seeded personas are loaded.
- president-admin has payments.record after local seeded personas are loaded.
- member does not have payments.record after local seeded personas are loaded.
- Seeded President/Admin can record a synthetic member payment through localhost/API-compatible backend flow.
- Seeded President/Admin payment recording no longer returns HTTP 403 due to missing payments.record.
- Seeded Normal Member still receives HTTP 403 for payment recording.
- DN-010 logout follow-up /auth/me behavior remains fixed.

## Current Validation

Focused backend validation passed:

- php artisan test --filter=Milestone3dsSeededPaymentPermissionDefectImplementationTest
- php artisan test --filter=LocalRoleTestUsersSeederTest
- php artisan test --filter=DefaultRolesAndPermissionsSeederTest
- php artisan test --filter=LocalRoleApiPermissionSmokeTest
- php artisan test --filter=Milestone3qPaymentRecordingApiNarrowImplementationTest
- php artisan test --filter=Milestone3dqLogout500DefectFixImplementationTest
- php artisan test --filter=AuthMeRoleAwareResponseTest
- php artisan test --filter=ClubPermissionServiceTest
- php artisan test --filter=ProtectedClubFoundationRoutesTest

Frontend validation passed:

- npm.cmd run build

Full backend validation note:

- php -d memory_limit=512M vendor/bin/phpunit was run before commit and exposed only current-working-tree lifecycle guard failures plus stale exact President/Admin permission-count assertions.
- The stale exact assertions were updated to include payments.record for the seeded local President/Admin persona.
- The lifecycle guard failures are expected to clear after this 3DS work is locally committed because those guards inspect uncommitted files.
- php -d memory_limit=512M vendor/bin/phpunit was rerun after the local commit from a clean working tree and passed: 1413 tests, 17422 assertions.

## Remaining Defects Outside 3DS

DN-020 remains outside 3DS:

- Frontend member edit-flow confirmation timed out.

DN-047 remains outside 3DS:

- Payment refresh blocked because payment recording failed.

DN-047 should be retested after DN-031 is fixed because the payment recording prerequisite is now expected to pass.

## Go/No-Go Interpretation

DN-031 can move to fixed when validation passes.

Internal MVP remains NO-GO PENDING DEFECT FIXES until DN-020 and DN-047 are handled by later milestones.

3DS does not open 3DT.

Expected next milestone after local commit and push verification:

- Milestone 3DT - Internal MVP manual acceptance re-run scope gate.
