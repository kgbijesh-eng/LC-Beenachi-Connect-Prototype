# Milestone 3O — Payment Recording API Scope Gate

## Status

Scope gate only.

## Purpose

Milestone 3O defines the future API boundary for recording member payments through the backend.

This milestone is docs/tests-only. It does not create routes, controllers, requests, resources, frontend screens, receipt generation, receipt numbering, cancellation/reversal workflows, special collection allocation, fellowship payment workflow, reminders, reports, or Excel import.

## Existing Foundation

The current foundation already includes:

- PaymentRecordingService
- Payment model
- MemberLedgerEntry model
- Payment table foundation
- Receipt table foundation
- Member ledger balance foundation
- Role/permission foundation
- Protected member and club read/create/update foundations

Milestone 3M implemented the internal service for recording payments.

Milestone 3N validated and closed the internal service implementation.

## Future API Candidate

A later milestone may add a narrow protected API endpoint for recording a normal member payment:

POST /api/v1/clubs/{club}/members/{member}/payments

This future route is not implemented in 3O.

## Future Controller Boundary

A later implementation milestone may create PaymentController.

Allowed future controller responsibility:

- Accept the authenticated user.
- Resolve the selected club.
- Resolve the selected member within that club.
- Pass validated input to PaymentRecordingService.
- Return a safe API response.
- Fail closed for unauthorized users, cross-club members, invalid input, inactive clubs, and inactive members.

Not allowed inside the future controller:

- No manual ledger mutation outside the service.
- No receipt generation.
- No receipt numbering.
- No cancellation/reversal.
- No special collection allocation.
- No fellowship payment workflow.
- No reminders.
- No reports.
- No Excel import.

## Future Request Boundary

A later implementation milestone may create RecordPaymentRequest.

Expected validation fields:

- amount
- payment_date
- payment_mode
- reference_number
- notes

Validation expectations:

- amount is required, numeric, and greater than zero.
- payment_date is required and must be a valid date.
- payment_mode is required and must match supported Payment::MODES.
- reference_number is optional and length-limited.
- notes is optional and length-limited.

The request must not accept:

- club_id
- member_id
- received_by_user_id
- ledger_entry_id
- status
- cancelled_at
- cancelled_by_user_id
- cancellation_reason
- receipt number fields

Those fields must remain controlled by backend logic.

## Future Resource Boundary

A later implementation milestone may create PaymentResource.

Expected safe response shape:

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

The response may include a minimal ledger linkage indicator.

The response must not expose unrelated member private data, other members' balances, receipt numbering internals, cancellation internals beyond the payment status, or raw audit metadata.

## Permission Boundary

The future route must require authentication and club access.

The future route must require the existing permission:

payments.record

Expected role behaviour:

- President/Admin may record member payments if the role has payments.record.
- Treasurer may record member payments if the role has payments.record.
- Secretary may record member payments only if the role is explicitly granted payments.record.
- Normal Member must not record payments for themselves or others through this API.

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

## Service Boundary

The future API must call PaymentRecordingService.

The API must not duplicate service behaviour.

The following must remain inside the service:

- transaction-safe payment creation
- transaction-safe ledger credit creation
- payments.ledger_entry_id linkage
- role/permission safety checks
- club/member safety checks
- amount/date/payment mode validation

## Explicitly Not Included in 3O

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

## Scope Gate Decision

Milestone 3O approves only the future design direction for a narrow payment recording API.

It does not authorize implementation in 3O.

A later milestone must separately authorize and implement the API route, controller, request, and resource if needed.