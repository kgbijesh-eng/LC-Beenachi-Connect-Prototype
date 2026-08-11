# Milestone 3BS - Member Data Import / Excel Onboarding Scope Gate

## Status

Milestone 3BS is a docs/tests-only scope gate.

It does not implement Excel import.

It does not implement file upload.

It does not change frontend runtime behavior.

It does not change backend runtime behavior.

It does not change API behavior.

## Base

Base milestone:

- Milestone 3BR - Payment History Lane Closure Review and Next Roadmap Decision

Base branch:

- milestone-3br-payment-history-lane-closure-review

Scope gate branch:

- milestone-3bs-member-data-import-excel-onboarding-scope-gate

## Purpose

The purpose of 3BS is to define the future Member Data Import / Excel Onboarding lane before any import implementation begins.

Existing clubs should be able to migrate old member records through Excel upload in a future implementation.

3BS authorizes no runtime implementation.

3BS keeps the first target narrow:

- member data import only.
- no dues import.
- no payment import.
- no receipt import.
- no committee import.
- no fellowship import.
- no activity import.
- no finance ledger import.

## Allowed files

Only these files are allowed in 3BS:

- backend/docs/milestone_3bs_member_data_import_excel_onboarding_scope_gate.md
- backend/tests/Feature/Milestone3bsMemberDataImportExcelOnboardingScopeGateTest.php

## Blocked files and scope

3BS must not edit runtime files:

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

3BS must not implement Excel import.

3BS must not implement upload storage.

3BS must not implement queues or jobs.

3BS must not mutate member data.

## Final scope decision

A future Member Data Import / Excel Onboarding feature is allowed only if it remains inside this boundary:

- Future implementation starts with member data import only.
- Future import is club-scoped.
- Future import is officer-only.
- Normal Member must not upload data.
- Normal Member must not import data.
- Normal Member must not preview other members' import files.
- President/Admin, Secretary, and Treasurer may be considered for import access in a later authorization milestone.
- Backend authorization must remain the source of truth.
- Cross-club import access must fail closed.
- Future implementation must validate required columns before creating or updating records.
- Future implementation must support dry-run preview before commit.
- Future implementation must report row-level validation errors.
- Future implementation must not silently overwrite existing member data.
- Future implementation must not mutate finance ledger, payments, receipts, or payment history.

## First implementation boundary

The first implementation, if later authorized, may evaluate only member profile onboarding fields.

Future safe fields to evaluate include:

- member code
- full name
- phone
- email
- status
- joined date
- blood group
- blood donor consent
- address or locality only if explicitly authorized
- date of birth only if privacy handling is explicitly authorized
- anniversary date only if privacy handling is explicitly authorized

3BS does not decide the final required column list.

3BS does not decide that sensitive dates or address fields are safe by default.

## Required future import questions

A future implementation authorization must answer:

- Which columns are required?
- Which columns are optional?
- What exact header names are accepted?
- Is a sample template required?
- What file formats are allowed?
- What maximum row count is allowed?
- What maximum file size is allowed?
- Should import update existing members or create new members only?
- How are duplicate rows detected inside the upload?
- How are duplicates detected against existing club members?
- What happens when a row has partial invalid data?
- What data is shown during dry-run preview?
- Who may commit an import after preview?
- Is rollback required?
- Is audit logging required?

## Duplicate handling boundary

Future duplicate handling must be club-scoped.

Future duplicate matching rules may evaluate:

- member code
- phone
- email

The exact matching priority must be finalized in a later authorization milestone.

Future implementation must not match members globally across clubs.

Future implementation must not silently merge records.

Future implementation must not silently overwrite existing member records.

## Dry-run and validation boundary

Future implementation must support dry-run preview before commit.

Dry-run preview should show:

- accepted rows
- rejected rows
- row-level validation errors
- duplicate candidates
- create versus update intent if updates are later authorized
- summary counts

Commit must be a separate explicit action after preview.

Dry-run must not mutate members, finance records, files, or import state beyond temporary validation context authorized later.

## Role and club boundary

Normal Member:

- Must not upload Excel files.
- Must not preview imports.
- Must not commit imports.
- Must not see other members' import data.

President/Admin, Secretary, and Treasurer:

- May be considered for future officer-only import access.
- Must remain scoped to their active club.
- Must not import data into another club.
- Must not access another club's upload or preview.

Backend authorization remains the source of truth for all future import access.

## Blocked first-lane imports

The first future implementation must not include:

- dues import
- payment import
- receipt import
- payment history import
- finance ledger import
- opening balance import
- committee import
- board role import
- fellowship import
- activity import
- project import
- document import
- donor consent bulk override unless explicitly authorized later

## Blocked behavior

Future Member Data Import / Excel Onboarding work must not include:

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

## Privacy and safety boundary

Future import work must treat member data as sensitive.

Future import work must define privacy handling before implementation for:

- phone numbers
- email addresses
- addresses
- blood group
- blood donor consent
- date of birth
- anniversary date
- family or spouse fields, if ever considered

Future implementation must not expose raw uploaded data to unauthorized users.

Future implementation must not persist failed upload data unless explicitly authorized.

## Existing models and APIs for context only

The following may be reviewed for context only and must not be changed in 3BS:

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

3BS confirms there is no member import API implementation in this milestone.

Future member import API shape must be authorized separately before implementation.

## Expected next milestone

The expected next milestone is:

Milestone 3BT - Member Data Import / Excel Onboarding Implementation Authorization

3BT should remain docs/tests-only unless the human explicitly authorizes a different scope.

3BT may authorize a future narrow implementation only after it confirms 3BS scope boundaries.

## Validation commands

Expected validation commands:

- php artisan test --filter=Milestone3bsMemberDataImportExcelOnboardingScopeGateTest
- php artisan test
- php -d memory_limit=512M vendor/bin/phpunit if the known wrapper cleanup issue appears after passed tests
- git status --short --branch
- git diff --stat
- git diff --check
- git diff --cached --stat
- git diff --cached --check
