# Milestone 3IIS — Announcements Database and Read-Only API Foundation

## Identity and scope

- Source branch: `milestone-3iir-announcements-database-read-api-scope-gate`
- Exact baseline: `0da957f05a2ee7d669e59054dac951769ecff441`
- Implementation branch: `milestone-3iis-announcements-database-read-api-foundation`
- Scope: additive database schema and read-only backend API foundation

This milestone implements the approved Milestone 3IIR read boundary. It does not authorize or implement announcement creation, editing, publication, withdrawal, correction, replacement, archival, deletion, scheduling, notification delivery, read receipts, attachments, external-link navigation, frontend integration, or seed announcement records.

## Implemented foundation

- Added the club-scoped `club_announcements` schema, controlled lifecycle constants, relationships, and a synthetic test factory.
- Added `announcements.view` for President, Secretary, Treasurer, and Member, and `announcements.management.view` for President and Secretary only.
- Added one permission-aware list request, one read/query service, explicit ordinary and management resource projections, one read-only controller, and exactly two GET routes.
- Added a dedicated announcement read-integrity exception. Collection integrity failures return one generic response with no partial data or diagnostic disclosure; concealed or invalid details remain 404.
- Derived effective expiry at read time. No `expired` status, scheduler, or automatic lifecycle mutation was added.
- Kept officer-audience ordinary consumption deferred. Officer-audience records are available only through authorized management reads.
- Enforced literal title-only search, club-local date boundaries converted to UTC, deterministic ordering and pagination, cross-club concealment, and permission-before-validation for management-only filters.

## Integrity and privacy boundary

Selected rows must satisfy the approved lifecycle, actor, club, replacement, controlled-value, and timestamp invariants. A selected invalid collection row fails the complete response closed. Member-facing resources omit management-only actor, lifecycle, scheduling, withdrawal, archive, replacement, and lock metadata.

All automated tests use the repository's `phpunit.xml` configuration with SQLite `:memory:` and an empty `DB_URL`. No operational database or operational announcement record was accessed, created, migrated, or seeded during this milestone.

## Validation

The milestone validation covers schema, indexes, foreign keys, controlled constants, permissions, all visibility combinations, ordinary/management resource separation, authentication and concealed authorization failures, club isolation, officer-audience concealment, derived expiry boundaries, scheduled-time non-publication, safe filters, literal search, deterministic pagination, lifecycle-integrity failure, no partial responses, no write routes, no records created by reads, and continued frontend fabricated-announcement suppression.

This foundation remains read-only. Mutation workflows require a separate approved milestone.
