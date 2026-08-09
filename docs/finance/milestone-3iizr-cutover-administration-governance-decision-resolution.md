# Milestone 3IIZR — Cutover Administration Governance Decision Resolution

## Purpose

Milestone 3IIZR resolves the seven cutover-administration governance questions identified by Milestone 3IIZQ.

This milestone is governance-decision resolution only.

It does not implement cutover administration, professional evidence handling, appointments, permissions, runtime authorization, production activation, or production cutover capability.

## Authoritative Base

3IIZR was created from the completed Milestone 3IIZQ commit:

`554bb86a480b3849d877b15361c862b31dd2d0a6`

Authoritative source branch:

`milestone-3iizq-controlled-professional-evidence-intake-cutover-authority-administration-contract`

3IIZR branch:

`milestone-3iizr-cutover-administration-governance-decision-resolution`

## Authoritative Decision Register

The authoritative governance decisions are recorded in:

`docs/finance/cutover-authority-administration-decision-register.md`

All seven CADRs are now Approved.

- CADR-001 — Package permission grantor policy
- CADR-002 — Independent-CA appointment grantor
- CADR-003 — Technical-operator appointment grantor
- CADR-004 — Appointment/permission revocation authority
- CADR-005 — Evidence metadata verifier authority
- CADR-006 — Independent-CA professional eligibility evidence
- CADR-007 — Authority administration read/inspection policy

Approved CADR count: 7

Pending CADR count: 0

## Resolution Summary

### CADR-001 — Package permission grantor policy

Package-scoped cutover permission assignments require joint approval by the active same-club President and Secretary.

Neither officer acting alone may grant the permission.

Generic finance/admin authority, repository ownership, technical access, or ordinary administrator power cannot substitute for the required dual approval.

Assignments remain exact-package scoped, auditable, and subject to the approved separation-of-duties matrix.

### CADR-002 — Independent-CA appointment grantor

A package-scoped Independent CA appointment requires joint approval by the active same-club President and Secretary.

The CA cannot self-appoint and must remain separate from the President, package preparer, internal verifier, and Technical Operator for that package.

An application appointment alone does not make the CA operationally eligible.

CADR-006 eligibility and independence requirements must also be satisfied.

### CADR-003 — Technical-operator appointment grantor

A package-scoped Technical Operator appointment requires joint approval by the active same-club President and Secretary.

The Technical Operator must remain separate from the President, package preparer, Secretary/internal verifier, and Independent CA for that package.

The appointment grants execution responsibility only and does not confer preparation, verification, accounting-review, business-approval, dry-run acceptance, activation-authorization, or rollback authority.

Execution authority and current appointment must be revalidated immediately before execution.

### CADR-004 — Appointment/permission revocation authority

An authority holder may immediately self-revoke their own unconsumed appointment or package permission as a fail-safe.

Revocation or invalidation of another person's appointment or package permission requires joint approval by the active same-club President and Secretary.

Revocation/invalidation must remain append-only and auditable.

Historical authority, permissions, reviews, approvals, and decisions must not be deleted or rewritten.

Consumed execution authority cannot be made reusable by revocation or replacement.

Existing President/Independent-CA lifecycle-decision revocation rules remain separate and unchanged.

### CADR-005 — Evidence metadata verifier authority

The Treasurer/package preparer may submit or replace evidence metadata only in the unverified state.

The Secretary/internal verifier is the authenticated actor authorized to mark evidence metadata verified or rejected.

Verification/rejection must bind the actor, club, package, package version, opaque evidence reference, resulting status, timestamp, and safe reason or note.

Evidence-metadata verification is an internal control only.

It does not substitute for Independent CA professional review, document authenticity, reconciliation, or professional acceptance.

### CADR-006 — Independent-CA professional eligibility evidence

An Independent CA appointment becomes operational only after the required professional qualification, identity, current standing where applicable, engagement/appointment evidence, and independence evidence have been externally checked.

The active same-club President and Secretary jointly confirm that the required external eligibility and independence evidence has been checked before the application treats the appointment as operationally eligible.

The President and Secretary act only as governance-attesting officers.

They must not be represented as professional certifiers merely because they provide that confirmation.

CADR-006 is separate from CADR-005:

- CADR-005 governs package evidence-metadata verification.
- CADR-006 governs whether an Independent CA appointment may become operationally eligible.

Sensitive professional credentials, identity documents, signatures, declarations, and professional working papers remain outside Git and ordinary application audit content.

Only safe eligibility status, verification date, governance-attesting officer identities, opaque external references, optional digests, independence status, and safe provenance metadata may later be represented in ordinary application data.

### CADR-007 — Authority administration read/inspection policy

The President and Secretary receive full metadata-only visibility of cutover authority-administration history for their club.

The Treasurer/package preparer may see package readiness and their own relevant assignments and authority status, but not unrelated CA/operator administration history.

The Independent CA may see their own appointment, permissions, currentness/revocation/expiry state, and package metadata required for professional review.

The Technical Operator may see their own appointment, execute permission, and current execution-authority status.

Normal Members receive no authority-administration access.

Protected evidence content remains separately controlled.

`finance.cutover.package.view-restricted-manifest` must not be repurposed as a general authority-administration-history permission.

## DR-033 Boundary

DR-033 remains Pending.

Nothing in CADR-001 through CADR-007 resolves, supersedes, bypasses, weakens, or operationalizes DR-033.

3IIZR does not authorize accounting activation or production cutover.

## Implementation Boundary

Milestone 3IIZR authorizes no runtime implementation.

This milestone does not create or authorize:

- routes;
- controllers;
- runtime policies;
- commands;
- frontend or UI;
- database migrations;
- models;
- application services;
- package permission assignments;
- authority appointments;
- CA appointments;
- evidence records;
- evidence ingestion;
- credential-verification logic;
- professional review records;
- President cutover approval;
- activation authorization;
- opening-journal execution;
- production activation;
- production cutover.

Any future implementation must occur only in a separately authorized milestone and must enforce the approved CADR resolutions exactly.

## Changed Files

Expected 3IIZR documentation files:

1. `docs/finance/cutover-authority-administration-decision-register.md`
2. `docs/finance/milestone-3iizr-cutover-administration-governance-decision-resolution.md`

No runtime source file is permitted in this milestone.

## Milestone Outcome

All seven governance questions raised by 3IIZQ have been resolved.

Governance ambiguity from CADR-001 through CADR-007 no longer blocks later explicitly authorized implementation work.

This does not mean production cutover is authorized.

DR-033 remains Pending and all existing production-activation restrictions remain in force.

Milestone 3IIZR is therefore complete as a governance-decision-resolution milestone only.
