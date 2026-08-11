# Milestone 3BU - Member Data Import / Excel Onboarding Narrow Implementation

## Status

Milestone 3BU is a narrow backend implementation milestone.

It implements a pure member data import preview and validation foundation service.

It does not implement Excel file upload.

It does not implement xlsx parsing.

It does not implement API routes or controllers.

It does not implement frontend upload UI.

It does not mutate database records.

## Base

Base milestone:

- Milestone 3BT - Member Data Import / Excel Onboarding Implementation Authorization

Base branch:

- milestone-3bt-member-data-import-excel-onboarding-implementation-authorization

Implementation branch:

- milestone-3bu-member-data-import-excel-onboarding-narrow-implementation

## Purpose

The purpose of 3BU is to add a deterministic backend-only preview foundation for future member data import.

The implementation accepts spreadsheet-like rows as arrays, normalizes supported column names, validates member data, detects duplicate rows inside the batch, detects possible existing-member matches inside the same club, and returns a dry-run preview result.

3BU does not create, update, delete, import, upload, parse files, queue jobs, or persist import state.

## Allowed files

Only these files are allowed in 3BU:

- backend/app/Services/Members/MemberDataImportPreviewService.php
- backend/docs/milestone_3bu_member_data_import_excel_onboarding_narrow_implementation.md
- backend/tests/Feature/Milestone3buMemberDataImportExcelOnboardingNarrowImplementationTest.php

## Blocked files and scope

3BU must not edit:

- app/page.tsx
- frontend runtime files
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

## Implemented service

3BU adds:

- App\Services\Members\MemberDataImportPreviewService

The service exposes:

- preview(int $clubId, array $rows): array

The input is spreadsheet-like rows represented as PHP arrays.

The output is a deterministic dry-run result with:

- dry_run
- committed
- summary
- rows
- row_number
- valid
- intent
- normalized
- errors
- duplicate_matches

## Supported column normalization

The service normalizes supported column headings for:

- member_code
- full_name
- phone
- email
- status
- joined_at
- blood_group
- blood_donor_consent

Common heading variants such as Member Code, Code, Full Name, Member Name, Mobile, Phone Number, Joined Date, Blood Group, and Donor Consent may map into these internal fields.

Unsupported columns are ignored by the preview foundation.

## Validation behavior

The service validates:

- member code is required.
- full name is required.
- phone must be a plausible string length when present.
- email must be a valid email when present.
- status must be active or inactive when present.
- joined date must be a valid date when present.
- blood donor consent must be boolean-compatible when present.

The service returns row-level validation errors.

Invalid rows are marked with intent reject.

Valid rows with no existing match are marked with intent create_preview.

Valid rows with possible existing matches are marked with intent matched_preview.

## Duplicate detection behavior

The service detects duplicate rows inside the import batch using:

- member_code
- phone
- email

Batch duplicates are row-level validation errors.

The service detects possible existing-member matches inside the same club using:

- member_code
- phone
- email

Existing-member matches are preview information only.

Existing-member matches do not update records.

Existing-member matches do not silently overwrite records.

Existing-member matching is club-scoped.

The service does not match members globally across clubs.

## Non-mutating dry-run behavior

3BU is dry-run only.

The service returns committed false.

The service does not create members.

The service does not update members.

The service does not delete members.

The service does not create import state.

The service does not store uploaded files.

The service does not create finance ledger, payment, receipt, or payment history records.

## Explicitly blocked behavior

3BU does not add:

- Excel upload
- xlsx parser integration
- composer package changes
- API route
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
- finance import
- committee import
- fellowship import
- activity import
- event import
- export
- PDF
- reminder
- deployment implementation
- SaaS implementation
- client-specific hardcoding

## Backend source of truth

Backend validation remains the source of truth for:

- normalized fields
- required fields
- row-level errors
- duplicate detection
- club-scoped existing matches
- dry-run preview result

Future API or frontend code may display preview results only after separate authorization.

## Expected next milestone

The expected next milestone is:

Milestone 3BV - Member Data Import / Excel Onboarding Validation and Closure

3BV should validate and close only what 3BU implemented.

## Validation commands

Expected validation commands:

- php artisan test --filter=Milestone3buMemberDataImportExcelOnboardingNarrowImplementationTest
- php artisan test
- php -d memory_limit=512M vendor/bin/phpunit if the known wrapper cleanup issue appears after passed tests
- git status --short --branch
- git diff --stat
- git diff --check
- git diff --cached --stat
- git diff --cached --check
