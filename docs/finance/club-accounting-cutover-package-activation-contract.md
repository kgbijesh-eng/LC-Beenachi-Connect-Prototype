# Club Accounting Cutover Package and Activation Authorization Contract

**Contract version:** `CUTOVER-AUTHORIZATION-CONTRACT-2026-01`

**Status:** Proposed documentation contract only. This version is not a package version, Lions Year, evidence verification, accounting approval, activation authorization, schema authorization, or posting permission. It appoints no reviewer and accesses no operational data.

## 1. Boundary and prerequisites

This contract defines a future normalized, club- and tenant-scoped persistence and authorization domain for one controlled accounting cutover. It authorizes no migration, model, permission, route, service, feature flag, source hook, package creation, journal posting, activation, merge, or deployment.

DR-033 remains **Pending**. No equity account or amount is selected. No package can become activation-ready until reconciled evidence supports exactly one economically correct equity classification and the required accounting and business decisions are recorded. Independent Chartered Accountant/accounting review remains mandatory and is not performed by technical validation or this contract.

Existing journal tables can represent posting identity, lines, dates, actors, idempotency, and reversal linkage. They cannot safely enforce package versions, manifest integrity, multi-party decisions, separation of duties, activation authority, rollback authority, or retention state. Journal JSON metadata must never be the sole package approval or activation mechanism.

## 2. Three distinct integrity identities

### A. Structural validation fingerprint

The pure 3IIM validator produces a deterministic fingerprint over canonical non-sensitive structural input and blocker output. It is used only to compare `PROPOSED-UNVERIFIED` validation results.

It:

- is not evidence verification;
- is not a final persisted package fingerprint;
- is not the DR-044 evidence-package checksum;
- cannot alone receive accounting review, business approval, or activation authorization.

### B. Final persisted package fingerprint

Future normalized persistence must calculate a final fingerprint from the exact immutable package version, canonical non-sensitive manifest, exact contract/catalogue/policy versions, approved mapping versions, finalized structural-validation result, and all required canonical opaque references. Protected evidence content is excluded.

Every review, approval, authorization, revocation, invalidation, execution, reversal, and supersession record must bind the exact final persisted fingerprint. A material package change creates a new package version and fingerprint and invalidates all dependent decisions on the prior binding.

### C. Final evidence-package checksum

The finalized evidence package approved under DR-044 has a separately controlled checksum. Ordinary application persistence may retain only its non-sensitive checksum or opaque reference. The system must not fetch protected evidence into journal metadata or store protected evidence in Git, ordinary audit metadata, or the non-sensitive package manifest.

Any package or evidence change invalidates the package-fingerprint/evidence-checksum binding and all dependent approvals. The three identities must never be described as interchangeable.

## 3. Future normalized domain

### Cutover package

A future immutable package version must bind:

- opaque package ID and version;
- club and tenant;
- Lions Year and exact 1 July cutover date;
- package structure `CUTOVER-EVIDENCE-PACKAGE-2026-01`;
- validation policy `OPENING-BALANCE-VALIDATION-2026-01`;
- catalogue `CLUB-COA-2026-01`;
- provisioning-plan policy `INACTIVE-PROVISIONING-PLAN-2026-01`;
- authorization contract `CUTOVER-AUTHORIZATION-CONTRACT-2026-01`;
- approved chart, subset, and source/category mapping versions;
- structural validation fingerprint and result;
- final persisted package fingerprint;
- final evidence-package checksum/reference;
- proposed opening source identity and idempotency identity;
- current state and optimistic concurrency version;
- predecessor, successor, rejection, and supersession links;
- non-sensitive creation/preparation provenance;
- opening-journal link only after controlled execution.

An approved package version is never edited. Any material change produces a successor version and repeats validation and every dependent decision.

### Evidence-reference manifest

The future manifest stores only opaque non-sensitive references, evidence category, privacy/storage classification, verification state, reviewer role, retention category, legal-hold state, and exception link. It must not store filenames, paths, URLs, real bank identifiers, member identities, donor or beneficiary identities, credentials, signatures, or protected evidence content.

### Decision and transition record

Every decision is append-only and binds the package ID, exact version, final fingerprint, evidence checksum/reference, club, tenant, actor, authenticated role/permission basis, decision, expected state/version, resulting state/version, timestamp, and opaque decision reference. Rejection, revocation, invalidation, supersession, execution, reversal, and replay outcomes are retained permanently.

The existing `AuditLog` may provide supplemental security context but cannot replace immutable package decision and transition records.

### Technical execution record

Technical execution is distinct from approval. It binds the authorized package and provenance, records create, exact idempotent replay, conflict, or failure, and cannot override a blocker. The technical operator receives execution authority only for the exact authorization and package binding.

## 4. Lifecycle and transition control

The future lifecycle is:

`DRAFT → PREPARED → INTERNALLY_VERIFIED → ACCOUNTING_REVIEWED → BUSINESS_APPROVED → DRY_RUN_ACCEPTED → ACTIVATION_AUTHORIZED → POSTED`

Terminal or alternate states are `REJECTED`, `SUPERSEDED`, `REVERSED`, and, for an unconsumed authorization, `REVOKED` or `INVALIDATED` on the authorization record.

Rules:

- no automatic or skipped transition;
- every transition supplies expected current state and concurrency version;
- optimistic concurrency or equivalent compare-and-swap rejects stale and competing transitions;
- decision, state change, and audit provenance are atomic;
- exact replay is idempotent; changed semantics conflict;
- immutable transition history is retained;
- approved package versions are never mutated;
- rejection or supersession prohibits later review, approval, authorization, or posting;
- `POSTED` prohibits further approval or activation transitions and requires a journal link;
- `REVERSED` requires a linked full reversal and prohibits reactivation;
- no two active activation authorizations may cover the same club and cutover boundary;
- a corrected package is a new version and requires new decisions.

Preparation and internal document verification may occur while DR-033 is Pending only as explicitly incomplete work. `ACCOUNTING_REVIEWED`, later approval states, and activation remain blocked where the equity decision is required.

## 5. Separation of duties

Future controls must enforce:

- the preparer cannot internally verify the same package;
- the preparer cannot independently accounting-review the same package;
- internal verification does not replace independent accounting review;
- President/business approval and independent accounting review are separate decisions;
- technical access does not confer business-approval or accounting-review authority;
- joint activation consists of two separately authenticated package-bound authorization decisions, never one actor asserting two roles or a shared credential;
- the technical operator cannot approve by virtue of execution access;
- active role, club membership, permission, and authority are checked at every transition and immediately before execution;
- loss of required authority before posting invalidates an unconsumed activation authorization.

Reusable role labels do not appoint real people. Actual assignments remain governed by the decision register and future authenticated club membership and permission records.

## 6. Future permission boundary

Security review must define distinct permissions for preparation, internal verification, accounting review, business approval, dry-run acceptance, activation authorization, technical execution, rollback authorization, and restricted-manifest viewing.

Candidate concepts include:

- `finance.cutover.package.prepare`
- `finance.cutover.package.verify`
- `finance.cutover.package.accounting-review`
- `finance.cutover.package.business-approve`
- `finance.cutover.package.dry-run-accept`
- `finance.cutover.package.activate-authorize`
- `finance.cutover.package.execute`
- `finance.cutover.package.rollback-authorize`
- `finance.cutover.package.view-restricted-manifest`

These names are not approved permissions and must not be seeded by this milestone. Existing `finance.manage`, `finance.void`, and `finance.club.statement.view` are insufficient for cutover authority. Role names alone confer no authority.

## 7. Activation authorization

A future activation authorization must be bound to one club, tenant, package ID, exact package version, final persisted package fingerprint, evidence checksum/reference, cutover coverage, and proposed opening source/idempotency identity.

It must be:

- single-use for one controlled opening-posting operation;
- unconsumed before execution;
- consumed atomically only when the opening journal is successfully created or an exact authorized idempotent replay is confirmed;
- unable to authorize another package, version, fingerprint, checksum, club, coverage, source identity, or changed retry;
- revocable before posting only through an append-only revocation record;
- automatically invalidated by package change, checksum change, supersession, rejection, expiry, role loss, failed prerequisite, new blocker, or authorization conflict;
- permanently retained after consumption, revocation, expiry, or invalidation.

A failed technical attempt does not consume authorization unless a journal was successfully created or an exact authorized idempotent replay was confirmed. Decision, execution outcome, journal creation/replay, authorization consumption, and audit provenance must have one safe atomic boundary.

Activation prerequisites include no validation blocker, resolved DR-033, complete evidence/checksum binding, independent accounting review, business approval, dry-run acceptance, privacy/isolation acceptance, reversal readiness, two separate joint-authorization decisions, and immediate pre-execution role checks.

Posting does not automatically enable any charge, payment, allocation, receipt, void, or other source integration.

## 8. DR-033 resolution boundary

A future DR-033 resolution must:

- follow reconciled asset, liability, restriction, and member-receivable evidence;
- identify exactly one economically correct equity classification;
- include independent accounting-review and business-approval references;
- bind the applicable exact package version or approved chart version;
- never be inferred from the amount needed to force journal balance;
- never create retroactive approval for an earlier package version.

Any later package version must independently prove that the decision still applies to its exact facts and bindings. DR-033 remains Pending during this milestone.

## 9. Rollback and reversal authority

Before posting, rollback means preventing activation or append-only revocation/invalidation. After posting, correction means one linked full reversal and a separately approved replacement package. Posted journal headers and lines are never edited or deleted. Database restoration is not an ordinary correction method.

Original package, authorization, journal, reversal, replacement, and every decision remain linked and retained. Source integrations and authoritative statements remain stopped until correction and revalidation are complete.

## 10. Privacy-minimized audit context

Audit and decision records must minimize data and exclude protected evidence, credentials, signatures, bank details, member identities beyond separately authorized opaque links, donor/beneficiary information, and unrestricted free-form evidence content.

IP address and user-agent are optional security context only where lawfully collected, operationally justified, access-restricted, retention-limited, and approved through privacy/security review. Their absence does not invalidate an otherwise properly authenticated accounting decision unless a future approved security policy explicitly requires them.

Restricted evidence remains in separately approved encrypted, access-controlled, club/tenant-scoped storage. Access, export, exceptional technical access, legal hold, and deletion are auditable. No storage, signature, identity, or encryption provider is selected here.

## 11. Activation is not a feature flag

No accounting activation or club accounting-setting model currently exists. The normalized package-bound authorization is the future authority. A feature flag may later provide defense in depth only, must default off, and cannot substitute for review or authorization. Global activation is prohibited.

Source integrations require a separate club-scoped coverage and integration authorization after a correct opening is posted. Cutover activation never activates source hooks automatically.

## 12. Source-integration gates

Before any charge or payment hook, require:

- approved and posted opening package;
- authoritative coverage start;
- exact active account instances;
- approved mapping version and effective-date rules;
- deterministic source event and idempotency contracts;
- linked cancellation/void reversal semantics;
- atomic source-and-journal transaction or durable outbox design;
- fail-closed per-club enablement;
- reconciliation and stop behavior;
- separate integration authorization.

Charge integration remains blocked by unresolved exact due-category mappings, broad `other_contribution` semantics, absent mapping persistence, absent approved economic-date rule, source `financial_year` fields that do not prove journal Lions Year placement, and absent cancellation linkage.

Payment/allocation integration remains blocked because payment mode is not a ledger account, real asset mapping/private bank inventory is absent, payment void has no journal linkage, and no coverage/integration gate exists. Allocations remain subledger settlement evidence and create no second cash or bank posting.

Do not add dormant hooks, listeners, observers, queues, outbox writes, service bindings, or default-off code to the live source-write path before these contracts and gates are approved.

## 13. Implementation boundary and next gates

The next gates after approval of this contract are:

1. business, accounting, security, privacy, and architecture review;
2. reconciled evidence and separate DR-033 resolution;
3. separately authorized additive package/decision schema;
4. persistent package workflow without posting;
5. persisted-manifest validation and dry-run acceptance;
6. package-bound activation authorization implementation;
7. controlled opening posting;
8. separately authorized source-integration contracts and implementations.

This contract implements none of those gates.

## Unsafe shortcuts rejected

- Journal JSON metadata as the approval system.
- Treating the 3IIM fingerprint as final approval or evidence checksum.
- Shared credentials or one actor asserting joint authorization.
- Consuming authorization on a failed, non-posting attempt.
- Reusing authorization for changed semantics.
- Generic `finance.manage` as cutover authority.
- Feature flags as accounting approval.
- Dormant source hooks before coverage and activation gates.
- Inferring DR-033 from the balancing amount.
- Protected evidence in Git, journal metadata, or ordinary audit metadata.
