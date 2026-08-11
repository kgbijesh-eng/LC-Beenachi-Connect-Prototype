# Milestone 3IIZQ — Controlled Professional Evidence Intake & Cutover Authority Administration Contract

**Milestone mode:** First implementation/rehearsal pass
**Default scope:** Documentation / contract only
**Runtime change:** None intended
**Starting authoritative source:** `milestone-3iizb-announcement-read-state-foundation`
**Starting SHA:** `46bb5a1fb0e2ce238c1f94e8539e7d52c9cc04db`
**Milestone branch:** `milestone-3iizq-controlled-professional-evidence-intake-cutover-authority-administration-contract`

## A. Executive result

```text
PROFESSIONAL EVIDENCE PACKAGE READINESS:
PASS

EVIDENCE INTAKE CONTRACT READINESS:
PARTIAL

AUTHORITY ADMINISTRATION CONTRACT READINESS:
PARTIAL

AUTHENTICATED DELIVERY-SURFACE READINESS:
BLOCKED

OPERATIONAL AUTHORIZATION READINESS:
BLOCKED

TECHNICAL POSTGRESQL OPERATIONS READINESS:
PASS

OVERALL ACCOUNTING PRODUCTION READINESS:
NO-GO

DR-033:
Pending
```

Real-world gates remain unchanged:

```text
Independent CA review:
NOT PROVIDED

President approval:
NOT PROVIDED

Reconciled opening evidence:
NOT PROVIDED

Production mapping approval:
NOT PROVIDED

Activation authority:
NOT PROVIDED
```

No milestone document changes those facts.

## B. Baseline

The local preflight for this rehearsal was required to prove:

- source branch `milestone-3iizb-announcement-read-state-foundation`;
- exact local/remote SHA `46bb5a1fb0e2ce238c1f94e8539e7d52c9cc04db`;
- clean working tree;
- then creation of the local milestone branch with no upstream.

This report assumes that gate remains true and must be rechecked before final completion reporting.

## C. 3IIZP gap confirmation

3IIZP identified three administration/authoring limitations. Implementation inspection independently confirms them:

1. `ClubCutoverAuthorityAdministrationService` validates authority types and permission namespace but does not authenticate or authorize the supplied `appointedBy`, `assignedBy`, or revocation actor.
2. `ClubAccountingCutoverPackageService` draft creation, schedule replacement, evidence replacement, and validation-refresh accept/record actor identity for persistence/audit but do not resolve the approved package authoring permission themselves. `validate()` has no actor at all.
3. No production route/controller/command/job/listener/scheduler/frontend surface exposes cutover package/authority administration. Existing lifecycle/execution gates therefore are not currently bypassable through a production administration path.

An additional implementation-vs-approved-contract finding is recorded:

- current explicit active same-club membership/role resolution is implemented for the President, but preparer/verifier/CA/operator paths do not independently prove their active same-club role/membership;
- the approved technical-operator-vs-President separation rule is not explicitly rejected by the current operator resolver/execution path.

These must be closed before an authenticated surface can be ready.

## D. Authority and permission inventory

### Appointment authority types

Exactly:

- `independent_ca`;
- `technical_operator`.

President is resolved separately from active same-club `president-admin` membership. Preparer and internal verifier are lifecycle/package roles rather than authority-appointment types.

### Canonical permissions

Exactly:

1. `finance.cutover.package.prepare`
2. `finance.cutover.package.validate`
3. `finance.cutover.package.verify`
4. `finance.cutover.package.accounting-review`
5. `finance.cutover.package.business-approve`
6. `finance.cutover.package.dry-run-accept`
7. `finance.cutover.package.activate-authorize`
8. `finance.cutover.package.execute`
9. `finance.cutover.package.rollback-authorize`
10. `finance.cutover.package.view-restricted-manifest`

No cutover authority-administration permission exists.

No default seeded role receives any of the ten cutover permissions.

## E. Package-authoring authorization findings

Authoritative policy is already approved:

- Treasurer / appointed preparer + `prepare`: create/edit editable package and submit prepared;
- authorized preparer/internal verifier + `validate`: non-decisional validation;
- Secretary / appointed internal verifier + `verify`: internal-verification decision.

Current implementation enforcement begins too late for draft authoring:

- `createDraft`: actor exists/audited, no `prepare` resolution;
- `replaceSchedules`: expected lock/DRAFT/scope validation, no `prepare` resolution;
- `replaceEvidenceReferences`: expected lock/DRAFT/scope/input validation, no `prepare` resolution;
- `validate`: no actor;
- `refreshValidation`: actor audited, expected lock, no `validate` resolution;
- `prepare`: lifecycle service finally resolves `finance.cutover.package.prepare`;
- `verify`: resolves `finance.cutover.package.verify` and rejects preparer=self-verifier.

No current production surface exposes the earlier methods.

## F. Evidence-intake findings

Current evidence metadata is intentionally non-sensitive and package scoped.

Required verified evidence types:

- `cash_count`;
- `bank_reconciliation`;
- `member_receivables`;
- `liabilities_restrictions`;
- `reconciliation`.

Implemented metadata statuses:

- `unverified`;
- `verified`;
- `rejected`.

There is no implemented `submitted` or `superseded` evidence status.

The current model does not bind the `verification_status` to an authenticated verifier. Therefore metadata verification authority remains CADR-005.

## G. Digest findings

`digest_sha256`:

- optional;
- format-validated as 64 hexadecimal characters;
- included in the canonical package checksum;
- not calculated by the application from the external document;
- does not prove authenticity, provenance, signer identity, professional qualification, or accounting validity.

The canonical package checksum is SHA-256 over canonical package metadata, schedule data, and selected evidence metadata including digest/status. `reviewer_note` is not included.

## H. Evidence replacement/version/checksum findings

- evidence replacement is allowed only while `DRAFT`;
- replacement deletes/recreates the draft evidence-reference set;
- expected `lock_version` is required;
- change clears canonical checksum, validation timestamp, DR-033 classification, and readiness fields;
- lock version increments;
- package accounting-content change is audited;
- non-DRAFT evidence mutation is rejected.

Trusted review/approval records bind exact package version/checksum and are immutable once trusted. Current implementation has no post-review evidence edit path.

## I. Appointment/permission lifecycle findings

### Appointments

- immediate effective time;
- optional expiry;
- exact package/club/actor/type;
- DB trigger permits only one current appointment per package authority type;
- currentness excludes expired/revoked/invalidated records;
- immutable base record;
- append-only revocation/invalidation events;
- replacement is a new record after prior currentness ends.

### Permission assignments

- exact package/club/user/permission;
- immediate effective time;
- optional expiry;
- immutable base record;
- append-only revocation/invalidation events;
- resolver requires exactly one current assignment.

There is no equivalent DB one-current-per-user/permission guard; ambiguous duplicates fail closed at resolver time.

## J. Grantor/revoker findings

### Grantor

Not authorized by current administration service.

Pending:

- CADR-001 package permission grantor;
- CADR-002 independent-CA appointment grantor;
- CADR-003 technical-operator appointment grantor.

### Revoker

Appointment/permission revocation actor is recorded but not authorized by administration service.

Pending:

- CADR-004 appointment/permission revocation/invalidation authority.

Lifecycle decision revocation is separate and already controlled: original actor only + `finance.cutover.package.rollback-authorize`.

## K. Actor-separation findings

Already enforced:

- preparer != internal verifier;
- preparer/internal verifier != appointed CA;
- preparer/internal verifier != appointed technical operator;
- CA appointment != technical-operator appointment for the same actor;
- President != preparer/internal verifier;
- President final approver != trusted CA reviewer;
- dry-run/activation President decision actor = trusted President approver;
- dry-run/activation CA decision actor = trusted CA reviewer and != President approver;
- only original decision actor may revoke that current decision with rollback authorization.

Approved but not fully enforced in current internal services:

- technical operator must not also be President/business approver/dry-run/activation authorizer;
- non-President lifecycle actors require the approved active club role/membership checks before a future surface is exposed.

No new unapproved separation policy is promoted by 3IIZQ.

## L. President administration boundary

President authority requires:

- unique active same-club membership;
- role slug `president-admin`;
- exact package-scoped President permission;
- no preparer/internal-verifier collision.

Cutover authority administration does not create or modify the President club role. Underlying club-office governance remains separate.

## M. Independent-CA administration boundary

Application authority:

- current `independent_ca` appointment;
- exact package permission;
- actor separation;
- trusted review continuity.

Professional eligibility is external and unresolved operationally:

- CADR-006.

## N. Technical-operator administration boundary

Execution requires:

- current `technical_operator` appointment;
- `finance.cutover.package.execute`;
- exact package state/lock/checksum;
- current review/approval and decision pairs;
- UUID idempotency;
- no prior boundary execution;
- single transaction and one-time authority consumption.

Administration authority never implies execute authority.

## O. Read/inspection findings

Evidence-access principles are approved by DR-052, and `view-restricted-manifest` is a least-privilege evidence-manifest permission.

General authority/permission/revocation administration read access is not defined by a canonical permission and must not borrow `view-restricted-manifest`.

Pending:

- CADR-007 authority administration read/inspection policy.

## P. Authentication requirements

A future cutover administration surface must:

- use existing application authentication;
- derive the authenticated user server-side;
- derive same-club membership server-side;
- never trust client actor/grantor/revoker IDs;
- enforce exact club/package/tenant scope;
- enforce approved package permission;
- enforce approved grantor/revoker policy;
- revalidate appointment/assignment currentness inside mutation/execution transactions;
- reject stale package versions/checksums where binding applies;
- reject cross-club access;
- close known approved separation gaps;
- record safe immutable audit provenance.

## Q. Idempotency/locking requirements

Current package authoring/lifecycle uses optimistic package `lock_version`.

Authority/permission admin records use immutable references/events but have no request-idempotent replay service.

Activation execution has the strongest semantics:

- expected lock version;
- exact package checksum;
- execution reference;
- UUID idempotency key;
- exact replay returns the same committed result;
- changed retry conflicts;
- prior boundary execution conflicts;
- activation authority is consumed once.

Any future administration endpoint must define retry semantics before exposure rather than assuming current administration methods are idempotent.

## R. Audit requirements

Safe future audit dimensions:

- club/package/version;
- authenticated actor and subject actor;
- authority type or exact permission slug;
- effective/expiry dates;
- old/new package lock where applicable;
- package checksum where applicable;
- action/result;
- immutable reference;
- timestamp;
- revocation/invalidation provenance;
- opaque evidence reference/status.

Prohibited audit content includes secrets, bank identifiers, member-level schedules, raw professional evidence, credentials, signatures, and database dumps.

## S. Threat-model summary

The detailed threat matrix is in `production-accounting-cutover-authority-administration-contract.md`.

Highest unresolved threats before surface exposure:

- self-grant/self-appointment;
- unauthorized grantor privilege escalation;
- unauthorized appointment/permission revocation;
- client-supplied evidence `verified` state;
- fake CA eligibility;
- unauthorized authority-history inspection.

Important implementation gaps with approved policy already available:

- pre-prepare create/edit/validate authorization not resolved in package service;
- non-President active-role/membership enforcement incomplete in internal resolver paths;
- President/technical-operator conflict not fully enforced;
- duplicate-current package permission assignments are not prevented at DB insertion.

## T. Database / service / future-surface enforcement

### Database

Strong guarantees include:

- package/club relational scope;
- allowed authority/event/decision values;
- immutable authority/permission/event/decision/execution records;
- one current appointment per package authority type;
- one current decision per package/stage/authority;
- trusted review/approval immutability after authority binding;
- execution/boundary/consumption uniqueness controls.

### Service

Strong guarantees include:

- DRAFT-only package edits;
- optimistic package locking;
- exact-one current permission/appointment resolution;
- expiry/revocation filtering;
- lifecycle transitions;
- review/approval checksum binding;
- President uniqueness;
- actor separation described above;
- execution-time revalidation;
- exact execution idempotency and one-time consumption.

### Future surface

Still required:

- real request authentication;
- server-derived caller identity;
- active membership/role checks for all approved roles;
- grantor/revoker authorization;
- evidence-verifier authorization;
- authority-read authorization;
- safe retry contract for administration writes;
- closure of approved separation gaps.

### External governance/evidence

Still required:

- CADR approvals;
- independent CA eligibility/independence evidence;
- real reconciled opening evidence;
- real professional review and President decision;
- production mapping and activation authority.

## U. Unresolved CADR decisions

Pending count: **7**

1. CADR-001 — Package-scoped permission grantor policy
2. CADR-002 — Independent-CA appointment grantor policy
3. CADR-003 — Technical-operator appointment grantor policy
4. CADR-004 — Appointment/permission revocation and invalidation authority
5. CADR-005 — Evidence metadata verifier authority
6. CADR-006 — Independent-CA professional eligibility evidence
7. CADR-007 — Authority/revocation administration read and inspection policy

No CADR item resolves DR-033.

## V. Already-approved CADR-equivalent authority discovered

The repository already answers these policy questions and they must not be reopened merely to make the administration register look complete:

- package preparer identity/responsibility — DR-041;
- internal verifier identity/responsibility — DR-042;
- joint final approvers — DR-043;
- activation authorizers and technical execution boundary — DR-047;
- evidence access/privacy principles — DR-052;
- exact permission semantics, lifecycle actor matrix, separation and rollback rules — DR-053 / `CUTOVER-AUTHORIZATION-CONTRACT-2026-01`.

## W. Alternate mutation paths / default roles

Static inspection confirms:

- no cutover route/controller;
- no cutover console command;
- no application Jobs/Listeners path exposing authority mutation;
- `routes/console.php` contains only the default `inspire` command;
- tests create/assign/revoke cutover authority through the internal service as fixtures;
- no default role receives any cutover permission;
- no alternate production authority mutation path was identified.

If later local static search finds an alternate production path, authenticated delivery-surface readiness must remain BLOCKED until it is reconciled with this contract.

## X. Runtime implementation

3IIZQ first pass is documentation-only.

Expected files:

- `docs/finance/production-accounting-professional-evidence-intake-contract.md`
- `docs/finance/production-accounting-cutover-authority-administration-contract.md`
- `docs/finance/cutover-authority-administration-decision-register.md`
- `docs/finance/milestone-3iizq-controlled-professional-evidence-intake-cutover-authority-administration-contract.md`

No runtime file is required or authorized.

## Y. Verification plan

Focused local verification must cover at least:

- `backend/tests/Feature/Milestone3iizeCutoverPackagePersistenceAndPreviewTest.php`;
- `backend/tests/Feature/Milestone3iizfCutoverAuthorizationAndLifecycleContractTest.php`;
- `backend/tests/Feature/Milestone3iizgOpeningJournalAndOneTimeActivationTest.php`;
- PostgreSQL cutover concurrency coverage where the normal gate permits;
- permission/default-role assertions.

Full backend and frontend/static baselines must then be run exactly as required by the milestone instruction, using the clean-tree safeguard for untracked 3IIZQ docs if necessary.

**Verified local execution results:**

- Focused backend gate: **48 passed, 399 assertions, 0 failed**.
- Full backend clean-tree gate: **2047 passed, 6 skipped, 25,385 assertions, 0 failed**.
- Frontend gate: **15 test files passed, 178 tests passed, 0 failed**.
- TypeScript (`npx tsc --noEmit`): **PASS**.
- Lint: **0 errors, 87 warnings**.
- Production build (`npm run build`): **PASS**.
- Privacy / secret-like content scan across the four 3IIZQ documents: **PASS**.
- `tsconfig.tsbuildinfo`: **absent after verification/build**.
- `next-env.d.ts`: **unchanged**.
- The initial full-backend run with the four untracked 3IIZQ documents present produced nine historical clean-tree guard failures; the safeguarded clean-tree rerun passed completely, confirming those failures were document-presence guard interference rather than a functional backend regression.
## Z. Remaining production blockers

Unchanged:

```text
DR-033:
Pending

Independent CA review:
NOT PROVIDED

President approval:
NOT PROVIDED

Reconciled opening evidence:
NOT PROVIDED

Production mapping approval:
NOT PROVIDED

Activation authority:
NOT PROVIDED
```

Additional administration blockers:

- CADR-001 through CADR-007 Pending;
- no authenticated cutover administration surface;
- current package authoring permission enforcement gap;
- current non-President membership/role enforcement gap;
- current President/technical-operator conflict enforcement gap;
- no operational CA eligibility verification.

## AA. Recommended next milestone

Because governance decisions remain unresolved:

**Milestone 3IIZR — Cutover Administration Governance Decision Resolution**

3IIZR should resolve only CADR-001 through CADR-007 and the exact approved enforcement implications. It should not implement a production administration surface until those decisions are Approved.

The alternative **Authenticated Cutover Administration Backend Surface** path is premature.

## AB. Integrity confirmations for this first pass

- No policy was fabricated.
- No recommendation is marked Approved.
- No approval was fabricated.
- No DR-033 resolution was created.
- No real evidence was ingested.
- No real CA review was recorded.
- No real President approval was recorded.
- No real mapping was approved.
- No authority appointment was created by this milestone.
- No package permission assignment was created by this milestone.
- No accounting activation/cutover occurred.
- No route/controller/command/job/UI/API is authorized.
- Nothing should be staged, committed, pushed, or assigned an upstream during this first pass.
