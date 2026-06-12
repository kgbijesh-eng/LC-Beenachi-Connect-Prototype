# Milestone 3CG - Member Data Import Commit Implementation Authorization

## Status

Milestone 3CG is a docs/tests-only implementation authorization milestone.

It authorizes only a future narrow backend commit implementation lane, if later approved.

It does not implement commit/import behavior.

It does not create a commit/import API endpoint.

It does not add frontend import, save, apply, or commit UI.

It does not add backend runtime or API changes.

It does not add frontend runtime changes.

## Purpose

The purpose of 3CG is to convert the 3CF Member Data Import Commit Scope Gate into a precise future implementation authorization boundary.

3CG does not start implementation.

3CG does not authorize executable mutation in this milestone.

3CG defines only what a later implementation milestone may introduce after another explicit approval.

## Base

Base milestone:

- Milestone 3CF - Member Data Import Commit Scope Gate

Base branch:

- milestone-3cf-member-data-import-commit-scope-gate

Authorization branch:

- milestone-3cg-member-data-import-commit-implementation-authorization

## Allowed files

Only these files are allowed in 3CG:

- backend/docs/milestone_3cg_member_data_import_commit_implementation_authorization.md
- backend/tests/Feature/Milestone3cgMemberDataImportCommitImplementationAuthorizationTest.php

Read-only inspection of existing frontend, backend, docs, and tests is allowed.

## Authorization focus

3CG authorizes only a future narrow backend commit implementation lane.

The future implementation must remain separate from the already closed preview-only module.

The future implementation must not be treated as a bulk Excel import system.

The future implementation must not add file upload or package-based spreadsheet parsing.

The future implementation must treat each row independently and report success or failure per row.

The future implementation must be safer than a blind bulk update.

The future implementation must prefer no-op or fail-closed behavior over uncertain mutation.

## Relationship to the closed preview module

The Member Data Import Preview Module was closed by Milestone 3CE.

The closed preview module remains preview/dry-run only.

Future commit behavior must not reopen or weaken the preview-only closure.

Future commit behavior must not use stale preview results blindly.

Future commit behavior must re-run server-side validation before any mutation.

Future commit behavior must re-check duplicates and existing-member matches before any mutation.

## Relationship to 3CF scope gate

3CF defined the future commit lane boundaries.

3CG authorizes only a later implementation milestone to work within those 3CF boundaries.

3CG does not implement the 3CF scope.

3CG does not authorize any executable import/save/commit action in this milestone.

## Future club-scoped commit requirement

Any future commit implementation must be club-scoped.

Future commit behavior must fail closed on cross-club access.

Future commit behavior must never create or update members outside the active authorized club.

## Future officer-only commit requirement

Any future commit implementation must be officer-only.

The only intended roles are:

- President/Admin through existing president or president-admin role flows
- Secretary
- Treasurer

Backend authorization must remain the source of truth.

## Normal Member exclusion

Normal Member must be excluded completely.

Normal Member must not access commit/import behavior.

Normal Member must not see commit/import controls.

Normal Member must not trigger commit/import API calls.

Normal Member must not load commit/import data into hidden frontend state.

Normal Member must not create, update, delete, save, commit, or import member data.

## Cross-club and unauthorized-role fail-closed requirements

Future commit behavior must fail closed on cross-club access.

Future commit behavior must fail closed for unauthorized roles.

Ambiguous club or role context must result in no mutation.

## Server-side validation requirement

Any future commit implementation must require server-side validation before mutation.

Future commit behavior must never trust frontend-only validation.

Future commit behavior must validate required member fields server-side.

Future commit behavior must validate club-scoped matching server-side.

Future commit behavior must reject unsafe or forbidden fields server-side.

## Fresh duplicate re-check requirement

Any future commit implementation must re-check duplicate rows at commit time.

Rows with unresolved duplicate warnings must remain ineligible.

Duplicate decisions must not be inferred only from an earlier preview result.

## Fresh existing-member match re-check requirement

Any future commit implementation must re-check existing-member matches at commit time.

Rows with unresolved existing-member match warnings must remain ineligible unless a later implementation authorization defines explicit matched-row handling.

Existing-member match decisions must not be inferred only from an earlier preview result.

## Row-level commit eligibility requirement

Any future commit implementation must provide row-level commit eligibility.

Rows that can be safely created or updated, if later authorized, must be identified separately from blocked rows.

Rows with validation errors must remain ineligible.

Rows with unresolved duplicate warnings must remain ineligible.

Rows with unresolved existing-member match warnings must remain ineligible unless explicitly handled by a later authorization.

## Row-level commit failure reporting requirement

Any future commit implementation must provide row-level commit failure reporting.

Failures must identify the row and reason.

Partial success behavior must require explicit later authorization before implementation.

Frontend or API summaries must not hide row-level failures.

## No silent overwrite requirement

Future commit behavior must not silently overwrite existing member data.

Any future member update behavior, if later authorized, must require explicit matched-row handling.

Existing-member matches must not automatically mutate records.

## No member deletion requirement

Future commit behavior must not delete members.

Member deletion remains blocked.

Member deactivation through import remains blocked unless a later scope gate explicitly authorizes it.

## Auditability requirement

Future commit behavior must preserve auditability for any future member create or update action, if such behavior is later authorized.

Auditability must identify who performed the action, when it happened, which club it affected, and which rows were created or updated.

Auditability requirements must be tested before implementation in later milestones.

## Input path requirement

Manual/paste rows remain the only currently authorized input path.

Future commit behavior must not introduce Excel upload, CSV upload, drag-and-drop upload, browser-side xlsx parser packages, browser-side CSV parser packages, file upload, upload storage, template download, or sample file generation without a later explicit milestone.

## Explicit non-authorization

3CG does not authorize:

- commit/import API endpoint implementation
- frontend commit button
- frontend import button
- frontend save button
- frontend apply button
- production member create action
- production member update action
- member delete action
- Excel upload
- xlsx parser package
- CSV parser package
- file upload
- upload storage
- queue/job import processing
- backend route changes in 3CG
- backend controller changes in 3CG
- backend request changes in 3CG
- backend resource changes in 3CG
- backend service changes in 3CG
- backend model changes in 3CG
- backend migration changes in 3CG
- backend config changes in 3CG
- frontend runtime changes in 3CG
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

## Queue and job processing remains blocked

Future queue or job import processing remains blocked unless separately authorized later.

3CG does not authorize queue files, job files, background import processing, retry workflow, or batch import workers.

## Backend runtime and API changes blocked in 3CG

3CG must not edit:

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
- API response contracts

## Frontend runtime changes blocked in 3CG

3CG must not edit:

- app/page.tsx
- frontend runtime files
- package.json
- package-lock.json
- pnpm-lock.yaml
- yarn.lock

## Finance and adjacent module mutation blocked

3CG keeps blocked:

- finance ledger mutation
- dues import
- payment mutation
- receipt mutation
- payment-history mutation
- balance mutation
- committee import
- fellowship import
- activity import
- event import
- export
- PDF generation
- reminder sending
- notification sending

## Deployment, SaaS, and client-specific behavior blocked

3CG keeps blocked:

- deployment implementation
- SaaS implementation
- client-specific behavior
- hardcoded Lions Club Beenachi rules
- hardcoded Rotary Central Bathery rules
- hardcoded future pilot club rules

## Expected next milestone

The expected next milestone is:

Milestone 3CH - Member Data Import Commit Backend Scope and Authorization Closure

3CH should remain docs/tests-only unless explicitly approved otherwise.

3CG must not start implementation.
