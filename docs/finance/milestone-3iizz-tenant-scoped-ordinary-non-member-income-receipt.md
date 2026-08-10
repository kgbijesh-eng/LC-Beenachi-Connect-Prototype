# Milestone 3IIZZ — Tenant-Scoped Ordinary Non-Member Income Receipt

**Policy:** DR-064, Approved with qualification on 10 August 2026.

3IIZZ implements only money already known to be earned, unrestricted, non-refundable, economically club-owned ordinary income. Incoming cash never determines the credit classification.

## Contract

The bounded subtypes are `sponsorship`, `advertising`, `event_proceeds`, `interest`, `facility_income`, and `other_approved_ordinary_income`. Sponsorship, advertising, and facility income require a payer/source name. Subtype is provenance only and never selects an account or proves eligibility.

The server derives one INR journal:

```text
Debit  selected active same-club CASH_AND_BANK asset — Fund X
Credit selected active same-club INCOME account       — Fund X
```

The amount and canonical fund are identical on both lines. Raw journal lines, separate line funds, currency override, balancing plugs, liability/equity overrides, and inter-fund behavior are not accepted.

The source persists a public UUID, club, subtype, accounting date, public account bindings, fund, integer-paise amount, description, optional payer/source, external reference and non-sensitive event/project label, tenant-scoped idempotency UUID, canonical digest, creator, journal binding, and timestamps. Posted sources cannot be edited or deleted. Correction remains a future linked full reversal plus optional replacement; 3IIZZ implements no reversal.

## Authority and history

| Role | Create | View history/detail |
|---|---:|---:|
| President/Admin | Yes | Yes |
| Treasurer | Yes | Yes |
| Secretary | No | Yes |
| Normal Member | No | No |

History is tenant-scoped, read-only, newest-first by accounting date then stable source ID, paginated, and bounded by date, subtype, receiving account, income account, fund, exact reference, and text filters. Responses expose public identities only.

## Integrity

Posting requires an open Lions Year accounting period and posted cutover package covering the date. Both accounts must be active, same-club and INR; the debit account must be an asset classified `CASH_AND_BANK`, and the credit account must be an income account classified `INCOME`.

Source creation, journal creation, two lines, and source-to-journal binding run in one transaction. `(club_id, idempotency_key)` is database-unique. Exact canonical replay returns the original source and journal; changed replay conflicts with `ORDINARY_INCOME_RECEIPT_IDEMPOTENCY_CONFLICT`; another club may independently use the same UUID. PostgreSQL worker proof covers exact retry, changed retry, tenant UUID independence, and controlled atomic rollback.

The existing statement projection derives the effect without receipt-specific arithmetic: cash/bank increases, income increases, expenses and liabilities are unchanged, net assets increase in the selected fund, the trial balance remains balanced, and `snapshot_fingerprint` changes.

## Verification evidence

PostgreSQL 17.5 verification ran against the administrator-created disposable database `lcb_3iizz_ordinary_income_receipt_concurrency_test`. A fresh migration including the 3IIZZ source migration passed. The independent-worker concurrency suite passed `1 test / 28 assertions / 0 failures / 0 skipped`, proving exact concurrent replay, changed concurrent replay, tenant-scoped UUID reuse, cross-tenant account rejection, exact two-line INR same-fund accounting, and controlled rollback without an orphan source, journal, or line. The disposable database was then dropped; the protected `lc_beenachi_connect_reconstructed_20260727` database remained present and was never the Laravel test target.

## Explicit exclusions

Donations and restricted donations, Fellowship, Special/Voluntary Collection, Collection Box, unresolved grants, deposits and advances, member payments and receivable settlements, refunds/recoveries, asset sales, restricted/conditional/refundable/custodial money, tax-enabled receipts, arbitrary liability credits, attachments, approvals, reconciliation, export, multi-currency, manual journals, and ordinary-income reversal are outside 3IIZZ.
