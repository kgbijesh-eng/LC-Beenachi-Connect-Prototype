# Milestone 3IIZS â€” Cutover Administration Internal Enforcement Foundation

Authoritative base SHA: `0ca55eb397a125cf0e3e3b4e7cdd148c876d95f6`
Branch: `milestone-3iizs-cutover-administration-internal-enforcement-foundation`

## Purpose and boundary

This milestone adds internal, fail-closed administration controls only. It provides no route, controller, command, job, scheduler, listener, API, or UI. All records introduced here are domain/database foundation records; no production authority or professional evidence is populated.

## CADR enforcement matrix

| Decision | Internal enforcement |
|---|---|
| CADR-001 | Permission assignment is an immutable package/club/subject/permission-bound operation. Distinct unique active President and Secretary approvals are required before atomic application. Package bootstrap is a separate immutable club/version/cutover/preparer operation: neither approval creates state, while the second approval atomically creates both the DRAFT package and its exact package-scoped `prepare` assignment. Single-grantor legacy entry points fail closed. Duplicate-current permission assignments are serialized and rejected by the database. |
| CADR-002 | Independent-CA appointment uses the same dual operation and rejects President, preparer, verifier, operator and self-governance conflicts. Appointment and professional eligibility remain separate. |
| CADR-003 | Technical-operator appointment uses dual approval and rejects President, preparer, verifier and CA conflicts. Appointment does not grant lifecycle permissions. |
| CADR-004 | A holder can append a revocation event for their own unconsumed record. Third-party revocation is applied only by a dual-approved operation. Original records and provenance remain immutable. |
| CADR-005 | Submitted evidence is forcibly `unverified`. Only a same-club active Secretary holding the exact package `verify` permission can append a version/lock-bound verification and update the denormalized status. |
| CADR-006 | Eligibility stores only opaque external-check references, optional digest, checked date, independence state and expiry. It is created only after separate President and Secretary attestations and is required independently when resolving CA operational authority. |
| CADR-007 | An internal query service returns club-scoped metadata: full history to President/Secretary, own/readiness data to preparer, and own appointment/permission/currentness data to CA/operator. Members are denied. Protected evidence content is absent. |

## Controls

Administration operations have immutable semantic digests and globally unique references. Exact request replay returns the existing operation; changed replay conflicts. Approval rows have one-role-per-operation and one-actor-per-operation uniqueness. The package row is locked while approvals are recorded and the mutation is applied once. PostgreSQL locks the package before checking duplicate current permissions, closing the concurrency race. Approval, verification and eligibility provenance is update/delete protected in both PostgreSQL and SQLite.

Operation records bind the package version and lock version; content mutation makes an uncompleted operation stale. Evidence replacement returns new preparer metadata to `unverified` while retaining the prior verification's immutable public-id and digest snapshot. Revocation and final approval serialize on the package row. Self-revocation opens one transaction, locks the authoritative package row in activation-compatible order, then re-reads exact binding, subject, currentness, expiry and consumption before appending an event. Activation consumption and self-revocation therefore cannot both succeed. Consumed execution authority cannot be revoked into reuse.

Active membership is canonical for President, Secretary and package authors. Independent CA and Technical Operator identity remains appointment-based because the existing contracts allow external specialists; no fictitious membership is created. Every permission use revalidates its approved runtime basis: Treasurer/preparer, Secretary/internal verifier, unique active President, current eligible Independent-CA appointment, or current Technical-Operator appointment as appropriate. Losing that basis removes authorization without erasing historical permission provenance. Package edits require exact package `prepare`; persisted validation requires exact package `validate`.

Package authoring, persisted validation and evidence verification acquire the package lock before resolving their actor's current role and exact package permission, closing permission-revocation TOCTOU windows. Evidence `verification_status` is only a convenience projection: validation requires the latest immutable verification provenance to match the exact evidence public identity, type, opaque reference, date, version, optional digest, package/version and authoritative `verified` result. Verification replay additionally binds actor, result and safe note; changed retries fail closed. Runtime CA/operator resolution rechecks current opposing appointments and active President, Secretary, preparer and verifier conflicts.

Draft initialization no longer accepts Treasurer membership alone. A canonical bootstrap request binds the club, package version, Lions Year, cutover date, chart version, designated Treasurer/preparer, exact `finance.cutover.package.prepare` permission and semantic digest. Exact replay resolves the same operation; changed semantics conflict. The second distinct President/Secretary approval locks the club bootstrap boundary, revalidates both officers and the preparer, and atomically creates the package, initial assignment, operation bindings and safe audit provenance. The old `createDraft()` signature remains only as a rejecting compatibility shim.

Eligibility attestations are governance attestations, not professional certification or accounting review. Evidence metadata verification is an internal control and neither proves document authenticity nor satisfies CA eligibility. Sensitive credential documents, identity numbers, URLs, file paths, secrets, signatures, bank data and working papers are rejected/out of scope.

## Concurrency and tests

Laravel Herd PHP `8.4.23` was resolved from `C:\Users\dell\.config\herd\bin\php.bat`.

- Focused 3IIZS before final review corrections: **15 passed / 70 assertions**.
- Focused 3IIZS after bootstrap-reference normalization: **18 passed / 85 assertions**.
- 3IIZE persistence/preview: **16 passed / 92 assertions**.
- 3IIZG lifecycle/execution: **25 passed / 123 assertions**.
- 3IIZF plus the normally gated PostgreSQL file without its opt-in: **4 passed / 47 assertions / 2 skipped**.
- PostgreSQL migration fresh apply: **PASS**; 3IIZS rollback/reapply: **PASS**.
- Expanded PostgreSQL concurrency before final review corrections: **6 passed / 215 assertions / 0 skipped** against authorized disposable databases. Databases `lcb_3iizo_3iizs_20260809_a` and `lcb_3iizo_3iizs_20260809_b` were removed afterward; remaining disposable count was zero. The protected `lc_beenachi_connect_reconstructed_20260727` database remained present and was not the test target.
- Final expanded PostgreSQL concurrency: **7 passed / 219 assertions / 0 failed / 0 skipped**, exit code **0**, against fresh disposable database `lcb_3iizo_3iizs_20260809_c`. Fresh migration including the 3IIZS migration passed. Database `_c` was subsequently removed and its remaining database count was **0**. The protected `lc_beenachi_connect_reconstructed_20260727` database remained present and was not used as the test target. This final run is authoritative; the earlier **6 passed / 215 assertions** run remains historical evidence.
- SQLite migration: fresh apply, rollback and reapply passed.
- Full backend invocation after final review corrections: **2,076 tests; 2,044 passed; 21 failed; 11 skipped; 25,140 assertions**. All 21 failures are historical milestone scope-gate tests that deliberately fail whenever later-milestone backend files are dirty; no functional test failed. The skips are explicitly gated PostgreSQL tests. Because the command nevertheless returned failure, this report does not represent the literal full-suite gate as passing.
- Frontend lint: **0 errors / 87 existing warnings**.
- Frontend tests: **15 files / 178 tests passed**.
- Frontend production build: passed.

The older 3IIZE/3IIZG synthetic fixtures now create valid President/Secretary operations and approvals through the canonical service. No valid fixture calls a single-grantor method. The former public signatures remain as explicitly rejecting compatibility shims because existing repository callers/tests established those signatures; silently making them private or removing them would create an unnecessary compatibility ambiguity. They cannot create authority.

## Changed implementation areas

Additive migration; administration operation/approval, evidence-verification and CA-eligibility models; authority administration, actor resolver, evidence verification, read query and package authoring services; focused and PostgreSQL concurrency tests; shared valid-governance test helpers; migrated 3IIZE/3IIZG fixtures; this milestone report.

## Remaining blockers and status

DR-033 still requires reconciled actual assets, liabilities, restrictions and receivables plus an Independent Chartered Accountant's economic classification. There is no real reconciled opening package, real Independent CA review, President production approval, production mapping approval, activation authority, or authenticated production administration surface.

The only automated verification caveat is the literal full-backend command's 21 historical dirty-worktree scope-gate failures described above. They cannot be made to pass during an authorized backend milestone without weakening or rewriting historical scope assertions; they are not production/runtime failures.

DR-033: Pending
Production cutover: NOT AUTHORIZED
Production activation: NOT AUTHORIZED
Authenticated administration delivery surface: NOT PROVIDED
Real professional evidence: NOT INGESTED
Real authority assignments/appointments: NOT CREATED

Accounting production readiness remains **NO-GO**.
