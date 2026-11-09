# P0.2B6-A profile lock order

Profile submission, approval, rejection and withdrawal each use one attempt at a B1-owned PostgreSQL transaction. B1 verifies the configured organisation and locks its club row before the service locks profile resources. Decision queries require the request and its member to belong to that club. The existing routes retain their permission checks, and withdrawal still checks the stored requester.

Successful writes and their audits share the same transaction. Stale approval exits through rollback, then audits the verified request using field keys only. That audit can commit after suspension because the attempt already passed admission. An audit-write failure still takes precedence over the stale conflict response.

## Verification

Run `scripts/verify-cutover-bootstrap-postgres.ps1 -Suite B6A` for actor admission, profile lifecycle and cross-service races. The races require PostgreSQL to identify the first worker as the second worker's blocker and check both worker outcomes after release. The profile cases exercise member status, user linkage, imports, duplicate submission and competing decisions in both winner orders. The lifecycle group also exercises foreign rows held by another session, suspension, ambient rejection and audit failure ordering.

Run `-Suite B6ADiagnostic` separately. It recreates the historical request/member-first versus club-first cycle using disposable rows and expects one PostgreSQL `40P01` victim. The corrected acceptance group excludes that case and checks the PostgreSQL log for unexpected deadlocks.

## Deferred coverage

- B6-B retains membership, officer, role and permission administration outside the B6-A writer boundary. The existing membership race remains a characterization of that gap. This change adds no credential lifecycle synchronization.
- B6-C retains the other operational writers, including announcements, events, projects and reminders, outside the club-first coverage demonstrated here. This change establishes no suspension or concurrency guarantees for those writers.
