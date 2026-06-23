# Milestone 3FO - Events & Meetings Database and Read API Foundation

## 1. Milestone identity

- Base branch: milestone-3fn-events-meetings-backend-authorization-contract-approval.
- Verified base commit: 02206652d88317b94c025b29d5ed0a205f107ff8.
- Implementation branch: milestone-3fo-events-meetings-database-read-api-foundation.
- Commit message: Add events meetings read API foundation.

Milestone 3FO implements only the Events & Meetings database foundation and read API foundation approved by 3FN. It does not implement create, update, publish, cancel, complete, mutation audit writing, frontend integration, browser mutation workflows, delete, attendance, RSVP, minutes, recurrence, reminders, notifications, files, reports, public publication, or external calendar integration.

## 2. Implemented backend scope

3FO adds:

- `club_events` migration.
- `App\Models\ClubEvent`.
- controlled constants for type, status, visibility, permissions, and management roles.
- `ListClubEventsRequest` query validation.
- `ClubEventResource` explicit response shaping.
- `ClubEventReadService` for list filtering, ordering, pagination, and member-readable constraints.
- `ClubEventController` with read-only `index` and `show`.
- read-only routes:
  - `GET /api/v1/clubs/{club}/events`
  - `GET /api/v1/clubs/{club}/events/{event}`
- `events.view` and `events.manage` seeded permissions.
- synthetic feature tests for schema, model, permissions, read access, filters, ordering, pagination, response shape, ownership, and absence of mutation routes.

## 3. Database result

The `club_events` table is club-scoped and uses stable numeric backend IDs. It includes:

- `id`
- `club_id`
- `type`
- `title`
- `description`
- `is_all_day`
- `starts_at`
- `ends_at`
- `start_date`
- `end_date`
- `venue`
- `status`
- `visibility`
- `published_at`
- `cancelled_at`
- `cancellation_reason`
- `completed_at`
- `created_by_user_id`
- `updated_by_user_id`
- `lock_version`
- timestamps

Indexes are added for:

- `club_id`, `starts_at`
- `club_id`, `status`, `starts_at`
- `club_id`, `type`, `starts_at`
- `club_id`, `visibility`, `status`

The first-release controlled values are:

- type: `event`, `meeting`
- status: `draft`, `published`, `completed`, `cancelled`
- visibility: `members`, `management`

Timed records use `is_all_day=false`, `starts_at`, and optional `ends_at`. All-day records use `is_all_day=true`, local `start_date`, optional inclusive `end_date`, and canonical UTC boundary timestamps.

## 4. Authorization result

3FO seeds:

- `events.view` for President/Admin, Secretary, Treasurer, and Normal Member.
- `events.manage` for President/Admin and Secretary only.

All Events read routes are under Sanctum and `club.permission:events.view,club`. The middleware enforces active same-club membership and permission. The controller separately determines management readership through active same-club role plus `events.manage`.

Management readers can list/read all same-club statuses and visibilities. Treasurer and Normal Member can read only same-club member-readable records: `members` visibility and status `published`, `completed`, or `cancelled`. Draft and management-only records are hidden from non-management readers.

Wrong-club object IDs and undisclosed object access return 404. Known club permission failures return 403. Unauthenticated access returns 401.

## 5. Read API result

Implemented endpoints:

- `GET /api/v1/clubs/{club}/events`
- `GET /api/v1/clubs/{club}/events/{event}`

List response:

- `data` array.
- `meta.current_page`
- `meta.per_page`
- `meta.total`
- `meta.last_page`
- `meta.filters`

Detail response:

- `data` object.

Management responses include visibility, lifecycle timestamps, actor IDs, cancellation reason, `lock_version`, and timestamps. Member-facing responses omit management-only metadata and do not expose cancellation reason.

## 6. Filters and ordering

Implemented filters:

- `scope=upcoming|past|all`, default `upcoming`.
- `type=event|meeting`.
- management-only `status`.
- management-only `visibility`.
- `from`.
- `to`.
- `q`.
- `page`.
- `per_page`, maximum 50.
- `sort=starts_at`.
- `direction=asc|desc`.

Upcoming defaults to `starts_at asc, id asc`. Past defaults to `starts_at desc, id desc`. Authorization, club, status, and visibility constraints apply before user filters. Non-management attempts to use `scope=all`, `status`, or `visibility` return 403.

## 7. Explicit non-implementation

3FO does not add routes or code for:

- `POST /api/v1/clubs/{club}/events`
- `PATCH /api/v1/clubs/{club}/events/{event}`
- `POST /api/v1/clubs/{club}/events/{event}/publish`
- `POST /api/v1/clubs/{club}/events/{event}/cancel`
- `POST /api/v1/clubs/{club}/events/{event}/complete`
- DELETE
- `PATCH status`

No frontend runtime file is edited. The existing 3FL not-connected Events & Meetings frontend remains truthful until 3FQ.

## 8. Validation summary

3FO targeted tests cover:

- migration schema and columns;
- model fillable fields and relationships;
- controlled type/status/visibility constants;
- timed and all-day representation;
- permission seeding and role assignments;
- President/Admin read access;
- Secretary read access;
- Treasurer published member-visible access;
- Normal Member published member-visible access;
- Draft denial for Treasurer and Normal Member;
- management visibility denial;
- inactive membership denial;
- unauthenticated denial;
- cross-club list isolation;
- cross-club detail denial;
- nested ownership protection;
- genuine empty list;
- list filtering;
- ordering;
- pagination;
- detail not found;
- response shape;
- no mutation route exists.

3FO uses only synthetic test records and does not mutate genuine operational Events & Meetings data.

## 9. Next milestone boundary

Milestone 3FP may open only after 3FO is committed, pushed, remotely verified, clean, and tracking its remote with no ahead/behind state. 3FP may implement create Draft, update, publish, cancel, complete, mutation Form Requests, transactional service methods, `lock_version` conflicts, and audit logging. Frontend integration remains blocked until 3FQ.
