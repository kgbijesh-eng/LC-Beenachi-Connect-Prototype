# Milestone 3BZ - Member Data Import API Validation and Closure

## Status

Milestone 3BZ is a docs/tests-only validation and closure milestone.

It validates and closes Milestone 3BY.

It does not change frontend runtime behavior.

It does not change backend runtime behavior.

It does not change API behavior.

## Base

Base milestone:

- Milestone 3BY - Member Data Import API Narrow Implementation

Base branch:

- milestone-3by-member-data-import-api-narrow-implementation

Closure branch:

- milestone-3bz-member-data-import-api-validation-and-closure

## Purpose

The purpose of 3BZ is to prove that the 3BY member data import preview API is safely closed.

3BZ validates only what 3BY implemented:

- one preview API route
- JSON spreadsheet-like rows input
- officer-only access
- club-scoped authorization
- Normal Member denial
- cross-club fail-closed behavior
- existing 3BU preview service usage
- row-level validation errors
- duplicate warnings as preview-only information
- existing-member match warnings as preview-only information
- no commit/import mutation endpoint
- no member create, update, or delete behavior
- no upload, xlsx parser, or package changes
- no frontend upload UI
- no finance, ledger, payment, receipt, payment-history, dues, committee, fellowship, activity, event, deployment, SaaS, or client-specific behavior

## Allowed files

Only these files are allowed in 3BZ:

- backend/docs/milestone_3bz_member_data_import_api_validation_and_closure.md
- backend/tests/Feature/Milestone3bzMemberDataImportApiValidationAndClosureTest.php

## Blocked files and scope

3BZ must not edit:

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

## Closure claims verified

3BZ verifies that 3BY added only this preview API route:

- POST api/v1/clubs/{club}/members/import-preview

3BZ verifies that the route is preview/dry-run only.

3BZ verifies that no commit/import mutation route exists.

3BZ verifies that no upload route exists.

3BZ verifies that no parser route exists.

3BZ verifies that no all-member import route exists.

## API implementation closure

3BZ verifies that:

- MemberDataImportPreviewController exists.
- MemberDataImportPreviewRequest exists.
- MemberDataImportPreviewResource exists.
- MemberDataImportPreviewService remains the existing source service.
- The controller calls MemberDataImportPreviewService::preview.
- The controller returns import_preview.
- The controller does not contain create, update, delete, commit, upload, parser, queue, or finance behavior.

## Authorization closure

3BZ verifies that the API is officer-only for active club roles:

- president-admin
- secretary
- treasurer

3BZ verifies that:

- Normal Member is denied.
- unauthenticated access is denied.
- cross-club access fails closed.
- backend authorization remains the source of truth.

## Request and response closure

3BZ verifies that:

- request input accepts rows.
- request input accepts rows.*.
- rows must be JSON arrays.
- rows are capped.
- backend-controlled mutation/upload/finance fields are rejected.
- response exposes dry_run and committed.
- response exposes summary.
- response exposes row_number, valid, intent, normalized, errors, and duplicate_matches.
- response exposes row-level validation errors.
- duplicate warnings remain preview-only.
- existing-member match warnings remain preview-only.

## Non-mutating closure

3BZ verifies that 3BY did not add:

- member create behavior
- member update behavior
- member delete behavior
- silent overwrite behavior
- finance ledger mutation
- payment mutation
- receipt mutation
- payment-history mutation
- dues import
- committee import
- fellowship import
- activity import
- event import
- finance import
- file upload
- xlsx parser
- composer package changes
- queue
- job
- migration
- seeder
- frontend upload UI
- deployment implementation
- SaaS implementation
- client-specific behavior

## Compatibility guard closure

3BZ verifies that older guard tests were updated only to recognize the exact 3BY preview route:

- POST api/v1/clubs/{club}/members/import-preview

Those guard updates must not authorize:

- generic import routes
- upload routes
- parser routes
- commit/import mutation routes
- member create/update/delete import behavior
- finance import behavior
- frontend upload UI
- deployment behavior
- SaaS behavior
- client-specific behavior

## Expected next milestone

The expected next milestone is recorded only as a future decision candidate:

Milestone 3CA - Member Data Import Frontend Scope Gate

3CA must be a scope-gate milestone only.

3CA must not implement frontend upload UI unless a later human approval changes the milestone type.

No next milestone is started by 3BZ.

## Validation commands

Expected validation commands:

- php artisan test --filter=Milestone3bzMemberDataImportApiValidationAndClosureTest
- php artisan test
- php -d memory_limit=512M vendor/bin/phpunit if the known wrapper cleanup issue appears after passed tests
- git status --short --branch
- git diff --stat
- git diff --check
- git diff --cached --stat
- git diff --cached --check

## Closure decision

Milestone 3BZ closes the Member Data Import API preview lane.

The next milestone candidate is:

Milestone 3CA - Member Data Import Frontend Scope Gate

No next milestone is started by 3BZ.
