# Milestone 3G - Payment Recording Scope Gate

## Status

Milestone 3G is a docs/tests-only scope gate.

This milestone authorizes the rules for future payment recording, but does not implement any payment route, controller, migration, payment table, receipt table, ledger mutation code, receipt generation code, frontend screen, reminder, report, or Excel import.

## Purpose

The purpose of Milestone 3G is to define the payment recording rules before any financial mutation workflow is implemented.

Payment recording is higher risk than balance reading because it changes member financial data.

This milestone follows:

- Milestone 3A - Member Account Ledger and Dues Foundation Scope Gate
- Milestone 3B - Member Account Ledger and Dues Database Foundation
- Milestone 3C - Member Ledger Balance Calculation Foundation
- Milestone 3D - Member Ledger Balance Read API Scope Gate
- Milestone 3E - Member Ledger Balance Read API Narrow Implementation
- Milestone 3F - Member Ledger Balance Read API Validation and Closure

## Core Principle

A recorded payment must become a posted ledger credit entry.

The ledger must remain the source of truth for member balances.

Payments must not directly overwrite member balance values.

Future balance calculation must continue to use:

Total Debits - Total Credits = Current Balance

## Who Can Record Payments

Future payment recording may be authorized only for finance/leadership roles.

Approved future payment recording candidates:

- Treasurer
- President/Admin

Secretary payment recording must not be automatically authorized.

Secretary may be considered later only if the club explicitly approves a narrow operational role.

Normal members must never record payments.

## Treasurer Permission Boundary

Treasurer is the primary future role for recording payments.

Future Treasurer payment recording may include:

- Recording payment received from a member
- Selecting the member
- Entering amount
- Entering payment date
- Entering payment mode
- Entering reference number when available
- Adding payment notes
- Creating a ledger credit entry
- Generating or preparing a receipt record if separately authorized

Treasurer access must remain auditable.

## President/Admin Permission Boundary

President/Admin may be allowed to record or supervise payments depending on future implementation approval.

President/Admin access must not bypass audit trail requirements.

President/Admin payment recording must still create ledger credit entries and audit records.

## Secretary Permission Boundary

Secretary may need visibility for coordination, but payment recording by Secretary is not automatically approved in this scope gate.

Secretary payment recording, if ever allowed, must be separately authorized.

## Normal Member Visibility

Normal members may see only their own payment-related summaries after payment records are implemented.

Normal members may see:

- Own payment confirmation
- Own receipt reference
- Own updated balance
- Own payment date
- Own payment amount
- Own payment mode if appropriate

Normal members must not see:

- Other members' payments
- Other members' receipts
- Other members' balances
- Treasurer collection lists
- Club-wide outstanding summaries
- Internal audit notes
- Cancellation/reversal controls

## Required Future Payment Fields

A future payment recording workflow should define and validate:

- club_id
- member_id
- amount
- payment_date
- payment_mode
- reference_number
- notes
- received_by_user_id
- status

Payment amount must be positive.

Payment date must be valid.

Member must belong to the selected club.

The recorder must have an active authorized role in the selected club.

## Payment Modes

Future payment modes may include:

- cash
- bank_transfer
- upi
- cheque
- card
- adjustment

The final allowed list must be confirmed during implementation.

## Ledger Credit Rule

A valid posted payment must create a member ledger credit entry.

The credit entry should use:

- entry_type: credit
- source_type: payment
- amount: payment amount
- transaction_date: payment date
- reference_number: receipt or payment reference
- created_by_user_id: recorder
- status: posted

The payment must not silently modify debit entries.

The payment must not overwrite calculated balance.

## Receipt Numbering Rule

Receipt numbering must be deterministic and auditable.

Future receipt numbering should consider:

- Club code
- Financial year or accounting year
- Sequential number
- Optional prefix

Receipt numbers must be unique within the club.

Receipt numbers should not be reused after cancellation.

## Cancellation and Reversal Rule

Payment cancellation or reversal must not delete financial history.

A future cancellation workflow should either:

- Mark the payment/ledger credit as cancelled, or
- Create a reversal entry

The chosen method must be explicitly authorized before implementation.

Normal members must not cancel payments.

Treasurer and President/Admin cancellation permissions must be separately controlled and audited.

## Audit Requirements

Future payment recording must capture:

- Who recorded the payment
- When the payment was recorded
- Which member paid
- Which club the payment belongs to
- Amount
- Payment mode
- Reference number
- Status changes
- Cancellation or reversal reason when applicable

Audit trail must be retained.

## Special Collections

Payments made toward special collections must remain identifiable.

A payment may be linked to a special collection only after the special collection workflow is authorized.

Milestone 3G does not implement special collection payment allocation.

## Fellowship Payments

Fellowship payment handling must be separately scoped.

Milestone 3G does not implement fellowship payment recording.

## Future Implementation Direction

A later milestone may implement a narrow payment recording foundation.

Recommended sequence:

- 3H - Payment Recording Database/Foundation Authorization or Design
- 3I - Payment Recording Narrow Implementation
- 3J - Payment Recording Validation and Closure
- Later - Receipt generation
- Later - Payment cancellation/reversal
- Later - Frontend payment screen

## Not Implemented In 3G

The following are not allowed in Milestone 3G:

- No payment route
- No payment controller
- No payment request class
- No payment resource class
- No payment table
- No receipt table
- No migration
- No ledger mutation code
- No payment recording service
- No receipt generation code
- No payment cancellation code
- No payment reversal code
- No dues creation workflow
- No special collection workflow
- No fellowship payment workflow
- No frontend changes
- No dashboard
- No reminder automation
- No reports
- No Excel import

## Closure Criteria

Milestone 3G can be closed only when:

- The scope document exists
- The document defines who can record payments
- The document defines required payment fields
- The document defines payment-to-ledger-credit behavior
- The document defines receipt numbering requirements
- The document defines cancellation/reversal boundaries
- The document defines audit requirements
- The document defines role boundaries for Treasurer, Secretary, President/Admin, and Normal Member
- The document blocks payment implementation until a later milestone
- The matching test file validates the scope gate
- Focused tests pass
- Full backend test suite passes
- Git status shows only approved 3G docs/tests files before commit