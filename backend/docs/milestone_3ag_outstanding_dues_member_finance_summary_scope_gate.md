# Milestone 3AG — Outstanding Dues and Member Finance Summary Scope Gate

## Status

Milestone 3AG defines the scope for future Outstanding Dues and Member Finance Summary work.

This checkpoint is docs/tests-only.

## Scope Decision

OUTSTANDING_DUES_MEMBER_FINANCE_SUMMARY_SCOPE_DEFINED

## Purpose

The next finance workstream should convert the completed ledger, payment, receipt, and receipt PDF foundation into useful read-only finance summaries.

The first implementation after this scope gate should help:

- Normal Members understand their own pending dues
- Treasurer review dues and collections
- Secretary verify member finance status when required
- President/Admin monitor finance status at club level

## Recommended Next Implementation Style

The next implementation should be API-first and read-only.

Recommended future milestone:

Milestone 3AH — Outstanding Dues and Member Finance Summary Read API Authorization

The API should be authorized separately before any implementation.

No UI should be added before the read API contract is stable.

## Normal Member Visibility

Normal Members should see only their own finance summary.

Normal Member view should use member-friendly wording.

Allowed Normal Member fields:

- current dues amount
- last payment amount
- last payment date
- pending dues items
- receipt availability for own payments
- payment mode for own payments if already recorded
- status labels such as Pending, Partly Paid, Paid, or Advance

Normal Members must not see:

- other members' dues
- other members' payment status
- club-wide outstanding dues
- ledger debit wording
- ledger credit wording
- internal accounting notes
- audit metadata
- correction, reversal, or cancellation controls

## Treasurer Visibility

Treasurer should be able to view finance summaries for members within the Treasurer's own club.

Allowed Treasurer fields:

- member identity summary
- current dues amount
- pending dues items
- last payment amount
- last payment date
- receipt availability
- payment status label
- collection totals by member
- outstanding totals by member

Treasurer access must remain club-scoped and fail closed across clubs.

## Secretary Visibility

Secretary should be able to view member finance status for coordination and follow-up purposes.

Allowed Secretary fields:

- member identity summary
- current dues amount
- pending dues items
- last payment date
- payment status label
- receipt availability

Secretary view should not become an accounting control surface.

## President/Admin Visibility

President/Admin should be able to view club-level finance summaries and selected member finance summaries within the same club.

Allowed President/Admin fields:

- member identity summary
- current dues amount
- pending dues items
- last payment amount
- last payment date
- receipt availability
- outstanding totals
- collection totals
- member-wise status labels

President/Admin access must remain club-scoped and fail closed across clubs.

## Summary Fields

Future finance summary response may include:

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

Pending items may include:

- monthly dues
- membership fee
- fellowship contribution
- special collection
- project share
- fine or adjustment if separately authorized later

## Member-Friendly Wording

Normal Member screens and API-facing labels should avoid internal accounting terms.

Preferred member-facing wording:

- Current Dues
- Pending Items
- Last Payment
- Receipt Available
- Paid
- Partly Paid
- Pending
- Advance

Avoid member-facing wording:

- debit
- credit
- ledger posting
- receivable
- journal
- accounting entry
- reversal
- audit trail

## Privacy and Access Rules

Future implementation must enforce:

- unauthenticated access fails closed
- normal member can view only own finance summary
- Treasurer can view same-club member finance summaries
- Secretary can view same-club member finance summaries
- President/Admin can view same-club member and club-level finance summaries
- cross-club member finance access fails closed
- finance summary remains read-only
- route must not mutate payments, receipts, or ledger entries

## Explicitly Not Authorized in 3AG

Milestone 3AG does not authorize or add:

- routes
- controllers
- services
- requests
- resources
- models
- migrations
- frontend finance screens
- outstanding dues read API
- member finance summary read API
- Treasurer finance dashboard
- Secretary finance dashboard
- President finance dashboard
- special collection creation workflow
- fellowship payment allocation workflow
- payment correction workflow
- payment reversal workflow
- payment cancellation workflow
- reminder implementation
- finance reports
- Excel import/export
- PDF changes
- payment mutation
- receipt mutation
- ledger mutation

## Future Scope Boundaries

Future implementation should be split narrowly.

Recommended sequence:

1. 3AH — Outstanding Dues and Member Finance Summary Read API Authorization
2. 3AI — Outstanding Dues and Member Finance Summary Read API Narrow Implementation
3. 3AJ — Outstanding Dues and Member Finance Summary Read API Validation and Closure

Frontend finance screens should come only after the backend read API is validated.

## Allowed Files in 3AG

Only these files are allowed for this milestone:

- docs/milestone_3ag_outstanding_dues_member_finance_summary_scope_gate.md
- tests/Feature/Milestone3agOutstandingDuesMemberFinanceSummaryScopeGateTest.php

## Closure Rule

Milestone 3AG defines scope only.

No finance summary implementation is allowed in this milestone.