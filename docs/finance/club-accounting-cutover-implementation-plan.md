# Club Accounting Cutover Future Implementation Plan

**Status:** Proposed sequencing only. No milestone below is authorized by this document. Every gate requires a new reviewed instruction from a clean approved baseline.

## 1. Universal controls

Every future milestone is club-scoped, INR integer-paise, additive until activation, idempotent where it writes, privacy-minimizing, and independently reversible without deleting posted journals. Operational records may be accessed only when that milestone explicitly authorizes them. A failed gate stops the sequence; later milestones cannot compensate for missing approval or reconciliation.

## 2. Gated milestone sequence

| # | Objective and dependencies | Authorized / prohibited changes | Required tests | Operational-data risk and rollback | Approval gate and stop condition |
|---:|---|---|---|---|---|
| 1 | Business-owner/accounting review of DR-026–DR-052. Depends on this contract | Docs/decision records only; no code/data | Completeness, contradiction, all-new-status checks | None; revert docs before implementation | Owners record explicit choices; stop on any unresolved activation-critical decision |
| 2 | Final chart approval. Depends 1 | Approve catalog/version only; no provisioning | Code/type/normal-balance/catalog review | None | Business owner + accounting reviewer; stop on unmapped purpose |
| 3 | Due-category and source-event mapping approval. Depends 2 | Mapping documentation only; no hooks | Every source has accounts/date/idempotency/reversal | None | Accounting/domain owners; stop on ambiguous mapping |
| 4 | Cutover evidence template. Depends 1–3 | Documentation/template only; no operational schedules | Required fields, privacy, hash and sign-off review | None | Accounting/privacy approval; stop if evidence cannot be secured |
| 5 | Internal chart provisioning implementation. Depends 2 | Internal service/catalog controls; no route, activation, posting | Replay/conflict, code, club isolation, inactive/history tests | Empty-table only; roll back while unused | Architecture/security/accounting; stop on schema mismatch |
| 6 | Opening-balance validation service. Depends 3–5 | Validate sanitized package inputs; no posting | Paise, mapping, evidence, totals, subledger, zero/suspense negative tests | Test/sanitized data; disable service | Accounting/security; stop on any unexplained difference |
| 7 | Opening-journal preparation/dry-run service. Depends 6 | Produce non-authoritative deterministic preview/hash; no journal write | Balance, line order, source identity, replay, package-hash tests | Test-only; discard preview | Accounting/architecture; stop if repeat output differs |
| 8 | Explicit activation authorization domain. Depends 4, 6–7 | Additive approval states and separation of duties; no posting | Actor separation, immutable evidence, state transition, club isolation | Sensitive metadata; disable before use | Business/accounting/security; stop without enforceable approvals |
| 9 | Controlled opening-journal posting. Depends 8 and approved package | One idempotent opening post only; no source integrations/backfill | Exact package-to-lines, replay, conflict, atomic rollback, reversal rehearsal | Critical; stop integration, linked reversal if approved | Joint go/no-go; stop on any mismatch |
| 10 | Charge integration. Depends 3, 5, 9 | Issued charge → receivable/mapped credit | Atomicity/outbox, exactly-once, cancel reversal, club/concurrency tests | Live duplication risk; feature flag stops new posts, history retained | Domain/accounting approval; stop on reconciliation drift |
| 11 | Payment/allocation integration. Depends 10 and asset mapping | Received fully allocated payment → real asset/receivable; allocation no second cash | Asset selection, exact allocation, idempotency, club/privacy tests | Live cash risk; disable hook, reverse errors | Treasurer/accounting/security; stop on ambiguous asset |
| 12 | Reversal/void integration. Depends 10–11 | Bind source void/cancel to linked journal reversal | Replay, replacement, closed-period, original visibility tests | Live correction risk; disable new integration | Accounting/domain owner; stop if histories diverge |
| 13 | Expense workflow prerequisite. Depends approved DR-040 | Build separately authorized payment/approval source before mapping; no accrual | Maker/checker, payee/evidence, mapping, void, privacy tests | High spend risk; disable workflow and retain audit | Business/accounting/security; stop without approval hierarchy |
| 14 | Donation/collection prerequisites. Depends DR-036–DR-039 | Separate authoritative sources; no UI-fixture conversion | Restriction, count, receipt, custody, privacy, reversal tests | High custody/restriction risk; disable each source | Business/accounting/privacy; stop on unclear classification |
| 15 | Reconciliation service. Depends 9–14 as applicable | Read and compare source/subledger/journal; no auto-repair | Drift, missing, duplicate, timing, club isolation, overflow | Read operational data; disable job, preserve reports | Accounting/operations; stop on unexplained variance |
| 16 | Authoritative balance calculation. Depends 9 and 15 clean | Internal openings/movement/closing over approved coverage | Opening + movement = closing, reversal, inactive account, snapshot tests | Read-only integrity risk; disable calculator | Accounting/architecture; stop if coverage incomplete |
| 17 | Statement read API. Depends 16 | GET, permission, coverage/error/pagination; no writes/exports | 401/403/404/409/422/503, running balance, consistency, performance | Privacy/read load; disable route | API/security/accounting; stop on leakage or disagreement |
| 18 | Authorization/privacy acceptance. Depends 17 | Test roles and same-club reads; no policy expansion | Normal Member denial, foreign-club opacity, member-link redaction | Sensitive read risk; disable permission/route | Security/privacy/business; stop on any exposure |
| 19 | Frontend statement. Depends 17–18 | Officer read UI with coverage states; no posting/editing | Source-truth, responsive, accessibility, no fabricated fallback | Presentation/privacy risk; feature flag restores placeholder | Product/accounting/security; stop if any value lacks source |
| 20 | Export and audit. Depends 17–19 and separate approval | Approved CSV/PDF/read-audit only; no mutable statement records | Export totals, privacy, retention, deterministic metadata | Sensitive files; disable export and revoke artifacts per policy | Business/privacy/accounting; stop without retention policy |
| 21 | Operational cutover rehearsal. Depends 4–20 as applicable | Sanitized or explicitly approved rehearsal; no production activation | Full manifest, dry run, restore, reversal, monitoring drill | Controlled high risk; discard isolated environment | Operations/accounting/security; stop on any failed criterion |
| 22 | Business-owner go/no-go. Depends 21 | Record decision only; no technical activation | Evidence checklist and commit provenance review | None | Joint business/accounting approval; no approval means stop |
| 23 | Controlled deployment. Depends 22 | Staged approved schema/services/features for one club/account set | Migration, canary, reconciliation, privacy, performance checks | Critical production risk; stop hooks/UI, preserve journals, disaster restore only if authorized | Deployment/business/accounting/security |
| 24 | Post-cutover monitoring. Depends 23 | Reconciliation, alerts, incident runbook; no destructive auto-repair | Drift injection, alert, next-open correction, backup/restore tests | Ongoing read/audit load; disable monitors independently | Operations/accounting; stop statement/integrations on material drift |

For milestone 23, the stop condition is any deployment, migration, canary, provenance, reconciliation, privacy, or performance failure. Force deployment and force migration are prohibited.

**Internal template foundation note:** immutable catalogue version `CLUB-COA-2026-01` implements the approved 24-row template semantics only. It creates no club-account record and does not invoke or extend provisioning. Milestone 5 remains unexecuted: exact instances, private bank aliases, mappings, opening positions, DR-033 equity selection, provisioning, activation, and posting remain separately gated.

**Inactive provisioning-plan note:** policy `INACTIVE-PROVISIONING-PLAN-2026-01` validates only pure, deterministic `PROPOSED-UNVERIFIED` descriptions. It neither verifies opaque references nor creates, provisions, audits, authorizes, or activates an account. No template is approved for persistence, both equity candidates remain blocked while DR-033 is Pending, and Milestone 5 write semantics remain unexecuted.

## 3. Sequential and parallel work

Decisions → chart/mappings → evidence → validation/dry run → enforceable approval → opening posting are strictly sequential. Charge and payment integrations follow a correct opening and can be developed in parallel only after their shared mapping/idempotency contracts are frozen; activation remains sequential. Expense, donation, Collection Box, Special/Voluntary Collection, and Fellowship are independent prerequisite lanes and remain outside the initial release until approved. API security, frontend design, and operational runbooks may be prepared in parallel after authoritative balance semantics stabilize, but cannot activate early.

## 4. Rollback doctrine

- Before any journal posting, disable or roll back additive unused components.
- After posting, never delete or edit journal history; stop new integrations and use approved linked reversal/replacement.
- A feature flag may restore the truthful disconnected frontend and disable routes/hooks.
- Database restoration is disaster recovery only, separately authorized and rehearsed.
- No rollback may reintroduce dynamic synthetic statements from old tables.

## Unsafe shortcuts rejected

- Current member ledger as general ledger.
- Dynamic statement composition from charges, payments, allocations, receipts, or legacy reports.
- Frontend fund sections as accounts or balances.
- Missing openings as zero, generic bank, unallocated receipts, or suspense balancing.
- Mid-year cutover, edited openings, deleted corrections, or unaudited backdating.
- Incomplete historical import, receipt-as-income, or allocation-as-cash.
- Journal activation before idempotent source integration.
- Authoritative statements before approved complete coverage.

## 5. Stop point

After this documentation milestone, the next action is business-owner and accounting review of all Pending decisions. Chart provisioning, opening preparation, operational inspection, journal posting, source integration, reconciliation, API, frontend, merge, and deployment remain prohibited.
