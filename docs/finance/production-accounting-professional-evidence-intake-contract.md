# Production Accounting Professional Evidence Intake Contract

**Contract:** Milestone 3IIZQ first implementation/rehearsal pass
**Scope:** Non-sensitive professional-evidence metadata intake for the controlled accounting cutover
**Runtime impact:** None. This document creates no route, controller, command, job, UI, API, permission grant, appointment, approval, evidence record, journal, activation, or cutover.

## 1. Purpose

This contract defines the repository-safe boundary for professional-evidence metadata used by the controlled accounting cutover.

It preserves the approved accounting and cutover decisions in:

- `docs/finance/club-accounting-cutover-decision-register.md`;
- `docs/finance/club-accounting-cutover-package-activation-contract.md`;
- `docs/finance/production-accounting-professional-evidence-pack.md`;
- `docs/finance/production-accounting-operational-authorization-checklist.md`;
- `docs/finance/milestone-3iizp-production-accounting-professional-evidence-operational-authorization-gate.md`.

It does not establish a document repository for restricted evidence. It does not resolve DR-033. It does not establish professional qualification or independence by itself.

## 2. Approved actor baseline

Existing approved authority is preserved:

- DR-041 identifies the Treasurer / appointed preparer as the opening-package preparer.
- DR-042 identifies the Secretary / appointed internal verifier as the internal document verifier and requires separate qualified Chartered Accountant review.
- DR-043 and DR-047 require separate President and independent accounting-reviewer decisions for final approval and activation.
- DR-052 establishes least-privilege evidence-access principles.
- DR-053 and `CUTOVER-AUTHORIZATION-CONTRACT-2026-01` approve the cutover permission semantics and role/separation matrix.

For package authoring, the approved contract is explicit:

- `finance.cutover.package.prepare` covers creation and accounting-content edits while a package version remains editable.
- `finance.cutover.package.validate` covers structural, schedule, evidence-reference, checksum, reconciliation, and preview checks, without creating an independent decision.
- the Treasurer / appointed preparer creates and edits the editable package and may validate it;
- the Secretary / appointed internal verifier may validate and records the internal-verification decision using `finance.cutover.package.verify`.

The current internal package service does not enforce those authoring permissions on `createDraft`, schedule replacement, evidence replacement, or `refreshValidation`; that is an implementation boundary to close before a production surface is introduced.

## 3. Evidence categories

The current validation service requires a `verified` metadata reference for each of these exact evidence types:

| Evidence type | Purpose |
|---|---|
| `cash_count` | Opening cash-count support |
| `bank_reconciliation` | Opening bank reconciliation support |
| `member_receivables` | Private member/obligation receivables support |
| `liabilities_restrictions` | Liability and external-restriction support |
| `reconciliation` | Overall opening reconciliation support |

Other evidence types may exist only if accepted by the existing metadata validation rules and an approved accounting/package contract requires them. A new evidence type does not acquire accounting meaning merely because the string format is accepted.

## 4. Repository-safe metadata

The current `ClubAccountingCutoverEvidenceReference` persistence model supports non-sensitive metadata only:

- club;
- cutover package;
- `evidence_type`;
- `opaque_reference`;
- `evidence_date`;
- `evidence_version`;
- optional `digest_sha256`;
- `verification_status`;
- optional non-sensitive `reviewer_note`;
- ordinary row timestamps.

The contract additionally requires future authenticated intake/audit handling to retain, through existing package/decision/audit records where applicable:

- authenticated actor;
- exact package/version and lock version;
- canonical package checksum when one exists;
- action/outcome;
- safe immutable operation/reference identity;
- verification/revocation provenance without protected document content.

No schema addition is authorized by 3IIZQ.

## 5. Restricted external evidence

The following remains outside Git, public documentation, ordinary audit payloads, and non-sensitive package manifests:

- bank statements;
- real bank account numbers or identifiers;
- member-level receivables and obligation schedules;
- liability schedules containing protected personal or third-party information;
- Chartered Accountant working papers;
- professional credential documents;
- signed confidential opinions;
- identity documents;
- real signatures;
- private financial exports;
- passwords, tokens, keys, database credentials;
- production database dumps.

Restricted evidence belongs only in separately approved encrypted, access-controlled, club/tenant-scoped storage with auditable access, export, hold, retention, and deletion controls.

## 6. Current evidence metadata states

The implemented evidence-reference states are exactly:

- `unverified`;
- `verified`;
- `rejected`.

There is no implemented evidence-reference status named `submitted` or `superseded`.

These metadata states must not be overstated. Four different facts remain distinct:

1. **Evidence metadata recorded** — a non-sensitive reference row exists.
2. **External source document actually reviewed** — an authorized human reviewed the controlled external bytes/document.
3. **Evidence professionally accepted** — the appropriate professional decision-maker accepted its accounting relevance/quality.
4. **Package accounting reconciliation passed** — application validation and reconciliation checks pass for the package.

A `verified` metadata value does not, by itself, prove items 2–4.

## 7. Evidence-verifier authorization gap

The current evidence replacement API accepts `verification_status` as caller-supplied metadata and persists `verified` or `rejected` after format/input validation. The evidence-reference model has no verifier-user binding and no evidence-verification event.

The repository does approve the Secretary / internal verifier for package internal verification and requires an independent Chartered Accountant for professional accounting review, but it does not yet prove which authenticated actor is authorized to set the evidence-reference metadata field itself to `verified` or `rejected`, nor what evidence-level audit binding is required.

Therefore:

**EVIDENCE METADATA VERIFIER POLICY: PENDING — CADR-005**

Until CADR-005 is approved, a future production intake surface must fail closed rather than treating a client-supplied verification status as an authorized verification act.

## 8. Digest semantics

`digest_sha256` is optional in the current evidence-reference input.

When present:

- it must be exactly 64 hexadecimal characters;
- its field name and format represent a SHA-256 digest;
- it is included in the canonical package-checksum input;
- changing it while the package is editable changes the subsequently calculated package checksum.

The current application does **not**:

- fetch the external document;
- calculate the digest from externally controlled bytes;
- compare the supplied digest with a document stored in an approved evidence store;
- prove who created the document;
- prove who signed it;
- prove professional validity;
- prove provenance or authenticity.

A SHA-256 digest proves equality to a byte sequence only when the reference byte sequence is independently controlled and compared. Digest equality is not an authenticity or professional-approval guarantee.

`reviewer_note` is validated for length/sensitive-value patterns but is not part of the canonical package checksum. It may nevertheless be changed only through evidence replacement while the package is `DRAFT`; that replacement clears the package checksum and advances the package lock version.

## 9. Replacement and package binding

Current implementation behavior:

- evidence references may be replaced only while package status is `DRAFT`;
- replacement deletes the current evidence-reference rows for that draft and creates the supplied normalized set;
- the caller must supply the expected package `lock_version`;
- a stale lock fails;
- replacement clears the canonical checksum and last-validation timestamp;
- replacement clears DR-033 classification/readiness fields;
- replacement records an accounting-content-change audit event;
- replacement increments `lock_version`.

A package in `PREPARED` or any later lifecycle state cannot have evidence metadata replaced through the current package service.

The approved cutover contract is stricter for trusted history: a material change after trust has begun is a new package/version and requires validation and all dependent decisions again. Current runtime successor/supersession workflow is not created by 3IIZQ.

Required principle:

> No mutable evidence fact may silently survive into a trusted approval under an old package binding.

The current DRAFT-only edit rule, checksum clearing, optimistic lock increment, and later immutable review/approval bindings satisfy that principle for the implemented edit path. No post-review evidence edit path exists.

## 10. Submit/edit/validate authority

### Approved policy

- Create initial editable package: Treasurer / appointed preparer with `finance.cutover.package.prepare`.
- Replace schedules/accounting content while editable: same preparer with `finance.cutover.package.prepare`.
- Replace evidence metadata while editable: same preparer under the approved accounting-content-edit authority.
- Run non-decisional validation: authorized preparer or internal verifier with `finance.cutover.package.validate`.
- Record internal-verification decision: Secretary / appointed internal verifier with `finance.cutover.package.verify`.
- Record professional accounting review: appointed independent Chartered Accountant with `finance.cutover.package.accounting-review`.
- Record President business approval: unique active same-club President with `finance.cutover.package.business-approve`.

### Current service limitation

`ClubAccountingCutoverPackageService` records/accepts actor IDs for draft creation and draft mutation but does not itself resolve the required package-scoped permission. `validate()` has no actor argument, and `refreshValidation()` records an actor but does not resolve `finance.cutover.package.validate`.

A future authenticated surface must enforce the approved policy before calling those methods. A client-supplied actor ID is never authorization.

## 11. Professional acceptance and CA eligibility

Application appointment and real professional eligibility are separate.

The application can require:

- exact package-scoped `independent_ca` appointment;
- exact package-scoped `finance.cutover.package.accounting-review`;
- actor separation;
- checksum/version binding;
- immutable review.

It cannot establish real professional qualification, current standing, independence, or credential validity from those records alone.

The repository requires a qualified independent Chartered Accountant, but the exact external eligibility evidence and who verifies that eligibility are not yet operationally defined.

Therefore:

**INDEPENDENT-CA ELIGIBILITY EVIDENCE POLICY: PENDING — CADR-006**

No credential-verification logic is created by 3IIZQ.

## 12. Approved evidence read boundary

DR-052 already establishes these evidence-access principles:

- President: approval/oversight access;
- Treasurer: preparation/authorized finance access;
- Secretary: time-limited verification access;
- independent reviewer: time-limited review access;
- technical operator: no default restricted-evidence access; exceptional minimum-scope access only when separately authorized, time-limited, and logged;
- Normal Member: no club-wide/bank/other-member/donor/beneficiary evidence access.

`finance.cutover.package.view-restricted-manifest` permits least-privilege manifest access only. It never authorizes unrestricted external evidence content.

Authority-administration records and revocation-history inspection are a different read domain and remain pending under CADR-007.

## 13. Future authenticated intake surface requirements

Any future evidence-intake surface must:

1. use the existing application authentication system;
2. derive the authenticated user server-side;
3. never trust an actor, submitter, verifier, grantor, or reviewer ID supplied by the client as authorization;
4. resolve active same-club membership server-side where the approved role contract requires it;
5. enforce exact package club scope and tenant isolation;
6. require the exact package-scoped cutover permission;
7. require expected `lock_version` for draft mutation/validation refresh;
8. reject stale versions and cross-club package identifiers;
9. prevent the client from claiming `verified`/`rejected` until CADR-005 supplies an approved verifier policy;
10. keep restricted document bytes outside Git and ordinary application audit metadata;
11. record safe actor/package/old-new-version/outcome audit provenance;
12. re-evaluate current authority inside the mutation transaction where trust would otherwise be created.

## 14. Idempotency and concurrency

Current semantics are preserved:

- draft replacement and validation refresh use optimistic `lock_version`;
- after a successful mutation, an exact retry using the old lock is stale rather than idempotently replayed;
- evidence references are unique by `(cutover_package_id, opaque_reference)`;
- no evidence-intake request idempotency key exists today.

A future endpoint may add explicit request-idempotency handling only as a separately implemented surface requirement. 3IIZQ does not pretend the underlying package service already provides it.

## 15. Audit requirements

Safe audit dimensions for a future surface:

- club;
- package public ID;
- package version;
- old/new lock version;
- authenticated actor;
- action;
- evidence type;
- opaque evidence reference;
- evidence version/date;
- verification status transition;
- digest-present/digest value where approved as non-sensitive metadata;
- timestamp;
- immutable operation/reference;
- outcome/blocker.

Do not log raw evidence, credentials, bank account numbers, member schedules, signatures, tokens, or unrestricted reviewer working papers.

## 16. Fail-closed rules

A future evidence-intake surface must fail closed when:

- actor authentication is absent;
- same-club/tenant scope cannot be proven;
- required package permission cannot be resolved exactly once;
- package version/lock is stale;
- the package is no longer editable;
- a restricted value appears where only opaque metadata is permitted;
- required evidence metadata is absent;
- evidence status is invalid;
- a digest is malformed;
- evidence-verifier authorization is not approved/resolvable;
- CA professional eligibility is not externally verified under an approved policy;
- package/checksum binding is stale;
- DR-033 or another readiness blocker remains unresolved.

## 17. Readiness classification

**EVIDENCE INTAKE CONTRACT READINESS: PARTIAL**

The safe metadata, privacy, package-authoring, replacement, checksum, access, and fail-closed boundaries are sufficiently defined. Production authorization remains incomplete because CADR-005 and CADR-006 are unresolved and current draft/validation service methods do not enforce the approved authoring permissions themselves.

**OPERATIONAL AUTHORIZATION READINESS: BLOCKED**

**OVERALL ACCOUNTING PRODUCTION READINESS: NO-GO**
