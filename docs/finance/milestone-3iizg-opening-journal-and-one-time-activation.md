# Milestone 3IIZG — Opening Journal and One-Time Cutover Activation

**Implementation type:** Internal backend foundation only

**Cutover contract:** `CUTOVER-AUTHORIZATION-CONTRACT-2026-01`

## Objective and boundary

3IIZG implements package-scoped authority, trusted lifecycle decisions, exact one-time opening-journal posting, and replay of an already committed execution. It exposes no API, controller, frontend, Artisan command, queue, scheduler, source hook, accounting-coverage change, or production activation entrypoint.

`club_id` is the current tenant-isolation boundary. No synthetic tenant identifier is introduced.

DR-033 follows Position A. It remains unresolved for the real cutover package until the explicitly appointed independent CA makes the one-time, package-specific selection during the qualifying accounting-review transition. The implementation accepts only `OPENING-BALANCE-EQUITY` or `ACCUMULATED-SURPLUS`; after that selection and every later required approval and authorization, internal execution may proceed. It contains no default, inference, suspense account, balancing plug, or fallback.

## Additive persistence

The package gains internal-verifier provenance and a nullable opening-journal link, posted timestamp, and posting actor. New additive tables retain:

- immutable independent-CA and technical-operator appointments;
- append-only appointment revocation/invalidation events;
- explicit package/user/permission assignments and their append-only events;
- immutable President and CA dry-run and activation decisions;
- append-only decision revocation/invalidation events;
- immutable committed execution success;
- separate immutable authorization-pair consumption;
- append-only attempt, success, failure, replay, and conflict events.

Existing review and approval tables gain nullable authority-provenance links. Legacy rows without that provenance remain historical but cannot satisfy the 3IIZG execution service.
Trusted review and President-approval rows are immutable at both model and database boundaries. Revocation or invalidation is represented only by append-only review/approval event rows, and current validity is resolved from those events.

## Permissions and authority

The ten exact `finance.cutover.package.*` permissions approved by 3IIZF are defined but granted to no default role. Generic administrator roles and `finance.manage` confer no cutover authority.

Every action requires one current package-scoped permission assignment. Independent CA and technical execution additionally require one current package-scoped appointment. Appointment alone and permission alone are insufficient. Appointments and assignments are rejected when absent, ambiguous, expired, revoked, invalidated, cross-club, cross-package, or actor-conflicting.

The President resolver requires exactly one active same-club membership whose role slug is `president-admin`, plus the exact package permission assignment. It fails closed for zero or multiple active Presidents and provides no administrator fallback.

The independent CA and technical operator are authenticated application users; their package appointments provide the club-scoped relationship and ordinary club membership is not required.

## Lifecycle and immutability

The service controls:

`DRAFT → PREPARED → INTERNALLY_VERIFIED → ACCOUNTING_REVIEWED → BUSINESS_APPROVED → DRY_RUN_ACCEPTED → ACTIVATION_AUTHORIZED → POSTED`

Accounting content is immutable after `PREPARED`. The sole exception is the atomic transition from `INTERNALLY_VERIFIED` to `ACCOUNTING_REVIEWED`, where the qualifying CA sets a previously null DR-033 classification exactly once. It becomes permanently immutable immediately afterward.

Preparation requires structural validation and the canonical checksum. Internal verification requires a different actor. Accounting review requires the appointed CA and exact permission. President approval requires the unique President and a current trusted review. Dry-run and activation each require separate immutable President and CA decisions bound to the same package version, checksum, chart, Lions Year, date, review, approval, and classification.

Revocation creates a separate event and never mutates the original decision. A revoked or invalidated decision is unusable.

## Opening execution

The internal activation service checks committed success before first-execution readiness.

### Exact replay

An exact execution-reference and boundary match returns the same success and journal only when package binding, checksum, version, idempotency key, journal linkage, consumption record, and `POSTED` state remain consistent. Replay creates no journal, line, reference, success, or consumption. Changed input conflicts.

### First execution

First execution requires no committed success, no opening journal, no consumption, `ACTIVATION_AUTHORIZED`, the expected lock version, current checksum, trusted review and President approval, two current dry-run decisions, two current activation decisions, valid technical appointment and execute assignment, an open matching accounting period, successful validation, and a balanced preview.

The existing `ClubJournalPostingService` remains authoritative for account locking, sequence/reference allocation, positive integer-paise lines, debit/credit equality, posting, immutability, and journal idempotency. The opening source is `accounting_opening_package`; zero schedule values create no lines.

## Atomicity and failure behavior

Journal creation, lines, posting, committed-success creation, authority consumption, package linkage, `POSTED` transition, and success event occur in one database transaction. Any exception rolls them all back.

Attempt events precede the posting transaction. Failure and conflict events are written only after rollback in a separate safe transaction and explicitly record `not_posted`. They do not consume authority, create journal linkage, or close the first-execution path.

## Database safeguards

Uniqueness enforces:

- one consumption per President/CA activation pair;
- one success and one consumption per package/version/checksum;
- one committed success per package, regardless of retry bindings;
- one opening success and consumption per club/Lions Year/date/opening source;
- one committed execution reference;
- one success and consumption per journal;
- one package opening-journal link;
- journal source/idempotency uniqueness through the existing journal schema.

Foreign keys reinforce club/package and club/journal isolation. SQLite check triggers are restored after table rebuilding; PostgreSQL retains native constraints.
Database triggers also restrict posting metadata to the exact `ACTIVATION_AUTHORIZED → POSTED` transition, prevent mutation or deletion of trusted decisions and authority records, and reject a second current package appointment for an exclusive authority type. The migration is verified through a complete SQLite migrate, rollback, and re-apply round trip.

## Verification coverage

Focused tests cover permission non-grants, package appointment requirements, conflicting authority, unique-President resolution, legal lifecycle progression, one-time DR-033 selection, explicit review and approval dates, model and database decision immutability, append-only invalidation, posting-metadata transition guards, revoked authorization blocking, balanced first execution, exact replay, changed retry conflict, committed-success uniqueness, rollback at every material write stage, no partial state, and delivery-surface scope.

Simultaneous two-connection locking behavior is verified by the isolated PostgreSQL regression harness. Database uniqueness remains the final duplicate-execution safeguard on every supported database.

The PostgreSQL-only regression harness requires an explicitly isolated PostgreSQL database and `RUN_3IIZG_POSTGRES_CONCURRENCY=1`; otherwise it reports a clear skip and never falls back to SQLite. It uses two independent PHP processes and connections, deterministic ready/release barriers, and an orchestrator-held package lock. Ten identical-request iterations must produce one success plus one exact replay, and ten changed-request iterations must produce one success plus one `not_posted` conflict, with one balanced journal, success, consumption, and package linkage per iteration.

## Explicit exclusions

No production activation is performed or exposed. There is no API route, controller, frontend, command, job, queue, source integration, charge hook, payment hook, receipt posting, expense posting, donation posting, fellowship posting, manual-journal surface, or authoritative accounting-coverage change.
