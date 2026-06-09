# Milestone 3AH — Outstanding Dues and Member Finance Summary Read API Authorization

## Status

Milestone 3AH authorizes a future read-only API for Outstanding Dues and Member Finance Summary.

This checkpoint is docs/tests-only.

## Authorization Decision

OUTSTANDING_DUES_FINANCE_SUMMARY_READ_API_AUTHORIZED_FOR_FUTURE_IMPLEMENTATION

## Source Scope

This authorization follows:

- Milestone 3AF — Finance Module Post-Receipt Closure Review and Next Scope Decision
- Milestone 3AG — Outstanding Dues and Member Finance Summary Scope Gate

Milestone 3AG selected an API-first, read-only path before any frontend finance screen.

## Future Implementation Candidate

The future implementation candidate is:

Milestone 3AI — Outstanding Dues and Member Finance Summary Read API Narrow Implementation

3AH authorizes only a future implementation milestone. It does not implement the API.

## Future Route Boundary

The future implementation may add only read-only GET routes.

Authorized future routes:

- GET /api/v1/clubs/{club}/finance/member-summary
- GET /api/v1/clubs/{club}/members/{member}/finance-summary

Route purpose:

- /finance/member-summary returns the authenticated normal member's own finance summary when linked to a member record.
- /members/{member}/finance-summary returns a selected member finance summary for authorized same-club PST users.

No POST, PATCH, PUT, or DELETE route is authorized.

## Future Controller Boundary

Future implementation may add a narrow controller such as:

- MemberFinanceSummaryController

The controller should:

- accept only authenticated requests
- enforce club-scoped access
- delegate summary calculation to a dedicated service
- return a resource response
- avoid payment, receipt, or ledger mutation logic
- avoid report, Excel, PDF, reminder, and dashboard logic

## Future Service Boundary

Future implementation may add a narrow read-only service such as:

- MemberFinanceSummaryService

The service should:

- read existing member ledger entries
- read existing payments
- read existing receipts
- calculate current dues from ledger balance rules
- calculate last payment from existing posted payments
- identify pending items from existing ledger entries
- expose receipt availability from existing issued receipts
- return read-only summary data
- avoid creating, updating, or deleting records

The service must not:

- record payments
- generate receipts
- create ledger entries
- reverse payments
- cancel payments
- correct payments
- allocate fellowship payments
- create special collections
- send reminders
- generate reports
- export Excel

## Future Request and Query Boundary

Future implementation may add a request class only if query validation is needed.

Allowed query parameters may include:

- include_pending_items
- include_last_payment
- include_receipt_status

Query parameters must not allow:

- changing member_id for normal member own-summary access
- creating records
- updating records
- deleting records
- triggering reminders
- generating reports
- exporting Excel
- generating PDFs

## Future Resource and Response Boundary

Future implementation may add a resource such as:

- MemberFinanceSummaryResource

Allowed response fields:

- member_id
- member_code
- member_name
- current_dues_amount
- current_dues_label
- last_payment_amount
- last_payment_date
- pending_items
- receipt_available
- payment_status
- generated_at

Allowed pending item fields:

- source_type
- source_label
- amount
- due_date
- status

Member-facing labels should use:

- Current Dues
- Pending Items
- Last Payment
- Receipt Available
- Paid
- Partly Paid
- Pending
- Advance

Member-facing output should avoid:

- debit
- credit
- ledger posting
- receivable
- journal
- accounting entry
- reversal
- audit trail

## Access Rules

Future implementation must enforce:

- unauthenticated access fails closed
- normal member can read only own finance summary
- normal member cannot read another member finance summary
- Treasurer can read same-club member finance summaries
- Secretary can read same-club member finance summaries
- President/Admin can read same-club member finance summaries
- cross-club member finance access fails closed
- user without active club membership fails closed
- finance summary API remains read-only

## Normal Member Privacy Rule

Normal members must not see:

- other members' dues
- other members' payment status
- club-wide outstanding dues
- internal accounting notes
- audit metadata
- correction controls
- reversal controls
- cancellation controls

## Explicitly Not Authorized in 3AH

Milestone 3AH does not authorize or add:

- route implementation
- controller implementation
- service implementation
- request implementation
- resource implementation
- model implementation
- migration implementation
- frontend finance screens
- Treasurer finance dashboard
- Secretary finance dashboard
- President finance dashboard
- finance reports
- Excel import/export
- PDF changes
- reminder implementation
- special collection workflow
- fellowship allocation workflow
- payment correction workflow
- payment reversal workflow
- payment cancellation workflow
- payment mutation
- receipt mutation
- ledger mutation

## Allowed Files in 3AH

Only these files are allowed for this milestone:

- docs/milestone_3ah_outstanding_dues_finance_summary_read_api_authorization.md
- tests/Feature/Milestone3ahOutstandingDuesFinanceSummaryReadApiAuthorizationTest.php

## Closure Rule

Milestone 3AH authorizes a future read-only API implementation only.

No runtime behavior changes are allowed in this milestone.