# Milestone 3IIU — Announcement Publication Decision and Permission Foundation

## Identity and scope

- Source branch: `milestone-3iit-announcements-mutation-lifecycle-scope-gate`
- Exact baseline: `394c5ab2d19eb23d8353ba03ca649740880ed309`
- Implementation branch: `milestone-3iiu-announcement-decision-permission-foundation`
- Scope: additive publication-decision, fingerprint, and permission foundation only

This milestone implements the smallest safe persistence and authorization foundation approved by 3IIT. It adds no announcement mutation request, service, controller, route, decision workflow, AuditLog insertion, lifecycle mutation, frontend integration, scheduler, notification, read receipt, attachment, external link, accounting behavior, deletion, merge, deployment, seed announcement, or operational-data access.

## Audit findings

The implementation audit covered the accepted 3IIT scope gate; approved AN-001–AN-025; the 3IIS announcement migration, model, factory, read request/resource/service/controller/routes/tests; DefaultRolesAndPermissionsSeeder; ClubPermissionService; membership-role relationships; AuditLog; profile-update fingerprints and reviewer decisions; journal-model immutability guards; migration/index/foreign-key conventions; and SQLite/PostgreSQL portability.

The existing `club_announcements` schema remains sufficient and unchanged. AuditLog remains supporting evidence rather than authoritative checker persistence. One additive publication-decision table is required. Model hooks provide safe ordinary-Eloquent append-only protection, while raw SQL/query-builder access and all same-club, maker/checker, lifecycle, version-finality, and cross-decision compatibility remain responsibilities of a later transactionally locked service.

## Implemented files

Exactly eight files form this milestone:

- new `database/migrations/2026_07_20_000002_create_club_announcement_publication_decisions_table.php`;
- new `app/Models/ClubAnnouncementPublicationDecision.php`;
- new `app/Support/Announcements/ClubAnnouncementFingerprint.php`;
- relationship-only update to `app/Models/ClubAnnouncement.php`;
- six permission records and assignments in `database/seeders/DefaultRolesAndPermissionsSeeder.php`;
- focused `tests/Feature/Milestone3iiuAnnouncementDecisionPermissionFoundationTest.php`;
- necessary count and assignment regression updates in `tests/Feature/DefaultRolesAndPermissionsSeederTest.php`;
- this milestone record.

No decision factory is required; focused tests use explicit synthetic records.

## Decision schema and portability

`club_announcement_publication_decisions` contains required club, announcement, exact announcement lock version, lowercase SHA-256 content fingerprint, maker identity and role snapshot, decision, decision actor and role snapshot, decision time, and creation time. It contains nullable reason and nullable self-referencing `supersedes_decision_id`.

All club, announcement, actor, and supersession foreign keys use restrictive deletion. Required indexes cover club/announcement/version, announcement/decision/time, and fingerprint. A unique nullable supersession constraint both indexes the reference and guarantees that one approval cannot be revoked more than once. Multiple nulls remain portable between SQLite and PostgreSQL.

There is no `updated_at` or `deleted_at`. No database-specific trigger, partial index, or CHECK expression is used. Same-club, self-supersession, cross-announcement supersession, exact-version compatibility, one-adjudication finality, and lifecycle compatibility intentionally remain future locked-service validation.

## Controlled append-only model

The controlled decisions are `approved`, `rejected`, and `revoked`. Role snapshots are `president` and `secretary`. Creation rejects unknown constants and any fingerprint not matching exactly `\A[0-9a-f]{64}\z`.

Approval may store a null or nonblank reason. Rejection and revocation require a nonblank reason. Whitespace-only reasons are invalid. Validation does not rewrite the stored reason. Revocation requires a superseded decision; approval and rejection prohibit one.

Every ordinary Eloquent update or delete throws `LogicException`. This is a model/service-boundary guard, not a claim that raw SQL cannot bypass Eloquent. A future authorized service must insert decisions only and must never update or delete decision history.

Relationships include club, announcement, maker, decision actor, superseded decision, revocation, and the announcement's publication decisions.

## Fingerprint contract

The exact version is `ANNOUNCEMENT-FINGERPRINT-2026-01`.

The utility hashes fixed-key-order UTF-8 JSON with unescaped Unicode/slashes and exception-on-error semantics. It contains contract version; integer announcement, club, and lock identities; Unicode-trimmed title; body with CRLF and CR normalized to LF; priority; audience; UTC whole-second scheduled and expiry timestamps or null; and integer replacement ID or null.

The body is not trimmed. Its leading, trailing, and internal whitespace is material. No Unicode normalization is applied. Whole-second `Y-m-d\TH:i:s\Z` matches the precision of the existing announcement timestamp schema. Actor identity, unrelated database timestamps, locale, randomness, and caller-supplied fingerprint attributes are excluded.

## Permission foundation

The six permissions are:

- `announcements.drafts.manage` — create and edit authorized same-club announcement drafts;
- `announcements.replace` — create authorized same-club correction or replacement drafts;
- `announcements.approve` — approve, reject, or revoke publication approval for authorized versions;
- `announcements.publish` — explicitly publish authorized same-club drafts;
- `announcements.withdraw` — withdraw authorized same-club Published announcements;
- `announcements.archive` — archive authorized same-club announcements.

All six are assigned to the existing `president-admin` and `secretary` roles. None is assigned to Treasurer or Member. Existing read and management-read permissions remain unchanged. Permission records alone expose no runtime mutation capability: the announcement surface remains exactly two GET routes and the controller remains `index`/`show` only.

## Validation boundary

Validation uses the explicitly approved Herd PHP 8.4 runtime and repository `phpunit.xml`, which fixes `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, and an empty `DB_URL`. No persistent migration or operational record is permitted.

The focused tests cover exact schema and relationships; restrictive foreign keys; indexes and unique supersession; absence of update/delete timestamps; controlled constants; fingerprint-format rejection; reason semantics without rewriting; model append-only guards; schema-versus-service compatibility boundary; canonical fingerprint ordering, timestamp representation, line-ending normalization, title trimming, material body whitespace, and every material field; exact permissions and assignments; unchanged read permissions; idempotent seeding; no announcement write route; and no record creation through model/relationship/fingerprint resolution.

The accepted 3IIS read suite, permission suites, protected-route suites, and complete isolated backend suite must remain green.

## Stop conditions and closure

Stop if a ninth file, persistent database, operational data, mutation request/service/controller/route/workflow, AuditLog insertion, lifecycle mutation, frontend integration, scheduler, notification, read receipt, attachment, storage, external link, accounting work, deletion, merge, or deployment is required.

Closure requires focused and full isolated tests, `git diff --check`, exact eight-file allowlist verification, no announcement write routes, one commit, push only to the implementation branch, local/upstream equality, and a clean repository.
