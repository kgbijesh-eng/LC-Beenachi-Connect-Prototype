# Milestone 3H - Payment Recording Database Foundation Authorization

## Status

Milestone 3H is a docs/tests-only database foundation authorization and design checkpoint.

This milestone defines the future payment recording database foundation, but does not implement any migration, model, route, controller, service, request class, resource class, ledger mutation code, receipt generation code, frontend screen, reminder, report, or Excel import.

## Purpose

The purpose of Milestone 3H is to define the database structure and implementation boundaries required before payment recording can be safely implemented.

Milestone 3G defined the payment recording rules.

Milestone 3H defines the future database foundation that may support those rules.

Payment recording must remain audit-safe because it changes financial records and member balances indirectly through ledger credits.

## Core Principle

The ledger remains the source of truth for member balances.

A future payment record may describe the payment received.

A future receipt record may describe the receipt issued.

But the member balance must still be calculated from posted member ledger entries.

Payments must not overwrite member balance values.

Receipts must not overwrite member balance values.

Future balance calculation must continue to use:

Total Debits - Total Credits = Current Balance

## Future Database Objects

A later implementation milestone may introduce database foundations for:

- payments
- receipts

The final implementation may choose one combined table or two separate tables, but the recommended design is:

- payments table for recording money received
- receipts table for receipt identity and receipt issue tracking
- member_ledger_entries table remains the balance source of truth

## Future Payments Table Design

A future payments table should record money received from a member.

Recommended fields:

- id
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
- created_at
- updated_at
- cancelled_at
- cancelled_by_user_id
- cancellation_reason

The payment amount must be positive.

The selected member must belong to the selected club.

The selected recorder must have an active authorized club role.

The payment must not directly change stored member balance values.

## Future Receipts Table Design

A future receipts table should record issued receipt identity.

Recommended fields:

- id
- club_id
- payment_id
- receipt_number
- receipt_date
- issued_by_user_id
- status
- notes
- created_at
- updated_at
- cancelled_at
- cancelled_by_user_id
- cancellation_reason

Receipt numbers must be unique within the club.

Receipt numbers should not be reused after cancellation.

Receipt records must remain auditable.

Receipt generation must not be implemented in Milestone 3H.

## Payment To Ledger Relationship

A posted payment must create or reference one posted member ledger credit entry.

Recommended relationship:

- payments.ledger_entry_id references member_ledger_entries.id
- member_ledger_entries.source_type = payment
- member_ledger_entries.entry_type = credit
- member_ledger_entries.amount = payment amount
- member_ledger_entries.transaction_date = payment date
- member_ledger_entries.created_by_user_id = received_by_user_id

The payment record and the ledger credit must belong to the same club.

The payment member and ledger member must match.

A payment must not silently modify debit entries.

A payment must not overwrite calculated balance.

## Receipt To Payment Relationship

A receipt should belong to one payment.

Recommended relationship:

- receipts.payment_id references payments.id
- receipts.club_id must match payments.club_id

A payment may have one active receipt.

A cancelled receipt must remain in history.

Receipt cancellation must not delete the payment.

## Status Values

Future payment status values may include:

- draft
- posted
- cancelled
- reversed

Future receipt status values may include:

- issued
- cancelled

The final allowed status list must be confirmed during implementation.

Only posted payments should affect member balance through posted ledger credit entries.

Draft payments must not affect member balance.

Cancelled or reversed payments must not incorrectly remain active as payable credits.

## Cancellation And Reversal Boundary

Payment cancellation or reversal must not delete financial history.

A future implementation must choose one explicit method:

- Mark the payment and linked ledger credit as cancelled
- Or create a separate reversal ledger entry

The chosen method must be separately authorized before implementation.

Milestone 3H does not implement cancellation or reversal.

## Receipt Numbering Boundary

Receipt numbering must be deterministic and auditable.

Future receipt numbering should consider:

- Club code
- Financial year or accounting year
- Sequential number
- Optional prefix

Receipt numbers must be unique within a club.

Receipt numbers must not be reused after cancellation.

Race-condition safe numbering must be considered before implementation.

## Required Constraints

A future database implementation should consider:

- Foreign key from payments.club_id to clubs.id
- Foreign key from payments.member_id to members.id
- Foreign key from payments.received_by_user_id to users.id
- Foreign key from payments.ledger_entry_id to member_ledger_entries.id
- Foreign key from receipts.club_id to clubs.id
- Foreign key from receipts.payment_id to payments.id
- Foreign key from receipts.issued_by_user_id to users.id
- Unique receipt number per club
- Positive payment amount constraint where supported
- Indexed club_id
- Indexed member_id
- Indexed payment_date
- Indexed status
- Indexed receipt_number

## Audit Requirements

Future payment and receipt records must capture:

- Who recorded the payment
- When the payment was recorded
- Which club the payment belongs to
- Which member paid
- Amount
- Payment mode
- Reference number
- Linked ledger entry
- Receipt number if issued
- Status changes
- Who cancelled or reversed the record
- Cancellation or reversal reason when applicable

Audit trail must be retained.

## Role Boundary

Future payment database implementation must support role-based access.

Treasurer is the primary payment recording role.

President/Admin may supervise or record payments if authorized.

Secretary payment recording is not automatically approved.

Normal members must never record payments.

Normal members may later see only their own payment confirmation, receipt reference, amount, payment date, and updated balance.

Normal members must not see other members' payments, receipts, balances, internal audit notes, cancellation controls, or Treasurer collection lists.

## Special Collection Boundary

Payments toward special collections must remain identifiable.

A future database design may later support special collection allocation.

Milestone 3H does not implement special collection payment allocation.

## Fellowship Payment Boundary

Fellowship payment handling must be separately scoped.

Milestone 3H does not implement fellowship payment recording.

## Future Implementation Direction

Milestone 3H may authorize a later narrow database foundation implementation milestone.

Recommended next sequence:

- 3I - Payment Recording Database Foundation Narrow Implementation
- 3J - Payment Recording Database Foundation Validation and Closure
- Later - Payment Recording Service Authorization
- Later - Payment Recording Service Implementation
- Later - Receipt Generation Authorization
- Later - Receipt Generation Implementation
- Later - Frontend Payment Screen

## What 3I May Implement If Separately Approved

A future 3I implementation may be allowed to add only the narrow database foundation.

Possible 3I scope:

- payments migration
- receipts migration
- Payment model
- Receipt model
- model relationships
- database-level constraints where practical
- tests proving no payment recording behavior exists yet

3I must still avoid actual payment recording behavior unless separately authorized.

## Not Implemented In 3H

The following are not allowed in Milestone 3H:

- No payment migration
- No receipt migration
- No payment model
- No receipt model
- No payment route
- No receipt route
- No payment controller
- No receipt controller
- No payment request class
- No payment resource class
- No payment recording service
- No receipt generation service
- No ledger mutation code
- No payment posting code
- No receipt generation code
- No payment cancellation code
- No payment reversal code
- No special collection payment allocation
- No fellowship payment workflow
- No frontend changes
- No dashboard
- No reminder automation
- No reports
- No Excel import

## Closure Criteria

Milestone 3H can be closed only when:

- The scope/design document exists
- The document defines future payment table design
- The document defines future receipt table design
- The document defines payment-to-ledger relationship
- The document defines receipt-to-payment relationship
- The document defines status boundaries
- The document defines cancellation and reversal boundaries
- The document defines receipt numbering boundaries
- The document defines constraints and indexes
- The document defines audit requirements
- The document defines role boundaries
- The document defines what a later 3I may implement
- The document blocks implementation in 3H
- The matching test file validates the authorization boundary
- Focused tests pass
- Milestone3 test chain passes
- Full backend test suite passes
- Git status shows only approved 3H docs/tests files before commit