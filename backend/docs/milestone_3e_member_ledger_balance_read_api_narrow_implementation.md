# Milestone 3E - Member Ledger Balance Read API Narrow Implementation

## Status

Milestone 3E implements a narrow read-only API for member ledger balance summaries.

This milestone follows:

- Milestone 3A - Member Account Ledger and Dues Foundation Scope Gate
- Milestone 3B - Member Account Ledger and Dues Database Foundation
- Milestone 3C - Member Ledger Balance Calculation Foundation
- Milestone 3D - Member Ledger Balance Read API Scope Gate

## Purpose

The purpose of this milestone is to expose the existing member ledger balance calculation service through tightly controlled read-only API routes.

## Implemented Scope

Milestone 3E may implement only:

- Read-only own member balance endpoint
- Read-only selected member balance endpoint for authorized roles
- Controller for returning balance summary
- Route registration under authenticated club scope
- Club scoping
- Role visibility checks
- Tests proving normal members cannot read other members' balances
- Tests proving Treasurer, Secretary, and President/Admin can read member-wise balance summaries

## Routes

The implemented read-only routes are:

- GET /api/v1/clubs/{club}/members/me/balance
- GET /api/v1/clubs/{club}/members/{member}/balance

Both routes require authentication.

Both routes require club access through the existing club permission middleware.

## Visibility Rules

Normal Member:

- May read own balance through the own balance endpoint
- May read own selected member balance only when the member record belongs to the authenticated user
- Must not read other members' balances

Treasurer:

- May read member-wise balance summaries within the club
- Read-only access only

Secretary:

- May read member-wise balance summaries within the club for coordination
- Read-only access only

President/Admin:

- May read member-wise balance summaries within the club for supervision
- Read-only access only

## Response Boundary

The API returns only balance summary data from the existing balance service:

- club_id
- member_id
- debit_total
- credit_total
- balance
- balance_type

The API does not return ledger entry lists, receipts, audit logs, payment history, or detailed finance reports.

## Privacy Boundary

Normal members must not access other members' balance summaries.

Selected member balance lookup must verify:

- The target member belongs to the requested club
- The authenticated user has an active club membership
- The authenticated user's role is allowed
- Normal member access is restricted to their own linked member record

## Legacy Scope Test Update

Earlier milestones blocked balance routes because balance API implementation was not yet authorized.

Milestone 3E is the authorized read-only balance API milestone.

Therefore, 3E may narrowly update earlier route-absence tests so they continue blocking payment, receipt, reminder, report, Excel, and ledger mutation workflows while allowing the authorized read-only balance routes.

## Not Implemented In 3E

The following are not allowed in this milestone:

- No payment recording
- No receipt generation
- No ledger entry listing
- No ledger mutation
- No dues creation
- No special collection creation
- No fellowship payment workflow
- No reminder automation
- No reports
- No Excel import
- No frontend changes
- No dashboard
- No member-facing screen implementation

## Closure Criteria

Milestone 3E can be closed only when:

- Own balance endpoint exists
- Selected member balance endpoint exists
- Both endpoints are read-only GET routes
- Normal member can read only own balance
- Normal member cannot read another member's balance
- Treasurer can read member balance summaries
- Secretary can read member balance summaries
- President/Admin can read member balance summaries
- Cross-club member balance lookup fails closed
- Payment, receipt, reminder, report, Excel, and mutation workflows remain blocked
- Focused tests pass
- Full backend test suite passes
- Git status shows only approved 3E files before commit