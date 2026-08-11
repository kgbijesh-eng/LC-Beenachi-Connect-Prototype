# Milestone 3AZ - Club Finance Reports and Balance Sheet Scope Gate

Status: SCOPE GATE ONLY

Milestone 3AZ defines the future scope for club finance reports and balance sheet visibility.

This milestone is docs/tests-only. No reporting implementation is allowed in 3AZ.

## Reason for this scope gate

Milestone 3AY closed the payment receipt frontend plateau and selected the next safe direction as read-only club finance reporting and balance sheet scoping.

The finance foundation now includes:

- Member ledger entry foundation
- Member balance calculation
- Member balance read API
- Payment recording database foundation
- Payment recording service
- Payment recording API
- Receipt generation service
- Receipt JSON view API
- Receipt PDF download API
- Member finance summary read API
- Member finance summary frontend
- Payment recording frontend
- Payment receipt frontend

The next step must remain read-only and reporting-first.

## Future reporting purpose

Future club finance reports should help authorized club officers understand:

- Total outstanding dues
- Member-wise dues
- Member payment collections
- Issued receipts
- Pending receivables
- Basic income/payment movement
- Club-level balance sheet position

The reports must be management reports for club operations.

They must not be presented as audited statutory financial statements.

## Future report candidates

A later implementation may define read-only reports such as:

- Outstanding dues summary
- Member dues ageing report
- Member receivables report
- Payment collection report
- Receipt register
- Member-wise ledger summary
- Club income and payment movement summary
- Basic club balance sheet view

## Future balance sheet boundary

A future balance sheet view may summarize:

- Assets
- Liabilities
- Member receivables
- Cash or bank position if a future cash/bank source exists
- Net club fund position
- Generated date and reporting period

The first future balance sheet must be read-only.

It must not introduce accounting journal workflows, bank reconciliation, statutory audit filing, tax filing, or external accounting integration.

## Backend source-of-truth rule

Reports must read from backend-approved sources only.

Future reports must not calculate privileged finance data in the frontend.

Future report calculations must be backend-owned and must preserve:

- Club access rules
- Member access rules
- Cross-club blocking
- Ledger as balance source of truth
- Payment and receipt authorization rules
- Read-only reporting behavior

## Role visibility boundary

Normal Member:

- Must not access club-wide finance reports
- Must not access club-wide balance sheet
- May continue to access only their own finance summary, receipts, and receipt PDFs where backend rules allow it

Treasurer:

- May be eligible for future club-wide finance reports
- May be eligible for future balance sheet view
- Must remain within own club only

President/Admin:

- May be eligible for future club-wide finance reports
- May be eligible for future balance sheet view
- Must remain within own club only

Secretary:

- May be eligible for future read-only operational finance reports if later authorized
- Must remain within own club only

## Future API direction

A later milestone may authorize read-only reporting APIs.

Possible future endpoint families:

- GET /api/v1/clubs/{club}/finance/reports/outstanding-dues
- GET /api/v1/clubs/{club}/finance/reports/collections
- GET /api/v1/clubs/{club}/finance/reports/receipts
- GET /api/v1/clubs/{club}/finance/balance-sheet

These routes are not authorized for implementation in 3AZ.

## Future frontend direction

A later milestone may authorize frontend report screens only after read-only backend report APIs exist.

No report frontend is authorized in 3AZ.

## Blocked scope

3AZ blocks all implementation.

Blocked in 3AZ:

- app/page.tsx
- backend routes
- backend controllers
- backend requests
- backend resources
- backend services
- backend models
- migrations
- seeders
- report implementation
- balance sheet implementation
- export implementation
- PDF report generation
- Excel import
- Excel export
- reminder implementation
- payment history implementation
- payment cancellation
- payment reversal
- special collection allocation workflow
- fellowship payment allocation workflow
- receipt sharing workflow
- audit dashboard implementation
- statutory accounting workflow
- bank reconciliation workflow
- tax filing workflow

## Allowed files in 3AZ

Only these files are allowed:

- backend/docs/milestone_3az_club_finance_reports_balance_sheet_scope_gate.md
- backend/tests/Feature/Milestone3azClubFinanceReportsBalanceSheetScopeGateTest.php

## Next milestone candidate

Selected next milestone:

Milestone 3BA - Club Finance Reports and Balance Sheet Read API Authorization

3BA must be docs/tests-only.

3BA may authorize a later narrow read API implementation, but must not implement reports, balance sheet runtime logic, exports, reminders, allocation workflows, cancellation, reversal, Excel import, or frontend screens.

## Closure rule

3AZ closes only after:

- Scope document exists
- Scope test exists
- Focused 3AZ test passes
- Full backend test suite passes
- No runtime file is changed
