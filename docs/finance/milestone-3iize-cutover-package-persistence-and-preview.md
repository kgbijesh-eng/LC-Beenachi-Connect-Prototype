# Milestone 3IIZE — Cutover Package Persistence and Opening-Journal Preview

**Implementation contract:** Internal backend foundation

**Target cutover:** 1 July 2027 (`2027-28`)

**Chart:** `CLUB-COA-2026-01`
**Preview contract:** `OPENING-JOURNAL-PREVIEW-2026-01`

## Boundary

This milestone persists a club-scoped, versioned cutover package and its non-sensitive schedules, evidence references, accounting review, and club approval bindings. It calculates a canonical SHA-256 checksum, validates structural reconciliation, produces a deterministic read-only opening-journal preview, and evaluates non-activation readiness.

It does not activate a cutover, post a journal, establish authoritative accounting coverage, add a permission, expose an API route, connect a source workflow, or implement the Club Account Statement. No method on the service calls `ClubJournalPostingService`.

Cutover permissions remain unresolved by the approved contract. Consequently, the implementation is internal and has no HTTP or command surface. A later authorization milestone must approve distinct permissions before any route is added.

## Relational aggregate

- `club_accounting_cutover_packages`: immutable identity fields, package version, status, current checksum, reconciliation result, Pending/selected DR-033 state, readiness snapshot fields, optimistic lock, and actor metadata.
- `club_accounting_cutover_schedule_rows`: explicit integer-paise balances, existing ledger-account mappings, and DR-032 presentation groupings.
- `club_accounting_cutover_evidence_references`: non-sensitive opaque references only; no evidence content or storage locator.
- `club_accounting_cutover_reviews`: append-only Chartered Accountant review binding and invalidation history.
- `club_accounting_cutover_approvals`: append-only club approval binding and invalidation history.

Composite foreign keys reinforce club isolation for packages, accounts, schedules, evidence, reviews, and approvals. Package edits use `lock_version` and occur transactionally.

A corrective integrity migration applies the repository's PostgreSQL `CHECK`/SQLite trigger convention to enforce positive package and lock versions, nonnegative paise, INR currency, the approved cutover identity, supported package/schedule/evidence/binding statuses, debit-or-credit direction, approved schedule and presentation types, and nullable-or-approved DR-033 classifications. Partial unique indexes allow at most one current accounting review and one current club approval per package. The existing ledger migration provides the required unique `(id, club_id)` key for the composite account foreign key.

## Canonical checksum

The checksum is SHA-256 over canonical JSON containing club, cutover date, Lions Year, chart and package versions, currency, DR-033 selection, every schedule amount/mapping/presentation group, and every contractually relevant evidence reference. Schedule rows and evidence references are sorted by stable semantic keys. Database IDs, public IDs, timestamps, lock version, and incidental insertion order are excluded. Ledger mappings enter the checksum as immutable canonical account codes.

Any accounting-content change clears the checksum and readiness snapshot, clears DR-033, and invalidates every current review and approval while retaining their records and invalidation reason.

Recording a replacement accounting review invalidates the prior current review and every current approval before inserting the new binding. Recording a replacement club approval invalidates the prior current approval. Both operations lock the parent package row, and partial unique indexes provide a second concurrency barrier at the database level.

## DR-033 and review controls

DR-033 has no database or application default. Only `OPENING-BALANCE-EQUITY` and `ACCUMULATED-SURPLUS` are accepted. Selection occurs only inside the accounting-review operation after structural and zero-difference validation. The selected code must map to an active, same-club INR equity account with that exact canonical code. No value, sign, or net-asset calculation selects a classification.

The review binds the exact package version, chart version, checksum, reviewer identifier, date, and written rationale. Club approval is permitted only after a current matching review and binds the same identity triple. Neither record stores a signature or professional file.

## Schedule and preview semantics

Cash, separate bank aliases, receivables, other approved assets, liabilities, restrictions, and the equity preview form the proposed opening journal. Assets use debit direction; liabilities and restrictions use credit direction. Positive journal-preview lines are ordered by stable row key. Zero schedule balances remain evidenced but create no preview line.

General Club Fund, Service Activity Fund, and Fellowship and Member Activity Fund are persisted as DR-032 presentation schedules. They do not create ledger lines, separate cash holdings, legal restrictions, or a fund dimension.

While DR-033 is Pending, the preview may balance mathematically but returns `BLOCKED_DR033`; its equity line has no account code and is explicitly unresolved. After a matching review, it returns `BALANCED_PREVIEW` only when debit and credit totals agree. Preview execution writes no journal, coverage, activation, or package state.

## Readiness

Readiness is a read-only result and is true only when identity, required schedules, account eligibility, presentation mappings, verified evidence references, zero reconciliation difference, current checksum, explicit DR-033 selection, exact current review and approval bindings, absence of a prior opening-package journal, and a balanced non-blocked preview all pass.

Readiness is not activation authority. It neither updates authoritative coverage nor consumes an authorization.

## Sensitive-data boundary

Opaque identifiers use a restrictive non-URL, non-path format. Services reject obvious URLs, filesystem paths, credentials/tokens, key material, and bank-account-number text in accepted non-sensitive fields. This is defense in depth, not evidence authentication or a substitute for restricted storage. Bank statements, account numbers, member-level schedules, signatures, identity documents, credentials, secret URLs, and private storage locations remain outside Git and this database domain.
