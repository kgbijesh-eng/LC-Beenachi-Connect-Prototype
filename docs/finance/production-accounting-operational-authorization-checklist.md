# Production Accounting Operational Authorization Checklist

## Control statement

This execution-facing checklist is evidence collection only. It does not grant a permission, create an appointment, approve DR-033, authorize activation, or replace application enforcement. Allowed statuses are `PASS`, `FAIL`, and `NOT VERIFIED`. Every real-world item defaults to `NOT VERIFIED`.

## Identification and deployment

| Gate | Evidence/reference | Status |
|---|---|---|
| Approved Git commit and deployed release match | `[UNSET]` | NOT VERIFIED |
| Target environment and exact database positively identified | `[UNSET]` | NOT VERIFIED |
| Target club and same-club isolation confirmed | `[UNSET]` | NOT VERIFIED |
| Lions Year and 1 July cutover boundary approved | `[UNSET]` | NOT VERIFIED |
| Package public ID, version, chart version, lock version, and checksum | `[UNSET]` | NOT VERIFIED |
| No unrelated deployment or schema drift | `[UNSET]` | NOT VERIFIED |

## Package and professional evidence

| Gate | Evidence/reference | Status |
|---|---|---|
| All canonical schedules present and mapped | `[UNSET]` | NOT VERIFIED |
| All five required evidence types verified | `[UNSET]` | NOT VERIFIED |
| Zero-paise reconciliation and per-fund balance | `[UNSET]` | NOT VERIFIED |
| DR-033 selected once from the two implemented classifications | `[UNSET]` | NOT VERIFIED |
| Current trusted independent-CA review bound to checksum | `[UNSET]` | NOT VERIFIED |
| Current trusted President approval bound to checksum | `[UNSET]` | NOT VERIFIED |
| No CA exceptions remain unresolved | `[UNSET]` | NOT VERIFIED |

## Mapping and configuration

| Gate | Evidence/reference | Status |
|---|---|---|
| Opening accounts are active, INR, canonical, same-club, and fund-attributed | `[UNSET]` | NOT VERIFIED |
| Exact production charge/payment mappings independently approved | `[UNSET]` | NOT VERIFIED |
| Integration configuration version/currentness and coverage boundary verified | `[UNSET]` | NOT VERIFIED |
| No overlap, expiry, revocation, wrong-club mapping, or fallback | `[UNSET]` | NOT VERIFIED |

## Dual control and authority

| Gate | Evidence/reference | Status |
|---|---|---|
| Unique active same-club President verified | `[UNSET]` | NOT VERIFIED |
| Current package-scoped independent-CA appointment | `[UNSET]` | NOT VERIFIED |
| Current package-scoped technical-operator appointment | `[UNSET]` | NOT VERIFIED |
| Exact package-scoped permissions current and unrevoked | `[UNSET]` | NOT VERIFIED |
| Preparer, verifier, CA, President, and operator conflicts excluded | `[UNSET]` | NOT VERIFIED |
| President and CA separately accepted dry run | `[UNSET]` | NOT VERIFIED |
| President and CA separately authorized activation | `[UNSET]` | NOT VERIFIED |
| Decisions bind the current package, checksum, chart, year, date, review, and approval | `[UNSET]` | NOT VERIFIED |
| No decision, appointment, permission, review, or approval was revoked/invalidated/expired | `[UNSET]` | NOT VERIFIED |

Required permission set, assigned only as needed for the exact package: `finance.cutover.package.prepare`, `finance.cutover.package.verify`, `finance.cutover.package.accounting-review`, `finance.cutover.package.business-approve`, `finance.cutover.package.dry-run-accept`, `finance.cutover.package.activate-authorize`, `finance.cutover.package.execute`, `finance.cutover.package.rollback-authorize`, and where necessary `finance.cutover.package.view-restricted-manifest`. The catalogue also contains `finance.cutover.package.validate`; no cutover permission is assigned to a default role.

## Execution-time revalidation

| Gate | Evidence/reference | Status |
|---|---|---|
| Package status is exactly `activation_authorized` | `[UNSET]` | NOT VERIFIED |
| Expected lock version and canonical checksum match | `[UNSET]` | NOT VERIFIED |
| Current validation succeeds and preview is `BALANCED_PREVIEW` | `[UNSET]` | NOT VERIFIED |
| Matching accounting period is open | `[UNSET]` | NOT VERIFIED |
| No previous opening journal, execution success, or authority consumption exists | `[UNSET]` | NOT VERIFIED |
| Execution reference and idempotency key are unique and approved | `[UNSET]` | NOT VERIFIED |
| Exact replay semantics understood; changed retry must conflict | `[UNSET]` | NOT VERIFIED |

## Operations and recovery

The proven disposable procedure is [PostgreSQL Accounting Operations Runbook](./postgresql-accounting-operations-runbook.md). Do not copy a disposable PASS into a production PASS.

| Control | Disposable evidence | Production status |
|---|---|---|
| Exact target verification and protected-target exclusion | PROVEN IN DISPOSABLE POSTGRESQL | NOT VERIFIED |
| Role isolation and no privilege elevation | PROVEN IN DISPOSABLE POSTGRESQL | NOT VERIFIED |
| Forward migration and schema verification | PROVEN IN DISPOSABLE POSTGRESQL | NOT VERIFIED |
| Custom backup and dump validation | PROVEN IN DISPOSABLE POSTGRESQL | NOT VERIFIED |
| Isolated restore and deterministic validation | PROVEN IN DISPOSABLE POSTGRESQL | NOT VERIFIED |
| Recovery-point proof and exact cleanup | PROVEN IN DISPOSABLE POSTGRESQL | NOT VERIFIED |
| Encryption, retention, approved RTO/RPO | NOT PROVEN IN PRODUCTION | NOT VERIFIED |
| Database-aware health/readiness | NOT PROVEN IN PRODUCTION | NOT VERIFIED |
| Monitoring, alerting, log retention, and incident ownership | NOT PROVEN IN PRODUCTION | NOT VERIFIED |
| Deployed authentication/browser acceptance | NOT PROVEN IN PRODUCTION | NOT VERIFIED |
| Traffic isolation, service restart, and rollback decision authority | NOT PROVEN IN PRODUCTION | NOT VERIFIED |

## Final authorization record

| Field | Value | Status |
|---|---|---|
| Final GO/NO-GO meeting reference | `[UNSET]` | NOT VERIFIED |
| Operations owner | `[UNSET]` | NOT VERIFIED |
| Accounting reviewer reference | `[UNSET]` | NOT VERIFIED |
| President reference | `[UNSET]` | NOT VERIFIED |
| Technical operator reference | `[UNSET]` | NOT VERIFIED |
| Approved execution window | `[UNSET]` | NOT VERIFIED |
| Recovery decision-maker | `[UNSET]` | NOT VERIFIED |
| Final result | `NO-GO` | FAIL |

Execution is prohibited unless every mandatory gate is `PASS` and the canonical application independently revalidates all bindings inside its transaction.
