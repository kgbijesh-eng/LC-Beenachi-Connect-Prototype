# Milestone 3M — Payment Recording Service Narrow Implementation

## Status

Implemented for local validation.

## Purpose

Milestone 3M adds the internal backend service foundation for recording member payments.

This milestone is intentionally narrow. It creates only the internal service needed to record a member payment and create the matching member ledger credit safely.

## Implemented Scope

- `PaymentRecordingService`
- Internal service tests
- Transaction-safe payment creation
- Transaction-safe member ledger credit creation
- `payments.ledger_entry_id` linkage
- Role and permission checks through the existing club permission service
- Club access safety checks
- Active member safety checks
- Amount validation
- Payment date validation
- Payment mode validation

## Service Behaviour

The service records a member payment by:

1. Confirming the receiving user can access the member's club.
2. Confirming the receiving user has the `payments.record` permission.
3. Confirming the club is active.
4. Confirming the member is active.
5. Validating amount, payment date, and payment mode.
6. Creating a posted payment row.
7. Creating a posted member ledger credit row with `source_type = payment`.
8. Linking the payment to the ledger credit through `payments.ledger_entry_id`.

The payment row and ledger entry row are created inside a single database transaction.

## Explicitly Not Included

- No route
- No controller
- No request class
- No resource class
- No frontend
- No receipt generation
- No receipt numbering
- No cancellation or reversal workflow
- No special collection allocation
- No fellowship payment workflow
- No reminders
- No reports
- No Excel import

## Boundary Decision

Milestone 3M is an internal service implementation milestone only.

Public payment recording APIs, receipt generation, receipt numbering, cancellation/reversal, special collection allocation, fellowship-specific workflows, reminders, reports, and Excel import must remain blocked until separately scoped and authorized.
