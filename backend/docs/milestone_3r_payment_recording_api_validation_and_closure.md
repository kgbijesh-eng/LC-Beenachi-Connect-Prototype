# Milestone 3R - Payment Recording API Validation and Closure

## Status

Validation and closure checkpoint.

## Purpose

Milestone 3R validates and closes the Milestone 3Q Payment Recording API narrow implementation.

This milestone is docs/tests-only. It does not modify routes, controllers, request classes, resource classes, services, frontend screens, receipt behaviour, cancellation/reversal behaviour, special collection allocation, fellowship payment workflow, reminders, reports, or Excel import.

## Validated 3Q Implementation

Milestone 3Q added:

- One protected payment recording POST route
- PaymentController
- RecordPaymentRequest
- PaymentResource
- 3Q implementation document
- 3Q focused implementation test
- Compatibility updates to older Milestone 3 route-absence tests

## Validated Route

POST /api/v1/clubs/{club}/members/{member}/payments

The route must remain the only payment mutation route.

## Validation Expectations

The 3R validation confirms:

1. The 3Q route exists.
2. The 3Q route is POST-only.
3. No payment list, detail, update, delete, cancel, reverse, receipt, reminder, report, Excel import, or frontend route exists.
4. PaymentController delegates to PaymentRecordingService.
5. PaymentController does not manually create Payment records.
6. PaymentController does not manually create MemberLedgerEntry records.
7. PaymentController does not manually link ledger_entry_id outside PaymentRecordingService.
8. RecordPaymentRequest accepts only the approved input fields.
9. RecordPaymentRequest prohibits backend-controlled fields.
10. PaymentResource exposes only the approved safe response fields.
11. Authenticated authorized users can record a payment.
12. The API creates the payment and ledger credit through PaymentRecordingService authority.
13. Unauthorized normal members cannot record payments.
14. Cross-club member payment recording fails closed.
15. Invalid input fails validation.
16. Receipt generation, receipt numbering, cancellation, reversal, special collection allocation, fellowship payment workflow, reminders, reports, Excel import, and frontend remain absent.

## Explicitly Not Included

- No route change
- No controller change
- No request class change
- No resource class change
- No service change
- No frontend
- No receipt generation
- No receipt numbering
- No cancellation or reversal workflow
- No special collection allocation
- No fellowship payment workflow
- No reminders
- No reports
- No Excel import

## Closure Decision

Milestone 3Q is validated and ready to be treated as closed.

The backend now has a narrow protected API route for recording normal member payments through PaymentRecordingService.

Receipt generation, receipt numbering, cancellation/reversal, special collection allocation, fellowship payment workflow, reminders, reports, Excel import, and frontend integration remain blocked until separately scoped and authorized.