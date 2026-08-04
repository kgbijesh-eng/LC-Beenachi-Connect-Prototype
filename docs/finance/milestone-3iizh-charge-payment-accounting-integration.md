# Milestone 3IIZH — Charge and Payment Accounting Integration

## Objective

Integrate the existing Finance Collections charge-issuance and fully allocated payment-recording transactions with the club-scoped double-entry journal. The integration is additive, internal, disabled by default, and atomic with each source transaction.

## Sources and activation boundary

The exact source types are `member_charge` and `member_payment`. A posted opening package does not enable either source. Each source requires its own immutable activation, posted opening-package and journal binding, coverage start date, mapping version, activation reference, actor, and timestamp. Revocation or invalidation disables the source.

Activation cannot claim a boundary containing pre-existing unintegrated sources. Issued, closed and cancelled charges are checked by issue date; recorded and voided payments are checked by payment date. Any source on or after the proposed boundary without a committed integration makes activation fail because this milestone has no backfill workflow.

Every issued charge and recorded payment receives one immutable coverage decision in its authoritative source transaction while the common club lock is held. The decision records whether integration was required, the exact activation or terminal activation event, and one of `not_activated`, `before_coverage`, `integration_required`, or `after_terminal_event`. Replay and reversal use this decision instead of timestamp ordering. Legacy rows without a decision are accepted only when their accounting date safely predates every applicable coverage boundary; ambiguity fails closed.

No production activation route, controller, command, job, scheduler, frontend, or default-role permission exists. The actor-authority rule for production activation is not approved; production activation therefore remains blocked pending a separate decision. The internal configuration service exists for controlled setup and verification only.

## Coverage and mappings

Dates before coverage fail closed. Dates on or after coverage require exactly one open accounting period and exactly one active, unrevoked, effective mapping under the activated mapping version.

- Charge category keys map only to one active same-club INR income account.
- Payment-mode keys map only to one active same-club INR cash or bank asset account.
- `MEMBER-RECEIVABLES` is resolved by its exact account code; labels and partial text are never used.
- Missing, ambiguous, expired, revoked, cross-club, inactive, wrong-type, wrong-currency, or wrong-version mappings fail closed.

No fallback, generic Other, suspense, unidentified-receipts, or balancing account exists.

Configuration inputs are normalized before persistence: dates must round-trip as exact `Y-m-d`, canonical source keys must be approved charge categories or payment modes, versions/references are trimmed and bounded, and terminal-event reasons must be nonempty. Configuration and source mutation use one lock order: club row first, then activation, mapping, source, account, period and journal rows. Mapping creation rejects overlapping current effective intervals. Mapping creation/revocation, activation/revocation, charge issuance and payment recording all serialize on that club lock.

Posting resolves the selected ledger account by primary key with `FOR UPDATE` and then revalidates club, active state, INR currency, type, and approved cash/bank code. No authoritative concurrent ledger-account mutation service exists in this milestone, so account overlap is covered by locked direct validation rather than a public mutation race.

## Journals

Charge issue aggregates the member obligations created by the existing transaction:

```text
Debit  MEMBER-RECEIVABLES
Credit exact mapped category income account
```

Payment recording uses the existing enforced invariant that allocation total equals payment amount:

```text
Debit  exact mapped cash or bank asset account
Credit MEMBER-RECEIVABLES
```

The journal, source mutation, allocations, receipt, immutable linkage, success event, and audit record share one database transaction.

Charges and payments receive immutable canonical lowercase UUID public identifiers without changing numeric routes. SQLite and PostgreSQL reject null, malformed, or changed identities. The UUID is the accounting source identity and journal `source_id`. Each integration retains the exact activation and mapping rows, canonical source key, integer-paise amount, complete ordered canonical source binding, source version/date, journal identity/reference, checksum and actor/timestamp provenance.

## Idempotency and concurrency

The original payment request idempotency UUID remains source provenance. Journal posting uses a separate deterministic UUID derived from club, source type, public source ID, and source event; charges use the same journal-key rule. Linkage uniqueness protects source identity, journal identity, and journal idempotency. Exact replay rebuilds and verifies the entire committed integration and journal contract: immutable coverage decision, activation and mapping identities, source identity/key/version/date/amount/request key, deterministic journal identity/reference/idempotency/checksum, actor and metadata, and the exact ordered two-line posting. It returns the historical integration after later configuration revocation only when every binding still agrees. Missing linkage is allowed only for a legacy source whose accounting date safely predates every applicable coverage boundary.

The source aggregate, activation, mappings, accounts, period, and journal sequence are locked. Database uniqueness is the final safeguard. PostgreSQL process-level tests exercise genuine overlapping retries; SQLite tests cover ordinary semantics.

## Reversals

Cancellation and void transition the charge/payment/receipt to its terminal source state before accounting reversal inside the same outer transaction. The reversal service locks and verifies that terminal state. An integrated source is reversed even after later activation or mapping revocation. An unintegrated source may be corrected without a journal only when its immutable coverage decision says integration was not required, or for a legacy source whose accounting date safely predates every applicable coverage boundary. Missing linkage inside coverage fails closed. Reversal journal creation and an explicit club-scoped append-only integration-reversal event are atomic with the source correction. One terminal activation/mapping event and one success/reversal event per integration are database-enforced.

## Reconciliation

Reconciliation independently scans immutable coverage decisions, source records, integrations and journals instead of treating any one table as the inventory. The decision made under the common club lock is authoritative for historical coverage; activation and mapping timestamps are not used to reinterpret it, and later same-clock or later revocations cannot invalidate a committed integration. The scan reports covered sources without integrations or journals, outside-coverage sources with integrations, sources without required decisions, decisions without sources, and source/date mismatches. For integrations it rebuilds the complete ordered charge/member-obligation or payment/allocation binding and checksum and validates source identity/key/version/date/amount, journal event/date/currency/status/reference/idempotency/metadata, ordered lines, exact accounts, success event, balance and duplicates. Original integrations are distinguished from reversal journals; linked reversal identity/reference/date/actor/reason and exact opposite ordered lines must agree. A global reversal scan detects orphan, missing, duplicate and unrelated reversals.

## Migration reversibility

The additive migration creates activation, mapping, linkage and event tables and adds source UUID columns. Every event-parent relationship carrying `club_id` uses a composite club-scoped foreign key. SQLite triggers and PostgreSQL checks enforce source/event domains, checksum/amount/interval validity and immutability. Rollback removes every trigger, function, table and source-column addition; reapplication restores them.

PostgreSQL verification uses deterministic process barriers for identical/changed retries and configuration/source races. Configuration commits either before posting or after it; covered sources, journals, links and events never commit partially.

## Explicit exclusions

No statement projection/API/frontend/permissions, expenses, donations, fellowship, fundraising, Kuri/Chit, manual-journal UI, production cutover execution, automatic coverage activation, administrator override, fallback account, suspense account, balancing plug, or direct journal mutation is included.
