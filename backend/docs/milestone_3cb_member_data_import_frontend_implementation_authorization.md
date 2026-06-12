# Milestone 3CB - Member Data Import Frontend Implementation Authorization

## Status

Milestone 3CB is a docs/tests-only frontend implementation authorization milestone.

It authorizes a future narrow frontend implementation in Milestone 3CC.

It does not implement frontend upload UI.

It does not change frontend runtime behavior.

It does not change backend runtime behavior.

It does not change API behavior.

## Base

Base milestone:

- Milestone 3CA - Member Data Import Frontend Scope Gate

Base branch:

- milestone-3ca-member-data-import-frontend-scope-gate

Authorization branch:

- milestone-3cb-member-data-import-frontend-implementation-authorization

## Purpose

The purpose of 3CB is to convert the 3CA scope gate into exact future authorization for Milestone 3CC.

3CB authorizes only a future frontend preview implementation that remains inside the 3CA boundary.

3CB does not authorize Excel upload, parser packages, import save or commit behavior, member mutation, backend API changes, finance behavior, deployment, SaaS, or client-specific behavior.

## Allowed files

Only these files are allowed in 3CB:

- backend/docs/milestone_3cb_member_data_import_frontend_implementation_authorization.md
- backend/tests/Feature/Milestone3cbMemberDataImportFrontendImplementationAuthorizationTest.php

## Blocked files and scope

3CB must not edit:

- app/page.tsx
- frontend runtime files
- backend/routes/api.php
- backend/app/Http/Controllers/Api/V1/Members/MemberDataImportPreviewController.php
- backend/app/Http/Requests/Members/MemberDataImportPreviewRequest.php
- backend/app/Http/Resources/Members/MemberDataImportPreviewResource.php
- backend/app/Services/Members/MemberDataImportPreviewService.php
- backend models
- backend middleware
- backend migrations
- backend seeders
- backend config
- composer.json
- composer.lock
- file upload files
- file storage files
- Excel package files
- Excel parser integration files
- queue files
- job files
- finance files
- payment files
- receipt files
- payment-history files
- ledger files
- committee import files
- fellowship import files
- activity import files
- event import files
- export files
- PDF files
- reminder files
- deployment files
- SaaS files
- client-specific hardcoding files

3CB must not implement frontend UI.

3CB must not add import commit or save behavior.

3CB must not add Excel upload.

3CB must not add parser packages.

3CB must not change backend runtime files.

## Authorized future 3CC implementation

Milestone 3CC may implement only a narrow frontend member import preview UI.

Future 3CC may add an officer-only member import preview UI.

Future 3CC may use only the existing 3BY preview API:

- POST /api/v1/clubs/{club}/members/import-preview

Future 3CC must remain club-scoped.

Future 3CC must remain preview/dry-run only.

Future 3CC may allow spreadsheet-like manual rows or paste input only if implemented without Excel upload, file upload, browser parsing packages, or backend parser changes.

Future 3CC may send preview rows to the existing 3BY API.

Future 3CC may display row-level validation errors.

Future 3CC may display duplicate warnings.

Future 3CC may display existing-member match warnings.

Future 3CC must clearly state that preview does not save changes.

Future 3CC must clearly state that existing member data will not be silently overwritten.

Future 3CC must hide the UI from Normal Member role.

Future 3CC must limit UI access to President/Admin, Secretary, and Treasurer.

Backend authorization remains the source of truth.

## Future UI input boundary

Future 3CC may evaluate only:

- manual row entry
- paste-based spreadsheet-like row entry
- controlled JSON-row preview request construction

Future 3CC must not add:

- Excel upload
- xlsx upload
- CSV upload
- drag-and-drop file upload
- browser-side xlsx parser
- browser-side CSV parser
- parser package
- upload storage
- file persistence
- sample file generation
- template download

Upload and parser behavior require a separate future scope gate.

## Future UI display boundary

Future 3CC may display only preview-safe data returned by the 3BY API:

- dry_run
- committed
- summary counts
- row number
- row validity
- intent
- normalized member fields returned by the backend
- row-level validation errors
- duplicate row warnings
- existing-member match warnings

Future 3CC must not invent fields, matches, duplicate decisions, member statuses, balances, totals, labels, or import outcomes.

If a backend field is absent, null, forbidden, or empty, future 3CC must show a safe unavailable or empty state.

## Future UI states

Future 3CC must define safe UI states for:

- loading
- empty or no rows entered
- validation errors returned by backend
- duplicate warnings returned by backend
- existing-member match warnings returned by backend
- missing active club context
- unauthenticated or 401
- forbidden or 403
- general API error
- successful preview display
- missing or null backend fields

Future 3CC must not expose raw technical errors to users.

## Explicitly blocked future frontend behavior

Future 3CC must not add:

- import save button
- import commit button
- create members through import
- update members through import
- delete members through import
- silent overwrite behavior
- Excel upload
- xlsx parser package
- CSV parser package
- upload storage
- backend route changes
- backend controller changes
- backend service changes
- backend request changes
- backend resource changes
- backend model changes
- backend migration changes
- finance ledger mutation
- payment mutation
- receipt mutation
- payment-history mutation
- balance mutation
- dues import
- committee import
- fellowship import
- activity import
- event import
- finance import
- export
- PDF generation
- reminder sending
- notification sending
- deployment implementation
- SaaS implementation
- client-specific behavior
- hardcoded Lions Club Beenachi rules
- hardcoded Rotary Central Bathery rules
- hardcoded future pilot club rules

## Role and visibility boundary

Normal Member:

- Must not see the member import preview UI.
- Must not trigger import preview API calls.
- Must not load import preview data into hidden frontend state.
- Must not upload, paste, preview, save, or commit import data.

President/Admin, Secretary, and Treasurer:

- May use the future preview UI only for the active club.
- May submit preview rows only to the existing 3BY endpoint.
- May view preview errors and warnings only for the active club.
- Must not receive import commit or save controls.
- Must not receive cross-club preview data.

## Backend and API contract

Future 3CC must use the existing 3BY API contract.

Future 3CC must not change the API response contract.

Future 3CC must not require new API routes.

Future 3CC must not infer permissions beyond backend authorization.

Future 3CC must not implement frontend-side accounting, finance, ledger, or balance calculations.

## Expected validation for 3CC

Milestone 3CC should include focused validation proving:

- frontend import preview UI is present only if authorized by 3CB.
- UI is hidden from Normal Member.
- Normal Member does not trigger import preview API calls.
- President/Admin, Secretary, and Treasurer can access the preview UI.
- the UI uses only POST /api/v1/clubs/{club}/members/import-preview.
- the UI displays row-level validation errors.
- the UI displays duplicate warnings.
- the UI displays existing-member match warnings.
- the UI clearly states preview does not save changes.
- no import/save/commit button exists.
- no Excel upload or parser exists.
- no backend API changes are made.
- no finance, ledger, payment, receipt, payment-history, export, reminder, deployment, SaaS, or client-specific behavior is added.

## Validation commands

Expected validation commands:

- php artisan test --filter=Milestone3cbMemberDataImportFrontendImplementationAuthorizationTest
- php artisan test
- php -d memory_limit=512M vendor/bin/phpunit if the known wrapper cleanup issue appears after passed tests
- git status --short --branch
- git diff --stat
- git diff --check
- git diff --cached --stat
- git diff --cached --check

## Next milestone

The expected next milestone is:

Milestone 3CC - Member Data Import Frontend Narrow Implementation

3CC may implement only the narrow member import preview frontend authorized by this document.
