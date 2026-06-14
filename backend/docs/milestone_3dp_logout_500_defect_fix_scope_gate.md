# Milestone 3DP - Logout 500 Defect Fix Scope Gate

## Status

Milestone 3DP is a docs/tests-only defect-fix scope gate for DN-010.

3DP defines and authorizes the narrow future fix path for DN-010.

3DP does not implement the logout fix.

3DP does not edit frontend runtime code.

3DP does not edit app/page.tsx.

3DP does not change backend runtime behavior.

3DP does not add or edit routes, controllers, requests, resources, services, models, migrations, policies, middleware, seeders, factories, config, package files, or lock files.

3DP does not perform new manual acceptance execution.

3DP does not deploy the app.

3DP does not use production data.

3DP does not use real club financial records.

3DP does not perform real payment collection.

3DP must not start 3DQ.

## Purpose of 3DP

The purpose of 3DP is to scope the first controlled defect-fix lane after corrected 3DN evidence and the 3DO defect-fix scope gate.

3DP scopes this defect only:

- DN-010 - Logout token follow-up /auth/me returns HTTP 500 after logout.

3DP records the observed defect, expected behavior, suspected root-cause area, authorized future implementation boundary, and validation requirements for the next implementation milestone.

3DP must not falsely mark DN-010 fixed.

3DP must not mark the Internal MVP accepted.

Internal MVP remains NO-GO PENDING DEFECT FIXES.

## Base and Created Branch

Base branch:

- milestone-3do-internal-mvp-defect-fix-scope-gate

Verified base hash:

- 2e6d06ddd474cb867f974ac7dfcce6434d5f2275

Created branch:

- milestone-3dp-logout-500-defect-fix-scope-gate

## Docs and Tests Only Scope

Only these files are allowed in 3DP:

- backend/docs/milestone_3dp_logout_500_defect_fix_scope_gate.md
- backend/tests/Feature/Milestone3dpLogout500DefectFixScopeGateTest.php

Read-only inspection of existing frontend, backend, docs, and tests is allowed.

app/page.tsx is not edited in 3DP.

Frontend runtime files are not edited in 3DP.

Backend runtime files are not edited in 3DP.

Routes/controllers/requests/resources/services/models/migrations are not edited in 3DP.

Seeders, factories, config, package files, and lock files are not edited in 3DP.

Older milestone tests are not edited in 3DP unless the narrow 3DO lifecycle guard exception is required.

## Relationship to Prior Milestones

Milestone 3DN - Internal MVP Manual Acceptance Execution recorded corrected localhost evidence and resulted in:

- NO-GO PENDING DEFECT FIXES.

Milestone 3DN recorded DN-010 as:

- FAIL.
- Logout endpoint returned success for seeded users.
- A subsequent /auth/me request with the logged-out token returned HTTP 500 instead of a clean unauthorized response.

Milestone 3DO - Internal MVP Defect Fix Scope Gate classified DN-010 as the first backend/auth defect-fix lane and decided backend/auth/permission defects should be fixed before frontend retesting.

Milestone 3DM authorized the controlled internal MVP manual acceptance execution lane.

Milestone 3DL defined the internal MVP manual acceptance scope gate and result categories.

Milestone 1C established the Laravel backend foundation, including Sanctum-based authentication foundation and the /api/v1 auth endpoint boundary.

3DP does not reopen 3DN, 3DO, 3DM, 3DL, or 1C.

3DP does not downgrade DN-010 from FAIL to BLOCKED.

## DN-010 Observed Defect

DN-010 finding:

- DN-010 - FAIL.
- Area: Login/session.
- Severity: Major.
- Logout deletes or invalidates the token.
- Follow-up /auth/me with the logged-out token returns HTTP 500.

Acceptance impact:

- This blocks reliable logout/session validation during internal MVP acceptance.
- HTTP 500 after logout is unacceptable for internal MVP acceptance because it indicates unsafe error handling.

## Expected Future Behavior

After logout deletes or invalidates the current token, a follow-up /auth/me request using that logged-out token must not return HTTP 500.

Preferred expected response:

- HTTP 401 unauthenticated.

If existing project auth contracts require another safe unauthenticated response, 3DQ may document and use that project-consistent response.

The future fix must not weaken authentication.

The future fix must not mask authorization failures as success.

The future fix must preserve valid /auth/me behavior for authenticated users.

Backend authorization must remain authoritative.

## Read-Only Inspection Notes

Read-only inspection in 3DP reviewed:

- backend/routes/api.php.
- backend/app/Http/Controllers/Api/V1/Auth/AuthController.php.
- backend/tests/Feature/AuthApiTest.php.
- backend/tests/Feature/AuthMeRoleAwareResponseTest.php.
- backend/docs/milestone_3dn_internal_mvp_manual_acceptance_execution.md.
- backend/docs/milestone_3do_internal_mvp_defect_fix_scope_gate.md.
- docs/milestone-1c/Milestone_1C_Laravel_Backend_Foundation_Setup.md.

Observed from read-only inspection:

- /api/v1/auth/me is protected by auth:sanctum middleware.
- /api/v1/auth/logout is protected by auth:sanctum middleware.
- AuthController::logout deletes the current access token when present and returns "Logged out successfully."
- AuthApiTest covers unauthenticated /auth/me, authenticated /auth/me, login, and token revocation.
- Existing tests do not cover a same-token /auth/me request after logout token deletion.

Suspected root-cause area:

- Backend auth/Sanctum token invalidation response handling for a deleted current access token used again against /auth/me.
- The missing regression test for post-logout same-token /auth/me behavior.

This is a suspected root-cause area based on read-only inspection. 3DP does not claim the root cause is confirmed.

## Authorized Future Implementation Boundary for 3DQ

Expected next implementation milestone:

- Milestone 3DQ - Logout 500 Defect Fix Implementation.

3DQ may address DN-010 only.

3DQ may inspect and minimally change backend/auth behavior required to make post-logout protected endpoint access safe and deterministic.

3DQ may consider these file categories after inspection:

- Existing auth controller/logout/me handling file if needed.
- A focused backend feature test for logout follow-up behavior.
- Documentation for the 3DQ fix.

Likely allowed files for 3DQ, to be confirmed by the 3DQ prompt:

- backend/app/Http/Controllers/Api/V1/Auth/AuthController.php, only if the implementation requires a controller guard.
- backend/tests/Feature/AuthApiTest.php or a focused new backend feature test, only for logout follow-up behavior.
- backend/docs/milestone_3dq_logout_500_defect_fix_implementation.md.

3DQ must not authorize or touch:

- Frontend runtime files.
- app/page.tsx.
- Payment, receipt, ledger, or payment-history backend runtime files.
- Member edit/runtime files unless proven directly necessary for DN-010, which is not expected.
- Profile document runtime files.
- Member import runtime files.
- Committee, fellowship, activity, or event runtime files.
- Export or reminder runtime files.
- Deployment or SaaS files.
- Client-specific hardcoding files.
- File upload, file storage, public URL, signed URL, download URL, OCR, profile document PDF generation, or profile document DELETE behavior.

## Future Validation Requirements for 3DQ

3DQ must validate:

- A logout request using a valid token succeeds according to the existing logout contract.
- A follow-up /auth/me request using the same logged-out token does not return HTTP 500.
- The preferred expected response is HTTP 401 unauthenticated, unless existing project auth contracts require another safe response.
- The fix is covered by a focused backend feature test.
- Existing auth behavior for valid logged-in users remains unchanged.
- Existing unauthenticated /auth/me behavior remains unchanged.
- No frontend behavior is changed unless proven necessary.
- No production data, real club financial data, or real payment collection is used.

Recommended future validation commands for 3DQ:

- php artisan test --filter=AuthApiTest.
- php artisan test --filter=AuthMeRoleAwareResponseTest.
- php artisan test.
- php -d memory_limit=512M vendor/bin/phpunit.

## Remaining Defects Outside 3DP

3DP scopes DN-010 only.

These corrected 3DN defects remain unresolved outside this lane:

- DN-020 - Frontend member edit-flow confirmation timed out.
- DN-031 - Seeded President/Admin payment attempt returned 403 due missing payments.record.
- DN-047 - Payment refresh blocked because payment recording failed.

DN-020, DN-031, and DN-047 must be handled by later milestones authorized by 3DO or successor gates.

## Go/No-Go Interpretation

GO: No.

NO-GO: Yes, pending DN-010 and remaining defect fixes.

Current recommendation: NO-GO PENDING DEFECT FIXES.

Internal MVP must not be declared accepted in 3DP.

No production readiness, SaaS readiness, or deployment readiness is claimed.

Internal MVP may proceed only after defect fixes and a controlled re-run.

## Unsupported Feature Exclusions

3DP does not add:

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

Receipt PDF remains allowed only because it belongs to the approved finance/receipt lane.

3DP does not change receipt PDF behavior.

3DP does not generalize receipt PDF wording to Profile Documents.

## Profile Document Upload/Download/PDF/OCR Exclusion

Profile document upload/download/PDF/OCR remains out of scope.

Profile document storage, public URLs, signed URLs, preview, file download, DELETE behavior, and OCR remain out of scope.

3DP does not modify Profile Documents behavior.

## Risk Notes

3DP is not a fix milestone.

3DP is not new manual acceptance execution.

3DP is not deployment readiness.

3DP is not SaaS readiness.

3DP does not certify production use.

Corrected 3DN result is NO-GO PENDING DEFECT FIXES.

DN-010 is a backend/auth reliability defect until fixed.

HTTP 500 after logout is unacceptable for internal MVP acceptance because it indicates unsafe error handling.

The fix must not weaken authentication.

The fix must not mask authorization failures as success.

The fix must preserve valid /auth/me behavior for authenticated users.

Backend authorization must remain authoritative.

Local/test data may not reveal all real-data issues.

Existing app/page.tsx is large and future changes must remain narrow.

Missing backend/API gaps discovered later must become separate future milestones, not fake frontend behavior.

Receipt PDF is allowed only because it belongs to the approved finance/receipt lane.

Profile document upload/download/PDF/OCR remains out of scope.

## Scope Gate Decision

Milestone 3DP scopes DN-010 only.

DN-010 remains unfixed in 3DP.

Internal MVP remains NO-GO PENDING DEFECT FIXES.

Next work should be Milestone 3DQ - Logout 500 Defect Fix Implementation.

No fix was implemented in 3DP.

No new manual acceptance execution was performed in 3DP.

No production data or real financial data was used in 3DP.

No deployment occurred in 3DP.

## Expected Next Milestone

Expected next milestone:

- Milestone 3DQ - Logout 500 Defect Fix Implementation.

3DP must not start 3DQ.
