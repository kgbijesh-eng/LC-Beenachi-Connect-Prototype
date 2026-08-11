# Milestone 3L - Payment Recording Service Implementation Authorization

## Status

Milestone 3L is a docs/tests-only implementation authorization checkpoint.

This milestone authorizes a future narrow implementation of payment recording service behavior, but does not implement the service.

Milestone 3L does not create any service class, controller, route, request class, resource class, API endpoint, ledger mutation implementation, receipt generation implementation, frontend screen, reminder, report, or Excel import.

## Purpose

The purpose of Milestone 3L is to define exactly what a later implementation milestone may build.

Milestone 3K defined the payment recording service scope gate.

Milestone 3L authorizes a future narrow implementation milestone to create the service layer only, within strict boundaries.

## Authorization Decision

Decision:

PAYMENT_RECORDING_SERVICE_NARROW_IMPLEMENTATION_AUTHORIZED

This decision authorizes opening a later implementation milestone:

3M - Payment Recording Service Narrow Implementation

Milestone 3L itself does not implement payment recording behavior.

## Prior Milestone Chain

This authorization follows:

- Milestone 3G - Payment Recording Scope Gate
- Milestone 3H - Payment Recording Database Foundation Authorization
- Milestone 3I - Payment Recording Database Foundation Narrow Implementation
- Milestone 3J - Payment Recording Database Foundation Validation and Closure
- Milestone 3K - Payment Recording Service Scope Gate

## Authorized Future Service

A future 3M implementation may create:

PaymentRecordingService

Recommended path:

app/Services/Payments/PaymentRecordingService.php

The final implementation may add a narrow service result object or internal DTO only if required by tests.

No controller, route, API request, API resource, or frontend integration is authorized by 3L.

## Core Principle

The member ledger remains the source of truth for balances.

The future service must not directly overwrite member balances.

A posted payment must affect balance only through a posted member ledger credit entry.

Future balance calculation must continue to use:

Total Debits - Total Credits = Current Balance

## Authorized Future 3M Scope

A later 3M implementation may add only:

- PaymentRecordingService
- payment recording service tests
- transaction-safe payment creation
- transaction-safe member ledger credit creation
- payment-to-ledger linkage
- role/permission checks
- club/member validation
- amount/date/payment mode validation
- safe service result return
- no route/controller/API wiring

The service may create a payment record.

The service may create a linked member ledger credit entry.

The service may link payments.ledger_entry_id to the created member_ledger_entries record.

The service may return a structured result for internal backend use.

## Authorized Service Method Boundary

A future service method may accept an authenticated actor, selected club, selected member, and validated payment data.

Recommended future method shape may be similar to:

recordPayment(actor, club, member, data)

The exact method signature must be decided during 3M.

The method must remain internal service behavior only.

No public API endpoint is authorized in 3M unless a later API scope gate authorizes it.

## Permission Boundary

The future service must require club-scoped payment recording permission.

Recommended permission name:

payments.record

The final permission name must be confirmed during implementation.

The permission must be checked against the selected club.

An authenticated user without active club membership must fail closed.

A user with membership in another club must fail closed.

## Role Boundary

The future service may authorize payment recording only for approved roles.

Primary role:

- Treasurer

Possible supervisory role:

- President/Admin

Not automatically authorized:

- Secretary

Never authorized:

- Normal Member

Secretary payment recording must remain blocked unless a later milestone explicitly authorizes it.

Normal members must never record payments.

## Input Validation Boundary

The future service must validate:

- club is required
- member is required
- authenticated actor is required
- amount is required
- amount must be positive
- payment_date is required
- payment_date must be valid
- payment_mode is required
- payment_mode must be allowed
- reference_number is optional
- notes are optional
- member must belong to selected club
- actor must have active membership in selected club
- actor must have payment recording permission in selected club

## Allowed Payment Modes

The future service must use the payment modes already defined by the Payment model and database foundation.

Allowed modes include:

- cash
- bank_transfer
- upi
- cheque
- card
- adjustment

The service must reject unsupported payment modes.

## Transaction Safety Authorization

The future service must use a database transaction.

The following must succeed or fail together:

- payment record creation
- member ledger credit creation
- payment-to-ledger linkage

If ledger credit creation fails, payment creation must roll back.

If payment-to-ledger linkage fails, both payment and ledger credit must roll back.

The service must not leave orphan payment records or orphan ledger credit entries.

## Ledger Credit Authorization

The future service may create a member ledger credit entry only for a valid posted payment.

The ledger entry must use:

- same club as the payment
- same member as the payment
- entry_type: credit
- source_type: payment
- amount equal to payment amount
- transaction_date equal to payment date
- reference_number from payment reference when available
- created_by_user_id from authenticated actor
- status: posted

The service must not modify existing debit entries.

The service must not manually update stored member balance values.

## Payment Record Authorization

The future service may create a payment record using:

- club_id
- member_id
- amount
- payment_date
- payment_mode
- reference_number
- notes
- received_by_user_id
- ledger_entry_id
- status

For a successfully recorded payment, status should be posted unless 3M explicitly chooses another narrow safe default.

Draft workflows are not authorized in 3L.

## Receipt Boundary

Receipt generation is not authorized in 3L.

The future 3M service must not:

- generate receipt numbers
- create receipt records
- issue receipt PDFs
- send receipt messages
- expose receipt APIs

Receipt generation must be separately scoped later.

## Cancellation And Reversal Boundary

Payment cancellation and reversal are not authorized in 3L.

The future 3M service must not implement:

- payment cancellation
- payment reversal
- ledger reversal entry creation
- cancellation reason capture
- cancellation controls
- reversal controls

Cancellation and reversal must require separate future authorization.

## Special Collection Boundary

Special collection allocation is not authorized in 3L.

The future 3M service must not allocate payments to special collections.

Special collection payment allocation must be separately scoped.

## Fellowship Payment Boundary

Fellowship payment recording is not authorized in 3L.

The future 3M service must not implement fellowship payment recording.

Fellowship payment workflow must be separately scoped.

## API Boundary

No API work is authorized in 3L.

The future 3M implementation must not add:

- payment routes
- receipt routes
- payment controllers
- receipt controllers
- API request classes
- API resource classes
- route registration
- frontend integration

Payment API work must require a later separate API scope gate.

## Audit Boundary

The future service must preserve audit-relevant fields:

- who recorded the payment
- selected club
- selected member
- amount
- payment mode
- payment date
- reference number
- linked ledger entry
- status
- timestamps

President/Admin must not bypass audit trail requirements.

## Cross-Club Safety Boundary

The future service must fail closed when:

- member does not belong to selected club
- actor does not belong to selected club
- actor lacks permission in selected club
- payment club and ledger club would differ
- payment member and ledger member would differ

Cross-club payment recording must never be allowed.

## Normal Member Boundary

Normal members must not record payments.

Normal members must not access internal payment recording service behavior.

Normal members may later see only safe own-payment summaries through separately authorized read APIs.

## Forbidden In 3L

The following are forbidden in Milestone 3L:

- No PaymentRecordingService implementation
- No service class
- No payment controller
- No receipt controller
- No payment route
- No receipt route
- No request class
- No resource class
- No payment API
- No receipt API
- No ledger mutation implementation
- No payment posting implementation
- No receipt generation implementation
- No receipt numbering implementation
- No payment cancellation implementation
- No payment reversal implementation
- No special collection allocation
- No fellowship payment workflow
- No frontend changes
- No dashboard
- No reminder automation
- No reports
- No Excel import

## 3M Implementation Guardrails

A future 3M implementation must remain narrow.

3M may implement the internal service only.

3M must not expose the service through routes or controllers.

3M must not implement receipt generation.

3M must not implement cancellation or reversal.

3M must not implement frontend screens.

3M must include tests proving transaction safety, permission safety, cross-club safety, and ledger consistency.

## Closure Criteria

Milestone 3L can be closed only when:

- The authorization document exists
- The document records the authorization decision
- The document defines future 3M scope
- The document defines the service boundary
- The document defines permission and role boundaries
- The document defines input validation boundaries
- The document defines transaction safety requirements
- The document defines ledger credit authorization
- The document defines receipt, cancellation, reversal, special collection, and fellowship exclusions
- The document blocks implementation in 3L
- The matching test file validates the authorization
- Focused tests pass
- Milestone3 test chain passes
- Full backend test suite passes
- Git status shows only approved 3L docs/tests files before commit