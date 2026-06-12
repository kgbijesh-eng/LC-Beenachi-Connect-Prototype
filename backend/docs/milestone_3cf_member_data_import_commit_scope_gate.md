# Milestone 3CF - Member Data Import Commit Scope Gate

## Status

Milestone 3CF is a docs/tests-only scope gate for a possible future member data import commit lane.

It does not implement commit behavior.

It does not authorize executable mutation.

It does not add frontend runtime changes.

It does not add backend runtime or API changes.

## Purpose

The purpose of 3CF is to define the boundaries that a later authorization milestone may use if the project chooses to pursue member data import commit behavior.

3CF opens only a scope discussion for future commit behavior.

3CF must not start implementation.

## Base

Base milestone:

- Milestone 3CE - Member Data Import Preview Module Closure Review

Base branch:

- milestone-3ce-member-data-import-preview-module-closure-review

Scope gate branch:

- milestone-3cf-member-data-import-commit-scope-gate

## Allowed files

Only these files are allowed in 3CF:

- backend/docs/milestone_3cf_member_data_import_commit_scope_gate.md
- backend/tests/Feature/Milestone3cfMemberDataImportCommitScopeGateTest.php

Read-only inspection of existing frontend, backend, docs, and tests is allowed.

## Scope gate focus

3CF defines the future member data import commit lane only.

3CF keeps the completed preview module separate from any future commit lane.

3CE closed preview-only behavior.

The closed preview module remains a club-scoped, officer-only, preview/dry-run-only module using manual/paste spreadsheet-like rows and the existing preview endpoint.

3CF does not reopen the preview module.

3CF does not authorize import save or commit behavior.

3CF does not authorize member create, update, or delete behavior.

## Relationship to the closed preview module

The completed preview module is closed as preview/dry-run only.

Any future commit/import lane must remain separate from the completed preview-only module.

Future commit behavior must not assume that a previous preview result is still safe.

Future commit behavior must not use stale preview results blindly.

Future commit behavior must re-run server-side validation before any mutation.

Future commit behavior must re-check duplicates and existing-member matches before any mutation.

## Future club-scoped commit requirement

If later authorized, future commit behavior must be club-scoped.

Future commit behavior must fail closed on cross-club access.

Future commit behavior must never create or update members outside the active authorized club.

## Future officer-only commit requirement

If later authorized, future commit behavior must be officer-only.

The only intended roles are:

- President/Admin through existing president or president-admin role flows
- Secretary
- Treasurer

Future commit behavior must fail closed for unauthorized roles.

Backend authorization must remain the source of truth.

## Normal Member exclusion

Normal Member must not access commit/import behavior.

Normal Member must not see commit/import controls.

Normal Member must not trigger commit/import API calls.

Normal Member must not load commit/import data into hidden frontend state.

Normal Member must not create, update, delete, save, commit, or import member data.

## Server-side validation requirement

Any future commit lane must require server-side validation before any mutation.

Future commit behavior must not trust frontend-only validation.

Future commit behavior must validate required member fields server-side.

Future commit behavior must validate club-scoped matching server-side.

Future commit behavior must reject unsafe or forbidden fields server-side.

## Fresh duplicate and existing-member match re-check requirement

Any future commit lane must re-check duplicate rows before commit.

Any future commit lane must re-check existing-member matches before commit.

Future commit behavior must not use stale preview results blindly.

Future commit behavior must provide a clear result when a row no longer matches its preview eligibility.

## Row-level commit eligibility requirement

Any future commit lane must provide row-level commit eligibility.

Rows that can be safely created or updated, if later authorized, must be identified separately from blocked rows.

Rows with validation errors must remain ineligible.

Rows with unresolved duplicate warnings must remain ineligible.

Rows with unresolved existing-member match warnings must remain ineligible unless a later authorization milestone defines explicit matched-row handling.

## Row-level commit failure reporting requirement

Any future commit lane must provide row-level failure reporting.

Failures must identify the row and reason.

Partial success behavior must require later explicit authorization before implementation.

Frontend summaries must not hide row-level failures.

## No silent overwrite requirement

Future commit behavior must not silently overwrite existing member data.

Any future member update behavior, if later authorized, must require explicit matched-row handling.

Existing-member matches must not automatically mutate records.

## No member deletion requirement

Future commit behavior must not delete members.

Member deletion remains blocked.

Member deactivation through import remains blocked unless a later scope gate explicitly authorizes it.

## No finance or adjacent module mutation requirement

Future commit behavior must not mutate:

- finance ledger
- dues
- payments
- receipts
- payment history
- balances
- committee data
- fellowship data
- activity data
- event data
- export files
- PDF files
- reminders
- notifications

Member import commit must remain member-data only unless a later scope gate explicitly changes that boundary.

## Input path requirement

Manual/paste rows remain the only currently scoped input path.

Future commit behavior must not introduce Excel upload, CSV upload, drag-and-drop upload, browser-side xlsx parser packages, browser-side CSV parser packages, file upload, upload storage, template download, or sample file generation without a later explicit milestone.

## Auditability requirement

Future commit behavior must preserve auditability for any future member create or update action, if such behavior is later authorized.

Auditability must identify who performed the action, when it happened, which club it affected, and which rows were created or updated.

Auditability requirements must be tested before implementation in later milestones.

## Explicit non-authorization

3CF does not authorize:

- commit/import API endpoint
- commit/import frontend button
- save action
- apply action
- commit action
- import action
- member creation
- member update
- member deletion
- Excel upload
- xlsx parser package
- CSV parser package
- upload storage
- queue/job import processing
- backend route changes
- backend controller changes
- backend request changes
- backend resource changes
- backend service changes
- backend model changes
- backend migration changes
- backend config changes
- frontend runtime changes
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

## Backend runtime and API changes blocked in 3CF

3CF must not edit:

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

## Frontend runtime changes blocked in 3CF

3CF must not edit:

- app/page.tsx
- frontend runtime files
- package.json
- package-lock.json
- pnpm-lock.yaml
- yarn.lock

## Deployment, SaaS, and client-specific behavior blocked

3CF keeps blocked:

- deployment implementation
- SaaS implementation
- client-specific behavior
- hardcoded Lions Club Beenachi rules
- hardcoded Rotary Central Bathery rules
- hardcoded future pilot club rules

## Expected next milestone

The expected next milestone is:

Milestone 3CG - Member Data Import Commit Implementation Authorization

3CG may only authorize a later implementation.

3CF does not start implementation.
