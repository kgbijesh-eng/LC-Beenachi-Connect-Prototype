# Milestone 3B - Member Account Ledger and Dues Database Foundation

## Status

Milestone 3B implements the database/model foundation for member account ledger entries.

This milestone follows Milestone 3A, which approved the ledger and dues scope gate.

## Purpose

The purpose of this milestone is to create the storage foundation for member dues and payments without implementing payment workflows, receipt generation, balance APIs, reports, reminders, Excel import, or frontend screens.

## Implemented Scope

Milestone 3B may implement only:

- Member ledger entries migration
- MemberLedgerEntry model
- MemberLedgerEntry factory
- Club relationship to ledger entries
- Member relationship to ledger entries
- User reference as the creator or recorder of an entry
- Database tests for debit and credit structure
- Basic enum-like constants for entry type, source type, and status

## Ledger Table

The table is:

member_ledger_entries

Each row represents one ledger entry.

A debit entry means the member owes money.

A credit entry means the member has paid money or received a valid adjustment credit.

## Core Fields

Each ledger entry stores:

- Club
- Member
- Created by user
- Entry type
- Source type
- Amount
- Accounting month
- Due date
- Transaction date
- Reference number
- Description
- Metadata
- Status

## Entry Types

Allowed foundation entry types:

- debit
- credit

This milestone only stores the structure.

It does not calculate balances in application code.

## Source Types

Allowed foundation source types include:

- monthly_dues
- membership_fee
- fellowship
- project_share
- fine
- special_collection
- payment
- adjustment

Special collections must remain identifiable and must not be silently mixed with monthly dues.

## Balance Rule Preserved From 3A

The future balance rule remains:

Total Debits - Total Credits = Current Balance

Milestone 3B does not implement the balance calculation API or frontend display.

## Privacy Boundary

This milestone does not expose ledger entries through any API route.

Normal members still cannot access other members' financial data.

Role-based ledger visibility must be handled in a later API milestone.

## Not Implemented In 3B

The following are not allowed in this milestone:

- No payment recording API
- No receipt generation
- No balance display API
- No frontend changes
- No reminder automation
- No reports
- No Excel import
- No dues creation workflow
- No special collection workflow
- No fellowship payment workflow
- No payment approval workflow
- No member-facing financial screen

## Closure Criteria

Milestone 3B can be closed only when:

- The ledger migration exists
- The MemberLedgerEntry model exists
- The factory exists
- Club and Member relationships exist
- Debit and credit entries can be stored
- Source types can identify dues, payments, and special collections
- No API routes are added for payments, receipts, balances, reminders, reports, or Excel import
- Focused tests pass
- Full backend test suite passes
- Git status shows only approved 3B files before commit