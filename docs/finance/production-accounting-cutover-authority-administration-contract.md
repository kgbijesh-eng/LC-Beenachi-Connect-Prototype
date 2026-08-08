# Production Accounting Cutover Authority Administration Contract

**Contract:** Milestone 3IIZQ first implementation/rehearsal pass
**Scope:** Administration of controlled cutover authority and package-scoped permissions
**Runtime impact:** None. This contract does not add or grant a permission, create an appointment, expose a surface, record a decision, or activate accounting.

## 1. Purpose and authority boundary

This contract separates:

- approved lifecycle authority;
- currently implemented mechanical authority records;
- authorization of the person performing authority administration;
- future authenticated delivery-surface controls.

The first two exist. The third is not fully defined. Therefore the fourth must remain blocked.

Authoritative sources include:

- `docs/finance/club-accounting-cutover-decision-register.md`;
- `docs/finance/club-accounting-cutover-package-activation-contract.md`;
- `docs/finance/milestone-3iizp-production-accounting-professional-evidence-operational-authorization-gate.md`;
- `backend/app/Services/Finance/ClubCutoverAuthorityAdministrationService.php`;
- `backend/app/Services/Finance/ClubCutoverAuthorityResolver.php`;
- `backend/app/Services/Finance/ClubCutoverLifecycleService.php`;
- `backend/app/Services/Finance/ClubOpeningJournalActivationService.php`;
- cutover models, migrations, permission seeder, and feature tests.

## A. Existing authority types

`ClubAccountingCutoverAuthorityAppointment` defines exactly two appointment types:

| Exact authority type | Purpose | Package scoped | Currentness |
|---|---|---:|---|
| `independent_ca` | Binds the independent Chartered Accountant/accounting reviewer to one package | Yes | Effective, unexpired, not revoked/invalidated |
| `technical_operator` | Binds the technical execution operator to one package | Yes | Effective, unexpired, not revoked/invalidated |

There is no authority-appointment type for:

- preparer;
- internal verifier;
- President;
- grantor;
- revoker;
- evidence submitter;
- evidence verifier.

The preparer/internal verifier are represented by approved business-role decisions plus package permissions and lifecycle provenance. The President is resolved from active same-club role membership, not an appointment record.

## B. Existing canonical cutover permissions

The authoritative permission catalogue contains exactly ten `finance.cutover.package.*` permissions:

| Canonical permission slug | Purpose / approved actor | Package-scoped assignment? | Assignment consumed? | Revocable? | Lifecycle use | Existing resolver/service |
|---|---|---:|---|---:|---|---|
| `finance.cutover.package.prepare` | Create/edit editable package and submit prepared; Treasurer / appointed preparer | Yes | No | Yes | DRAFT authoring; DRAFT→PREPARED | Resolver at `prepare`; package draft methods currently do not resolve it |
| `finance.cutover.package.validate` | Non-decisional validation; preparer/internal verifier as approved | Yes | No | Yes | DRAFT/preparation verification checks | Catalogue exists; `refreshValidation` currently does not resolve it |
| `finance.cutover.package.verify` | Internal verification; Secretary / appointed internal verifier | Yes | No | Yes | PREPARED→INTERNALLY_VERIFIED | `ClubCutoverLifecycleService::verify` |
| `finance.cutover.package.accounting-review` | Independent accounting review | Yes | No | Yes | INTERNALLY_VERIFIED→ACCOUNTING_REVIEWED; rechecked at execution | CA appointment + resolver + lifecycle/execution |
| `finance.cutover.package.business-approve` | President business approval | Yes | No | Yes | ACCOUNTING_REVIEWED→BUSINESS_APPROVED; rechecked at execution | unique active President resolver + lifecycle/execution |
| `finance.cutover.package.dry-run-accept` | Separate President and CA dry-run acceptance | Yes | No | Yes | BUSINESS_APPROVED→DRY_RUN_ACCEPTED after both current decisions | lifecycle decision resolver |
| `finance.cutover.package.activate-authorize` | Separate President and CA activation authorization | Yes | **Permission assignment no; decision pair is consumed by successful execution** | Yes before consumption | DRY_RUN_ACCEPTED→ACTIVATION_AUTHORIZED | lifecycle decision resolver + execution |
| `finance.cutover.package.execute` | Technical execution | Yes | No; one-time posting enforced by success/consumption/boundary controls | Yes | ACTIVATION_AUTHORIZED→POSTED | technical appointment + resolver + activation service |
| `finance.cutover.package.rollback-authorize` | President/CA own-decision revocation and approved rollback controls | Yes | No | Yes | Current decision revocation; future rollback/reversal | lifecycle `revoke` for current decision |
| `finance.cutover.package.view-restricted-manifest` | Least-privilege restricted manifest read, never raw evidence | Yes | No | Yes | Read only | No production delivery surface currently uses it |

There is **no canonical cutover authority-administration permission**.

Required finding:

**NO CANONICAL PERMISSION CURRENTLY DEFINED** for granting/revoking cutover appointments or package-permission assignments, or for general authority/revocation-history administration reads.

## C. Authority-administration public methods

`ClubCutoverAuthorityAdministrationService` exposes four public methods:

| Method | Mutation | Grantor/actor input | Current validation | Caller authorization | Scope/result | Risk before future exposure |
|---|---|---|---|---|---|---|
| `appoint(...)` | Creates authority appointment | subject actor + `appointedBy` | authority type limited to `independent_ca`/`technical_operator` | **None for `appointedBy`** | package/club bound appointment, immediate effective time, optional expiry | client/server caller could self-appoint or appoint others if exposed without wrapper policy |
| `assignPermission(...)` | Creates package permission assignment | subject actor + `assignedBy` | permission must exist and slug start `finance.cutover.package.` | **None for `assignedBy`** | package/club/user/permission bound assignment | grantor privilege escalation/self-grant if exposed |
| `revokeAppointment(...)` | Appends `revoked` authority event | supplied actor | creates event against existing appointment | **None for revoker** | append-only revocation event | unauthorized revocation if exposed |
| `revokePermission(...)` | Appends `revoked` permission event | supplied actor | creates event against existing assignment | **None for revoker** | append-only revocation event | unauthorized revocation if exposed |

Input validation is not caller authorization.

The service does not authenticate a request, resolve the current authenticated user, prove active club membership, prove a grantor permission, prevent self-grant, or prove governance approval.

## D. Appointment lifecycle

### Creation

- exact package and club are copied from the package object;
- subject is a `User`;
- type is only `independent_ca` or `technical_operator`;
- `effective_at` is `now()`;
- `expires_at` is optional;
- appointing user is persisted as `appointed_by_user_id`.

### Currentness

`ClubCutoverAuthorityResolver::appointment` requires exactly one appointment for:

- same package club;
- same package ID;
- same actor;
- exact authority type;
- effective time reached;
- not expired;
- no `revoked`/`invalidated` event.

### Uniqueness and overlap

Database triggers allow only one current package appointment per authority type. A replacement therefore requires the old current appointment to expire or be revoked/invalidated before another current appointment of that type is created.

### Conflict detection

The resolver rejects an appointed actor who:

- also holds the other current appointment type for the package;
- is the package preparer;
- is the package internal verifier.

Later lifecycle checks enforce additional President/CA decision separation.

### Historical retention and immutability

Authority appointments and authority events are immutable at model/database level. Replacement is not mutation of the old appointment; it is a new record after currentness of the old appointment ends. Revocation is append-only.

The public administration service creates `revoked` events. The database event type also permits `invalidated`, but 3IIZQ creates no new invalidation API.

## E. Permission-assignment lifecycle

### Creation

A permission assignment binds:

- club;
- package;
- user;
- exact permission record;
- assignment reference;
- effective time;
- optional expiry;
- assigning user.

### Currentness

The resolver requires exactly one current assignment for the same club/package/user/permission and excludes expired, revoked, or invalidated assignments.

### Duplicate-current behavior

Unlike authority appointment types, the database does not provide the same one-current-per-slug guard for permission assignments. Multiple current assignments may therefore be created mechanically. The resolver then fails closed because it requires exactly one.

A future administration surface must prevent such ambiguous duplicate-current assignments rather than relying on downstream failure.

### Immutability/revocation

Assignments and assignment events are immutable. Revocation is append-only.

## F. Grantor rules

### Approved facts

- lifecycle actors and permission meanings are approved by DR-041/042/043/047/052/053 and `CUTOVER-AUTHORIZATION-CONTRACT-2026-01`;
- generic `finance.manage`, generic administrator power, repository ownership, technical access, or a role label alone is insufficient;
- no default role receives any cutover permission;
- grant/assignment must remain package scoped and must not defeat actor separation.

### Unresolved

The repository does not define who is authorized to call:

- `assignPermission`;
- `appoint(... independent_ca ...)`;
- `appoint(... technical_operator ...)`.

It also does not define whether such grants require one authorized grantor or dual approval.

Therefore:

- **CADR-001 — Package-scoped permission grantor policy: Pending**
- **CADR-002 — Independent-CA appointment grantor policy: Pending**
- **CADR-003 — Technical-operator appointment grantor policy: Pending**

No grantor permission slug is invented by 3IIZQ.

## G. Revocation rules

### Already approved for lifecycle decisions

An unconsumed President/CA dry-run or activation decision may be revoked only by the original decision actor with `finance.cutover.package.rollback-authorize`. One withdrawal breaks the joint pair and blocks execution.

### Mechanically implemented for appointments/permissions

`revokeAppointment` and `revokePermission` create append-only revocation events but do not authorize the supplied revoker.

### Unresolved for administration records

The repository does not yet define:

- who may revoke an appointment;
- who may revoke a package permission assignment;
- whether the original grantor may revoke;
- whether a separate governance authority may revoke;
- whether a subject may self-revoke;
- whether one-person or two-person approval is required for high-risk revocation;
- whether revocation of a preparatory authority should force a package state transition versus only block the next controlled action.

Therefore:

**CADR-004 — Appointment/permission revocation and invalidation authority: Pending**

Consumed activation history is never deleted or made reusable by revocation.

## H. Actor separation

### ALREADY ENFORCED

| Relationship | Current enforcement |
|---|---|
| Preparer vs internal verifier | `verify` rejects same actor as `prepared_by_user_id` |
| Preparer/internal verifier vs CA | CA appointment resolver rejects package preparer/internal verifier |
| Preparer/internal verifier vs technical operator | technical-operator appointment resolver rejects package preparer/internal verifier |
| CA vs technical operator | current appointment of the other authority type is a conflict |
| President vs preparer/internal verifier | President resolver rejects same actor as package preparer/internal verifier |
| President vs CA final approval | approval rejects President actor equal to trusted review actor |
| President vs CA dry-run/activation decision | trusted President approver and trusted CA reviewer must make their own distinct decisions |
| CA decision continuity | CA decision actor must be the trusted accounting reviewer |
| President decision continuity | President decision actor must be the trusted President approver |
| Decision revocation | only original decision actor, with `rollback-authorize`, may revoke a current decision |

### APPROVED CONTRACT REQUIREMENT — NOT FULLY ENFORCED BY CURRENT INTERNAL SERVICES

The approved contract states that the technical operator cannot business-approve, dry-run accept, or activation-authorize the same package. Current technical-operator resolution does not explicitly reject the unique active President/business approver merely because that actor is also the technical operator.

This must be closed before a production delivery surface exists.

The approved contract also requires active role/club membership checks. Current explicit membership-role resolution is implemented for the President. Package permission/appointment resolution for preparer, verifier, CA, and technical operator does not independently prove their active same-club role/membership.

### RECOMMENDED BUT NOT YET APPROVED

3IIZQ does not create additional separation rules such as:

- grantor must always be different from every grantee;
- two separate grantors for all grants;
- revoker must always be different from grantor.

Those may be recommendations in CADR decisions, not canonical policy until approved.

## I. President boundary

The President is not created by cutover authority administration.

Current President resolution requires:

- same package club;
- `ClubMembership.status = active`;
- role slug exactly `president-admin`;
- exactly one active President in the club;
- actor identity equal to that unique President;
- no preparer/internal-verifier conflict;
- exact package-scoped permission for the requested President action.

Cutover administration may only assign an approved package permission after CADR-001 defines the authorized grantor. It must not create, modify, or manufacture the underlying `president-admin` club membership.

Club-office governance and cutover package-permission administration remain separate.

## J. Independent-CA boundary

Application authority requires:

1. a current package-scoped `independent_ca` appointment;
2. the exact package-scoped permission for the action;
3. actor separation;
4. trusted review/decision continuity.

Real professional eligibility is external:

- qualified Chartered Accountant status;
- current professional standing where required;
- independence from the package/club decision conflicts;
- credential/eligibility evidence.

The application appointment does not prove those facts.

**CADR-006 — Independent-CA professional eligibility evidence and verification: Pending**

No credential-verification implementation is authorized by 3IIZQ.

## K. Technical-operator boundary

Technical execution requires:

- current package-scoped `technical_operator` appointment;
- current package-scoped `finance.cutover.package.execute`;
- exact package state `ACTIVATION_AUTHORIZED`;
- exact expected lock version;
- exact canonical checksum;
- current trusted CA review and President approval;
- current dry-run and activation decision pairs;
- no prior execution/consumption conflict;
- UUID idempotency key;
- one safe transaction.

Successful execution records the operator, appointment, permission assignment, decisions, journal, execution success, and one-time authority consumption.

Administration authority does not confer execution authority. The technical operator must not become execution-authorized merely by being an administrator.

The approved President/technical-operator separation gap identified in section H must be closed before exposure.

## L. Package-authoring boundary

Approved policy is not unresolved:

- Treasurer / appointed preparer + `finance.cutover.package.prepare`: create/edit editable DRAFT and prepare it;
- authorized preparer/internal verifier + `finance.cutover.package.validate`: non-decisional validation;
- Secretary / appointed internal verifier + `finance.cutover.package.verify`: internal verification.

Current package-service implementation gap:

- `createDraft` records an actor ID but does not resolve `prepare`;
- schedule/evidence replacement records an actor ID but does not resolve `prepare`;
- `validate()` has no actor;
- `refreshValidation()` records an actor ID but does not resolve `validate`;
- lifecycle `prepare` first enforces `prepare` only at DRAFT→PREPARED.

A future surface must enforce the approved authoring policy server-side and must not treat an actor ID argument as authorization.

## M. Read/inspection policy

### Approved evidence-manifest principles

DR-052 and `view-restricted-manifest` define least-privilege evidence-manifest access and prohibit raw evidence exposure.

### Authority administration records

The repository has no canonical permission specifically defining who may inspect:

- authority appointments;
- permission assignments;
- grantor identities;
- effective/expiry dates;
- revocation/invalidation history;
- administration blockers.

`finance.cutover.package.view-restricted-manifest` must not be silently stretched into an authority-administration permission.

Therefore:

**CADR-007 — Authority/revocation administration read and inspection policy: Pending**

Until approved, a future administrative read surface is blocked.

## N. Future authenticated surface requirements

Any future administration surface must:

1. authenticate with the existing application auth system;
2. derive the authenticated user server-side;
3. never accept client-supplied actor/grantor/revoker IDs as authorization;
4. resolve active same-club membership and approved role basis server-side;
5. enforce exact club/package/tenant scope;
6. enforce an approved grantor/revoker authorization policy;
7. enforce exact package-scoped permissions;
8. revalidate effective/expiry/current/revocation state inside the mutation transaction;
9. reject cross-club and cross-package mutations;
10. reject self-grant unless a CADR explicitly approves it;
11. reject ambiguous duplicate-current permission assignments;
12. preserve appointment exclusivity;
13. close the approved President/technical-operator separation gap;
14. use optimistic package version/checksum binding where a package-content or decision binding is affected;
15. record safe immutable audit provenance;
16. fail closed when any governance decision remains Pending.

## O. Idempotency, references, and locking

### Existing semantics

- package content mutations and lifecycle transitions use expected package `lock_version`;
- package trusted decisions bind exact package version/checksum;
- authority/permission records use immutable reference fields and append-only events;
- event references are unique;
- administration methods do not currently implement request-idempotency replay;
- activation execution requires UUID idempotency, exact checksum, expected lock, exact replay, and changed-retry conflict.

### Future administration requirement

If mutating administration endpoints are introduced, their surface contract must define exact retry semantics before exposure. At minimum:

- each mutation needs an immutable operation/reference identity;
- duplicate-current grant creation must be rejected or safely replayed;
- same reference with changed semantics must conflict;
- revocation races must be serialized/revalidated;
- package-bound grants that depend on editable package identity must reject stale package/version bindings.

This is a future delivery-surface requirement, not a claim that current administration methods are already idempotent.

## P. Audit requirements

Future administration audit/provenance must safely identify:

- club;
- package;
- package version where relevant;
- authenticated administrator;
- subject actor;
- authority type;
- exact permission slug;
- effective/expiry dates;
- action;
- immutable reference;
- timestamp;
- previous/current effective state;
- revocation/invalidation reference;
- package checksum where the action binds a trusted package version;
- outcome/blocker.

Do not log:

- passwords/tokens/keys;
- bank identifiers;
- member-level financial schedules;
- raw evidence;
- CA credential documents;
- signatures;
- unrestricted working papers.

## Q. Threat model

| Threat | Existing backend control | Future surface control required | Still unresolved? |
|---|---|---|---|
| Client-forged actor ID | No production surface; lifecycle resolvers use passed `User` objects | derive authenticated actor server-side | No policy question; required control |
| Cross-club mutation | package/club FKs, scoped resolver, package lock helpers | derive club membership and package club server-side | No |
| Self-appointment | no production surface; appointment type validation only | approved grantor policy + self-grant prohibition/decision | Yes — CADR-002/003 |
| Self-grant | no production surface; permission namespace validation only | approved permission grantor policy | Yes — CADR-001 |
| Grantor privilege escalation | no grantor auth in admin service | explicit grantor authorization, least privilege, audit | Yes — CADR-001/002/003 |
| CA/President collision | final approval/decision binding requires distinct actors | preserve at administration and request layer | No policy gap |
| Preparer/verifier collision | lifecycle rejects same actor | prevent conflicting grant/role assignment early | No policy gap |
| President/technical-operator collision | approved contract prohibits; current resolver does not fully reject | explicit conflict check before grant and execution | Implementation gap |
| Stale version mutation | package lock/version/checksum controls for content/lifecycle | require expected lock/checksum where applicable | No |
| Revoked authority reuse | resolver excludes revoked/invalidated appointments/assignments | revalidate in transaction | Revoker policy remains CADR-004 |
| Expired authority reuse | resolver checks expiry | revalidate in transaction | No |
| Evidence substitution | DRAFT-only replacement, checksum clearing/version lock | authorized submitter/verifier + controlled external storage | verifier policy CADR-005 |
| Digest mismatch | format only; application does not hash external bytes | approved external-store digest verification if later implemented | CA/evidence trust CADR-006/005 |
| Post-review evidence mutation | current package service prohibits non-DRAFT edits; approvals bind checksum | successor/new-version workflow for corrections | No current mutation bypass |
| Duplicate mutation/retry | lock version; immutable unique references; execution idempotency | endpoint-specific retry contract | No governance question |
| Revocation race | resolver/current events; some DB serialization/immutability | authorized transactional revoke + idempotent conflict handling | Yes — CADR-004 |
| Execution after authority loss | execution re-resolves appointment/permission/decisions | preserve immediate revalidation | No |
| Unauthorized authority-history read | no production surface | approved read policy | Yes — CADR-007 |
| Fake CA eligibility | application appointment does not prove professional status | external eligibility evidence/verification | Yes — CADR-006 |

## R. Database / service / future-surface / external enforcement matrix

| Property | DB-enforced | Service-enforced | Future surface required | External governance/evidence |
|---|---:|---:|---:|---:|
| package/club relational scope | Yes | Yes | Yes | No |
| authority type enum | Yes | Yes | Yes | No |
| one current appointment per package authority type | Yes, trigger | resolver exact-one | prevent conflicting request | No |
| one current permission assignment per user/slug | No equivalent active guard | resolver exact-one fails closed | prevent duplicate-current grants | No |
| effective/expiry dates | stored | resolver currentness | revalidate transactionally | No |
| authority/permission record immutability | Yes | model protection | no direct edits | No |
| append-only revocation events | immutable event tables | resolver excludes events | authorize revoker | CADR-004 |
| package authoring role/permission | No | only at prepare boundary today | enforce create/edit/validate policy | DR-041/042/053 approved |
| President identity | membership data constraints plus relations | unique active same-club President resolver | derive actor server-side | club-office governance |
| CA professional eligibility | No | No | do not overclaim appointment | CADR-006 / external verification |
| evidence verifier identity | No | No | authorized verifier binding | CADR-005 |
| activation execution idempotency | uniqueness/consumption constraints | exact replay/changed conflict | preserve request contract | No |
| grantor authorization | No | No | required before any admin write | CADR-001/002/003 |
| authority read authorization | No | No production surface | required before read surface | CADR-007 |

## S. Explicit prohibited behavior

A future surface must prohibit:

- client-supplied actor IDs being treated as authorization;
- client-supplied grantor/revoker IDs being treated as authorization;
- cross-club or cross-package authority mutation;
- self-grant/self-appointment unless explicitly approved by CADR;
- silent privilege escalation;
- generic `finance.manage` or generic administrator authority as a substitute for cutover authority;
- manufacturing President identity through cutover administration;
- treating application CA appointment as professional credential verification;
- direct mutation/deletion of immutable appointments, assignments, decisions, reviews, approvals, executions, or consumption;
- bypassing currentness, expiry, revocation, package checksum, lock, or separation checks;
- stretching `view-restricted-manifest` into a general authority-administration permission;
- storing restricted evidence in Git or audit payloads;
- executing after authority loss;
- allowing the technical operator to satisfy President/business-approval authority.

## T. Readiness

**CUTOVER AUTHORITY GRANTOR POLICY: BLOCKED — GOVERNANCE DECISION REQUIRED**

**AUTHORITY ADMINISTRATION CONTRACT READINESS: PARTIAL**

**AUTHENTICATED DELIVERY-SURFACE READINESS: BLOCKED**

**OPERATIONAL AUTHORIZATION READINESS: BLOCKED**

**OVERALL ACCOUNTING PRODUCTION READINESS: NO-GO**
