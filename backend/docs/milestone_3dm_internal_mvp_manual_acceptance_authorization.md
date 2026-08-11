# Milestone 3DM - Internal MVP Manual Acceptance Authorization

## Status

Milestone 3DM is a docs/tests-only authorization milestone.

3DM authorizes a future controlled internal MVP manual acceptance execution lane.

3DM does not execute manual acceptance.

3DM does not run browser/manual role testing beyond normal automated validation commands.

3DM does not edit frontend runtime code.

3DM does not edit app/page.tsx.

3DM does not change backend runtime behavior.

3DM does not add routes, controllers, requests, resources, services, models, migrations, policies, middleware, seeders, factories, config, package files, or lock files.

3DM does not deploy the app.

3DM does not use production data.

3DM does not use real club financial records.

3DM does not add file upload, file storage, profile document file download, profile document public URLs, profile document signed URLs, OCR, profile document PDF generation, profile document DELETE behavior, new finance backend mutation behavior, member import behavior, deployment/SaaS behavior, or client-specific behavior.

3DM must not start 3DN.

## Purpose of 3DM

The purpose of 3DM is to authorize the future internal MVP manual acceptance execution lane and define its guardrails.

3DM answers whether the project is allowed to run the internal MVP manual acceptance test next, under what environment, with which roles, what evidence, what stop conditions, what pass/fail categories, and what must not be tested or claimed.

3DM must not claim that the app has passed manual acceptance.

3DM must not claim production readiness.

3DM must not claim SaaS readiness.

3DM must not claim deployment readiness.

## Base and Created Branch

Base branch:

- milestone-3dl-internal-mvp-manual-acceptance-scope-gate

Verified base hash:

- f0052943e5f53299b8c67c9cc11c3f5b093cc29d

Created branch:

- milestone-3dm-internal-mvp-manual-acceptance-authorization

## Docs and Tests Only Authorization Scope

Only these files are allowed in 3DM:

- backend/docs/milestone_3dm_internal_mvp_manual_acceptance_authorization.md
- backend/tests/Feature/Milestone3dmInternalMvpManualAcceptanceAuthorizationTest.php

Read-only inspection of existing frontend, backend, docs, and tests is allowed.

app/page.tsx is not edited in 3DM.

Frontend runtime is not edited in 3DM.

Backend runtime is not edited in 3DM.

Routes/controllers/requests/resources/services/models/migrations are not edited in 3DM.

Older milestone tests are not edited in 3DM.

## Relationship to Existing Foundation

Milestone 2E recorded Member Module Closure.

Milestones 3A through 3AF established finance foundation, payment recording, receipt, receipt PDF, and related closure lanes.

Milestones 3AG through 3AI established Outstanding Dues and Member Finance Summary read API work.

Milestones 3AJ through 3BZ covered Payment History, Import, roadmap, and related closure lanes where relevant.

Milestones 3CT through 3DG covered Member Profile Documents backend and frontend lanes through metadata-only frontend closure.

Milestone 3DK closed the MVP frontend usability implementation lane.

Milestone 3DL defined the Internal MVP Manual Acceptance Scope Gate.

3DM authorizes the next controlled execution lane without reopening closed member, finance, import, receipt, payment history, or profile document runtime lanes.

## Future 3DN Execution Boundary

3DM authorizes this future execution milestone:

- Milestone 3DN - Internal MVP Manual Acceptance Execution

3DN may execute manual acceptance only under these conditions:

- Local development environment.
- Non-production database.
- Seeded/local test users.
- No real member financial records.
- No production deployment.
- No SaaS onboarding.
- No client-specific hardcoding.
- Existing frontend only.
- Existing APIs only.

3DN must not use:

- Production database.
- Real club financial records.
- Real payment collection.
- Live club deployment.
- Public URLs.
- Member import using real data.
- Unapproved file upload.
- Unapproved profile document storage.
- SaaS subscription/onboarding flows.
- Client-specific hardcoding.

## Future 3DN Allowed Manual Checks

3DM authorizes 3DN to manually check existing internal MVP behavior only:

- Login/session using local/test users.
- Dashboard usability.
- Internal MVP quick actions.
- Role-based navigation.
- Member list.
- Member create/edit.
- Member detail/profile context.
- Current dues/member finance summary.
- Payment recording flow where existing support allows.
- Receipt visibility.
- Receipt PDF access where existing support allows.
- Payment history visibility where existing support allows.
- Profile Documents metadata list/create/edit.
- Role-based button visibility.
- Backend authorization blocking unauthorized actions.
- Loading states.
- Empty states.
- Safe error states.
- Validation messages.
- Success/refresh behavior after write actions.
- Unsupported feature exclusions.

## Future 3DN Blocked Manual Checks

3DM blocks 3DN from testing or implying support for:

- Profile document upload.
- Profile document download.
- Profile document preview.
- Profile document delete.
- Profile document OCR.
- Profile document PDF generation.
- Profile document storage/public/signed URLs.
- New finance backend mutation behavior.
- Member import with real files/data.
- Deployment readiness.
- SaaS readiness.
- Production use.
- Real money collection.
- Real club financial settlement.
- Client-specific rollout.

## Required Acceptance Roles

3DM authorizes future 3DN testing for these roles:

- President/Admin.
- Secretary.
- Treasurer.
- Normal Member.

Existing local role test users are documented in LocalRoleTestUsersSeeder:

- President/Admin: president@lcbeenachi.test
- Secretary: secretary@lcbeenachi.test
- Treasurer: treasurer@lcbeenachi.test
- Normal Member: local.member@lcbeenachi.test

The local/test-user requirement is to use existing seeded/local test users and documented local credentials only.

The inspected seeder uses LC_BEENACHI_BOOTSTRAP_PASSWORD and defaults to password in local/testing environments.

These credentials are local/test-only and must not appear in evidence reports as real passwords.

## Future 3DN Evidence Requirements

3DN must produce a written evidence report.

The report must include:

- Date/time of run.
- Branch/hash tested.
- Environment used.
- Database/data source confirmation.
- Role personas tested.
- Checklist results.
- Defects found.
- Blocked items.
- Not-in-scope items.
- Screenshots optional but not required.
- Final go/no-go recommendation.

The evidence must not include real passwords, real member financial records, or private personal data.

## Result Categories

3DN must use these result categories:

- PASS - works as expected for internal MVP.
- PASS WITH MINOR NOTES - usable but needs polish later.
- BLOCKED - cannot test because prerequisite/setup/API/data is missing.
- FAIL - approved MVP behavior exists but does not work.
- NOT IN MVP SCOPE - intentionally excluded from internal MVP.

## Defect Severity Categories

3DN must use these defect severity categories:

- Critical - blocks internal MVP use or creates wrong access/security behavior.
- Major - important MVP flow broken but workaround may exist.
- Minor - usability issue that does not block internal testing.
- Note - observation for later polish.

## Future 3DN Stop Conditions

3DN must stop and report if any of these occur:

- Wrong branch/hash.
- Production database detected.
- Real member financial data detected.
- Login/session cannot be safely tested.
- Backend server/frontend server cannot start.
- Migration/seed state is unsafe.
- Normal Member can access officer-only actions.
- Backend authorization appears bypassed.
- Payment/receipt behavior appears unsafe or misleading.
- Profile documents imply upload/download/storage/OCR/PDF/delete support.
- Runtime errors prevent basic navigation.
- Test requires unsupported feature work.

## Go Criteria

3DN may recommend go only when:

- President/Admin, Secretary, Treasurer, and Member login flows are testable.
- Member list and member detail flows are understandable.
- Authorized member create/edit is testable.
- Current dues/member finance summary is understandable.
- Payment/receipt flows are discoverable where supported.
- Profile Documents metadata flow is usable and metadata-only.
- Unauthorized controls are hidden where frontend role checks support it.
- Backend still blocks unauthorized actions.
- No unsupported upload/download/OCR/profile-document PDF/delete behavior appears.
- No critical defects remain open.

## No-Go Criteria

3DN must recommend no-go when:

- Login/session cannot be tested.
- Member list cannot load.
- Officer cannot manage members where authorized.
- Normal Member can access officer-only actions.
- Finance/current dues display is misleading or unusable.
- Payment/receipt flow is misleading or unsafe.
- Profile Documents imply upload/download/storage/PDF/OCR/delete support.
- Backend authorization is bypassed.
- Runtime errors prevent normal navigation.
- Critical defects remain open.

## Future 3DN Reporting Format

3DN must use this report table:

| ID | Area | Role | Test Step | Expected Result | Actual Result | Status | Severity | Notes |
|----|------|------|-----------|-----------------|---------------|--------|----------|-------|

3DN must include this summary:

```text
Total checks:
PASS:
PASS WITH MINOR NOTES:
BLOCKED:
FAIL:
NOT IN MVP SCOPE:
Critical defects:
Major defects:
Go/No-Go recommendation:
```

## Non-Production and Local-Data Requirement

3DN must run against local development data, seeded/local test users, and a non-production database only.

3DN must not use production data.

3DN must not use real member financial records.

3DN must not use real payment collection.

3DN must not use live club deployment.

## Unsupported Feature Exclusions

3DN must continue to exclude:

- Profile document upload.
- Profile document download.
- Profile document preview.
- Profile document delete.
- Profile document OCR.
- Profile document PDF generation.
- Profile document storage/public/signed URLs.
- New finance backend mutation behavior.
- Member import with real files/data.
- Deployment readiness.
- SaaS readiness.
- Production use.
- Real money collection.
- Real club financial settlement.
- Client-specific rollout.

## Receipt PDF Clarification

Receipt PDF remains allowed only because it belongs to the approved finance/receipt lane.

Receipt PDF manual acceptance must be limited to already-supported payment receipt behavior.

Receipt PDF wording must not be generalized to Profile Documents.

## Profile Document Upload/Download/PDF/OCR Exclusion

Profile document upload/download/PDF/OCR remains out of scope.

Profile document storage, public URLs, signed URLs, preview, file download, and DELETE behavior remain out of scope.

Profile Documents manual acceptance is metadata-only.

## Risk Notes

3DM is not manual acceptance execution.

3DM is not deployment readiness.

3DM is not SaaS readiness.

3DM does not certify production use.

Manual acceptance still must be run in 3DN.

Local/test data may not reveal all real-data issues.

UI role hiding is not security; backend authorization must remain authoritative.

Existing app/page.tsx is large and future changes must remain narrow.

Missing backend/API gaps discovered during 3DN must become separate future milestones, not fake frontend behavior.

Receipt PDF is allowed only because it belongs to the approved finance/receipt lane.

Profile document upload/download/PDF/OCR remains out of scope.

3DN must stop if production data or real financial data is detected.

## Authorization Decision

Milestone 3DM authorizes Milestone 3DN - Internal MVP Manual Acceptance Execution under the boundaries in this document.

3DM does not execute manual acceptance.

3DM does not certify that manual acceptance has passed.

## Expected Next Milestone

Expected next milestone:

- Milestone 3DN - Internal MVP Manual Acceptance Execution

3DM must not start 3DN.
