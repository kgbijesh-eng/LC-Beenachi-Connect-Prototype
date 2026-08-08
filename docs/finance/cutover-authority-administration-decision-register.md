# Cutover Authority Administration Decision Register

**Register:** CADR — Milestone 3IIZQ
**Purpose:** Only unresolved governance decisions required to complete safe cutover authority/evidence administration.
**Boundary:** CADR decisions are independent of DR-033. DR-033 remains Pending and is not renumbered here.

No item below is Approved merely because a recommendation is provided.

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

**Approval owner**
Business owner + security/architecture review, consistent with the DR-053 approval boundary.

**Status**
Pending

**Implementation blocked?**
Yes — all authenticated permission-assignment endpoints/policies and any automated assignment workflow.

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

**Approval owner**
Business owner + security/architecture review; professional-independence requirements require accounting/professional input.

**Status**
Pending

**Implementation blocked?**
Yes — production CA-appointment creation/replacement surface.

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

**Approval owner**
Business owner + security/architecture review.

**Status**
Pending

**Implementation blocked?**
Yes — production technical-operator appointment/replacement surface.

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

**Approval owner**
Business owner + security/architecture review.

**Status**
Pending

**Implementation blocked?**
Yes — appointment/permission revoke/invalidate endpoints and automated revocation workflows.

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

**Approval owner**
Business owner + accounting reviewer + privacy/security review for protected-evidence handling.

**Status**
Pending

**Implementation blocked?**
Yes — production evidence-verification mutation and any production validation gate that relies on authorized `verified` metadata.

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

**Approval owner**
Business owner + independent professional/accounting governance + privacy/security review.

**Status**
Pending

**Implementation blocked?**
Yes — any production rule that treats a CA application appointment as professionally eligible.

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

**Approval owner**
Business owner + security/privacy review.

**Status**
Pending

**Implementation blocked?**
Yes — authority-administration listing/detail/history endpoints or UI.

---

## Register summary

| ID | Short title | Status | Blocks implementation? |
|---|---|---|---:|
| CADR-001 | Package permission grantor policy | Pending | Yes |
| CADR-002 | Independent-CA appointment grantor | Pending | Yes |
| CADR-003 | Technical-operator appointment grantor | Pending | Yes |
| CADR-004 | Appointment/permission revocation authority | Pending | Yes |
| CADR-005 | Evidence metadata verifier authority | Pending | Yes |
| CADR-006 | Independent-CA professional eligibility evidence | Pending | Yes |
| CADR-007 | Authority administration read/inspection policy | Pending | Yes |

**Pending CADR count: 7**

These decisions are deliberately narrower than DR-033. They authorize no accounting classification, evidence, mapping, appointment, permission, approval, or production operation.
