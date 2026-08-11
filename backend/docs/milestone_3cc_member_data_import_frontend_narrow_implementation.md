# Milestone 3CC - Member Data Import Frontend Narrow Implementation

## Status

Milestone 3CC is a narrow frontend implementation milestone.

It implements an officer-only member import preview UI using the existing 3BY backend preview API.

It does not implement Excel upload.

It does not add a parser package.

It does not add file upload or storage.

It does not add backend routes, controllers, requests, resources, services, models, migrations, seeders, config, or API behavior.

It does not create, update, delete, save, commit, or import members.

## Base

Base milestone:

- Milestone 3CB - Member Data Import Frontend Implementation Authorization

Base branch:

- milestone-3cb-member-data-import-frontend-implementation-authorization

Implementation branch:

- milestone-3cc-member-data-import-frontend-narrow-implementation

## Allowed files

Only these files are allowed in 3CC:

- app/page.tsx
- backend/docs/milestone_3cc_member_data_import_frontend_narrow_implementation.md
- backend/tests/Feature/Milestone3ccMemberDataImportFrontendNarrowImplementationTest.php

## Implemented frontend behavior

3CC adds a Member Import Preview screen to the existing prototype frontend.

The screen is available only through officer navigation for:

- President/Admin through the existing president role flow
- Secretary
- Treasurer

Normal Member does not receive a navigation item for the preview screen.

Normal Member does not trigger the member import preview API.

Normal Member does not load import preview data into hidden frontend state.

The UI uses only the existing 3BY preview API:

- POST /api/v1/clubs/{club}/members/import-preview

The UI first uses the existing local authenticated officer flow to resolve the active club context.

The backend remains the source of truth for authorization, club scope, validation, duplicate detection, existing-member matching, and preview response content.

## Input boundary

3CC supports only manual or paste-based spreadsheet-like row entry.

The input is converted into JSON rows in the browser and sent to the existing preview API.

The UI accepts simple tab-separated or comma-separated pasted rows with a header row.

The UI does not upload Excel files.

The UI does not upload CSV files.

The UI does not add drag-and-drop file upload.

The UI does not add browser-side xlsx parser packages.

The UI does not add browser-side CSV parser packages.

The UI does not add upload storage.

The UI does not add template download or sample file generation.

## Display boundary

3CC displays only safe preview metadata returned by the backend:

- dry_run
- committed
- summary counts
- row number
- row validity
- intent
- normalized member fields returned by the backend
- row-level validation errors
- duplicate warnings
- existing-member match warnings

If a backend field is absent, null, forbidden, or empty, the UI shows a safe unavailable or empty state.

The UI does not invent fields, duplicate decisions, existing-member matches, member statuses, balances, totals, labels, or import outcomes.

Existing-member matches are displayed as preview-only warnings.

The UI clearly states that preview does not save changes.

The UI clearly states that existing member data will not be silently overwritten.

## Required states

3CC implements safe UI states for:

- loading access context
- loading preview
- empty or no rows entered
- validation errors returned by backend
- duplicate warnings returned by backend
- existing-member match warnings returned by backend
- missing active club context
- unauthenticated or 401
- forbidden or 403
- general API error
- successful preview display
- missing or null backend fields

## Explicitly blocked behavior

3CC does not add:

- import save button
- import commit button
- member import commit endpoint
- create members through import
- update members through import
- delete members through import
- silent overwrite behavior
- Excel upload
- xlsx parser package
- CSV parser package
- upload storage
- backend route changes
- backend controller changes
- backend service changes
- backend request changes
- backend resource changes
- backend model changes
- backend migration changes
- finance ledger mutation
- payment mutation
- receipt mutation
- payment-history mutation
- balance mutation
- dues import
- committee import
- fellowship import
- activity import
- event import
- finance import
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

## Validation scope

3CC validation verifies:

- the frontend preview screen exists
- the screen uses only POST /api/v1/clubs/{club}/members/import-preview
- officer roles can navigate to the preview screen
- Normal Member navigation excludes the preview screen
- Normal Member guard prevents preview API loading
- the UI uses manual or paste rows only
- the UI displays row-level validation errors
- the UI displays duplicate warnings
- the UI displays existing-member match warnings
- the UI displays safe unavailable or empty states
- no Excel upload, parser package, upload storage, backend API change, frontend import commit, member mutation, finance mutation, deployment, SaaS, or client-specific behavior was added

## Next milestone

The expected next milestone is:

Milestone 3CD - Member Data Import Frontend Validation and Closure

3CD should be docs/tests-only validation and closure for the frontend preview lane.
