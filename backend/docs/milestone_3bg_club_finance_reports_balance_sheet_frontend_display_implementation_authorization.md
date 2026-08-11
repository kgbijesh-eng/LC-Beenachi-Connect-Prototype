# Milestone 3BG - Club Finance Reports and Balance Sheet Frontend Display Implementation Authorization

## Status

Milestone 3BG is a docs/tests-only implementation authorization milestone.

It authorizes a future narrow frontend display implementation in Milestone 3BH.

It does not implement frontend display.

It does not change app/page.tsx.

It does not change backend runtime behavior.

## Background

Milestone 3BC implemented the read-only backend API for club finance reports and balance sheet data.

Milestone 3BD validated and closed that backend API.

Milestone 3BE defined the frontend display scope gate.

Milestone 3BF inserted a roadmap and Codex governance audit and shifted frontend display authorization from 3BF to 3BG.

Milestone 3BG now converts the 3BE scope gate into exact future authorization for Milestone 3BH.

## Purpose

The purpose of 3BG is to define the future 3BH frontend display boundary before any frontend runtime file is changed.

The future 3BH milestone may add a read-only frontend display for:

- Club Finance Reports.
- Balance Sheet Summary.
- Outstanding dues summary.
- Member-wise dues summary.
- Payment collection summary.
- Receipt summary.

The future display must be compact, officer-friendly, and suitable for President/Admin, Secretary, and Treasurer review.

## Authorized future implementation

Milestone 3BH may implement only a narrow read-only frontend display for the existing club finance reports and balance sheet backend responses.

Authorized future runtime file:

- app/page.tsx

Milestone 3BH may update app/page.tsx only to:

- Add a Club Finance Reports display area inside the existing Finance section.
- Add a Balance Sheet Summary display area inside the existing Finance section.
- Call the existing 3BC read-only API routes.
- Render allowed report and balance sheet fields.
- Add loading, empty, forbidden, unauthenticated, and API error states.
- Hide club-level reports from the Normal Member role.
- Preserve existing member-safe finance views.
- Format backend-provided values for display.

Milestone 3BH must not change any backend runtime file.

## Existing API usage only

Milestone 3BH may call only these existing routes:

- GET /api/v1/clubs/{club}/finance/reports
- GET /api/v1/clubs/{club}/finance/balance-sheet

Milestone 3BH must not add, rename, remove, or change backend routes.

Milestone 3BH must not create new report endpoints.

Milestone 3BH must not call the 3AZ candidate route family as if those routes exist:

- GET /api/v1/clubs/{club}/finance/reports/outstanding-dues
- GET /api/v1/clubs/{club}/finance/reports/collections
- GET /api/v1/clubs/{club}/finance/reports/receipts

The current source of truth for implemented report API shape is the 3BC aggregate reports endpoint plus the 3BC balance sheet endpoint.

## Backend source of truth

The backend remains the source of truth for all official finance report and balance sheet calculations.

Milestone 3BH frontend code may format values for display.

Milestone 3BH frontend code must not:

- Calculate official balances independently.
- Recalculate dues totals independently.
- Recalculate payment collection totals independently.
- Recalculate receipt totals independently.
- Derive accounting conclusions that are not returned by the backend.
- Override backend finance labels.
- Mutate finance data.

## Role visibility boundary

Future 3BH frontend display may be visible only to:

- President/Admin.
- Secretary.
- Treasurer.

Normal Member role must not see club-level finance reports or the club-level balance sheet display.

Normal Member role may continue to see only member-safe finance information already authorized elsewhere.

The future frontend display must preserve backend authorization behavior and must not expose privileged report data in hidden client state for unauthorized roles.

## Allowed future display fields

Milestone 3BH may display only backend-provided report and balance sheet fields from the two authorized API responses.

Allowed display fields include:

- club name.
- total debit amount.
- total credit amount.
- current dues amount.
- current dues label.
- members with dues count.
- total outstanding amount.
- member-wise dues summary.
- posted payment total.
- posted payment count.
- issued receipt count.
- member receivables.
- cash collected.
- member advances.
- net position.
- source label.
- generated at.

Milestone 3BH may use simple officer-friendly wording such as:

- Total Dues.
- Amount Collected.
- Current Dues.
- Members with Dues.
- Balance Sheet Summary.
- Net Position.

Milestone 3BH must not present these operational reports as audited statutory financial statements.

## Safety states

Milestone 3BH must include safe frontend states for:

- Loading.
- No finance records yet.
- Forbidden access.
- Unauthenticated access.
- API error.
- Missing active club context.

The frontend must not expose raw technical errors, stack traces, tokens, sensitive response internals, or unauthorized finance data.

## Explicitly blocked future implementation scope

Milestone 3BH must not add or change:

- Backend routes.
- Backend controllers.
- Backend services.
- Backend resources.
- Backend requests.
- Backend models.
- Migrations.
- Seeders.
- API behavior.
- Report exports.
- PDF generation.
- Excel import.
- Excel export.
- Reminder workflow.
- Payment mutation.
- Receipt mutation.
- Payment cancellation.
- Payment reversal.
- Allocation workflow.
- Receipt sharing.
- Receipt correction.
- Receipt reissue.
- Deployment scripts.
- SaaS implementation.
- Client-specific hardcoding.

Milestone 3BH must not introduce accounting mutation workflows, statutory accounting workflows, bank reconciliation workflows, tax filing workflows, or external accounting integrations.

## Client-specific hardcoding rule

The future frontend display must preserve the one shared codebase strategy.

Milestone 3BH must not hardcode special behavior for:

- Lions Club Beenachi.
- Rotary Central Bathery.
- Any future pilot club.

Allowed future display may use runtime club data returned by the backend or existing app state.

Blocked future display includes:

- Conditional behavior based on a specific club name.
- Conditional behavior based on a specific pilot club code.
- Lions-only report logic.
- Rotary-only report logic.
- Client-specific production forks.

## Expected validation for 3BH

Milestone 3BH must include focused validation proving:

- Future frontend display is implemented only in app/page.tsx.
- No backend runtime files are changed.
- Only the two existing 3BC API routes are used.
- Backend remains the calculation source of truth.
- President/Admin, Secretary, and Treasurer can reach the display when app state allows it.
- Normal Member role does not see club-level reports or balance sheet display.
- Loading state is present.
- Empty state is present.
- Forbidden or unauthorized state is handled safely.
- API error state is handled safely.
- No export, PDF, Excel, reminder, mutation, cancellation, reversal, or allocation workflow is added.
- No client-specific hardcoding is added.
- Frontend build passes.
- Relevant backend tests still pass.

Expected validation commands for 3BH should include:

- Focused 3BH frontend/runtime test or static guard test.
- Relevant continuity slice for finance frontend milestones.
- php artisan test.
- Frontend build or lint command used by the project.
- git status --short.
- git diff --stat.
- git diff --check.

## Scope boundary for 3BG

Milestone 3BG is docs/tests-only.

Allowed files in 3BG:

- backend/docs/milestone_3bg_club_finance_reports_balance_sheet_frontend_display_implementation_authorization.md
- backend/tests/Feature/Milestone3bgClubFinanceReportsBalanceSheetFrontendDisplayImplementationAuthorizationTest.php

Blocked in 3BG:

- app/page.tsx.
- Frontend runtime files.
- Backend routes.
- Backend controllers.
- Backend services.
- Backend resources.
- Backend requests.
- Backend models.
- Migrations.
- Seeders.
- API behavior.
- Export files.
- PDF files.
- Excel files.
- Reminder files.
- Payment mutation files.
- Receipt mutation files.
- Cancellation workflow files.
- Reversal workflow files.
- Allocation workflow files.
- Deployment files.
- SaaS files.
- Client-specific hardcoding.
- Runtime behavior changes.

## Closure rule

Milestone 3BG can close only if:

- This authorization document exists.
- The focused 3BG validation test exists.
- The focused 3BG validation test passes.
- The full backend test suite passes.
- git diff --check is clean.
- Only the two allowed files are changed.
- No frontend runtime file is changed.
- No backend runtime file is changed.

## Next milestone

After 3BG closes, the next milestone should be:

Milestone 3BH - Club Finance Reports and Balance Sheet Frontend Display Narrow Implementation.

3BH may implement only the narrow frontend display authorized by this document.
