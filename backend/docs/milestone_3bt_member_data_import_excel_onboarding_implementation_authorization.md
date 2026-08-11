# Milestone 3BT - Member Data Import / Excel Onboarding Implementation Authorization

## Status

Milestone 3BT is a docs/tests-only implementation authorization milestone.

It authorizes a future narrow implementation in Milestone 3BU.

It does not implement Excel import.

It does not implement file upload.

It does not implement frontend upload UI.

It does not change frontend runtime behavior.

It does not change backend runtime behavior.

It does not change API behavior.

## Base

Base milestone:

- Milestone 3BS - Member Data Import / Excel Onboarding Scope Gate

Base branch:

- milestone-3bs-member-data-import-excel-onboarding-scope-gate

Authorization branch:

- milestone-3bt-member-data-import-excel-onboarding-implementation-authorization

## Purpose

The purpose of 3BT is to convert the 3BS scope gate into exact future authorization for Milestone 3BU.

3BT authorizes only a future narrow member data import foundation that remains inside the 3BS boundary.

3BT does not authorize dues, ledger, payment, receipt, payment-history, committee, fellowship, activity, event, finance, deployment, SaaS, or client-specific import behavior.

## Allowed files

Only these files are allowed in 3BT:

- backend/docs/milestone_3bt_member_data_import_excel_onboarding_implementation_authorization.md
- backend/tests/Feature/Milestone3btMemberDataImportExcelOnboardingImplementationAuthorizationTest.php

## Blocked files and scope

3BT must not edit runtime files:

- app/page.tsx
- frontend runtime files
- backend routes
- backend controllers
- backend services
- backend resources
- backend requests
- backend models
- backend middleware
- backend migrations
- backend seeders
- backend config
- API behavior
- member import implementation files
- Excel parsing files
- Excel import files
- file upload files
- file storage files
- queue files
- job files
- finance files
- payment files
- receipt files
- payment-history files
- export files
- PDF files
- reminder files
- deployment files
- SaaS files
- client-specific hardcoding files

3BT must not implement Excel import.

3BT must not implement upload storage.

3BT must not implement queues or jobs.

3BT must not mutate member data.

## Authorized future 3BU implementation

Milestone 3BU may implement only a narrow backend member data import foundation.

Future 3BU may add backend import foundation code only if all of these remain true:

- It is member-data import only.
- It is club-scoped.
- It is officer-only.
- It uses backend authorization as the source of truth.
- It supports dry-run or preview before commit.
- It provides row-level validation errors.
- It avoids silent overwrites.
- It does not mutate existing members without explicit matched-row handling.
- It does not create finance ledger records.
- It does not create payment records.
- It does not create receipt records.
- It does not create payment history records.
- It remains narrow and local/backend-only unless separately authorized.

3BT does not authorize frontend upload UI.

3BT does not authorize deployment, SaaS, or client-specific import behavior.

## Future 3BU implementation candidates

Future 3BU may evaluate backend-only foundation pieces such as:

- import parser or preview service
- row validation helpers
- club-scoped duplicate detection helpers
- dry-run result structure
- implementation documentation
- focused backend tests

Future 3BU must stop and request approval before adding:

- routes
- controllers
- requests
- resources
- models
- migrations
- queues
- jobs
- storage persistence
- frontend UI

Those files are not pre-authorized by 3BT unless a later 3BU approval explicitly names them.

## Role and club authorization boundary

Future implementation must preserve this access boundary:

- Normal Member must not upload imports.
- Normal Member must not preview imports.
- Normal Member must not validate imports.
- Normal Member must not commit imports.
- Normal Member must not see other members' import data.
- President/Admin, Secretary, and Treasurer may be considered for future officer-only import access.
- Officers must remain scoped to their active club.
- Cross-club import access must fail closed.

Backend authorization remains the source of truth.

## Future import behavior boundary

Future implementation must:

- validate required columns before creating or updating records.
- report row-level validation errors.
- support dry-run or preview before commit.
- keep commit as a separate explicit action after preview.
- avoid silent overwrites.
- avoid silent merges.
- avoid global cross-club duplicate matching.
- use club-scoped member code, phone, or email matching rules only after they are finalized.

Future implementation must not:

- silently overwrite existing member data.
- mutate existing members without explicit matched-row handling.
- persist failed upload data unless explicitly authorized.
- expose raw uploaded data to unauthorized users.

## Future member fields boundary

Future 3BU may evaluate only member profile onboarding fields authorized by 3BS.

Candidate fields include:

- member code
- full name
- phone
- email
- status
- joined date
- blood group
- blood donor consent

Future 3BU must not treat sensitive optional fields as safe by default.

Sensitive fields requiring explicit later authorization include:

- address
- locality
- date of birth
- anniversary date
- family or spouse fields

## Explicitly blocked imports

Future 3BU must not include:

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

Future 3BU must not add:

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
- required columns
- duplicate detection
- row-level validation
- dry-run result
- create versus matched-row handling
- commit authorization if commit is later authorized

Frontend code may not infer import permissions or commit behavior unless separately authorized.

## Expected validation for 3BU

Milestone 3BU should include focused validation proving:

- member-only import foundation is present only if authorized by 3BT.
- dry-run or preview can validate rows without committing member data.
- row-level errors are reported safely.
- duplicate detection is club-scoped.
- Normal Member cannot access import behavior if routes are later authorized.
- officer access remains club-scoped if routes are later authorized.
- no finance, payment, receipt, ledger, or payment-history records are created.
- no frontend upload UI is added.
- no export, PDF, reminder, deployment, SaaS, or client-specific behavior is added.

## Validation commands

Expected validation commands:

- php artisan test --filter=Milestone3btMemberDataImportExcelOnboardingImplementationAuthorizationTest
- php artisan test
- php -d memory_limit=512M vendor/bin/phpunit if the known wrapper cleanup issue appears after passed tests
- git status --short --branch
- git diff --stat
- git diff --check
- git diff --cached --stat
- git diff --cached --check

## Next milestone

The expected next milestone is:

Milestone 3BU - Member Data Import / Excel Onboarding Narrow Implementation

3BU may implement only the narrow member data import foundation authorized by this document.
