# Milestone 3BK - Payment History Read-Only Scope Gate

## Status

Milestone 3BK is a docs/tests-only scope gate.

It does not implement payment history APIs.

It does not implement frontend payment history display.

It does not change frontend runtime behavior.

It does not change backend runtime behavior.

## Base

Base milestone:

- Milestone 3BJ - Finance Module Post Reports Frontend Closure Review and Next Scope Decision

Base branch:

- milestone-3bj-finance-module-post-reports-frontend-closure-review-next-scope-decision

Scope gate branch:

- milestone-3bk-payment-history-read-only-scope-gate

## Purpose

The purpose of 3BK is to decide whether a future read-only payment history feature is allowed, who can see what, what fields are safe to expose, and what remains blocked before any API or frontend implementation begins.

3BK authorizes no runtime implementation.

3BK keeps the target narrow:

- View payment records only.
- Do not edit payment records.
- Do not cancel payment records.
- Do not reverse payment records.
- Do not correct payment records.
- Do not allocate or reallocate payment records.

## Allowed files

Only these files are allowed in 3BK:

- backend/docs/milestone_3bk_payment_history_read_only_scope_gate.md
- backend/tests/Feature/Milestone3bkPaymentHistoryReadOnlyScopeGateTest.php

## Blocked files and scope

3BK must not edit:

- app/page.tsx
- backend routes
- backend controllers
- backend services
- backend resources
- backend requests
- backend models
- backend middleware
- backend migrations
- backend seeders
- backend config
- API behavior
- payment history API implementation
- frontend payment history display
- export files
- PDF files
- Excel files
- reminder files
- payment mutation files
- receipt mutation files
- cancellation workflow files
- reversal workflow files
- allocation workflow files
- deployment files
- SaaS files
- client-specific hardcoding

## Final scope decision

A future read-only payment history feature is allowed only if it remains inside this boundary:

- Normal Member may see only their own payment history.
- President/Admin, Secretary, and Treasurer may see club-scoped read-only payment history.
- Future selected-member payment history view may be authorized first.
- Future all-member payment history list may be authorized only if tightly filtered and paginated in a later implementation milestone.
- No broader admin-only finance access may be introduced silently.
- Cross-club access must fail closed.
- Backend must remain the source of truth.

## Role visibility boundary

Normal Member:

- May see only payment records tied to their own member identity.
- Must not see other members' payment records.
- Must not see all-member payment history.
- Must not see club-wide payment history.

President/Admin, Secretary, and Treasurer:

- May see club-scoped read-only payment history where backend authorization allows.
- May use a selected-member payment history view first in a future implementation milestone.
- May use an all-member list only if a later implementation milestone defines tight filters and pagination.
- Must remain blocked from cross-club payment records.

Backend authorization remains the source of truth for all role and club boundaries.

## Future read-only payment history boundary

Future payment history may read existing payment records only.

Future payment history must not:

- create payments
- update payments
- delete payments
- cancel payments
- reverse payments
- correct payments
- allocate payments
- reallocate payments
- generate receipts
- mutate receipts
- export records
- send reminders or notifications
- calculate balances
- invent totals
- invent accounting classifications

## Safe future fields to evaluate

Future implementation may evaluate these fields for safe read-only exposure:

- payment ID only if safe and public-facing enough, otherwise avoid exposing raw internal ID
- payment date
- amount
- payment mode
- status
- member name or basic member reference for officer view only
- receipt number or reference if already generated
- receipt availability
- recorded by display name or role if already safe
- created or generated timestamp if safe
- remarks or notes only if explicitly authorized and safe

3BK does not itself expose these fields.

3BK does not decide that raw internal IDs or notes are safe by default.

## Safe future states

A future payment history implementation should define safe states before implementation:

- loading
- empty or no payment history
- missing active club context
- unauthenticated or 401
- forbidden or 403
- API error
- successful member payment history display
- successful selected-member officer payment history display
- safe handling for missing or null fields

## Existing models and APIs for context only

The following may be reviewed for context only and must not be changed in 3BK:

- Payment
- Receipt
- Member
- Club
- ClubMembership
- Role
- User
- MemberLedgerEntry
- auth/me
- club/me
- member summary
- payment recording
- receipt view
- receipt PDF
- club finance reports
- balance sheet

3BK confirms there is no payment history API implementation in this milestone.

Future payment history API shape must be authorized separately before implementation.

## Blocked future behavior

Future payment history work must not include:

- create payment behavior
- update payment behavior
- delete payment behavior
- cancellation
- reversal
- correction
- allocation
- reallocation
- receipt generation
- receipt mutation
- export
- PDF
- Excel
- reminders
- notifications
- audit dashboard
- deployment implementation
- SaaS implementation
- frontend accounting calculations
- invented balances
- invented totals
- client-specific behavior

## Client-specific hardcoding rule

Future payment history work must preserve the one shared codebase strategy.

It must not hardcode special behavior for:

- Lions Club Beenachi
- Rotary Central Bathery
- any future pilot club

Allowed future behavior must use runtime club and member data returned by backend authorization and data APIs.

## Expected validation for a future implementation authorization

A future implementation authorization milestone should verify:

- selected-member payment history scope
- member-own payment history scope
- role visibility boundary
- cross-club fail-closed behavior
- safe field list
- blocked mutation workflows
- backend source-of-truth rule
- empty/loading/error states
- pagination/filter requirement before any all-member list
- no frontend display until separately authorized

## Next milestone

The expected next milestone is:

Milestone 3BL - Payment History Read-Only Implementation Authorization

3BL should remain docs/tests-only unless the human explicitly authorizes a different scope.

3BL may authorize a future narrow implementation only after it confirms 3BK scope boundaries.
