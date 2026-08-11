# Milestone 3BB — Club Finance Reports and Balance Sheet Read API Authorization

## Status

AUTHORIZED FOR FUTURE NARROW IMPLEMENTATION

Milestone 3BB authorizes the next narrow implementation checkpoint for read-only Club Finance Reports and Balance Sheet API access.

This milestone is docs/tests-only. It does not implement the API.

## Purpose

The club finance module already has member ledger balance, payment recording, receipt generation, receipt PDF, and member finance summary foundations.

The next required finance capability is a read-only club-level finance reporting API that can later support:

- Club finance overview
- Outstanding dues summary
- Member-wise dues report
- Payment collection summary
- Receipt summary
- Income and expense style reporting from approved ledger/payment data
- Balance sheet style club financial position view

This authorization exists to define the next implementation boundary before any runtime code is added.

## Authorized Future Implementation Scope

A later implementation milestone may add read-only API support for club finance reports and balance sheet data.

The future implementation may include only:

- Read-only report endpoints
- Read-only controller logic
- Read-only request validation if required
- Read-only resources if required
- Read-only finance report service logic
- Club-scoped financial aggregation from existing approved finance records
- Role-protected access for President, Secretary, Treasurer, and Admin
- Member access restrictions
- Cross-club fail-closed behavior
- Tests proving read-only behavior
- Tests proving no mutation behavior

## Future Report Categories

The future read API may support these report categories:

1. Club finance overview
2. Outstanding dues summary
3. Member-wise dues summary
4. Payment collection summary
5. Receipt summary
6. Balance sheet summary

## Balance Sheet Boundary

The future balance sheet API must be read-only.

It may present summarized financial position using approved club finance records, but it must not introduce accounting mutation workflows in the same milestone.

The future balance sheet API must not create, edit, reverse, cancel, allocate, or export financial records.

## Access Boundary

Future access should be restricted as follows:

- President/Admin: allowed to view club finance reports
- Secretary: allowed to view club finance reports
- Treasurer: allowed to view club finance reports
- Member: blocked from club-level reports unless a later milestone explicitly authorizes member-facing summary views

Cross-club access must fail closed.

## Explicitly Blocked in Milestone 3BB

The following are blocked in this milestone:

- routes
- controllers
- requests
- resources
- services
- models
- migrations
- seeders
- app/page.tsx
- report implementation
- balance sheet implementation
- exports
- reminders
- Excel import
- frontend screens
- payment history
- allocation workflows
- payment cancellation
- payment reversal
- receipt sharing

## Runtime Boundary

Milestone 3BB must not change runtime behavior.

No API route is added here.

No controller, request, resource, service, model, migration, seeder, frontend screen, report implementation, balance sheet implementation, export, reminder, Excel import, payment history, allocation workflow, cancellation workflow, reversal workflow, or receipt sharing workflow is added here.

## Authorized Next Milestone

Milestone 3BB authorizes opening:

Milestone 3BC — Club Finance Reports and Balance Sheet Read API Narrow Implementation

Milestone 3BC may implement the read-only API within the boundaries defined above.

## Closure Rule

Milestone 3BB can close only if:

- Only this documentation file and its matching validation test are added
- No runtime files are changed
- The validation test confirms the authorization boundary
- The full backend suite passes
