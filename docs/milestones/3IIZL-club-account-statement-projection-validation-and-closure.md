# Milestone 3IIZL — Club Account Statement Projection Validation and Closure

## Identity and purpose

Milestone 3IIZL validates the already delivered Club Account Statement projection foundation and formally closes the Milestone 3IIZK delivery lane. It starts from commit `b1044d1d0f0dbc71689633dda784afa90f12d71a` and introduces no application functionality.

The stable statement contract is `CLUB-ACCOUNT-STATEMENT-2026-01`, exposed by the existing read endpoint:

```text
GET /api/v1/clubs/{club}/finance/account-statement
```

## Authorization closure

Access remains protected by the club-scoped `finance.club.statement.view` permission. President, Secretary, and Treasurer officers may access the statement only while they have an active membership in the requested club and the required permission. Normal members remain forbidden, inactive officers remain forbidden, unauthenticated requests remain unauthorized, and permission in one club does not grant cross-club access.

Accounting activation remains mandatory. A club and Lions Year without the activated accounting boundary returns the existing `ACCOUNTING_NOT_ACTIVATED` conflict response.

## Projection contract validated

The endpoint remains read-only and deterministic. Repeated identical GET requests return identical statement data and do not insert, update, or delete accounting records.

Only canonical, ledger-effective posted journals contribute. Draft journals, pre-cutover activity, future activity, and activity outside the requested range do not contribute. Opening balances remain separate from period movement, and the activated opening journal is not double-counted when a range begins on the cutover date.

Fund attribution comes exclusively from canonical journal-line `fund_code` metadata. Statement classification comes exclusively from canonical ledger-account `statement_classification` metadata. A shared ledger account may appear separately under multiple authoritative funds. Debit-normal and credit-normal balances retain their correct natural signs, including opposite-direction negative balances.

Reversals remain append-only, visible, and traceable through canonical journal references and both reversal relationships. Public journal-line, opening schedule-row, payment, and allocation trace identifiers are exposed where required by the existing contract instead of internal database identifiers.

Overall statement totals, classification subtotals, and fund totals must reconcile. Opening debits equal opening credits, period debits equal period credits, and closing debits equal closing credits overall and per fund. Missing classification, missing or unsupported fund metadata, corrupt source traces, inconsistent activation state, and other projection defects fail closed with the existing statement-integrity response.

## Delivery boundaries

This validation milestone delivers no frontend, schema, or migration change. It changes no API contract, controller, request, resource, model, service, route, permission, authentication rule, seeder, factory, journal posting behavior, reversal behavior, accounting-period close behavior, cutover or activation behavior, or charge/payment integration behavior.

No posting, reversal, activation, payment, or charge mutation is introduced. DR-033 remains outside this milestone and is neither silently resolved nor changed.

## Closure decision

Milestone 3IIZK is validated and closed when the focused 3IIZL closure tests, the existing 3IIZK tests, all Club Account Statement tests, the full backend suite, formatting, and diff-integrity checks pass. The closure evidence is documentation and tests only.
