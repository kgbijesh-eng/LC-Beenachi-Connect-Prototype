# Milestone 3D - Member Ledger Balance Read API Scope Gate

## Status

Milestone 3D is a docs/tests-only scope gate.

This milestone authorizes the rules for a future read-only member ledger balance API, but does not implement any API route, controller, policy, resource, frontend screen, payment workflow, receipt workflow, reminder, report, or Excel import.

## Purpose

Milestone 3D defines who may read member ledger balances and what each role may see before any API implementation begins.

This milestone follows:

- Milestone 3A - Member Account Ledger and Dues Foundation Scope Gate
- Milestone 3B - Member Account Ledger and Dues Database Foundation
- Milestone 3C - Member Ledger Balance Calculation Foundation

## Core Principle

Balance visibility must follow privacy-first role-based access.

A member's financial balance is private data.

Normal members must not see other members' balances, payment status, ledger entries, outstanding dues, advance amounts, or collection summaries.

## Future Read-Only API Direction

A later milestone may implement read-only balance APIs.

Approved future API direction may include:

- A normal member reading only their own balance summary
- Treasurer reading member-wise balance summaries for finance work
- Secretary reading limited balance status only when needed for coordination
- President/Admin reading member-wise balance summaries for supervision

The future API must be read-only.

The future API must not record payments, generate receipts, create dues, create special collections, or mutate ledger entries.

## Normal Member Visibility

A normal member may see only their own balance summary.

A normal member may see:

- Own current balance
- Own balance type
- Own total posted debits
- Own total posted credits
- Own current amount due, clear, or advance status

A normal member must not see:

- Other members' balances
- Other members' debit totals
- Other members' credit totals
- Other members' payment status
- Other members' advance status
- Club-wide outstanding list
- Member-wise collection summary
- Treasurer-only finance data
- President/Admin oversight data
- Ledger audit data

## Treasurer Visibility

The Treasurer may read member-wise balance summaries for finance management.

The Treasurer may see:

- All member balance summaries within the club
- Member-wise amount due
- Member-wise clear status
- Member-wise advance status
- Posted debit totals
- Posted credit totals
- Outstanding summary for finance follow-up

The Treasurer read API must remain read-only.

Treasurer access in this scope gate does not authorize:

- Payment recording
- Receipt generation
- Ledger correction
- Ledger cancellation
- Dues creation
- Special collection creation
- Report generation

## Secretary Visibility

The Secretary may need limited balance visibility for coordination.

The Secretary may see:

- Whether a member has an amount due
- Whether a member is clear
- Whether a member has advance balance
- Limited member-wise balance status for communication coordination

The Secretary should not automatically get full finance control.

Secretary access in this scope gate does not authorize:

- Payment recording
- Receipt generation
- Ledger correction
- Ledger cancellation
- Full finance reports
- Excel export
- Treasurer-only collection controls

## President/Admin Visibility

President/Admin may read member-wise balance summaries for supervision and governance.

President/Admin may see:

- All member balance summaries within the club
- Member-wise amount due
- Member-wise clear status
- Member-wise advance status
- Posted debit totals
- Posted credit totals
- Outstanding summary for oversight

President/Admin access in this scope gate remains read-only.

President/Admin read access does not authorize payment recording, receipt generation, ledger mutation, reminders, reports, Excel import, or frontend implementation in this milestone.

## Future Route Boundary

A later API milestone may authorize narrow read-only routes such as:

- Own member balance summary
- Selected member balance summary for authorized roles
- Club member balance summary list for authorized finance/leadership roles

Future routes must be protected by authentication and club-role permissions.

Future routes must ensure club scoping.

Future routes must prevent normal members from reading other members' balances.

## Data Boundary

Future read APIs should return only balance summary data unless separately authorized.

Allowed future summary fields may include:

- club_id
- member_id
- debit_total
- credit_total
- balance
- balance_type

Ledger entry lists, audit logs, receipts, and detailed payment history are outside this scope gate.

## Not Implemented In 3D

The following are not allowed in Milestone 3D:

- No API route
- No controller
- No Laravel policy
- No request class
- No resource class
- No frontend changes
- No payment recording
- No receipt generation
- No ledger mutation
- No dues creation workflow
- No special collection workflow
- No fellowship payment workflow
- No reminder automation
- No reports
- No Excel import
- No dashboard
- No member-facing financial screen
- No Treasurer dashboard

## Closure Criteria

Milestone 3D can be closed only when:

- The scope document exists
- The document defines normal member visibility
- The document defines Treasurer visibility
- The document defines Secretary visibility
- The document defines President/Admin visibility
- The document defines future read-only API boundaries
- The document blocks payment, receipt, reminder, report, Excel, frontend, and ledger mutation work
- The matching test file validates the scope gate
- Focused tests pass
- Full backend test suite passes
- Git status shows only approved 3D docs/tests files before commit