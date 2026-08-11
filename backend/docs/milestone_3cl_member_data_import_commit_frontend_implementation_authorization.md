# Milestone 3CL - Member Data Import Commit Frontend Implementation Authorization

## Status

Milestone 3CL is a docs/tests-only frontend implementation authorization milestone.

It authorizes only a future narrow frontend commit implementation in Milestone 3CM.

It does not implement frontend commit UI.

It does not edit app/page.tsx.

It does not change frontend runtime behavior.

It does not change backend runtime behavior.

It does not change backend API behavior.

It does not alter the existing preview UI.

It does not alter the preview or commit endpoints.

## Purpose

The purpose of 3CL is to convert the 3CK frontend commit scope gate into a precise future implementation authorization boundary.

3CL authorizes only a future narrow frontend commit flow, if later approved in Milestone 3CM.

3CL must not start implementation.

## Base

Base milestone:

- Milestone 3CK - Member Data Import Commit Frontend Scope Gate

Base branch:

- milestone-3ck-member-data-import-commit-frontend-scope-gate

Authorization branch:

- milestone-3cl-member-data-import-commit-frontend-implementation-authorization

## Allowed files

Only these files are allowed in 3CL:

- backend/docs/milestone_3cl_member_data_import_commit_frontend_implementation_authorization.md
- backend/tests/Feature/Milestone3clMemberDataImportCommitFrontendImplementationAuthorizationTest.php

Read-only inspection of existing frontend, backend, docs, and tests is allowed.

## Authorization focus

3CL authorizes a later milestone to implement only a narrow frontend commit UI.

The future implementation must stay inside the 3CK scope gate.

The future implementation must use existing backend endpoints only.

The future implementation must not be treated as a bulk Excel import system.

The future implementation must not bypass backend server-side validation.

The future implementation must show backend row-level results instead of assuming success.

The future implementation must prefer fail-closed messaging over uncertain commit behavior.

## Relationship to the closed preview frontend lane

The member import preview frontend lane is closed.

The existing preview UI must remain a preview/dry-run workflow.

3CL does not alter the existing preview UI.

3CL does not authorize reopening preview behavior.

Future commit UI must preserve the preview-first workflow and remain separate from preview.

## Relationship to the closed backend commit lane

The backend commit lane is closed through Milestone 3CJ.

The backend commit path remains club-scoped, officer-only, create-only, non-financial, and manual/paste-row only.

3CL does not alter the backend commit implementation.

3CL does not alter backend commit authorization.

3CL does not alter backend commit validation, duplicate checks, existing-member matching, or row outcomes.

## Relationship to 3CK frontend commit scope gate

Milestone 3CK opened the frontend commit scope gate.

3CK defined the future frontend boundary without implementation.

3CL converts that boundary into future implementation authorization for 3CM only.

3CL does not broaden 3CK.

3CL does not add frontend runtime changes.

## Existing backend endpoints

Future frontend implementation may use the existing preview endpoint:

- POST /api/v1/clubs/{club}/members/import-preview

Future frontend implementation may use the existing commit endpoint:

- POST /api/v1/clubs/{club}/members/import-commit

3CL must not modify either endpoint.

3CL must not add new API routes.

3CL must not change API response contracts.

## Future club-scoped frontend requirement

Future frontend commit behavior must be club-scoped.

Future frontend commit behavior must use the active authorized club context.

Future frontend commit behavior must not show or submit commit rows for another club.

Cross-club behavior must fail closed through backend authorization.

## Future officer-only frontend requirement

Future frontend commit behavior must be officer-only.

The only intended roles are:

- President/Admin through existing president or president-admin role flows
- Secretary
- Treasurer

Backend authorization remains the source of truth.

Frontend visibility must not be treated as security.

## Normal Member exclusion

Normal Member must not see commit behavior.

Normal Member must not see commit controls.

Normal Member must not trigger commit API calls.

Normal Member must not load commit data into hidden frontend state.

Normal Member must not create, update, delete, save, commit, or import member data.

## Preview-first workflow requirement

Future frontend commit behavior must preserve the existing preview-first workflow.

Preview must be run before commit is offered.

Preview and Commit must be clearly separated.

Future frontend commit behavior must not treat a stale preview as final authorization to commit.

Future frontend commit behavior must not bypass backend commit-time validation.

## Explicit confirmation requirement

Future frontend commit behavior must require explicit user confirmation before calling the commit endpoint.

The confirmation must make clear that commit creates eligible new member records only.

The confirmation must not imply that all rows will be imported.

The confirmation must not imply that existing records will be overwritten.

The confirmation must not imply that existing members will be updated.

The confirmation must not imply that members will be deleted.

## Row-level eligibility display requirement

Future frontend commit behavior must display row-level eligibility before commit.

Rows with validation errors must not be presented as eligible.

Rows with duplicate warnings must not be silently committed.

Rows with existing-member match warnings must not overwrite or update existing member data.

## Row-level validation, error, and warning display requirement

Future frontend commit behavior must display:

- row-level validation errors
- duplicate warnings
- existing-member match warnings
- backend-provided ineligible row reasons

Future frontend commit behavior must not invent row eligibility.

Future frontend commit behavior must not invent duplicate decisions.

Future frontend commit behavior must not invent existing-member match decisions.

## Row-level created, skipped, and failed result display requirement

After commit, future frontend behavior must display:

- row-level created results
- row-level skipped results
- row-level failed results
- backend summary counts

Summary counts must not hide row-level failures.

Created rows must be distinct from skipped or failed rows.

Future frontend behavior must show backend row-level results instead of assuming success.

## Create-only new member messaging requirement

Future frontend commit behavior must clearly state that commit creates eligible new members only.

Future frontend commit behavior may use safe wording such as Commit eligible new members.

Future frontend commit behavior must avoid dangerous wording such as Import all, Overwrite, Apply all, or Replace.

## No existing-member update rule

Existing-member update remains blocked.

Future frontend commit behavior must clearly state that existing members will not be overwritten.

Future frontend commit behavior must clearly state that existing members will not be updated.

Existing-member matches must remain skipped or blocked unless a later scope gate explicitly authorizes matched-row update behavior.

## No member deletion rule

Member deletion remains blocked.

Future frontend commit behavior must clearly state that members will not be deleted.

Member deactivation through import remains blocked.

## No silent overwrite rule

Silent overwrite remains blocked.

Future frontend commit behavior must not imply that existing records can be replaced.

Future frontend commit behavior must not hide existing-member match warnings.

## Manual and paste rows only

Manual/paste JSON or spreadsheet-like rows remain the only currently authorized input path.

Future frontend commit behavior must not add a bulk Excel import system.

Future frontend commit behavior must not add package-based spreadsheet parsing.

## Excel and CSV upload blocked

Excel upload remains blocked.

CSV upload remains blocked.

xlsx upload remains blocked.

Drag-and-drop file upload remains blocked.

## Parser, package, and storage blocked

xlsx parser/package integration remains blocked.

CSV parser/package integration remains blocked.

File upload/storage flow remains blocked.

Template generation remains blocked.

Sample file download remains blocked.

## Queue and job processing blocked

Queue/job processing remains blocked.

No background import worker, retry workflow, asynchronous batch import, or queued import processing is authorized.

## Finance and adjacent module mutation blocked

Future frontend commit behavior must not mutate:

- finance ledger
- dues
- payments
- receipts
- payment history
- balances
- committee data
- fellowship data
- activity data
- event data
- export files
- PDF files
- reminders
- notifications

Member import commit must remain member-data only unless a later scope gate explicitly changes that boundary.

## Deployment, SaaS, and client-specific behavior blocked

Deployment implementation remains blocked.

SaaS implementation remains blocked.

Client-specific behavior remains blocked.

Hardcoded Lions Club Beenachi rules remain blocked.

Hardcoded Rotary Central Bathery rules remain blocked.

Hardcoded future pilot club rules remain blocked.

## Explicit non-authorization

3CL does not authorize:

- frontend commit UI implementation in 3CL
- app/page.tsx changes
- frontend runtime file changes
- backend route changes
- backend controller changes
- backend request changes
- backend resource changes
- backend service changes
- backend model changes
- backend migration changes
- backend config changes
- preview endpoint changes
- commit endpoint changes
- import button implementation in 3CL
- commit button implementation in 3CL
- save button implementation in 3CL
- Excel upload
- CSV upload
- xlsx parser/package integration
- file upload/storage
- queue/job processing
- existing-member update
- member deletion
- silent overwrite
- finance ledger mutation
- dues mutation
- payment mutation
- receipt mutation
- payment-history mutation
- balance mutation
- deployment implementation
- SaaS implementation
- client-specific behavior

## Blocked files

3CL must not edit:

- app/page.tsx
- frontend runtime files
- backend/routes/api.php
- backend/app/Http/Controllers/Api/V1/Members/MemberDataImportCommitController.php
- backend/app/Http/Requests/Members/MemberDataImportCommitRequest.php
- backend/app/Http/Resources/Members/MemberDataImportCommitResource.php
- backend/app/Services/Members/MemberDataImportCommitService.php
- backend/app/Http/Controllers/Api/V1/Members/MemberDataImportPreviewController.php
- backend/app/Http/Requests/Members/MemberDataImportPreviewRequest.php
- backend/app/Http/Resources/Members/MemberDataImportPreviewResource.php
- backend/app/Services/Members/MemberDataImportPreviewService.php
- backend existing member controllers/requests/resources/services
- backend models
- backend middleware
- backend migrations
- backend seeders
- backend config
- composer.json
- composer.lock
- package.json
- package-lock.json
- pnpm-lock.yaml
- yarn.lock
- file upload/storage files
- Excel package/parser integration files
- queue/job files
- finance/payment/receipt/payment-history files
- ledger files
- committee/fellowship/activity/event import files
- export/PDF/reminder files
- deployment/SaaS files
- client-specific hardcoding files
- older milestone tests

## Expected next milestone

The expected next milestone is:

Milestone 3CM - Member Data Import Commit Frontend Narrow Implementation

3CM may implement a narrow frontend commit UI only if explicitly approved later.

3CL must not start implementation.
