# Club Account Statement Future Implementation Plan

**Status:** Proposed sequencing aligned to the business-owner decisions approved on 13 July 2026. Documentation approval does not authorize implementation of any item below.

## Guardrails for every future milestone

Every milestone starts from a clean reviewed baseline, preserves the disconnected statement placeholder until its backend gate passes, scopes all data by club, uses INR integer paise, avoids operational-data inspection unless separately authorized, and has an explicit rollback point. No milestone may dynamically construct authoritative statements from incomplete existing domain tables.

## F1. Contract approval (business-owner policy gate complete)

- **Prerequisites:** 3IIA documents reviewed by the business owner.
- **In scope:** Record approved double-entry, Lions Year, recognition, permission, cutover, history, retention, and API policies. Additional accounting, security, and technical review remains required before F2 authorization.
- **Out of scope:** Code, schema, data access, UI.
- **Database impact / operational risk:** None / none.
- **Rollback:** Revert documentation approval before implementation authorization.
- **Tests/evidence:** Consistency review; decision record naming Bijesh K. G. and 13 July 2026; no contradictory period or recognition rules.
- **Approvals:** Business-owner policy approval recorded. F2 still requires separate accounting, architecture, database, and security authorization.

## F2. Schema and model foundation

- **Prerequisites:** F1; separate implementation authorization; approved ERD, Lions Year rules, naming, constraints, migration and tenant-isolation review.
- **In scope:** Additive chart-of-accounts, journal transaction, journal line, source-link, and period/coverage schema and models; no domain posting.
- **Out of scope:** Backfill, routes, UI, automatic posting.
- **Database impact / operational risk:** New empty tables and permissions only; migration locking and rollback tested on a representative non-operational database.
- **Rollback:** Roll back additive migration only while tables remain empty and no integration is active.
- **Tests/evidence:** Migration up/down, balanced constraints where enforceable, integer bounds, composite club ownership, uniqueness, model casts, no mutation of current finance tables.
- **Approvals:** Architecture, database, security.

## F3. Posting service and invariants

- **Prerequisites:** F2; approved lifecycle and permission design.
- **In scope:** Atomic draft/post/reverse/correct service, balanced-line validation, idempotency, immutable posted records, account and source club checks, audit emission.
- **Out of scope:** Existing-domain event hooks, backfill, statement API, UI.
- **Database impact / operational risk:** Test-only postings until activation; high integrity risk if invariants fail.
- **Rollback:** Disable posting feature flag; retain immutable test/non-production evidence; no destructive cleanup in production.
- **Tests/evidence:** Unit/property tests for debit=credit, overflow, concurrency, replay/conflict, cross-club rejection, immutability, reversal linkage, permissions, audit completeness.
- **Approvals:** Accounting, security, architecture.

## F4. Existing-domain integration

- **Prerequisites:** F3; explicit mappings for each source event and account; reconciliation design.
- **In scope:** Integrate issued member charges, fully allocated received payments, and approved expense-payment workflows one domain at a time. Integrate internal transfers only after both asset accounts and atomic posting pass. Collection Box, fellowship, donations, invoice accruals, unallocated receipts, and other unsupported workflows require separate contracts and approvals.
- **Out of scope:** Implicit conversion of legacy decimal records; enabling domains without source truth; frontend statement.
- **Database impact / operational risk:** New journal postings from live domain writes; highest duplication risk.
- **Rollback:** Per-domain feature flag stops new postings; posted entries remain and corrections use reversals. Never delete journals.
- **Tests/evidence:** Source event posts exactly once, atomic source+journal transaction, void/reversal, allocations, account mapping, receipt linkage, fail-closed club isolation, parallel request tests, source-to-journal reconciliation.
- **Approvals:** Domain owner, accounting, operations, security.

## F5. Read-only statement API

- **Prerequisites:** F2-F4 for at least one approved complete account and coverage period; API contract finalized.
- **In scope:** GET endpoint, permission, validation, period calculations, deterministic pagination, snapshot consistency, coverage states, privacy-aware source links.
- **Out of scope:** Exports, writes, UI, backfill.
- **Database impact / operational risk:** Read indexes may be added; performance and privacy risk, no journal mutation.
- **Rollback:** Disable route/feature flag; preserve journal and existing finance APIs.
- **Tests/evidence:** Opening/movement/closing reconciliation, cross-page running balance, empty/partial/unavailable states, 401/403/404/409/422/503, Normal Member denial, multi-club isolation, query performance, GET-only route inventory, mutation-count assertions.
- **Approvals:** API, security, accounting, product.

## F6. Historical cutover

- **Prerequisites:** Approved 1 July cutover Lions Year selected; account openings fully reconciled and approved; source inventory and reconciliation process reviewed; verified backup and restoration drill.
- **In scope:** Approved opening balances by account on the selected 1 July. Historical transaction backfill is a separate exceptional lane only after independent proof of completeness and non-duplication. Record coverage start and evidence.
- **Out of scope:** Routine historical transaction backfill; unreconciled dynamic history; silent decimal-to-paise conversion; source deletion.
- **Database impact / operational risk:** Controlled journal imports/openings; very high balance and duplication risk.
- **Rollback:** Dry-run first; immutable import batch identity; disable cutover and reverse approved imported batch if necessary. Restore only under separately authorized disaster-recovery procedure.
- **Tests/evidence:** Count/sum/hash reconciliation, balanced batch, duplicate prevention, rounding exception report, account-level sign-off, partial-history behavior before coverage.
- **Approvals:** Business owner, treasurer/accountant, database, operations.

## F7. Automated accounting invariants and operations

- **Prerequisites:** F3-F6.
- **In scope:** Continuous reconciliation checks, monitoring, alerting, controlled Lions Year close guards, next-open-period correction links, backup/restore verification, and operational runbooks.
- **Out of scope:** Automatic destructive repair.
- **Database impact / operational risk:** Read-heavy checks and audit records; alert fatigue or lock contention.
- **Rollback:** Disable scheduled checks independently; retain manual reconciliation path.
- **Tests/evidence:** Fault injection, drift detection, recovery rehearsal, permission and tenant tests, observability without sensitive payloads.
- **Approvals:** Operations, accounting, security.

## F8. Frontend Club Account Statement

- **Prerequisites:** F5 and F6 acceptance; authoritative coverage available; 3II separately re-authorized.
- **In scope:** Replace truthful placeholder with account selection, period filtering, opening/transactions/running/closing display, coverage and error states, officer-only navigation.
- **Out of scope:** Posting, editing, reversal, exports unless separately approved, global navigation redesign.
- **Database impact / operational risk:** None directly; privacy and misleading-presentation risk.
- **Rollback:** Feature flag restores disconnected placeholder without affecting backend.
- **Tests/evidence:** API source truth, no fabricated fallback, exact paise formatting, pagination, active navigation, responsive layout, accessibility, Normal Member exclusion, no mutation.
- **Approvals:** Product, accounting, security.

## F9. Non-mutating browser acceptance

- **Prerequisites:** F8 automated checks pass; sanitized or approved test context.
- **In scope:** President, Secretary, Treasurer desktop/mobile reads; empty, non-empty, partial, unavailable, pagination, navigation, and club boundaries.
- **Out of scope:** Operational posting or correction.
- **Database impact / operational risk:** GET-only; verify before/after mutation counters or hashes.
- **Rollback:** Disable frontend feature and statement route if acceptance fails.
- **Tests/evidence:** Host-assisted screenshots and steps; no Normal Member access; no overflow; values reconcile to approved fixtures; no operational mutation.
- **Approvals:** Host operator, product, accounting.

## F10. Controlled production rollout

- **Prerequisites:** All prior acceptance; production migration, backup, observability, support, incident, and rollback plans approved.
- **In scope:** Staged enablement by club/account, read API first, monitored posting integrations only after reconciliation gates.
- **Out of scope:** Global instant activation; unapproved historical promises; force migration.
- **Database impact / operational risk:** Production schema, journals, reads, and approved integrations; critical accounting and tenant risk.
- **Rollback:** Stop new posting integrations via feature flag; preserve posted journals; reverse incorrect postings through approved process; frontend returns to placeholder; database restore only for disaster recovery.
- **Tests/evidence:** Pre/post migration checks, canary reconciliation, performance/privacy monitoring, local-vs-deployed commit provenance, incident drill, owner sign-off.
- **Approvals:** Business owner, accounting, security, operations, deployment owner.

## Completion boundary

The business-owner policy review is recorded, but F2 is not authorized. No schema, migration, permission, route, posting service, historical opening entry, operational-data action, or frontend 3II work may begin without a new implementation milestone and its required technical approvals.
