# Milestone 3CA - Member Data Import Frontend Scope Gate

## Status

Milestone 3CA is a docs/tests-only scope gate.

It does not implement frontend upload UI.

It does not change frontend runtime behavior.

It does not change backend runtime behavior.

It does not change API behavior.

## Base

Base milestone:

- Milestone 3BZ - Member Data Import API Validation and Closure

Base branch:

- milestone-3bz-member-data-import-api-validation-and-closure

Scope gate branch:

- milestone-3ca-member-data-import-frontend-scope-gate

## Purpose

The purpose of 3CA is to define the future frontend scope for member data import preview after the Member Data Import API preview lane was closed in 3BZ.

3CA authorizes no frontend implementation.

3CA authorizes no backend API implementation.

3CA defines a future frontend candidate only if it stays inside the preview-only 3BY backend route boundary.

## Allowed files

Only these files are allowed in 3CA:

- backend/docs/milestone_3ca_member_data_import_frontend_scope_gate.md
- backend/tests/Feature/Milestone3caMemberDataImportFrontendScopeGateTest.php

## Blocked files and scope

3CA must not edit:

- app/page.tsx
- frontend runtime files
- backend routes
- backend controllers
- backend requests
- backend resources
- backend services
- backend models
- backend middleware
- backend migrations
- backend seeders
- backend config
- composer.json
- composer.lock
- API behavior
- member import API implementation files
- member import preview service
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

3CA must not implement frontend upload UI.

3CA must not implement import commit UI.

3CA must not add frontend file parsing.

3CA must not add backend routes or API changes.

## Future frontend preview boundary

A future frontend milestone may display a member import preview by using only the existing 3BY backend route:

- POST /api/v1/clubs/{club}/members/import-preview

The future frontend must not create or require new backend API routes.

The future frontend must not change the API response contract.

The future frontend must treat the backend as the source of truth.

The future frontend must send spreadsheet-like rows as JSON arrays only if a later implementation milestone explicitly authorizes the input mechanism.

## Role visibility boundary

Normal Member:

- Must not see member import preview UI.
- Must not upload member data.
- Must not paste import rows.
- Must not preview imports.
- Must not commit imports.
- Must not load import preview data into hidden frontend state.

President/Admin, Secretary, and Treasurer:

- May be considered for future officer-only import preview UI.
- Must remain scoped to the active club authorized by the backend.
- Must use only the existing 3BY preview endpoint.
- Must not receive cross-club import preview data.
- Must not receive commit/import mutation controls.

Backend authorization remains the source of truth for every frontend visibility decision.

## Future input boundary

Future frontend work may evaluate a controlled JSON-row entry or paste-preview experience only after separate implementation approval.

3CA does not authorize:

- file upload UI
- drag-and-drop upload
- xlsx parsing in the browser
- CSV parsing in the browser
- template download
- sample file generation
- import commit
- member create/update/delete action
- raw file storage

Any future upload or parser experience must be separately scoped after preview UI closure.

## Future preview display boundary

Future frontend display may show only preview-safe data already returned by the 3BY API:

- dry_run
- committed
- summary counts
- row number
- row validity
- intent
- normalized member fields returned by the backend
- row-level validation errors
- duplicate row warnings
- existing-member match warnings

If a backend field is absent, null, forbidden, or empty, the frontend must show a safe unavailable or empty state.

The frontend must not invent fields, matches, member statuses, duplicate decisions, balances, totals, labels, or import outcomes.

## Blocked frontend content

Future frontend display must not show:

- hidden columns
- raw uploaded file contents beyond submitted preview rows
- finance fields
- ledger fields
- payment fields
- receipt fields
- payment-history fields
- cancellation internals
- reversal internals
- allocation internals
- unrelated member private data
- global cross-club member matches
- client-specific labels or logic

## Required future frontend states

A future frontend implementation must define safe states for:

- loading
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

The frontend must not expose raw technical errors to users.

## Blocked behavior

3CA does not add or authorize:

- frontend implementation
- app/page.tsx changes
- backend route changes
- controller changes
- request class changes
- resource changes
- service changes
- model changes
- middleware changes
- migration changes
- seeder changes
- file upload UI
- parser UI
- import commit UI
- member create UI through import
- member update UI through import
- member delete UI through import
- silent overwrite UI
- finance import UI
- dues import UI
- ledger import UI
- payment import UI
- receipt import UI
- payment-history import UI
- committee import UI
- fellowship import UI
- activity import UI
- event import UI
- export UI
- PDF UI
- Excel UI
- reminder UI
- notification UI
- deployment implementation
- SaaS implementation
- client-specific hardcoding

## Existing route closure

3CA depends on the 3BY backend route that was validated and closed by 3BZ:

- POST /api/v1/clubs/{club}/members/import-preview

3CA must not reopen 3BY unless a real defect is found and separately reported.

## Next milestone

The expected next milestone is:

Milestone 3CB - Member Data Import Frontend Implementation Authorization

3CB must not start from 3CA unless explicitly approved.

3CB should remain docs/tests-only unless the human explicitly authorizes a different scope.

3CB may authorize a future frontend preview implementation only if it stays inside the 3CA frontend scope gate.
