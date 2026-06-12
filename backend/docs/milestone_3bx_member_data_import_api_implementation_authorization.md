# Milestone 3BX - Member Data Import API Implementation Authorization

## Status

Milestone 3BX is a docs/tests-only implementation authorization milestone.

It authorizes a future narrow API implementation in Milestone 3BY.

It does not implement a member import API.

It does not add routes.

It does not add controllers.

It does not add requests or resources.

It does not implement file upload.

It does not implement xlsx parsing.

It does not change frontend runtime behavior.

It does not change backend runtime behavior.

It does not change API behavior.

## Base

Base milestone:

- Milestone 3BW - Member Data Import API Scope Gate

Base branch:

- milestone-3bw-member-data-import-api-scope-gate

Authorization branch:

- milestone-3bx-member-data-import-api-implementation-authorization

## Purpose

The purpose of 3BX is to convert the 3BW scope gate into exact future authorization for Milestone 3BY.

3BX authorizes only a future narrow member import preview API implementation that remains inside the 3BW boundary.

3BX does not authorize commit/import mutation, member create/update/delete behavior, file upload, xlsx parsing, frontend upload UI, finance import, deployment, SaaS, or client-specific behavior.

## Allowed files

Only these files are allowed in 3BX:

- backend/docs/milestone_3bx_member_data_import_api_implementation_authorization.md
- backend/tests/Feature/Milestone3bxMemberDataImportApiImplementationAuthorizationTest.php

## Blocked files and scope

3BX must not edit:

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
- backend/app/Services/Members/MemberDataImportPreviewService.php
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

3BX must not add routes.

3BX must not add controllers.

3BX must not add request classes.

3BX must not add resource classes.

3BX must not change the 3BU preview service.

3BX must not implement file upload.

3BX must not implement Excel parsing.

3BX must not mutate member data.

## Authorized future 3BY implementation

Milestone 3BY may implement only a narrow backend preview API for member data import.

Future 3BY may expose the existing 3BU preview service through a controlled preview endpoint.

Future 3BY must remain preview/dry-run only.

Future 3BY must not add a commit endpoint.

Future 3BY must not add an import mutation endpoint.

Future 3BY must not create members.

Future 3BY must not update members.

Future 3BY must not delete members.

Future 3BY must not implement file upload.

Future 3BY must not add an xlsx parser or package.

Future 3BY must be club-scoped only.

Future 3BY must be officer-only.

Normal Member must not access the import preview API.

Backend authorization must remain the source of truth.

Cross-club import preview access must fail closed.

## Future 3BY implementation candidates

Future 3BY may add only the minimum backend API surface required for JSON-row preview:

- one controlled preview route
- one controller action for preview
- request validation only if explicitly included in the approved 3BY file boundary
- response resource only if explicitly included in the approved 3BY file boundary
- focused implementation documentation
- focused backend tests

Future 3BY must stop and request approval before adding:

- file upload handling
- storage persistence
- xlsx parser integration
- composer package changes
- import commit endpoint
- member create/update/delete import behavior
- model changes
- migrations
- seeders
- queues
- jobs
- frontend upload UI
- finance import behavior

Those files and behaviors are not pre-authorized by 3BX unless a later 3BY approval explicitly names them.

## Role and club authorization boundary

Future implementation must preserve this access boundary:

- Normal Member must not access import preview APIs.
- Normal Member must not preview imports.
- Normal Member must not upload data.
- Normal Member must not commit imports.
- Normal Member must not see other members' import data.
- President/Admin, Secretary, and Treasurer may be considered for officer-only import preview API access.
- Officers must remain scoped to their active club.
- Officers must not preview imports for another club.
- Cross-club import preview access must fail closed.

Backend authorization remains the source of truth.

## Future preview behavior boundary

Future implementation must:

- accept spreadsheet-like rows in JSON only.
- call the existing MemberDataImportPreviewService preview behavior.
- return a dry-run preview result.
- return row-level validation errors.
- return duplicate warnings as preview-only information.
- return existing-member match warnings as preview-only information.
- keep existing-member matches preview-only.
- avoid silent overwrites.
- avoid silent merges.
- avoid global cross-club duplicate matching.
- use club-scoped member code, phone, or email matching as preview information only.

Future implementation must not:

- silently overwrite existing member data.
- mutate existing members.
- create members.
- update members.
- delete members.
- persist import state.
- store uploaded files.
- parse xlsx files.
- expose raw uploaded data to unauthorized users.

## Future response boundary

Future 3BY may expose only preview-safe response data:

- dry_run
- committed
- summary
- row number
- row validity
- normalized member fields allowed by 3BU
- row-level validation errors
- duplicate row warnings
- club-scoped existing-member match warnings
- create_preview, matched_preview, or reject intent

Future 3BY must not expose:

- hidden columns
- finance fields
- ledger fields
- payment fields
- receipt fields
- payment-history fields
- internal cancellation fields
- internal reversal fields
- unrelated member private data
- global cross-club member matches

## Explicitly blocked imports

Future 3BY must not include:

- dues import
- ledger import
- payment import
- receipt import
- payment-history import
- finance import
- committee import
- board role import
- fellowship import
- activity import
- event import
- project import
- document import
- donor consent bulk override unless explicitly authorized later

## Explicitly blocked behavior

Future 3BY must not add:

- commit/import mutation endpoint
- member create endpoint for import
- member update endpoint for import
- member delete endpoint for import
- file upload implementation
- file storage implementation
- xlsx parser integration
- composer package changes
- queue implementation
- job implementation
- migration
- seeder
- frontend upload UI
- payment mutation
- receipt mutation
- finance ledger mutation
- payment history mutation
- dues creation
- balance calculation
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

## Backend source of truth

Backend validation and authorization must remain the source of truth for:

- club scope
- role access
- preview endpoint authorization
- row payload validation
- required member fields
- duplicate detection
- row-level validation
- dry-run result
- existing-member match warnings

Frontend code may not infer import permissions or commit behavior unless separately authorized.

## Expected validation for 3BY

Milestone 3BY should include focused validation proving:

- the member import preview API is present only if authorized by 3BX.
- the preview API is club-scoped.
- the preview API is officer-only.
- Normal Member cannot access the preview API.
- cross-club preview access fails closed.
- row-level validation errors are returned safely.
- duplicate and existing-member match warnings remain preview-only.
- no commit/import mutation endpoint is added.
- no member create/update/delete behavior is added.
- no file upload or xlsx parser is added.
- no finance, payment, receipt, ledger, or payment-history records are created.
- no frontend upload UI is added.
- no export, PDF, reminder, deployment, SaaS, or client-specific behavior is added.

## Validation commands

Expected validation commands:

- php artisan test --filter=Milestone3bxMemberDataImportApiImplementationAuthorizationTest
- php artisan test
- php -d memory_limit=512M vendor/bin/phpunit if the known wrapper cleanup issue appears after passed tests
- git status --short --branch
- git diff --stat
- git diff --check
- git diff --cached --stat
- git diff --cached --check

## Next milestone

The expected next milestone is:

Milestone 3BY - Member Data Import API Narrow Implementation

3BY may implement only the narrow member import preview API authorized by this document.
