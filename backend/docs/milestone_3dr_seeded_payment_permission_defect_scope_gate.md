# Milestone 3DR - Seeded Payment Permission Defect Scope Gate

## Status

Milestone 3DR is a docs/tests-only defect-fix scope gate for DN-031.

3DR scopes DN-031 only.

3DR does not implement the permission fix.

3DR does not edit runtime code.

3DR does not edit seeders, roles, permissions, policies, middleware, controllers, services, routes, requests, resources, models, migrations, factories, config, package files, or lock files.

3DR does not edit frontend runtime code.

3DR does not edit app/page.tsx.

3DR does not fix DN-020.

3DR does not fix DN-047.

3DR does not perform a new full manual acceptance execution.

3DR does not deploy the app.

3DR does not use production data.

3DR does not use real club financial records.

3DR does not perform real payment collection.

3DR must not start 3DS.

## Purpose of 3DR

The purpose of 3DR is to scope the second controlled defect-fix lane after corrected 3DN evidence, 3DO defect classification, 3DP logout scope, and 3DQ logout implementation.

3DR scopes this defect only:

- DN-031 - Seeded President/Admin payment attempt returned HTTP 403 because seeded roles do not include payments.record.

3DR records the observed defect, expected authorization behavior, suspected root-cause area, authorized future implementation boundary, and validation requirements for the next implementation milestone.

3DR must not falsely mark DN-031 fixed.

3DR must not declare the Internal MVP accepted.

Internal MVP remains NO-GO PENDING DEFECT FIXES.

## Base and Created Branch

Base branch:

- milestone-3dq-logout-500-defect-fix-implementation

Verified base hash:

- 13fd2e057f37a0871f0d6df395aa779869eb858a

Created branch:

- milestone-3dr-seeded-payment-permission-defect-scope-gate

## Docs and Tests Only Scope

Only these files are allowed in 3DR:

- backend/docs/milestone_3dr_seeded_payment_permission_defect_scope_gate.md
- backend/tests/Feature/Milestone3drSeededPaymentPermissionDefectScopeGateTest.php

Read-only inspection of existing frontend, backend, docs, and tests is allowed.

app/page.tsx is not edited in 3DR.

Frontend runtime is not edited in 3DR.

Backend runtime is not edited in 3DR.

Routes/controllers/requests/resources/services/models/migrations are not edited in 3DR.

Seeders, roles, and permissions are not edited in 3DR.

Older milestone tests are not edited in 3DR unless the narrow 3DQ lifecycle guard exception is required.

## Relationship to Prior Milestones

Milestone 3DN - Internal MVP Manual Acceptance Execution recorded corrected evidence and the Internal MVP result:

- NO-GO PENDING DEFECT FIXES.

Milestone 3DN recorded DN-031 as:

- FAIL.
- Synthetic President/Admin payment attempt against a synthetic local member returned HTTP 403 because the seeded local roles do not include payments.record.
- No payment was created.

Milestone 3DO - Internal MVP Defect Fix Scope Gate classified DN-031 as a backend permission/seeded local role readiness defect.

Milestone 3DP - Logout 500 Defect Fix Scope Gate scoped DN-010.

Milestone 3DQ - Logout 500 Defect Fix Implementation fixed DN-010.

Milestone 3DM authorized the controlled internal MVP manual acceptance execution lane.

Milestone 3DL defined the internal MVP manual acceptance scope gate and result categories.

Milestones 3A through 3AF established finance foundation, payment recording, receipt, receipt PDF, and closure lanes.

Payment recording authorization lanes documented the payments.record permission boundary.

Milestone 2D and related local role test user work established local seeded personas through LocalRoleTestUsersSeeder.

Milestone 1C established the Laravel backend foundation and role-permission foundation.

3DR does not reopen any prior milestone.

Corrected 3DN result remains NO-GO PENDING DEFECT FIXES.

DN-010 is fixed by 3DQ.

## DN-031 Observed Defect

DN-031 finding:

- DN-031 - FAIL.
- Area: Payment recording.
- Severity: Major.
- Seeded President/Admin payment attempt returned HTTP 403.
- Corrected 3DN evidence states the seeded local roles do not include payments.record.
- No payment was created.

Classification:

- Seed/permission defect.

Priority:

- Next fix after DN-010.

Acceptance impact:

- Blocks payment-recording acceptance for seeded President/Admin personas.
- Blocks dependent DN-047 payment refresh acceptance until payment recording can succeed for an intended authorized seeded persona.

## Expected Authorization Behavior

Expected future behavior:

- Seeded President/Admin persona used by internal MVP acceptance must have the intended payment-recording permission if the acceptance contract requires President/Admin payment recording.
- A payment recording request by that seeded authorized persona must no longer return HTTP 403 due to missing payments.record.
- Unauthorized payment attempts by Normal Member must still return HTTP 403.
- The fix must not weaken backend authorization.
- The fix must align seeded role permissions or test persona setup with the existing payment-recording authorization contract.

DN-031 is distinct from payment runtime behavior.

DN-031 must not be treated as a payment controller/service defect unless inspection proves the authorization contract itself is wrong.

## Read-Only Inspection Notes

Read-only inspection in 3DR reviewed:

- backend/database/seeders/DefaultRolesAndPermissionsSeeder.php.
- backend/database/seeders/LocalRoleTestUsersSeeder.php.
- backend/app/Http/Middleware/EnsureClubPermission.php.
- backend/app/Services/Finance/PaymentRecordingService.php.
- backend/routes/api.php.
- backend/docs/milestone_3dn_internal_mvp_manual_acceptance_execution.md.
- backend/docs/milestone_3do_internal_mvp_defect_fix_scope_gate.md.
- backend/docs/milestone_3dq_logout_500_defect_fix_implementation.md.
- backend/docs/milestone_3o_payment_recording_api_scope_gate.md.
- backend/docs/milestone_3p_payment_recording_api_implementation_authorization.md.
- backend/docs/milestone_3q_payment_recording_api_narrow_implementation.md.
- backend/docs/milestone_3n_payment_recording_service_validation_and_closure.md.
- backend/tests/Feature/LocalRoleTestUsersSeederTest.php.
- backend/tests/Feature/LocalRoleApiPermissionSmokeTest.php.

Observed from read-only inspection:

- The payment API route is wrapped in club.permission:payments.record,club.
- PaymentRecordingService also requires payments.record through ClubPermissionService.
- EnsureClubPermission returns HTTP 403 when a user lacks the required permission.
- DefaultRolesAndPermissionsSeeder does not currently define a payments.record permission in its default permission list.
- DefaultRolesAndPermissionsSeeder does not currently assign payments.record to president-admin.
- LocalRoleTestUsersSeeder assigns president@lcbeenachi.test to the president-admin role, and does not independently grant permissions.
- Earlier payment authorization docs state President/Admin may record payments only if the role has payments.record.

Suspected root-cause area:

- Seeded local role/permission setup is missing the payments.record permission required by the existing payment-recording authorization contract.
- The likely fix belongs in seeded roles/permissions or local test persona setup, not payment controller/service runtime.

This root-cause area is supported by read-only inspection, but 3DR does not implement the fix.

## Authorized Future Implementation Boundary for 3DS

Expected next implementation milestone:

- Milestone 3DS - Seeded Payment Permission Defect Implementation.

3DS may address DN-031 only.

3DS should be narrow and focused only on seeded payment permission alignment.

3DS may consider these file categories after inspection:

- Existing role/permission seeder if needed.
- Existing local role test user setup if needed.
- A focused backend feature test proving seeded President/Admin can record payment when intended by the acceptance contract.
- Documentation for the 3DS fix.

3DS must not do:

- No payment controller/service refactor unless inspection proves the authorization contract is wrong.
- No frontend changes.
- No app/page.tsx change.
- No import/profile-document/member-edit/deployment/SaaS changes.
- No broad role-permission redesign.
- No real payment collection.
- No production data.

If 3DS inspection proves President/Admin should not record payments by design, 3DS must reclassify the acceptance expectation rather than grant permission blindly.

## Future Validation Requirements for 3DS

3DS must validate:

- Seeded President/Admin persona used by internal MVP acceptance has the intended payment-recording permission if the acceptance contract requires President/Admin payment recording.
- A payment recording request by that seeded authorized persona no longer returns HTTP 403 due to missing payments.record.
- Unauthorized payment attempts by Normal Member still return HTTP 403.
- The fix does not weaken backend authorization.
- The fix is covered by a focused backend feature test.
- No frontend behavior is changed unless proven necessary.
- No production data, real club financial data, or real payment collection is used.

Recommended future validation commands for 3DS:

- php artisan test --filter=LocalRoleTestUsersSeederTest.
- php artisan test --filter=LocalRoleApiPermissionSmokeTest.
- php artisan test --filter=Milestone3qPaymentRecordingApiNarrowImplementationTest.
- php artisan test.
- php -d memory_limit=512M vendor/bin/phpunit.

## Remaining Defects Outside 3DR

3DR scopes DN-031 only.

DN-031 remains unfixed in 3DR.

DN-010 is already fixed by 3DQ.

These corrected 3DN defects remain unresolved outside this lane:

- DN-020 - Frontend member edit-flow confirmation timed out.
- DN-047 - Payment refresh blocked because payment recording failed.

DN-047 depends on DN-031 and must be retested only after DN-031 is fixed.

## Go/No-Go Interpretation

GO: No.

NO-GO: Yes, pending DN-031 and remaining defect fixes.

Current recommendation: NO-GO PENDING DEFECT FIXES.

Internal MVP must not be declared accepted in 3DR.

No production readiness, SaaS readiness, or deployment readiness is claimed.

Internal MVP may proceed only after defect fixes and a controlled re-run.

## Unsupported Feature Exclusions

3DR does not add:

- Profile document upload.
- Profile document download.
- Profile document preview.
- Profile document delete.
- OCR.
- Profile document PDF generation.
- File storage.
- Public file URLs.
- Signed file URLs.
- Download URLs.
- New finance backend mutation behavior.
- Member import behavior.
- Deployment behavior.
- SaaS behavior.
- Client-specific behavior.

## Receipt PDF Clarification

Receipt PDF is allowed only because it belongs to the approved finance/receipt lane.

3DR does not change receipt PDF behavior.

3DR does not generalize receipt PDF behavior to Profile Documents.

## Profile Document Upload/Download/PDF/OCR Exclusion

Profile document upload/download/PDF/OCR remains out of scope.

Profile document storage, public URLs, signed URLs, preview, file download, DELETE behavior, and OCR remain out of scope.

3DR does not modify Profile Documents behavior.

## Risk Notes

3DR is not a fix milestone.

3DR is not new manual acceptance execution.

3DR is not deployment readiness.

3DR is not SaaS readiness.

3DR does not certify production use.

Corrected 3DN result is NO-GO PENDING DEFECT FIXES.

DN-010 is fixed by 3DQ, but DN-031 remains unresolved.

Payment permission fixes must not weaken backend authorization.

Normal Member unauthorized payment behavior must remain 403.

Seeded role/persona fixes must align with the real intended role matrix, not bypass it.

If President/Admin should not record payments by design, then 3DS must reclassify the acceptance expectation rather than grant permission blindly.

Dependent DN-047 must be retested only after DN-031 is fixed.

Local/test data may not reveal all real-data issues.

UI role hiding is not security; backend authorization must remain authoritative.

Existing app/page.tsx is large and future changes must remain narrow.

Missing backend/API gaps discovered later must become separate future milestones, not fake frontend behavior.

Receipt PDF is allowed only because it belongs to the approved finance/receipt lane.

Profile document upload/download/PDF/OCR remains out of scope.

## Scope Gate Decision

Milestone 3DR scopes DN-031 only.

DN-031 remains unfixed in 3DR.

DN-010 is already fixed by 3DQ.

Internal MVP remains NO-GO PENDING DEFECT FIXES.

Next work should be Milestone 3DS - Seeded Payment Permission Defect Implementation.

No new manual acceptance execution was performed in 3DR.

No production data or real financial data was used in 3DR.

No deployment occurred in 3DR.

## Expected Next Milestone

Expected next milestone:

- Milestone 3DS - Seeded Payment Permission Defect Implementation.

3DR must not start 3DS.
