# Milestone 3P — Payment Recording API Implementation Authorization

## Status

Implementation authorization only.

## Purpose

Milestone 3P authorizes a later narrow API implementation milestone for recording member payments.

This milestone is docs/tests-only. It does not create routes, controllers, request classes, resource classes, frontend screens, service changes, receipt generation, receipt numbering, cancellation/reversal workflows, special collection allocation, fellowship payment workflow, reminders, reports, or Excel import.

## Background

Milestone 3M implemented the internal PaymentRecordingService.

Milestone 3N validated and closed the internal payment recording service.

Milestone 3O defined the future Payment Recording API scope gate.

Milestone 3P now authorizes a later narrow API implementation milestone, provided the future implementation stays inside the approved boundary.

## Future Implementation Candidate

The next implementation milestone may be:

Milestone 3Q — Payment Recording API Narrow Implementation

3P authorizes only that later narrow implementation. It does not implement it.

## Future Route Authorization

A later milestone may add exactly one protected payment recording route:

POST /api/v1/clubs/{club}/members/{member}/payments

Expected route purpose:

- Record one payment for one selected member.
- Create the payment through PaymentRecordingService.
- Return a safe payment response.
- Fail closed for unauthorized users, invalid club/member combinations, inactive clubs, inactive members, and invalid input.

The future API must not add list, update, delete, cancel, reverse, receipt generation, report, reminder, Excel import, fellowship payment, or special collection allocation routes.

## Future Files Authorized

A later implementation milestone may create or modify only the narrow files needed for the payment recording API:

- routes/api.php
- app/Http/Controllers/Api/V1/Finance/PaymentController.php
- app/Http/Requests/Finance/RecordPaymentRequest.php
- app/Http/Resources/Finance/PaymentResource.php
- tests/Feature/Milestone3qPaymentRecordingApiNarrowImplementationTest.php
- docs/milestone_3q_payment_recording_api_narrow_implementation.md

Any different file path requires a separate scope decision before implementation.

## Future Controller Guardrails

The future PaymentController must:

- Accept the authenticated user.
- Resolve the route club.
- Resolve the route member within that club.
- Use RecordPaymentRequest for input validation.
- Call PaymentRecordingService.
- Return PaymentResource.
- Fail closed for unauthorized users.
- Fail closed for cross-club member IDs.
- Fail closed for inactive clubs.
- Fail closed for inactive members.
- Avoid manual payment creation.
- Avoid manual ledger credit creation.
- Avoid manual ledger_entry_id linkage outside PaymentRecordingService.

The controller must not implement receipt generation, receipt numbering, cancellation, reversal, special collection allocation, fellowship workflow, reminders, reports, or Excel import.

## Future Request Guardrails

The future RecordPaymentRequest may accept only:

- amount
- payment_date
- payment_mode
- reference_number
- notes

The future request must not accept client-controlled backend fields:

- club_id
- member_id
- received_by_user_id
- ledger_entry_id
- status
- cancelled_at
- cancelled_by_user_id
- cancellation_reason
- receipt_number
- receipt_id

Validation expectations:

- amount is required, numeric, and greater than zero.
- payment_date is required and must be a valid date.
- payment_mode is required and must match Payment::MODES.
- reference_number is optional and length-limited.
- notes is optional and length-limited.

## Future Resource Guardrails

The future PaymentResource may expose only a safe payment response:

- id
- club_id
- member_id
- amount
- payment_date
- payment_mode
- reference_number
- notes
- status
- ledger_entry_id
- received_by_user_id
- created_at
- updated_at

It may include a minimal ledger linkage indicator.

It must not expose unrelated member private data, other members' balances, raw audit metadata, receipt numbering internals, cancellation internals, reversal internals, or full ledger history.

## Permission and Role Guardrails

The future API must require:

- authentication
- active club membership
- payments.record permission

Expected role behaviour:

- President/Admin may record payments only if the role has payments.record.
- Treasurer may record payments only if the role has payments.record.
- Secretary may record payments only if explicitly granted payments.record.
- Normal Member must not record payments for self or others.

The API must fail closed for:

- unauthenticated users
- users without active club membership
- users without payments.record
- cross-club member IDs
- inactive clubs
- inactive members
- invalid amount
- invalid payment date
- invalid payment mode

## Service Authority

PaymentRecordingService remains the source of payment recording behaviour.

The future API must not duplicate:

- transaction-safe payment creation
- transaction-safe ledger credit creation
- payments.ledger_entry_id linkage
- payment status assignment
- club/member safety checks
- payment validation rules already enforced by the service

The future API is only a protected transport layer over PaymentRecordingService.

## Explicitly Not Included in 3P

- No route
- No controller
- No request class
- No resource class
- No frontend
- No service change
- No receipt generation
- No receipt numbering
- No cancellation or reversal workflow
- No special collection allocation
- No fellowship payment workflow
- No reminders
- No reports
- No Excel import

## Authorization Decision

Milestone 3P authorizes opening a later narrow implementation milestone for the Payment Recording API.

It does not authorize implementation inside 3P.

The later implementation must stay limited to the single protected payment recording API route and must continue using PaymentRecordingService as the authority.