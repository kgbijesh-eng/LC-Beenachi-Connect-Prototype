# Cutover Authority Administration Decision Register

**Register:** CADR — Milestone 3IIZQ
**Purpose:** Only unresolved governance decisions required to complete safe cutover authority/evidence administration.
**Boundary:** CADR decisions are independent of DR-033. DR-033 remains Pending and is not renumbered here.

All seven CADR items below were explicitly approved in Milestone 3IIZR after governance review. Their approved resolutions are authoritative for later implementation, but 3IIZR itself authorizes no runtime implementation.

## Already-approved policy that does not require a CADR

The following are authoritative and must not be reopened by this register:

- DR-041 — Treasurer Ranjith M. R. is the opening-package preparer and cannot be sole verifier/approver.
- DR-042 — Secretary Rajeesh P. R. is internal document verifier; qualified independent Chartered Accountant review remains separate.
- DR-043 — final opening-package approval requires separate President and independent accounting-reviewer decisions.
- DR-047 — activation requires separate President and independent accounting-reviewer authorization; technical operator only executes.
- DR-052 — least-privilege cutover evidence access/privacy principles.
- DR-053 / `CUTOVER-AUTHORIZATION-CONTRACT-2026-01` — exact cutover permission semantics, role matrix, lifecycle transitions, separation, rollback principles, and Normal Member denial.
- Exact canonical cutover permission slugs already seeded in the permission catalogue.

This register does not invent a new authority type or permission slug.

---

## CADR-001 — Package-scoped permission grantor policy

**Question**
Who is authorized to create package-scoped `finance.cutover.package.*` permission assignments, and what approval proof must the assigning operation require?

**Why it matters**
`ClubCutoverAuthorityAdministrationService::assignPermission` validates only that the permission exists in the cutover namespace. It persists `assigned_by_user_id` but does not prove that the assigner may grant the permission. Without a policy, any exposed caller path could become a privilege-escalation/self-grant path.

**Known constraints**

- generic `finance.manage`, repository ownership, technical access, and ordinary administrator power are insufficient;
- no default role currently receives cutover permissions;
- package assignments must remain exact-package scoped;
- grant must not defeat approved actor-separation rules;
- there is no canonical cutover-administration permission today;
- DR-041/042/043/047/053 already define who performs lifecycle work after a valid assignment exists.

**Candidate options if objectively derivable**

1. One specifically approved governance actor may grant defined package permissions.
2. Different approved grantors may govern different permission classes.
3. Defined high-risk grants may require two separately authenticated approvals.
4. A dedicated cutover-administration authority may be created in a later approved contract.

The exact actor/permission name cannot be selected from current repository authority.

**Recommended direction**
Define an explicit least-privilege grantor policy, prohibit grantor-as-grantee unless expressly approved, and use stronger dual control for high-risk review/activation/execution grants if governance approves it. Do not reuse generic finance permissions.

**Approved resolution**

Package-scoped cutover permission assignments require joint approval by the active same-club President and Secretary.
Neither approval alone grants the permission. Generic finance/admin authority, repository ownership, technical access, or ordinary administrator power cannot substitute for this dual approval.
Every assignment remains exact-package scoped and auditable. A governance approver may also be the grantee only where the already-approved role matrix permits that role and the other governance officer independently co-approves it. No grant may create or preserve a prohibited separation-of-duties conflict.

**Approval owner**
Business owner + security/architecture review, consistent with the DR-053 approval boundary.

**Status**
Approved

**Implementation blocked?**
No longer blocked by this governance question. 3IIZR itself still authorizes no implementation; a later explicitly authorized implementation milestone must enforce this resolution exactly.

---

## CADR-002 — Independent-CA appointment grantor policy

**Question**
Who may create or replace a package-scoped `independent_ca` appointment?

**Why it matters**
The current `appoint` method persists `appointed_by_user_id` but does not authorize that user. The CA appointment is prerequisite authority for accounting review and joint activation decisions.

**Known constraints**

- only one current `independent_ca` appointment per package is DB-enforced;
- the appointee cannot be package preparer/internal verifier or current technical operator;
- application appointment does not prove professional qualification/independence;
- President and independent CA must remain separate decision actors;
- replacement must not rewrite the old appointment; currentness ends through expiry/revocation/invalidation and a new immutable appointment is created.

**Candidate options if objectively derivable**

- a specifically approved single governance actor;
- a separately approved dual-control appointment;
- a dedicated cutover-governance authority.

Current sources do not choose among them.

**Recommended direction**
Require an explicit non-self appointment authority, package scope, professional-eligibility prerequisite, and immutable appointment reference; consider dual control because the appointment participates in accounting review and activation.

**Approved resolution**

A package-scoped Independent CA appointment requires joint approval by the active same-club President and Secretary.
The CA cannot self-appoint and must be separate from the President, package preparer, internal verifier, and Technical Operator for that package.
The appointment does not become operational merely because it exists in the application. CADR-006 professional-eligibility and independence evidence must first be satisfied.

**Approval owner**
Business owner + security/architecture review; professional-independence requirements require accounting/professional input.

**Status**
Approved

**Implementation blocked?**
No longer blocked by this governance question. 3IIZR itself still authorizes no implementation; a later explicitly authorized implementation milestone must enforce this resolution exactly.

---

## CADR-003 — Technical-operator appointment grantor policy

**Question**
Who may create or replace a package-scoped `technical_operator` appointment?

**Why it matters**
The technical operator can execute the exact jointly authorized package. The current administration method validates the appointment type but does not authorize the appointing user.

**Known constraints**

- only one current `technical_operator` appointment per package is DB-enforced;
- the operator requires separate `finance.cutover.package.execute`;
- the operator cannot prepare, internally verify, accounting-review, business-approve, dry-run accept, or activation-authorize the same package under the approved contract;
- execution authority must be current immediately before posting;
- administration authority must not imply execution authority.

**Candidate options if objectively derivable**

- a specifically approved governance actor;
- a technical-security owner plus separate governance approval;
- a dedicated cutover-administration authority.

Current sources do not choose the grantor.

**Recommended direction**
Use explicit non-self, least-privilege appointment authority and enforce the approved President/technical-operator conflict before allowing the appointment.

**Approved resolution**

A package-scoped Technical Operator appointment requires joint approval by the active same-club President and Secretary.
The Technical Operator must be separate from the President, package preparer, Secretary/internal verifier, and Independent CA for that package.
The operator performs execution only. Appointment does not confer preparation, verification, accounting-review, business-approval, dry-run acceptance, activation-authorization, or rollback authority. Exact execute authority and current appointment must be revalidated immediately before execution.

**Approval owner**
Business owner + security/architecture review.

**Status**
Approved

**Implementation blocked?**
No longer blocked by this governance question. 3IIZR itself still authorizes no implementation; a later explicitly authorized implementation milestone must enforce this resolution exactly.

---

## CADR-004 — Appointment and permission revocation/invalidation authority

**Question**
Who may revoke or invalidate an authority appointment or package permission assignment, and may the subject self-revoke?

**Why it matters**
The current administration methods append revocation events using any supplied `User` as `recorded_by_user_id` without proving revocation authority.

**Known constraints**

- authority/permission records are immutable;
- revocation/invalidation must be append-only;
- resolver currentness immediately excludes revoked/invalidated authority;
- lifecycle decision revocation is already separately approved: only the original President/CA decision actor with `rollback-authorize` may revoke that actor's own current decision;
- consumed execution/activation provenance must never be deleted or made reusable;
- revocation must not silently rewrite trusted historical decisions.

**Candidate options if objectively derivable**

1. original grantor may revoke;
2. subject may self-revoke;
3. separately approved governance authority may revoke;
4. high-risk revocation may require dual control.

These are alternatives, not approved policy.

**Recommended direction**
Permit rapid fail-safe removal of unconsumed authority while preserving immutable history; define exactly who may initiate and approve it, and serialize/revalidate revocation transactionally. Self-revocation may be useful as a safety stop but must be explicitly approved.

**Approved resolution**

An authority holder may immediately self-revoke their own unconsumed cutover appointment or package permission as a fail-safe.
Revoking or invalidating another person's appointment or package permission requires joint approval by the active same-club President and Secretary.
All revocation/invalidation remains append-only and auditable. Historical appointments, permissions, reviews, approvals, and decisions are never deleted or rewritten. Consumed execution authority can never be made reusable by revocation or later replacement.
Existing President/Independent-CA lifecycle-decision revocation rules remain separate and unchanged.

**Approval owner**
Business owner + security/architecture review.

**Status**
Approved

**Implementation blocked?**
No longer blocked by this governance question. 3IIZR itself still authorizes no implementation; a later explicitly authorized implementation milestone must enforce this resolution exactly.

---

## CADR-005 — Evidence metadata verifier authority

**Question**
Which authenticated actor may set cutover evidence metadata to `verified` or `rejected`, and what evidence-level audit binding is required?

**Why it matters**
The current evidence replacement method accepts `verification_status` as input. The evidence-reference row has no verifier-user field/event. Validation treats required evidence types with status `verified` as present, but the service does not prove who authorized that status.

**Known constraints**

- implemented states are exactly `unverified`, `verified`, `rejected`;
- Treasurer/appointed preparer is approved to create/edit package accounting content while editable;
- Secretary/appointed internal verifier is approved to validate evidence references and record internal verification;
- independent CA review is separate and professionally stronger than a metadata flag;
- a `verified` flag cannot by itself establish document authenticity, provenance, professional acceptance, or reconciliation;
- client-supplied actor/status must not become authorization.

**Candidate options if objectively derivable**

- Secretary/internal verifier sets evidence-level verification;
- a separately appointed evidence verifier sets it;
- evidence rows remain `unverified` until a distinct verification event/binding is recorded;
- different evidence classes have different authorized verifiers.

Current approved contracts do not select the exact metadata-state actor/workflow.

**Recommended direction**
Use a separately authenticated verification act with actor, package/version, opaque evidence reference, status, time, and safe reason/provenance. Do not let the package preparer self-certify evidence merely by submitting `verified`.

**Approved resolution**

The Treasurer/package preparer may submit or replace evidence metadata only in the unverified state.
The Secretary/internal verifier is the authenticated actor authorized to mark evidence metadata verified or rejected.
Each verification/rejection must bind the actor, club, package, package version, opaque evidence reference, resulting status, timestamp, and a safe reason or note.
Evidence metadata verification is an internal control only and never substitutes for Independent CA professional review, document authenticity, reconciliation, or professional acceptance.

**Approval owner**
Business owner + accounting reviewer + privacy/security review for protected-evidence handling.

**Status**
Approved

**Implementation blocked?**
No longer blocked by this governance question. 3IIZR itself still authorizes no implementation; a later explicitly authorized implementation milestone must enforce this resolution exactly.

---

## CADR-006 — Independent-CA professional eligibility evidence

**Question**
What external evidence proves that an appointee is a qualified, currently eligible, independent Chartered Accountant for the package, and who verifies that evidence before the application treats the appointment as eligible?

**Why it matters**
The application can prove only package appointment/permission and actor separation. It cannot establish professional qualification, standing, identity, or real-world independence by code alone.

**Known constraints**

- independent accounting review is mandatory;
- the reviewer must be a qualified Chartered Accountant;
- protected credential/identity documents stay outside Git and ordinary audit metadata;
- a non-sensitive opaque reference/digest may be retained;
- 3IIZQ must not build credential-verification logic or ingest real credentials.

**Candidate options if objectively derivable**

- controlled external credential/standing check with an opaque eligibility reference;
- professional-engagement evidence plus independence declaration;
- a combination of eligibility and independence evidence.

The exact required evidence set and verifier are not approved.

**Recommended direction**
Require externally controlled qualification/standing and independence evidence before the application marks a CA appointment operationally eligible; retain only non-sensitive opaque references and audit provenance in ordinary application data.

**Approved resolution**

An Independent CA appointment becomes operational only after required professional qualification, identity, current standing where applicable, engagement/appointment evidence, and independence evidence have been externally checked.
The active same-club President and Secretary jointly confirm that the required external eligibility and independence evidence has been checked before the application treats the appointment as operationally eligible.
That confirmation does not make the President or Secretary the professional certifier. Sensitive credentials, identity documents, signatures, declarations, and professional working papers remain outside Git and ordinary application audit content.
This CADR-006 confirmation is separate from CADR-005 evidence-metadata verification: CADR-005 governs package evidence metadata verification, while CADR-006 governs whether an Independent CA appointment may become operationally eligible.
The application may retain only safe eligibility status, verification date, the identities of the President and Secretary as governance-attesting officers, opaque references to the external evidence or verification source, optional digests, independence status, and safe provenance metadata. Those officers must not be represented as the professional certifier merely because they provided the required governance confirmation.

**Approval owner**
Business owner + independent professional/accounting governance + privacy/security review.

**Status**
Approved

**Implementation blocked?**
No longer blocked by this governance question. 3IIZR itself still authorizes no implementation; a later explicitly authorized implementation milestone must enforce this resolution exactly.

---

## CADR-007 — Authority/revocation administration read and inspection policy

**Question**
Who may inspect cutover authority appointments, package permission assignments, grantor identities, effective/expiry dates, and revocation/invalidation history?

**Why it matters**
A future administration surface needs read access to operate safely. The current canonical `finance.cutover.package.view-restricted-manifest` concerns evidence-manifest access and must not be silently repurposed into a general administration-history permission.

**Known constraints**

- DR-052 already defines evidence-access principles;
- Normal Members are denied cutover permissions and protected club-wide evidence access;
- restricted evidence content must never be exposed merely because authority metadata is visible;
- no canonical authority-administration read permission exists today;
- President oversight does not automatically prove permission to every administration record unless the policy explicitly says so.

**Candidate options if objectively derivable**

- President oversight plus specifically designated finance/security administrators;
- subject-only view for own appointment/permissions plus governance-wide audit view;
- time-limited auditor/reviewer access.

Current sources do not provide a complete authority-history read matrix.

**Recommended direction**
Approve a least-privilege metadata-only read policy separately from restricted evidence access. Expose package status/version/checksum, authority/permission bindings, expiry/currentness, revocation history, opaque evidence status, and blockers only to explicitly authorized actors.

**Approved resolution**

The President and Secretary receive full metadata-only visibility of cutover authority administration history for their club.
The Treasurer/package preparer may see package readiness and their own relevant assignments and authority status, but not unrelated CA/operator administration history.
The Independent CA may see their own appointment, permissions, currentness/revocation/expiry state, and package metadata required for professional review.
The Technical Operator may see their own appointment, execute permission, and current execution-authority status.
Normal Members receive no authority-administration access.
Protected evidence content remains separately controlled. finance.cutover.package.view-restricted-manifest must not be repurposed as a general authority-administration-history permission.

**Approval owner**
Business owner + security/privacy review.

**Status**
Approved

**Implementation blocked?**
No longer blocked by this governance question. 3IIZR itself still authorizes no implementation; a later explicitly authorized implementation milestone must enforce this resolution exactly.

---

## Register summary

| ID | Short title | Status | Governance ambiguity still blocks later implementation? |
|---|---|---|---:|
| CADR-001 | Package permission grantor policy | Approved | No |
| CADR-002 | Independent-CA appointment grantor | Approved | No |
| CADR-003 | Technical-operator appointment grantor | Approved | No |
| CADR-004 | Appointment/permission revocation authority | Approved | No |
| CADR-005 | Evidence metadata verifier authority | Approved | No |
| CADR-006 | Independent-CA professional eligibility evidence | Approved | No |
| CADR-007 | Authority administration read/inspection policy | Approved | No |

**Approved CADR count: 7**

**Pending CADR count: 0**

All seven 3IIZQ governance questions are resolved.

Resolution of these CADRs does **not** itself authorize an authenticated administration API, controller, policy, command, UI, database migration, permission assignment, authority appointment, evidence ingestion, CA professional review, President cutover approval, accounting activation, or production cutover.

DR-033 remains Pending and is independent of this CADR register.
