# Milestone 3A - Member Account Ledger and Dues Foundation Scope Gate

## Status

Milestone 3A is a docs/tests-only scope gate.

This checkpoint defines the accounting rules for member dues, payments, balances, visibility, and special collections before any ledger implementation begins.

## Purpose

The member account system must give each club member a clear running account of amounts due and amounts paid.

The system must support club-level financial tracking without exposing private member payment details to normal members.

## Scope Decision

Milestone 3A approves the member account ledger and dues foundation for future implementation, but does not implement it.

Approved next implementation direction after this scope gate:

Milestone 3B - Member Account Ledger and Dues Database Foundation

## What Creates Dues

The following can create debit entries in a member account:

- Monthly dues
- Membership fee
- Fellowship contribution
- Project share
- Fines
- Special collections
- Other approved club-level payable items

Each due must be linked to:

- Club
- Member
- Due type
- Amount
- Due date or accounting month
- Description or reference
- Created by authorized role
- Status

## What Records Payments

Payments must create credit entries against a member account.

A payment may be recorded by authorized roles only.

Each payment must include:

- Club
- Member
- Amount received
- Payment date
- Payment mode
- Recorded by user
- Receipt or reference number when available
- Optional remarks

Payments should reduce the member's outstanding balance.

Payments should not delete or overwrite the original due entry.

## Balance Calculation Rule

Member balance must be calculated from ledger entries.

Basic rule:

Total Debits - Total Credits = Current Balance

Meaning:

- Positive balance means amount still due from the member
- Zero balance means no pending dues
- Negative balance means advance or excess payment

The system must not depend on a manually edited balance field as the source of truth.

The ledger entries must be the source of truth.

## Normal Member Visibility

Normal members can see only their own financial information.

A normal member may see:

- Their own current dues
- Their own payment history
- Their own receipts or payment references
- Their own pending special collections
- Their own fellowship dues if applicable

A normal member must not see:

- Other members' balances
- Other members' payment history
- Other members' pending dues
- Club-wide defaulter list
- Collection summary across members
- Treasurer-only or President/Admin-only financial reports

## Treasurer Visibility

The Treasurer can view and manage club financial records related to dues and payments.

The Treasurer may:

- View all member balances
- Record payments
- View outstanding dues
- View payment history
- View collection summaries
- View member-wise pending amounts
- View special collection status

The Treasurer should not get unrestricted administrative control beyond finance-related permissions unless separately authorized.

## Secretary Visibility

The Secretary may need limited financial visibility for coordination.

The Secretary may:

- View member due status when required for club administration
- View fellowship-related payment status
- View special collection status
- Coordinate reminders and communication

The Secretary should not be the primary finance controller unless the club explicitly assigns that permission.

## President/Admin Visibility

President/Admin can view complete member account and dues information for supervision.

President/Admin may:

- View all member balances
- View all dues and payment records
- View outstanding dues
- View collection summaries
- Review finance status
- Supervise Treasurer and Secretary workflows

President/Admin may have override or correction approval rights only if separately implemented in a future milestone.

## Special Collections

Special collections must be supported as club-approved payable items.

Examples:

- Project share
- Event contribution
- Emergency contribution
- Service activity contribution
- District or club-level special demand

Special collections must support:

- Collection title
- Collection purpose
- Target members
- Amount per member or custom amount
- Due date
- Status
- Member-wise payment tracking

Special collections must not be mixed silently with monthly dues.

They must remain identifiable in the ledger.

## Privacy Rules

Financial privacy is mandatory.

Normal members must never see other members' financial details.

PST roles may see broader financial data only according to role permissions.

Reports must respect role-based access.

## Audit Requirements

Future implementation should preserve accountability.

Important actions should be auditable, including:

- Creating dues
- Recording payments
- Editing/correcting entries
- Cancelling entries
- Generating receipts
- Creating special collections

Audit logs should capture:

- Acting user
- Club
- Member affected
- Action type
- Timestamp
- Before/after values where applicable

## What Must Wait For 3B Or Later

The following are not allowed in Milestone 3A:

- No migration
- No ledger table
- No dues table
- No payment table
- No receipt table
- No model
- No controller
- No API route
- No balance calculation code
- No payment recording code
- No frontend changes
- No Excel import
- No report generation
- No reminder automation
- No business module implementation

## Milestone 3A Boundary

Milestone 3A may only add:

- This scope gate document
- A matching test file that validates this scope gate document

Milestone 3A must not modify runtime application behavior.

## Closure Criteria

Milestone 3A can be closed only when:

- The scope document exists
- The scope document defines due creation rules
- The scope document defines payment recording rules
- The scope document defines balance calculation rules
- The scope document defines normal member visibility
- The scope document defines Treasurer, Secretary, and President/Admin visibility
- The scope document defines special collection rules
- The scope document clearly blocks implementation until 3B or later
- Focused tests pass
- Full backend test suite passes
- Git status shows only the approved docs/tests files before commit
