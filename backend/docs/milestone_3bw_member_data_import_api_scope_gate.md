# Milestone 3BW - Member Data Import API Scope Gate

## Status

Milestone 3BW is a docs/tests-only scope gate.

It does not implement a member import API.

It does not add routes.

It does not add controllers.

It does not add requests or resources.

It does not change frontend runtime behavior.

It does not change backend runtime behavior.

It does not change API behavior.

## Base

Base milestone:

- Milestone 3BV - Member Data Import / Excel Onboarding Validation and Closure

Base branch:

- milestone-3bv-member-data-import-excel-onboarding-validation-and-closure

Scope gate branch:

- milestone-3bw-member-data-import-api-scope-gate

## Purpose

The purpose of 3BW is to define the future Member Data Import API lane before any route or controller implementation begins.

3BW allows a future API lane to expose the existing 3BU preview service only through a controlled dry-run endpoint if later authorized.

3BW authorizes no runtime implementation.

The future API boundary must remain preview/dry-run first.

## Allowed files

Only these files are allowed in 3BW:

- backend/docs/milestone_3bw_member_data_import_api_scope_gate.md
- backend/tests/Feature/Milestone3bwMemberDataImportApiScopeGateTest.php

## Blocked files and scope

3BW must not edit:

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

3BW must not add routes.

3BW must not add controllers.

3BW must not add request classes.

3BW must not add resource classes.

3BW must not change the 3BU preview service.

3BW must not implement file upload.

3BW must not implement Excel parsing.

3BW must not mutate member data.

## Final scope decision

A future Member Data Import API feature is allowed only if it remains inside this boundary:

- Future API may expose the existing 3BU preview service through a controlled endpoint.
- Scope must remain preview/dry-run first.
- No commit endpoint is authorized yet.
- No import mutation endpoint is authorized yet.
- No member create behavior is authorized yet.
- No member update behavior is authorized yet.
- No member delete behavior is authorized yet.
- No actual file upload is authorized yet unless separately scoped.
- No xlsx parser or package is authorized yet.
- API must be club-scoped.
- API must be officer-only.
- Normal Member must not access import preview APIs.
- Backend authorization must remain the source of truth.
- Cross-club import preview access must fail closed.
- API must return row-level validation errors.
- API must return duplicate warnings as preview-only information.
- API must return existing-member match warnings as preview-only information.
- API must not silently overwrite existing member data.
- API must not mutate finance ledger, payments, receipts, or payment history.
- API must not import dues, committee, fellowship, activity, event, or finance data.
- No frontend upload UI is authorized yet.
- No deployment, SaaS, or client-specific behavior is authorized.

## Future API shape questions

A future implementation authorization must decide:

- What exact preview route URI is allowed?
- What request payload shape is allowed?
- What maximum row count is allowed?
- What maximum payload size is allowed?
- Which officer roles may access the preview endpoint?
- Whether President/Admin, Secretary, and Treasurer all receive the same preview capability.
- Whether request validation should live in a dedicated request class.
- Whether the response should use a dedicated resource class.
- How validation errors are represented.
- How duplicate and existing-match warnings are represented.
- How unauthorized and cross-club access fail closed.
- Whether a sample template endpoint is needed later.
- Whether upload and parser support should remain a separate later lane.

## Future preview API behavior

If later authorized, the first API implementation may accept spreadsheet-like rows in JSON.

The first API implementation may call:

- App\Services\Members\MemberDataImportPreviewService::preview

The first API implementation must return a dry-run preview result.

The first API implementation must not persist import state.

The first API implementation must not store raw uploaded files.

The first API implementation must not parse xlsx files.

The first API implementation must not create, update, delete, merge, or overwrite member records.

The first API implementation must not touch finance records.

## Role and club boundary

Normal Member:

- Must not access member import preview APIs.
- Must not preview imports.
- Must not upload data.
- Must not commit imports.
- Must not see other members' import data.

President/Admin, Secretary, and Treasurer:

- May be considered for future officer-only import preview API access.
- Must remain scoped to their active club.
- Must not preview imports for another club.
- Must not commit imports in the preview-only lane.

Backend authorization remains the source of truth for all future import API access.

## Response safety boundary

Future preview API responses may expose only preview-safe data:

- row number
- row validity
- normalized member fields allowed by 3BU
- row-level validation errors
- duplicate row warnings
- club-scoped existing-member match warnings
- dry-run summary counts
- create_preview, matched_preview, or reject intent

Future preview API responses must not expose:

- raw uploaded file contents beyond the submitted row preview
- hidden columns
- finance fields
- ledger fields
- payment fields
- receipt fields
- payment-history fields
- internal cancellation or reversal fields
- unrelated member private data
- global cross-club member matches

## Blocked behavior

Future Member Data Import API work must not include:

- commit/import mutation endpoint
- member create endpoint for import
- member update endpoint for import
- member delete endpoint for import
- silent overwrite behavior
- file upload implementation
- file storage implementation
- xlsx parser integration
- composer package changes
- queue implementation
- job implementation
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
- hardcoded Lions Club Beenachi rules
- hardcoded Rotary Central Bathery rules
- hardcoded future pilot club rules

## Existing implementation for context only

The following may be reviewed for context only and must not be changed in 3BW:

- MemberDataImportPreviewService
- Member
- Club
- ClubMembership
- Role
- User
- auth/me
- club/me
- member list
- member create
- member update

3BW confirms there is no member import API implementation in this milestone.

Future member import API implementation must be authorized separately before implementation.

## Expected next milestone

The expected next milestone is:

Milestone 3BX - Member Data Import API Implementation Authorization

3BX should remain docs/tests-only unless the human explicitly authorizes a different scope.

3BX may authorize a future narrow preview API implementation only after it confirms 3BW scope boundaries.

## Validation commands

Expected validation commands:

- php artisan test --filter=Milestone3bwMemberDataImportApiScopeGateTest
- php artisan test
- php -d memory_limit=512M vendor/bin/phpunit if the known wrapper cleanup issue appears after passed tests
- git status --short --branch
- git diff --stat
- git diff --check
- git diff --cached --stat
- git diff --cached --check
