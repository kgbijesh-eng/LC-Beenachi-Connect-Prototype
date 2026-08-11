# Milestone 3K - Payment Recording Service Scope Gate

## Status

Milestone 3K is a docs/tests-only service scope gate.

This milestone defines the rules for a future payment recording service, but does not implement any service class, controller, route, request class, resource class, ledger mutation code, payment recording API, receipt generation code, frontend screen, reminder, report, or Excel import.

## Purpose

The purpose of Milestone 3K is to define the service-layer rules required before payment recording behavior is implemented.

Payment recording is a financial mutation workflow.

A future payment recording service must be transaction-safe, audit-safe, permission-aware, and ledger-consistent.

Milestone 3K follows:

- Milestone 3G - Payment Recording Scope Gate
- Milestone 3H - Payment Recording Database Foundation Authorization
- Milestone 3I - Payment Recording Database Foundation Narrow Implementation
- Milestone 3J - Payment Recording Database Foundation Validation and Closure

## Core Principle

The member ledger remains the source of truth for member balances.

A future payment recording service must not directly overwrite a member balance value.

A valid posted payment must create a posted member ledger credit entry.

Future balance calculation must continue to use:

Total Debits - Total Credits = Current Balance

## Future Payment Recording Service Responsibility

A future payment recording service may be responsible for:

- Validating club access
- Validating member belongs to the selected club
- Validating recorder has payment recording permission
- Validating payment amount
- Validating payment date
- Validating payment mode
- Creating a payment record
- Creating a linked ledger credit entry
- Linking the payment to the ledger entry
- Optionally preparing receipt linkage if separately authorized
- Returning a safe service result

The service must not expose internal audit notes to normal members.

## Future Service Name

A future implementation may introduce a service such as:

PaymentRecordingService

The final service name must be confirmed during implementation.

Milestone 3K does not create this service.

## Role Boundary

Future payment recording service behavior may be authorized only for finance/leadership roles.

Primary role:

- Treasurer

Possible supervisory role:

- President/Admin

Not automatically authorized:

- Secretary

Never authorized:

- Normal Member

Secretary payment recording must require a separate explicit authorization if ever needed.

Normal members must never record payments.

## Permission Boundary

A future payment recording service should require a permission such as:

payments.record

The final permission name must be confirmed during implementation.

The permission must be checked against the selected club context.

Having a global user account must not be enough.

The recorder must have an active role in the selected club.

## Input Validation Rules

A future payment recording service should validate:

- club_id is required
- member_id is required
- amount is required
- amount must be positive
- payment_date is required
- payment_date must be valid
- payment_mode is required
- payment_mode must be from the allowed list
- reference_number is optional
- notes are optional
- received_by_user_id must be the authenticated recorder or validated actor
- member must belong to the selected club
- recorder must have active authorized role in the selected club

## Allowed Payment Modes

Future payment modes may include:

- cash
- bank_transfer
- upi
- cheque
- card
- adjustment

The final allowed list must match the Payment model and database constraints.

## Transaction Safety Rule

Payment recording must be atomic.

A future service must use a database transaction so that:

- payment record creation
- ledger credit creation
- payment-to-ledger linkage
- optional receipt preparation if authorized later

either all succeed together or all fail together.

A payment must not be saved without the required linked ledger credit when the payment is posted.

A ledger credit must not be orphaned from its payment when created through payment recording.

## Ledger Credit Posting Rule

A valid posted payment must create a member ledger credit entry.

The ledger credit should use:

- club_id from payment
- member_id from payment
- entry_type: credit
- source_type: payment
- amount from payment
- transaction_date from payment_date
- description or notes from payment context
- reference_number from payment or receipt reference
- created_by_user_id from recorder
- status: posted

The ledger credit and payment must belong to the same club.

The ledger credit and payment must belong to the same member.

The ledger credit amount must match the payment amount.

## Payment Status Rule

Future payment service behavior should define status handling clearly.

Draft payments must not affect member balance.

Posted payments may affect member balance only through posted ledger credit entries.

Cancelled payments must not remain active as valid credits.

Reversed payments must require an explicitly authorized reversal workflow.

Milestone 3K does not authorize cancellation or reversal implementation.

## Receipt Linkage Boundary

A future payment recording service may prepare a payment for receipt linkage only if separately authorized.

Receipt generation is not authorized in 3K.

Receipt PDF generation is not authorized in 3K.

Receipt message sending is not authorized in 3K.

Receipt numbering behavior must remain separately authorized.

## Audit Requirements

A future payment recording service must preserve audit information including:

- who recorded the payment
- when the payment was recorded
- selected club
- selected member
- payment amount
- payment mode
- payment date
- reference number
- linked ledger entry
- status
- future cancellation or reversal actor when applicable
- future cancellation or reversal reason when applicable

Audit trail must not be bypassed by President/Admin.

## Cross-Club Safety

The future service must fail closed when:

- the member does not belong to the selected club
- the recorder does not belong to the selected club
- the recorder lacks payment recording permission for the selected club
- the payment and ledger entry club do not match
- the payment and ledger entry member do not match

Cross-club payment recording must never be allowed.

## Normal Member Visibility Boundary

Normal members may later see only their own payment confirmation and safe summary information.

Normal members must not see:

- other members' payments
- other members' receipts
- other members' balances
- internal audit notes
- Treasurer collection lists
- payment cancellation controls
- payment reversal controls

## Special Collection Boundary

A future payment recording service may support special collection allocation only after a separate special collection allocation authorization.

Milestone 3K does not authorize special collection allocation.

## Fellowship Payment Boundary

Fellowship payment recording must be separately scoped.

Milestone 3K does not authorize fellowship payment recording.

## Cancellation And Reversal Boundary

Payment cancellation and reversal are not authorized in Milestone 3K.

A future milestone must separately define:

- who can cancel
- who can reverse
- whether cancellation marks records cancelled
- whether reversal creates separate ledger entries
- how cancellation/reversal reasons are captured
- what normal members can see

Milestone 3K does not implement cancellation or reversal behavior.

## Future Implementation Direction

A later milestone may authorize narrow implementation of payment recording service behavior.

Recommended next sequence:

- 3L - Payment Recording Service Implementation Authorization
- 3M - Payment Recording Service Narrow Implementation
- 3N - Payment Recording Service Validation and Closure
- Later - Payment Recording API Scope Gate
- Later - Payment Recording API Implementation
- Later - Receipt Generation Scope Gate
- Later - Receipt Generation Implementation
- Later - Frontend Payment Recording Screen

## What 3L May Authorize If Separately Approved

A future 3L milestone may authorize a narrow service implementation.

Possible later implementation scope:

- PaymentRecordingService
- service input contract or DTO if needed
- transaction-safe payment creation
- transaction-safe ledger credit creation
- payment-to-ledger linkage
- permission checks
- validation tests
- no route/controller/API yet unless separately authorized

3L must not itself implement behavior unless explicitly defined as an implementation authorization only.

## Not Implemented In 3K

The following are not allowed in Milestone 3K:

- No service class
- No PaymentRecordingService
- No controller
- No payment route
- No receipt route
- No request class
- No resource class
- No payment recording API
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

## Closure Criteria

Milestone 3K can be closed only when:

- The scope document exists
- The document defines future payment recording service responsibility
- The document defines role and permission boundaries
- The document defines validation rules
- The document defines transaction safety rules
- The document defines ledger credit posting rules
- The document defines receipt linkage boundaries
- The document defines audit requirements
- The document defines cross-club safety rules
- The document defines cancellation and reversal exclusions
- The document defines what a later 3L may authorize
- The document blocks service/controller/route/API implementation in 3K
- The matching test file validates the scope gate
- Focused tests pass
- Milestone3 test chain passes
- Full backend test suite passes
- Git status shows only approved 3K docs/tests files before commit