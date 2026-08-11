# Milestone 3BV - Member Data Import / Excel Onboarding Validation and Closure

## Status

Milestone 3BV is a docs/tests-only validation and closure milestone.

It validates and closes Milestone 3BU.

It does not change frontend runtime behavior.

It does not change backend runtime behavior.

It does not change API behavior.

## Base

Base milestone:

- Milestone 3BU - Member Data Import / Excel Onboarding Narrow Implementation

Base branch:

- milestone-3bu-member-data-import-excel-onboarding-narrow-implementation

Closure branch:

- milestone-3bv-member-data-import-excel-onboarding-validation-and-closure

## Purpose

The purpose of 3BV is to prove that the 3BU member data import preview foundation is safely closed.

3BV validates only what 3BU implemented:

- Pure backend preview/validation service only.
- Accepts spreadsheet-like row arrays.
- Normalizes supported member columns.
- Validates required member fields.
- Reports row-level validation errors.
- Detects duplicate rows inside the import batch.
- Detects club-scoped existing-member matches by member code, phone, or email.
- Existing-member matches are preview-only.
- No silent overwrite behavior.
- No database mutation.
- No member create/update behavior.
- No file upload.
- No `.xlsx` parser or package dependency.
- No routes/controllers/API behavior.
- No frontend upload UI.
- No dues, ledger, payment, receipt, payment-history, committee, fellowship, activity, event, or finance import.
- No queue/job/migration/package/deployment/SaaS/client-specific behavior.

## Allowed files

Only these files are allowed in 3BV:

- backend/docs/milestone_3bv_member_data_import_excel_onboarding_validation_and_closure.md
- backend/tests/Feature/Milestone3bvMemberDataImportExcelOnboardingValidationAndClosureTest.php

## Blocked files and scope

3BV must not edit:

- app/page.tsx
- frontend runtime files
- backend/app/Services/Members/MemberDataImportPreviewService.php
- backend routes
- backend controllers
- backend requests
- backend resources
- backend models
- backend middleware
- backend migrations
- backend seeders
- backend config
- composer.json
- composer.lock
- API behavior
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

3BV verifies that 3BU added:

- App\Services\Members\MemberDataImportPreviewService
- preview(int $clubId, array $rows): array

3BV verifies that the service remains a deterministic dry-run preview foundation.

The service accepts spreadsheet-like member rows as arrays.

The service returns dry_run true.

The service returns committed false.

The service returns a summary and row-level preview results.

## Column normalization closure

3BV verifies that supported column headings normalize into:

- member_code
- full_name
- phone
- email
- status
- joined_at
- blood_group
- blood_donor_consent

3BV verifies that unsupported finance, payment, ledger, receipt, and import-control columns are not promoted into the normalized member payload.

## Validation closure

3BV verifies that the service validates required member fields.

The required member fields are:

- member_code
- full_name

3BV verifies that row-level errors are returned for invalid rows.

3BV verifies that invalid rows are marked reject.

## Duplicate and existing-member closure

3BV verifies that duplicate rows inside the import batch are detected using:

- member_code
- phone
- email

3BV verifies that possible existing-member matches are detected only inside the same club using:

- member_code
- phone
- email

Existing-member matches are preview-only.

Existing-member matches do not automatically update records.

Existing-member matches do not silently overwrite records.

Existing-member matching is club-scoped and does not match globally across clubs.

## Non-mutating closure

3BV verifies that 3BU did not add:

- database mutation
- member create behavior
- member update behavior
- member delete behavior
- import commit behavior
- file upload
- xlsx parsing
- composer package dependency
- route
- controller
- request class
- resource class
- model
- migration
- seeder
- queue
- job
- frontend upload UI
- dues import
- ledger import
- payment import
- receipt import
- payment-history import
- committee import
- fellowship import
- activity import
- event import
- finance import
- export
- PDF
- reminder
- deployment implementation
- SaaS implementation
- client-specific hardcoding

## Backend source of truth

Backend preview validation remains the source of truth for:

- normalized member fields
- required fields
- row-level errors
- duplicate detection
- club-scoped existing matches
- dry-run preview result

Future API or frontend implementation may use the preview service only after separate authorization.

## Expected next milestone

The expected next milestone is recorded only as a future decision candidate:

Milestone 3BW - Member Data Import API Scope Gate

3BW should be a scope-gate milestone only.

3BW must not implement routes or controllers unless a later human approval changes the milestone type.

No next milestone is started by 3BV.

## Validation commands

Expected validation commands:

- php artisan test --filter=Milestone3bvMemberDataImportExcelOnboardingValidationAndClosureTest
- php artisan test
- php -d memory_limit=512M vendor/bin/phpunit if the known wrapper cleanup issue appears after passed tests
- git status --short --branch
- git diff --stat
- git diff --check
- git diff --cached --stat
- git diff --cached --check

## Closure decision

Milestone 3BV closes the Member Data Import / Excel Onboarding preview foundation lane.

The next milestone candidate is:

Milestone 3BW - Member Data Import API Scope Gate

No next milestone is started by 3BV.
