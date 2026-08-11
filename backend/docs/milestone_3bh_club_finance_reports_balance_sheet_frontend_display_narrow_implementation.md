# Milestone 3BH - Club Finance Reports and Balance Sheet Frontend Display Narrow Implementation

## Status

Milestone 3BH is a narrow frontend implementation milestone.

It implements a compact read-only frontend display for existing club finance reports and balance sheet responses.

It changes only:

- app/page.tsx
- backend/docs/milestone_3bh_club_finance_reports_balance_sheet_frontend_display_narrow_implementation.md
- backend/tests/Feature/Milestone3bhClubFinanceReportsBalanceSheetFrontendDisplayNarrowImplementationTest.php

## Base

Base milestone:

- Milestone 3BG - Club Finance Reports and Balance Sheet Frontend Display Implementation Authorization

Base branch:

- milestone-3bg-club-finance-reports-balance-sheet-frontend-display-implementation-authorization

Implementation branch:

- milestone-3bh-club-finance-reports-balance-sheet-frontend-display-narrow-implementation

## Purpose

The purpose of 3BH is to add officer-friendly display surfaces for backend-provided finance report data without changing backend behavior.

The display is read-only and compact.

It is placed inside the existing Finance section.

It does not create accounting workflows.

## Runtime boundary

Milestone 3BH updates app/page.tsx only for runtime behavior.

The frontend adds:

- Club Finance Reports display.
- Balance Sheet Summary display.
- Loading state.
- Empty response state.
- Missing active club context state.
- Unauthenticated state.
- Forbidden state.
- General API error state.
- Successful report display.
- Successful balance sheet display.

## Existing APIs used

The frontend uses only these existing backend APIs:

- GET /api/v1/clubs/{club}/finance/reports
- GET /api/v1/clubs/{club}/finance/balance-sheet

The frontend first obtains the authenticated officer context through existing auth endpoints already used elsewhere in the prototype:

- POST /api/v1/auth/login
- GET /api/v1/auth/me

No backend API route is added, renamed, removed, or changed.

## Backend source of truth

The backend remains the source of truth for finance report and balance sheet values.

The frontend displays only values returned by the existing API response shape.

The frontend may format backend amount strings for display.

The frontend does not:

- Invent totals.
- Invent balances.
- Invent classifications.
- Calculate official finance balances.
- Recalculate dues totals.
- Recalculate collection totals.
- Recalculate receipt totals.
- Add business or accounting rules.
- Patch backend runtime files to make frontend display easier.

If a field is missing, null, forbidden, or empty, the frontend shows a safe unavailable or empty state.

## Displayed backend-provided values

The Club Finance Reports display reads these existing response areas when present:

- finance_reports.club_name
- finance_reports.club_finance_overview.total_debit_amount
- finance_reports.club_finance_overview.total_credit_amount
- finance_reports.club_finance_overview.current_dues_amount
- finance_reports.club_finance_overview.current_dues_label
- finance_reports.club_finance_overview.posted_payment_count
- finance_reports.club_finance_overview.issued_receipt_count
- finance_reports.outstanding_dues_summary.members_with_dues_count
- finance_reports.outstanding_dues_summary.total_outstanding_amount
- finance_reports.member_wise_dues_summary
- finance_reports.payment_collection_summary.posted_payment_total
- finance_reports.payment_collection_summary.posted_payment_count
- finance_reports.receipt_summary.issued_receipt_count
- finance_reports.generated_at

The Balance Sheet Summary display reads these existing response areas when present:

- balance_sheet.club_name
- balance_sheet.assets.member_receivables
- balance_sheet.assets.cash_collected
- balance_sheet.liabilities.member_advances
- balance_sheet.net_position.amount
- balance_sheet.net_position.label
- balance_sheet.source
- balance_sheet.generated_at

## Role visibility

The club-level finance reports and balance sheet display is visible only to:

- President/Admin
- Secretary
- Treasurer

Normal Member role must not see this UI.

Normal Member role must not trigger the club-level reports or balance sheet API calls.

Unauthorized club-level finance data is not loaded into hidden frontend state for Normal Member users.

## Blocked scope

Milestone 3BH does not add or change:

- Backend routes.
- Backend controllers.
- Backend services.
- Backend resources.
- Backend requests.
- Backend models.
- Migrations.
- Seeders.
- API behavior.
- Export.
- PDF.
- Excel.
- Reminders.
- Payment mutation.
- Receipt mutation.
- Payment cancellation.
- Payment reversal.
- Allocation workflow.
- Deployment implementation.
- SaaS implementation.
- Client-specific hardcoding.

The frontend does not hardcode special behavior for:

- Lions Club Beenachi.
- Rotary Central Bathery.
- Any future pilot club.

## Validation

Expected validation commands:

- php artisan test --filter=Milestone3bhClubFinanceReportsBalanceSheetFrontendDisplayNarrowImplementationTest
- php artisan test
- npm run build
- git status --short --branch
- git diff --stat
- git diff --check

## Next milestone

After 3BH implementation is validated, the expected next milestone is:

Milestone 3BI - Club Finance Reports and Balance Sheet Frontend Display Validation and Closure.
