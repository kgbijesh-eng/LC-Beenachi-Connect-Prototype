# Milestone 3BM - Payment History Read-Only Narrow Implementation

## Status

Milestone 3BM is a narrow backend read-only API implementation milestone.

It implements payment history read APIs only.

It does not implement frontend payment history display.

It does not implement an all-member payment history endpoint.

It does not mutate payment, receipt, ledger, member, or club records.

## Base

Base milestone:

- Milestone 3BL - Payment History Read-Only Implementation Authorization

Base branch:

- milestone-3bl-payment-history-read-only-implementation-authorization

Implementation branch:

- milestone-3bm-payment-history-read-only-narrow-implementation

## Runtime files changed

Allowed runtime files changed:

- backend/routes/api.php
- backend/app/Http/Controllers/Api/V1/Finance/PaymentHistoryController.php
- backend/app/Http/Resources/Finance/PaymentHistoryResource.php

Allowed docs/tests files changed:

- backend/docs/milestone_3bm_payment_history_read_only_narrow_implementation.md
- backend/tests/Feature/Milestone3bmPaymentHistoryReadOnlyNarrowImplementationTest.php

## API routes added

3BM adds only GET read-only routes:

- GET /api/v1/clubs/{club}/members/me/payments/history
- GET /api/v1/clubs/{club}/members/{member}/payments/history

3BM does not add:

- all-member payment history endpoint
- club-wide payment history endpoint
- payment creation endpoint
- payment update endpoint
- payment delete endpoint
- cancellation endpoint
- reversal endpoint
- allocation endpoint
- export endpoint

## Controller behavior

PaymentHistoryController implements:

- own() for authenticated member own-payment-history lookup.
- show() for selected-member club-scoped payment history lookup.

The controller:

- scopes all payment history to the requested club.
- scopes member history to the selected member.
- blocks selected-member access unless the active role is President/Admin, Secretary, or Treasurer.
- returns 404 when the selected member does not belong to the requested club.
- uses capped pagination.
- returns read-only response data.

No request class was added.

No service class was added.

No model change was added.

## Resource behavior

PaymentHistoryResource exposes safe read-only payment metadata:

- payment_id
- payment_date
- amount
- payment_mode
- status
- member id, member code, and member name
- receipt availability
- issued receipt number when available
- issued receipt status when available
- recorded-by id and name
- created_at

PaymentHistoryResource does not expose:

- notes
- cancellation fields
- reversal fields
- allocation details
- ledger internals
- receipt notes
- audit internals
- invented balances
- invented totals

## Role visibility

Normal Member:

- can access only GET /api/v1/clubs/{club}/members/me/payments/history.
- sees only payment history tied to their own member profile.
- cannot access GET /api/v1/clubs/{club}/members/{member}/payments/history.
- cannot see another member's payment history.
- cannot see an all-member payment history list.

President/Admin, Secretary, and Treasurer:

- can access selected-member club-scoped payment history.
- can use GET /api/v1/clubs/{club}/members/{member}/payments/history.
- remain blocked from cross-club member payment history.

Backend authorization remains the source of truth.

## Pagination

Both routes use pagination.

Default per_page is 10.

Maximum per_page is capped at 25.

Invalid or less-than-one per_page values fall back to the default.

3BM does not add optional filters.

3BM does not add an all-member list.

## Blocked behavior

3BM does not add:

- frontend payment history display
- payment create behavior
- payment update behavior
- payment delete behavior
- payment cancellation
- payment reversal
- payment correction
- payment allocation
- payment reallocation
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

3BM preserves the one shared codebase strategy.

It does not hardcode special behavior for:

- Lions Club Beenachi
- Rotary Central Bathery
- any future pilot club

Payment history behavior is driven by runtime club, member, role, payment, and receipt records protected by backend authorization.

## Validation commands

Expected validation commands:

- php artisan test --filter=Milestone3bmPaymentHistoryReadOnlyNarrowImplementationTest
- php artisan test
- git status --short --branch
- git diff --stat
- git diff --check
- git diff --cached --stat
- git diff --cached --check

## Next milestone

The expected next milestone is:

Milestone 3BN - Payment History Read-Only Validation and Closure
