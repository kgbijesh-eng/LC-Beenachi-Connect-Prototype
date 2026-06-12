# Milestone 3CK - Member Data Import Commit Frontend Scope Gate

## Status

Milestone 3CK is a docs/tests-only frontend scope gate.

It does not implement frontend commit UI.

It does not edit app/page.tsx.

It does not change backend runtime or API behavior.

It does not alter the preview UI.

It does not alter the preview or commit endpoints.

## Purpose

The purpose of 3CK is to define the boundaries for a possible future frontend member data import commit lane.

3CK records what a later authorization milestone may consider for frontend commit behavior after the backend commit lane closed in 3CJ.

3CK must not start implementation.

## Base

Base milestone:

- Milestone 3CJ - Member Data Import Commit Backend Validation and Closure

Base branch:

- milestone-3cj-member-data-import-commit-backend-validation-and-closure

Scope gate branch:

- milestone-3ck-member-data-import-commit-frontend-scope-gate

## Allowed files

Only these files are allowed in 3CK:

- backend/docs/milestone_3ck_member_data_import_commit_frontend_scope_gate.md
- backend/tests/Feature/Milestone3ckMemberDataImportCommitFrontendScopeGateTest.php

Read-only inspection of existing frontend, backend, docs, and tests is allowed.

## Existing endpoints referenced only

3CK may reference only these existing endpoints:

- POST /api/v1/clubs/{club}/members/import-preview
- POST /api/v1/clubs/{club}/members/import-commit

3CK does not change either endpoint.

3CK does not add new API routes.

3CK does not change API response contracts.

## Future frontend lane boundary

A future frontend commit lane may only be considered after a separate implementation authorization milestone.

The future lane must remain club-scoped.

The future lane must remain officer-only.

The only intended officer roles are:

- President/Admin through existing president or president-admin role flows
- Secretary
- Treasurer

Normal Member must not see commit behavior.

Normal Member must not trigger commit API calls.

Normal Member must not load commit data into hidden frontend state.

## Preview-first workflow requirement

Future frontend commit behavior must preserve a preview-first workflow.

Preview must be run before commit is offered.

Preview and Commit must be clearly separated.

The frontend must not treat a stale preview as final authorization to commit.

The backend remains the source of truth for commit-time validation, duplicate checks, existing-member matching, and row outcomes.

## Commit warning requirement

Future frontend commit behavior must warn that commit creates eligible new members.

Future frontend commit behavior must state that existing members are not overwritten.

Future frontend commit behavior must state that existing members are not updated.

Future frontend commit behavior must state that members are not deleted.

Future frontend commit behavior must avoid wording such as Import all or Overwrite.

Future frontend commit behavior may use safe wording such as Commit eligible new members only if later authorized.

## Row-level pre-commit display requirement

Before commit is offered, a future frontend must show:

- row-level eligibility
- row-level validation errors
- duplicate warnings
- existing-member match warnings

Rows with validation errors must not be presented as eligible.

Rows with duplicate warnings must not be silently committed.

Rows with existing-member match warnings must not overwrite or update existing member data.

## Explicit confirmation requirement

Future frontend commit behavior must require explicit confirmation before calling the commit endpoint.

The confirmation must make clear that the operation creates eligible new member records.

The confirmation must not imply that all rows will be imported.

The confirmation must not imply that existing records will be overwritten.

## Post-commit result display requirement

After commit, a future frontend must display:

- row-level created results
- row-level skipped results
- row-level failed results
- summary counts

Summary counts must not hide row-level failures.

Created rows must be distinct from skipped or failed rows.

## Manual rows only

Future frontend commit behavior remains limited to manual/paste JSON or spreadsheet-like rows.

No Excel upload is authorized.

No CSV upload is authorized.

No file upload is authorized.

No upload storage is authorized.

No xlsx parser package is authorized.

No CSV parser package is authorized.

No queue or job workflow is authorized.

## Finance and adjacent modules remain blocked

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

## Explicit non-authorization

3CK does not authorize:

- frontend commit UI implementation
- app/page.tsx changes
- frontend runtime changes
- backend runtime changes
- API behavior changes
- preview endpoint changes
- commit endpoint changes
- import button implementation
- commit button implementation
- save button implementation
- apply button implementation
- Excel upload
- CSV upload
- file upload
- xlsx parser package
- CSV parser package
- upload storage
- queue/job import processing
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
- hardcoded Lions Club Beenachi rules
- hardcoded Rotary Central Bathery rules
- hardcoded future pilot club rules

## Blocked files

3CK must not edit:

- app/page.tsx
- frontend runtime files
- backend/routes/api.php
- backend controllers
- backend requests
- backend resources
- backend services
- backend models
- backend middleware
- backend migrations
- backend seeders
- backend config
- API behavior
- 3CI implementation files
- older milestone tests
- package files
- upload/parser/storage files
- queue/job files
- finance/payment/receipt/payment-history/ledger files
- deployment/SaaS files
- client-specific hardcoding files

## Relationship to 3CJ

Milestone 3CJ closed the backend commit lane.

The backend commit lane remains club-scoped, officer-only, create-only, non-financial, and manual/paste-row only.

3CK does not reopen 3CJ.

3CK does not alter 3CI runtime implementation.

3CK only records the frontend scope gate required before any future frontend commit implementation authorization.

## Expected next milestone

The expected next milestone is:

Milestone 3CL - Member Data Import Commit Frontend Implementation Authorization

3CL may only authorize a later frontend implementation.

3CK does not start frontend work.
