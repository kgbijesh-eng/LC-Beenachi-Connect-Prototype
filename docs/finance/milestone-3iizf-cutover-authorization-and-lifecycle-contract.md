# Milestone 3IIZF — Cutover Authorization and Lifecycle Contract

**Milestone type:** Contract and authorization only

**Approved contract:** `CUTOVER-AUTHORIZATION-CONTRACT-2026-01`

## Outcome

Milestone 3IIZF approves the exact cutover permission vocabulary, role responsibilities, package-specific separation of duties, lifecycle transition authority, dual-control rules, and required audit events in the Club Accounting Cutover Package and Activation Authorization Contract. DR-053 records the decision.

The Treasurer or appointed preparer owns creation and editable-package preparation. The Secretary or appointed internal verifier reproduces schedules, validates evidence references, and records internal verification. An independent Chartered Accountant records the written accounting review and selects exactly one DR-033 classification from supported choices only when the evidence substantiates it. The President or authorised club body records approval of the same immutable package binding. Normal Members are explicitly denied all cutover authority.

One authenticated person cannot prepare and verify or review the same package, cannot supply both President and independent-CA decisions, and cannot use a second displayed role to bypass actor-identity separation. A technical operator executes only a future, exact, jointly authorized binding and holds no decision authority by virtue of technical access.

## Permission prerequisite

The contract approves these future permission names without seeding them:

- `finance.cutover.package.prepare`
- `finance.cutover.package.validate`
- `finance.cutover.package.verify`
- `finance.cutover.package.accounting-review`
- `finance.cutover.package.business-approve`
- `finance.cutover.package.dry-run-accept`
- `finance.cutover.package.activate-authorize`
- `finance.cutover.package.execute`
- `finance.cutover.package.rollback-authorize`
- `finance.cutover.package.view-restricted-manifest`

No route, command, queued action, or executable lifecycle transition may be introduced until a later milestone seeds the required permissions and implements active same-club membership, tenant isolation, package-version binding, optimistic concurrency, separation-of-duty conflict checks, and append-only audit records.

## Lifecycle

The authorized design remains:

`DRAFT → PREPARED → INTERNALLY_VERIFIED → ACCOUNTING_REVIEWED → BUSINESS_APPROVED → DRY_RUN_ACCEPTED → ACTIVATION_AUTHORIZED → POSTED`

This milestone implements none of those transitions. `POSTED`, revocation, rollback, reversal, replacement, and technical execution remain future gates. DR-033 remains Pending and no classification is defaulted, inferred, or selected here.

## Scope exclusions

Milestone 3IIZF adds no activation, opening-journal posting, API route, command, frontend screen, accounting-coverage change, charge/payment integration, permission seed, role grant, or operational data access. It changes no application service, model, migration, seeder, policy, controller, route, command, queue, or frontend file.
