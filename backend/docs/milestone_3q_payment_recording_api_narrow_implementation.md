# Milestone 3Q — Payment Recording API Narrow Implementation

## Status

Narrow implementation.

## Purpose

Milestone 3Q adds the first protected API route for recording a member payment.

The API is only a transport layer over PaymentRecordingService. It does not duplicate payment creation, ledger credit creation, or payment-to-ledger linkage logic.

## Implemented Scope

- routes/api.php
- PaymentController
- RecordPaymentRequest
- PaymentResource
- One protected POST route
- 3Q implementation test

## Implemented Route

POST /api/v1/clubs/{club}/members/{member}/payments

## Behaviour

The route:

1. Requires authentication.
2. Requires active club permission through payments.record.
3. Confirms the route member belongs to the route club.
4. Validates payment input through RecordPaymentRequest.
5. Rejects backend-controlled client fields.
6. Calls PaymentRecordingService.
7. Returns a safe PaymentResource response.
8. Creates a posted payment and posted member ledger credit through the service.

## Explicitly Not Included

- No payment list route
- No payment detail route
- No update route
- No delete route
- No cancel route
- No reverse route
- No receipt generation
- No receipt numbering
- No special collection allocation
- No fellowship payment workflow
- No reminders
- No reports
- No Excel import
- No frontend
- No service behavior change

## Boundary Decision

Milestone 3Q implements only the narrow payment recording API authorized by Milestone 3P.

Receipt generation, receipt numbering, cancellation, reversal, special collection allocation, fellowship payment workflow, reminders, reports, Excel import, and frontend integration remain blocked until separately scoped and authorized.