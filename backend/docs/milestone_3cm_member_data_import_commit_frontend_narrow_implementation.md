# Milestone 3CM - Member Data Import Commit Frontend Narrow Implementation

## Status

Milestone 3CM is a narrow frontend implementation milestone.

It connects the existing member import preview UI to the existing backend commit endpoint.

It changes frontend runtime only in app/page.tsx.

It does not change backend runtime behavior.

It does not change backend API behavior.

It does not modify the preview endpoint.

It does not modify the commit endpoint.

## Purpose of 3CM

The purpose of 3CM is to implement a narrow frontend commit flow inside the existing member import preview UI.

The implementation keeps the preview-first workflow and adds a guarded commit action only after backend preview has completed.

The implementation remains inside the 3CL authorization boundary.

## Base

Base milestone:

- Milestone 3CL - Member Data Import Commit Frontend Implementation Authorization

Base branch:

- milestone-3cl-member-data-import-commit-frontend-implementation-authorization

Implementation branch:

- milestone-3cm-member-data-import-commit-frontend-narrow-implementation

## Allowed files

Only these files are allowed in 3CM:

- app/page.tsx
- backend/docs/milestone_3cm_member_data_import_commit_frontend_narrow_implementation.md
- backend/tests/Feature/Milestone3cmMemberDataImportCommitFrontendNarrowImplementationTest.php

## Frontend implementation focus

3CM extends the existing Member Import Preview screen.

3CM preserves the existing preview UI as much as possible.

3CM adds a commit review panel only after preview rows are available.

3CM adds explicit confirmation before calling commit.

3CM displays backend commit summary and row-level commit results.

3CM does not add a separate import module, route, package, upload flow, or backend change.

## Existing preview endpoint used

The frontend continues to use the existing preview endpoint:

- POST /api/v1/clubs/{club}/members/import-preview

Preview remains the first step.

Preview remains dry-run behavior.

Preview remains backed by backend authorization and validation.

## Existing commit endpoint used

The frontend uses the existing commit endpoint:

- POST /api/v1/clubs/{club}/members/import-commit

The endpoint is called only after preview completes and the user explicitly confirms commit.

The frontend sends the current manual/paste row data to the commit endpoint.

The frontend displays backend commit results.

## Backend endpoints not modified

3CM does not modify backend endpoints.

3CM does not add backend routes.

3CM does not change backend controllers, requests, resources, services, models, migrations, seeders, config, or API contracts.

Backend remains the source of truth for authorization, club scope, validation, duplicate detection, existing-member matching, member creation, skipped rows, failed rows, and summary counts.

## Officer-only access rule

The frontend commit flow remains officer-only.

The allowed roles are:

- President/Admin through the existing president role flow
- Secretary
- Treasurer

Frontend visibility is not treated as security.

Backend authorization remains the source of truth.

## Normal Member exclusion

Normal Member does not receive member import preview or commit controls.

Normal Member does not trigger preview API calls.

Normal Member does not trigger commit API calls.

Normal Member does not load preview or commit data into hidden frontend state.

Normal Member cannot create, update, delete, save, commit, or import member data.

## Club-scoped behavior

The frontend resolves the active club context before preview or commit.

Preview and commit calls are made only for the active authorized club.

Cross-club authorization remains enforced by the backend.

## Preview-first workflow

Preview must be run successfully before commit is offered.

Changing pasted rows clears preview and commit state.

Preview and Commit are separated in the UI.

The frontend does not treat stale preview as final authorization to commit.

The backend re-checks rows at commit time.

## Explicit confirmation before commit

The frontend requires an explicit confirmation checkbox before commit.

The commit action is disabled until preview has completed and confirmation is selected.

The UI states that commit creates eligible new members only.

The UI states that existing members are not overwritten.

The UI states that existing members are not updated.

The UI states that members are not deleted.

## Row-level eligibility display

The preview UI continues to show row-level eligibility before commit.

Rows with validation errors are shown before commit.

Duplicate warnings are shown before commit.

Existing-member match warnings are shown before commit.

The frontend does not invent row eligibility, duplicate decisions, existing-member match decisions, or import outcomes.

## Row-level validation, error, and warning display

The frontend displays backend-provided:

- validation errors
- duplicate warnings
- existing-member match warnings
- ineligible row reasons

Missing or null backend fields render safely as Unavailable or None.

## Row-level created, skipped, and failed result display

After commit, the frontend displays backend-provided:

- Created row results
- Skipped row results
- Failed row results
- row errors
- row warnings
- created member ID when provided by the backend
- normalized fields when provided by the backend

Summary counts do not hide row-level failures.

Created rows are distinct from skipped or failed rows.

## Create-only new member messaging

The commit action uses safe wording:

- Commit eligible new members

The UI also uses safe result labels:

- Confirm commit
- Created
- Skipped
- Failed

The implementation avoids dangerous wording such as Import all, Overwrite, Apply all, or Replace.

## No existing-member update rule

Existing-member update remains blocked.

Existing-member matches remain warnings or skipped outcomes from the backend.

The frontend does not provide matched-row update controls.

## No member deletion rule

Member deletion remains blocked.

The frontend does not provide member deletion or deactivation controls through import.

## No silent overwrite rule

Silent overwrite remains blocked.

The frontend states that existing members will not be overwritten or updated.

Existing-member match warnings are shown before commit.

## Manual and paste rows only

Manual/paste JSON or spreadsheet-like rows remain the only input path.

The frontend keeps the textarea input.

The frontend does not add file input.

## Excel and CSV upload blocked

Excel upload remains blocked.

CSV upload remains blocked.

xlsx upload remains blocked.

Drag-and-drop upload remains blocked.

Bulk file import remains blocked.

## Parser, package, and storage blocked

xlsx parser/package integration remains blocked.

CSV parser/package integration remains blocked.

File upload/storage flow remains blocked.

No package files were changed.

## Queue and job processing blocked

Queue/job processing remains blocked.

Background import processing remains blocked.

No asynchronous import worker was added.

## Finance and adjacent module mutation blocked

3CM does not add UI for mutating:

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

## Deployment, SaaS, and client-specific behavior blocked

Deployment implementation remains blocked.

SaaS implementation remains blocked.

Client-specific behavior remains blocked.

Hardcoded Lions Club Beenachi rules remain blocked.

Hardcoded Rotary Central Bathery rules remain blocked.

Hardcoded future pilot club rules remain blocked.

## Frontend runtime limited to app/page.tsx

The only frontend runtime file changed by 3CM is:

- app/page.tsx

No frontend runtime files outside app/page.tsx were changed.

No backend runtime files were changed.

## Expected next milestone

The expected next milestone is:

Milestone 3CN - Member Data Import Commit Frontend Validation and Closure

3CN should be docs/tests-only validation and closure for the 3CM frontend commit implementation.
