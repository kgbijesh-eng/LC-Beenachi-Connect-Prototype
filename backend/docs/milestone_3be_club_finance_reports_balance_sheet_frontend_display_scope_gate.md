# Milestone 3BE — Club Finance Reports and Balance Sheet Frontend Display Scope Gate

## Status

Milestone 3BE is a docs/tests-only scope gate.

It does not implement frontend display.

It defines the allowed future frontend display scope for the Club Finance Reports and Balance Sheet feature that was validated and closed in Milestone 3BD.

## Background

Milestone 3BC added the read-only backend API:

- `GET /api/v1/clubs/{club}/finance/reports`
- `GET /api/v1/clubs/{club}/finance/balance-sheet`

Milestone 3BD validated and closed that backend API.

Milestone 3BE now defines whether and how those existing API responses may be displayed in the frontend in a later milestone.

## Future frontend display goal

A future frontend milestone may add a read-only display area for:

- Club Finance Reports
- Balance Sheet Summary
- Outstanding dues summary
- Member-wise dues summary
- Payment collection summary
- Receipt summary

The frontend must use the existing 3BC read-only API routes only.

## Allowed future frontend placement

A future frontend implementation may place the display inside the existing Finance section.

Recommended navigation direction:

- Finance
  - Club Finance Reports
  - Balance Sheet

The display should be compact and Treasurer-friendly.

## Allowed future API usage

Future frontend display may call only:

- `GET /api/v1/clubs/{club}/finance/reports`
- `GET /api/v1/clubs/{club}/finance/balance-sheet`

The frontend must not create new backend API routes.

## Role visibility boundary

Future frontend display may be visible only to:

- President/Admin
- Secretary
- Treasurer

Normal Member role must not see the club-level finance reports or balance sheet display.

Normal Member role must continue to see only member-safe finance information already allowed elsewhere.

## Allowed future display fields

Future frontend display may show:

- club name
- total debit amount
- total credit amount
- current dues amount
- current dues label
- members with dues count
- total outstanding amount
- member-wise dues summary
- posted payment total
- posted payment count
- issued receipt count
- member receivables
- cash collected
- member advances
- net position
- generated at

## Display wording rule

The frontend should use simple club-friendly finance wording.

Allowed wording examples:

- Total Dues
- Amount Collected
- Current Dues
- Members with Dues
- Balance Sheet Summary
- Net Position

Avoid confusing accounting-heavy wording for normal club users unless it is clearly required.

## Safety and empty state rules

Future frontend display must include safe states for:

- loading
- no finance records yet
- API error
- forbidden access
- unauthenticated access

The frontend must not expose raw technical errors to users.

## Blocked scope

Milestone 3BE does not add or authorize current implementation of:

- frontend changes
- app/page.tsx changes
- backend route changes
- controller changes
- service changes
- resource changes
- request class changes
- model changes
- migration changes
- seeder changes
- export features
- PDF features
- Excel import or export
- reminder features
- payment mutation
- receipt sharing
- cancellation workflows
- reversal workflows
- allocation workflows

## Source of truth rule

Future frontend display must treat the 3BC backend API as the source of truth.

The frontend must not calculate official finance balances independently.

The frontend may format values for display, but it must not replace backend finance calculations.

## Next milestone candidate

The next milestone may be:

Milestone 3BF — Club Finance Reports and Balance Sheet Frontend Display Implementation Authorization

3BE does not itself authorize implementation.
