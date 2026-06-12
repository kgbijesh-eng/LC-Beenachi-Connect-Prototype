# Milestone 3BY - Member Data Import API Narrow Implementation

## Status

Milestone 3BY is a narrow backend API implementation milestone.

It exposes the existing 3BU member data import preview service through a controlled preview endpoint.

It is preview/dry-run only.

It does not add a commit endpoint.

It does not create, update, or delete members.

It does not implement file upload.

It does not implement xlsx parsing.

It does not change frontend runtime behavior.

## Base

Base milestone:

- Milestone 3BX - Member Data Import API Implementation Authorization

Base branch:

- milestone-3bx-member-data-import-api-implementation-authorization

Implementation branch:

- milestone-3by-member-data-import-api-narrow-implementation

## Purpose

The purpose of 3BY is to add the smallest backend API surface needed to preview spreadsheet-like member import rows.

The endpoint accepts JSON rows, validates request shape and officer access, calls the existing MemberDataImportPreviewService, and returns a safe dry-run preview result.

Backend remains the source of truth for authorization, row validation, duplicate detection, existing-member matches, and preview response shape.

## Allowed files

Only these files are allowed in 3BY:

- backend/app/Http/Controllers/Api/V1/Members/MemberDataImportPreviewController.php
- backend/app/Http/Requests/Members/MemberDataImportPreviewRequest.php
- backend/app/Http/Resources/Members/MemberDataImportPreviewResource.php
- backend/routes/api.php
- backend/docs/milestone_3by_member_data_import_api_narrow_implementation.md
- backend/tests/Feature/Milestone3byMemberDataImportApiNarrowImplementationTest.php

## Blocked files and scope

3BY must not edit:

- app/page.tsx
- frontend runtime files
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

## Implemented API endpoint

3BY adds exactly one member import preview route:

- POST /api/v1/clubs/{club}/members/import-preview

The route is authenticated.

The route is scoped to the active club.

The route is preview-only.

The route does not commit imports.

The route does not create, update, delete, merge, or overwrite members.

The route does not upload files.

The route does not parse xlsx files.

## Controller

3BY adds:

- App\Http\Controllers\Api\V1\Members\MemberDataImportPreviewController

The controller exposes:

- store(MemberDataImportPreviewRequest $request, Club $club): JsonResponse

The controller calls:

- MemberDataImportPreviewService::preview

The controller returns:

- import_preview

The controller does not contain import commit behavior.

The controller does not mutate member data.

The controller does not touch finance records.

## Request validation and authorization

3BY adds:

- App\Http\Requests\Members\MemberDataImportPreviewRequest

The request authorizes only active club officers with these role slugs:

- president-admin
- secretary
- treasurer

Normal Member must not access the import preview API.

Cross-club access fails closed through existing club permission middleware and the active club role check.

The request accepts:

- rows
- rows.*

The request requires rows to be an array.

The request requires each row to be an array.

The request caps rows at 500.

The request prohibits backend-controlled or mutation-like inputs:

- commit
- import
- dry_run
- file
- upload
- xlsx
- club_id
- created_by_user_id
- finance
- payments
- receipts
- ledger

## Resource response

3BY adds:

- App\Http\Resources\Members\MemberDataImportPreviewResource

The resource exposes preview-safe data only:

- dry_run
- committed
- summary
- row_number
- valid
- intent
- normalized
- errors
- duplicate_matches.batch
- duplicate_matches.existing

The response exposes row-level validation errors.

The response exposes duplicate warnings as preview-only information.

The response exposes existing-member match warnings as preview-only information.

Existing-member matches are preview-only and do not overwrite data.

## Explicitly blocked behavior

3BY does not add:

- commit/import mutation endpoint
- member create behavior
- member update behavior
- member delete behavior
- silent overwrite behavior
- file upload
- file storage
- xlsx parser
- composer package changes
- queue
- job
- model change
- migration
- seeder
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
- client-specific behavior

## Non-mutating closure

3BY must not mutate:

- members
- member ledger entries
- payments
- receipts
- payment history
- finance records

3BY must not silently overwrite existing member data.

3BY must not import dues, committee, fellowship, activity, event, or finance data.

## Expected next milestone

The expected next milestone is:

Milestone 3BZ - Member Data Import API Validation and Closure

3BZ should validate and close only what 3BY implemented.

## Validation commands

Expected validation commands:

- php artisan test --filter=Milestone3byMemberDataImportApiNarrowImplementationTest
- php artisan test
- php -d memory_limit=512M vendor/bin/phpunit if the known wrapper cleanup issue appears after passed tests
- git status --short --branch
- git diff --stat
- git diff --check
- git diff --cached --stat
- git diff --cached --check
