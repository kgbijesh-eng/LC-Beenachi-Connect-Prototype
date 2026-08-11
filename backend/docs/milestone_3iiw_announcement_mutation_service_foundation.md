# Milestone 3IIW — Announcement Mutation Service Foundation

## Identity and scope

- Source branch: `milestone-3iiv-announcement-mutation-service-scope-gate`
- Exact baseline: `03a6331355008f7a8e7d8a739d48312626ca0298`
- Implementation branch: `milestone-3iiw-announcement-mutation-service-foundation`
- Scope: internal mutation service, replacement-aware reads, and synthetic tests only

This milestone adds no migration, schema field, permission, request, controller, route, command, job, listener, scheduler, notification, read receipt, attachment, external link, frontend integration, accounting behavior, deletion, merge, or deployment.

## Implemented foundation

`ClubAnnouncementWriteService` implements the accepted Draft, decision, publication, withdrawal, archive, and replacement operations. Every public operation opens its transaction before authoritative authorization. Existing targets are acquired through club-scoped locked queries. Replacement graph operations lock announcement rows in ascending ID order. Actor membership is locked before role and exact-permission resolution; maker and checker memberships are locked in ascending membership-ID order before their decision-time classifications are derived.

The common lock order is announcement rows, membership rows, decision-state inspection or insertion, announcement mutation, then audit insertion. `createDraft` has no announcement row and begins with the locked actor membership. This prevents authorization from relying on mutable, unlocked membership state.

Maker role is a decision-time classification of the Draft's exact `updated_by_user_id`; it is not evidence of the role held when the material edit occurred. No historical role is inferred from current membership or AuditLog.

The service applies strict non-coercing attribute rules, Unicode title trimming, body line-ending normalization without body trimming or Unicode normalization, exact RFC 3339 whole-second timestamps normalized to UTC, optimistic locking, append-only decision finality, exact current fingerprint checks, and exactly one in-transaction success audit per logical mutation.

## Replacement-aware reads

Replacement suppression is composed into the existing SQL query before ordering and pagination. All accepted 3IIS filters remain in force.

For an authorized reader, a visible direct Published same-club, same-audience successor:

- removes its predecessor from `current`;
- places the predecessor in `history` with `effective_status` equal to `replaced`, taking precedence over `expired`;
- permits safe direct predecessor/successor linkage projection.

A concealed successor does not change ordinary effective state, scope membership, pagination, or linkage. Management responses retain all records under `all` and expose direct same-club replacement IDs in ascending numeric order. The complete chain is reconstructible by following safe direct links; no single response field is claimed to contain a transitive chain. Replacement publication does not withdraw or archive its predecessor.

## Tests and validation

All successful automated validation uses synthetic SQLite `:memory:` data with these explicit process-scoped values: `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, empty `DB_URL`, array cache/session/mail, and synchronous queue. PHP is the approved Herd PHP 8.4 executable.

The non-database preflight proved:

- Laravel environment `testing`;
- resolved default connection `sqlite`;
- resolved SQLite database `:memory:`;
- empty `DB_URL`;
- approved Herd PHP binary;
- PDO SQLite availability.

Validation completed before the candidate commit:

- focused 3IIW suite: 8 tests, 78 assertions, passed;
- focused 3IIW plus 3IIS, 3IIU, permission, middleware, authentication, and protected-route regressions: 43 tests, 685 assertions, passed;
- syntax checks for all four changed PHP files: passed;
- repository formatting for the four authorized PHP files: passed;
- pre-commit complete backend suite: 1,873 of 1,893 tests passed; the only 20 failures were historical lifecycle guards that intentionally reject a dirty runtime worktree and therefore require the established clean candidate-commit rerun.

The complete suite must be rerun after the single candidate commit under the identical explicit SQLite environment. The final commit/push and clean local/upstream result are recorded only after they succeed.

## Test-runtime recovery record

Before isolation recovery, an `artisan test --env=testing` invocation unintentionally resolved the ordinary PostgreSQL connection because `.env.testing` was absent and the repository's `phpunit.xml` environment declarations did not use `force="true"`. The attempt reached PostgreSQL migration setup and failed before tests ran. Its database-side effects were not inspected or verified, and this milestone does not claim that no changes occurred.

No PostgreSQL inspection, repair, migration, or command was performed during recovery. Any assessment or repair of that persistent database requires a separately authorized recovery task.

## Portability and concurrency boundary

SQLite `:memory:` tests verify deterministic locking code paths and invariant revalidation. They do not prove competing PostgreSQL transaction behavior. No real PostgreSQL contention-safety claim is made; such evidence requires a separately approved PostgreSQL-compatible synthetic concurrency test.

## Exact file containment

This milestone changes exactly:

1. `app/Services/Announcements/ClubAnnouncementWriteService.php`
2. `app/Services/Announcements/ClubAnnouncementReadService.php`
3. `app/Http/Resources/Announcements/ClubAnnouncementResource.php`
4. `tests/Feature/Milestone3iiwAnnouncementMutationServiceFoundationTest.php`
5. `docs/milestone_3iiw_announcement_mutation_service_foundation.md`

The announcement HTTP surface remains exactly the accepted two GET routes. No mutation capability is exposed through HTTP or the frontend.
