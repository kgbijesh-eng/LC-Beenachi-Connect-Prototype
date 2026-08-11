# Milestone 3C - Member Ledger Balance Calculation Foundation

## Status

Milestone 3C implements a pure backend balance calculation foundation for member ledger entries.

This milestone follows:

- Milestone 3A - Member Account Ledger and Dues Foundation Scope Gate
- Milestone 3B - Member Account Ledger and Dues Database Foundation

## Purpose

The purpose of this milestone is to calculate a member's current ledger balance from posted ledger entries.

This milestone does not expose the calculation through API routes or frontend screens.

## Implemented Scope

Milestone 3C may implement only:

- A pure service class for member ledger balance calculation
- Debit total calculation
- Credit total calculation
- Balance calculation
- Balance type classification
- Ignoring cancelled ledger entries
- Focused backend tests
- Documentation

## Balance Rule

The balance rule is:

Total Debits - Total Credits = Current Balance

Meaning:

- Positive balance means amount due from the member
- Zero balance means clear
- Negative balance means advance or excess payment

## Posted Entries Only

Only posted ledger entries are included in balance calculation.

Cancelled entries must be ignored.

## Balance Types

The service may classify balances as:

- amount_due
- clear
- advance

## Privacy Boundary

Milestone 3C does not expose ledger balances through any route.

Normal members still cannot access other members' financial details.

Role-based balance visibility must be implemented later through controlled APIs.

## Not Implemented In 3C

The following are not allowed in this milestone:

- No API route
- No controller
- No request class
- No resource class
- No frontend changes
- No payment recording API
- No receipt generation
- No reminder automation
- No reports
- No Excel import
- No dashboard
- No member-facing financial screen
- No Treasurer dashboard
- No dues creation workflow
- No special collection workflow
- No fellowship payment workflow

## Closure Criteria

Milestone 3C can be closed only when:

- The balance service exists
- The service calculates debit total
- The service calculates credit total
- The service calculates balance as debit minus credit
- Cancelled ledger entries are ignored
- Amount due, clear, and advance cases are tested
- No routes/controllers/API resources are added
- Focused tests pass
- Full backend test suite passes
- Git status shows only approved 3C files before commit