# Milestone 3FN - Events & Meetings Backend Authorization and API Contract Approval

## 1. Milestone identity

- Base branch: milestone-3fm-events-meetings-backend-activation-scope-gate.
- Verified base commit: d3fb29775c83f8c16b99fd53808a5d0e27278eb4.
- Contract branch: milestone-3fn-events-meetings-backend-authorization-contract-approval.
- Authorized files: backend/docs/milestone_3fn_events_meetings_backend_authorization_contract_approval.md and backend/tests/Feature/Milestone3fnEventsMeetingsBackendAuthorizationContractApprovalTest.php.

Milestone 3FN is a documentation-and-governance contract approval milestone. It finalizes implementation-critical authorization, API, validation, lifecycle, concurrency, audit, and milestone-boundary decisions for Events & Meetings. It does not implement runtime Events functionality, database migrations, models, controllers, requests, resources, services, policies, seed data, routes, frontend integration, generated files, or operational data.

Decision: GO for Milestone 3FO database and read API foundation only after this document and its deterministic governance test pass, are committed, pushed, and remotely verified. No create, update, lifecycle mutation, audit-writing workflow, or frontend activation is authorized in 3FN.

## 2. Repository evidence used

3FN inspected the closed 3FM document plus current repository conventions:

- backend/routes/api.php uses `/api/v1`, Sanctum, and `club.permission:<slug>,club` middleware.
- backend/database/seeders/DefaultRolesAndPermissionsSeeder.php uses dot-style slugs such as `club.view`, `members.manage`, `payments.record`, and `audit_logs.view`.
- backend/app/Services/Access/ClubPermissionService.php grants permissions only through active club memberships and optional club ID filtering.
- backend/app/Http/Middleware/EnsureClubPermission.php returns 401 for unauthenticated users and 403 for authenticated users missing the route permission.
- Existing nested object controllers explicitly verify route-club ownership and fail closed for wrong-club records.
- backend/app/Models/AuditLog.php and the audit log migration provide a polymorphic audit foundation.
- No Events, Meetings, Calendar, `ClubEvent`, `club_events`, or `/events` runtime implementation exists at the start of 3FN.

This evidence supports a narrow Events permission pair, club-scoped route family, explicit object ownership checks, Laravel Form Requests, Resource shaping, service-layer transition guards, synthetic feature tests, and later transactional audit writes.

## 3. Approved permissions and role assignments

Approve exactly these new permission slugs:

- `events.view`
- `events.manage`

These match the repository's dot-style permission convention and the module/action pattern already used for `club.view`, `members.manage`, and `payments.record`.

Approved role matrix:

| Role | events.view | events.manage |
|---|---:|---:|
| President/Admin (`president-admin`) | Yes | Yes |
| Secretary (`secretary`) | Yes | Yes |
| Treasurer (`treasurer`) | Yes | No |
| Normal Member (`member`) | Yes | No |

Permissions never override active membership, club scope, record status, record visibility, stable object ownership, or explicit lifecycle rules. `audit_logs.view` does not grant Events management. Event audit metadata is management-only and requires both `events.manage` and `audit_logs.view` if exposed by a later authorized API.

3FO may add the two permissions and role assignments to `DefaultRolesAndPermissionsSeeder` and related tests. 3FN does not change seed data.

## 4. Approved visibility values

Approve exactly these visibility values:

- `members`
- `management`

Meaning:

- `members`: authorized active same-club members with `events.view` may read the record when the status is member-readable.
- `management`: only authorized active same-club management users with `events.manage` may read the record.

No `public`, `board`, `pst`, `officers`, `committee`, or guest visibility is approved in the first release. Committee-specific and public publication rules are deferred.

## 5. Approved status values and read rules

Approve exactly these status values:

- `draft`
- `published`
- `completed`
- `cancelled`

Read contract:

- Draft: management only.
- Published + `members`: all authorized active same-club users with `events.view`.
- Published + `management`: management only.
- Completed: preserve original visibility. A completed `members` record remains readable to authorized active same-club users with `events.view`; a completed `management` record remains management only.
- Cancelled: preserve prior publication visibility with one privacy carve-out. A cancelled Draft remains management-only. A cancelled previously Published `members` record remains visible to authorized active same-club users so members can see that the event or meeting was cancelled. Cancellation reason is management-only in member-facing responses. A cancelled `management` record remains management only.

Ordinary Treasurer and Normal Member access is always filtered to same-club, `events.view`, `published`/member-readable completed/cancelled records, and `members` visibility. They cannot read Draft, management-only, actor/audit metadata, or management-only cancellation reasons.

## 6. Approved lifecycle authorization

Permitted transitions:

- Draft -> Published.
- Draft -> Cancelled.
- Published -> Completed.
- Published -> Cancelled.

Forbidden transitions:

- Published -> Draft.
- Completed -> any other status.
- Cancelled -> any other status.
- Any generic `PATCH status` workflow.
- Any DELETE or soft-delete workflow.

Create creates Draft only. Update cannot mutate status or lifecycle timestamps. Lifecycle actions use explicit routes and require `events.manage`, active same-club membership, stable object ownership, and current-state eligibility.

President/Admin and Secretary may create, update allowed fields, publish, cancel, and complete. Treasurer and Normal Member are read-only and receive no management controls or mutation access.

## 7. Approved early-completion rule

Early completion is not allowed in the first release.

- A Published timed record cannot be completed before `end_at`.
- If `end_at` is null, a Published timed record cannot be completed before `start_at`.
- A Published all-day record cannot be completed before the event date range ends in the owning club's `clubs.timezone`.
- `end_date` is inclusive for all-day records, so completion is allowed only after the end of that inclusive local club date.
- No management bypass, override flag, or emergency completion exception is approved.

Invalid early completion returns 409 conflict or 422 validation according to whether the request shape is valid but the lifecycle state is not yet eligible. No state change and no audit entry may be created for denied early completion.

## 8. Approved all-day API representation

Timed request contract:

- `is_all_day: false`
- required `start_at`
- optional `end_at`
- `start_at` and `end_at` are ISO-8601/RFC3339 timestamps with explicit offset.
- Reject `start_date` and `end_date` on timed records.

All-day request contract:

- `is_all_day: true`
- required `start_date`
- optional `end_date`
- `start_date` and `end_date` are ISO date values in the owning club timezone.
- `end_date` is inclusive.
- Reject `start_at` and `end_at` on all-day records.

Storage contract:

- Timed records store canonical UTC `starts_at` and nullable UTC `ends_at`.
- All-day records store canonical UTC boundary instants derived from the club timezone and retain date intent through `is_all_day` plus local `start_date`/`end_date` response fields.
- API output includes ISO-8601 UTC timestamps for canonical boundaries and local date fields for all-day display.
- The frontend must display in `clubs.timezone`, not browser locale.

Mixed date and timestamp representations are rejected with 422.

## 9. Approved idempotency and concurrency behaviour

Repository inspection found no generic idempotency-key infrastructure. Therefore:

- 3FO must not introduce idempotency infrastructure.
- 3FP must use `lock_version` plus transactional lifecycle guards.
- Create requests do not support idempotency keys in the first release.
- Update and lifecycle requests require `lock_version`.
- A stale `lock_version` returns 409 and creates no audit record.
- Repeated lifecycle calls after the first success are deterministic conflicts: repeat publish/cancel/complete on an already transitioned record returns 409 and creates no duplicate audit record.
- A request that already observes the target terminal or already-published state may return the current resource only if a later separately approved idempotency mechanism exists. It is not approved for 3FO or 3FP.

All successful mutations increment `lock_version` exactly once. Failed validation, authorization, ownership, stale-version, repeated-transition, or lifecycle requests create no audit entries.

## 10. Approved route family

All Events & Meetings routes are JSON routes under `/api/v1`, use Sanctum authentication, and remain club-scoped.

Approved route segment is `events` for both `event` and `meeting` record types.

Approved endpoints:

- `GET /api/v1/clubs/{club}/events`
- `GET /api/v1/clubs/{club}/events/{event}`
- `POST /api/v1/clubs/{club}/events`
- `PATCH /api/v1/clubs/{club}/events/{event}`
- `POST /api/v1/clubs/{club}/events/{event}/publish`
- `POST /api/v1/clubs/{club}/events/{event}/cancel`
- `POST /api/v1/clubs/{club}/events/{event}/complete`

No DELETE route is approved. No `PATCH status` contract is approved. No attendance, RSVP, minutes, reminder, notification, file, calendar sync, report, public publication, recurrence, committee visibility, or external integration routes are approved.

3FO may implement only the two read routes. 3FP may implement create, update, publish, cancel, and complete. 3FN implements none.

## 11. Approved club-scoping and stable identity rule

Every Events & Meetings record belongs to exactly one club.

Rules:

- `{event}` must belong to `{club}`.
- Active same-club membership is mandatory before any permission has effect.
- Route `{club}` supplies the club context; payload `club_id` is rejected or ignored and never trusted.
- Client payload cannot set actor IDs, lifecycle timestamps, audit metadata, status, or club ownership.
- Stable backend numeric IDs identify records.
- Title, date, venue, type, array order, list position, or frontend generated keys must never identify a record.
- Lists apply club scope before filters.
- Detail, update, and lifecycle actions must explicitly verify `event.club_id === club.id`.
- Cross-club object access fails closed with 404 to prevent enumeration, following existing nested ownership protection.
- Known-club permission failure returns 403 through the permission middleware.
- Unauthenticated access returns 401.

Malformed or nonexistent object IDs return 404 without leaking whether a cross-club record exists.

## 12. Approved request contract

Create request fields:

- `type`: required, `event` or `meeting`.
- `title`: required, trimmed string, max 200.
- `description`: nullable string, max 5000.
- `venue`: nullable string, max 255.
- `visibility`: required, `members` or `management`.
- Timed fields: `is_all_day: false`, `start_at`, optional `end_at`.
- All-day fields: `is_all_day: true`, `start_date`, optional inclusive `end_date`.

Update request fields:

- Requires `lock_version`.
- Allows title, description, venue, visibility, and schedule fields only while record state is editable.
- Does not allow type changes after creation.
- Does not allow status, club, actor, lifecycle timestamp, audit, or lock-version override fields.
- Completed and Cancelled records are immutable.

Publish request:

- Requires `lock_version`.
- Accepts no status field.
- Requires the record already has valid publishable title, type, schedule, and visibility.

Cancel request:

- Requires `lock_version`.
- Requires `cancellation_reason`, trimmed string, 1-1000.

Complete request:

- Requires `lock_version`.
- Accepts no body fields other than the approved concurrency field.

Unknown/client-owned fields are rejected with 422 rather than silently becoming hidden behaviour.

## 13. Approved response contract

Use explicit API resources; never blind serialize the model.

List response:

- Top-level `data` array.
- `meta` with current page, per page, total, last page, and applied filters.
- Each item includes stable `id`, `club_id`, `type`, `title`, schedule summary, venue, status if authorized, visibility if authorized, and `lock_version` only for management users.

Detail response:

- Top-level `data` object.
- Includes authorized fields only.
- Management responses may include lifecycle timestamps, actor IDs, cancellation reason, and `lock_version`.
- Member-facing responses omit actor IDs, audit metadata, management-only cancellation reason, and management-only lifecycle details not needed to understand the record.

Timestamp output:

- Canonical UTC timestamps use ISO-8601/RFC3339 with `Z`.
- All-day responses include local `start_date` and inclusive `end_date`.
- Display timezone is derived from `clubs.timezone`.

Error contract:

- 401 unauthenticated.
- 403 authenticated but missing route/action permission for the route club.
- 404 nonexistent, wrong-club, undisclosed, or unauthorized object identity.
- 409 stale lock version, repeated transition, terminal-state conflict, or lifecycle conflict.
- 422 validation errors.
- 500 unexpected server error without record leakage or simulated success.

## 14. Approved filtering and ordering

First release list filters:

- `scope=upcoming|past|all`, default `upcoming`. `all` is management-only.
- `type=event|meeting`.
- `status=draft|published|completed|cancelled` for management only; non-management is forced to member-readable records.
- `visibility=members|management` for management only; non-management is forced to `members`.
- `from` and `to` ISO-8601/RFC3339 boundaries with a safe range limit.
- `q` trimmed title search, max 100 characters.
- `page`.
- `per_page`, default 20, max 50.
- `sort=starts_at`.
- `direction=asc|desc`.

Ordering:

- Upcoming default: `starts_at asc, id asc`.
- Past default: `starts_at desc, id desc`.
- Detail always uses stable backend ID.

Authorization, club scope, status, and visibility filters apply before user-supplied filters. Unauthorized management-only filters from non-management users return 403 or are ignored only if documented by the 3FO implementation tests; preferred contract is 403 for management-only filter attempts.

## 15. Approved validation ownership

Laravel Form Requests own request shape, field validation, basic authorization, and rejected client-owned fields.

Service/domain code owns:

- club ownership confirmation;
- active membership and management role rechecks where needed;
- lifecycle transition eligibility;
- early-completion evaluation;
- stale `lock_version` detection;
- row locking or optimistic locking;
- audit atomicity;
- final response state.

Validation must not infer duplicates by title/date, because two legitimate meetings can share similar titles or dates.

## 16. Approved audit contract for 3FP

3FP must write exactly one `AuditLog` in the same database transaction for each successful mutation:

- `events.created`
- `events.updated`
- `events.published`
- `events.cancelled`
- `events.completed`

Audit fields:

- `club_id`
- actor `user_id`
- polymorphic target type `App\Models\ClubEvent`
- target ID
- action
- metadata containing relevant before/after state, status, visibility, schedule, and lock version

Rejected requests create no success audit. Repeated lifecycle conflicts create no duplicate audit. Audit metadata must avoid unnecessary private description text and must not expose member-only hidden cancellation details to unauthorized readers.

3FO does not implement mutation audit writing.

## 17. Approved 3FO implementation scope

3FO may implement only:

- `club_events` migration.
- `App\Models\ClubEvent`.
- controlled constants or enums for type/status/visibility.
- model relationships.
- permission seeding and related permission tests for `events.view` and `events.manage`.
- read authorization foundation.
- list query validation.
- API resource.
- list controller endpoint.
- detail controller endpoint.
- optional read service/query object if consistent with repository convention.
- factory or test helpers needed for synthetic tests.
- 3FO documentation and tests.

3FO must not implement create, update, publish, cancel, complete, mutation audit writing, frontend integration, browser mutation tests, attendance, RSVP, minutes, recurrence, reminders, notifications, files, reports, public publication, or calendar integrations.

## 18. Approved 3FP implementation boundary

3FP may implement only after 3FO is committed, pushed, remotely verified, clean, and used as the actual base:

- create Draft;
- update editable fields;
- publish;
- cancel;
- complete;
- Form Requests for mutation/lifecycle;
- transactional service methods;
- `lock_version` conflict handling;
- lifecycle validation;
- management authorization;
- audit logging;
- mutation and lifecycle tests.

3FP must not implement frontend integration, browser role verification, attendance, RSVP, minutes, recurrence, reminders, notifications, files, reports, public publication, calendar integrations, delete/soft-delete, or unrelated module changes.

## 19. Data-truth and frontend activation rule

The existing 3FL Events & Meetings not-connected state remains correct through 3FN, 3FO, and 3FP. Frontend activation is not authorized until 3FQ.

No milestone may restore hardcoded Events data, invented dates, fake statuses, simulated persistence, false success, dummy fallback, or failure-to-empty conversion. A failed API call is failure. An empty successful API response is a genuine empty state. A forbidden or unauthorized response must stay truthful and fail closed.

Stable backend IDs are mandatory for any later frontend list/detail/edit/lifecycle workflow.

## 20. Deferred features and exclusions

The following remain explicitly deferred:

- attendance;
- RSVP;
- guests;
- meeting minutes;
- recurrence;
- reminders;
- email, WhatsApp, SMS, push, or other notifications;
- files and attachments;
- OCR and PDF generation;
- reports and aggregates;
- external calendar integration;
- public website publication;
- service project linkage;
- finance or payment linkage;
- committee-specific visibility;
- online meeting link/contact/internal notes;
- delete or soft delete.

These fields, routes, controls, migrations, services, and tests cannot silently enter 3FO or 3FP.

## 21. Required 3FO tests

3FO must use synthetic test data only and cover at minimum:

- migration schema;
- required constraints;
- model relationships;
- controlled type values;
- controlled status values;
- controlled visibility values;
- timed representation;
- all-day representation;
- President/Admin read access;
- Secretary read access;
- Treasurer published-member-visible access;
- Normal Member published-member-visible access;
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

## 22. Required 3FP tests

3FP must use synthetic test data only and cover at minimum:

- President/Admin create;
- Secretary create;
- Treasurer and Normal Member forbidden from create;
- unauthenticated and inactive membership denial;
- cross-club create/update/lifecycle protection;
- invalid type;
- invalid visibility;
- invalid all-day payload;
- invalid timed payload;
- club override attempt;
- audit created;
- authorized update;
- stale `lock_version`;
- terminal-state update denial;
- arbitrary status mutation denial;
- update audit before/after state;
- valid Draft -> Published;
- repeat publish conflict;
- invalid publish state;
- missing required publish fields;
- unauthorized publish;
- Draft -> Cancelled;
- Published -> Cancelled;
- required cancellation reason;
- repeat cancel conflict;
- terminal-state cancel denial;
- approved cancellation visibility behaviour;
- Published -> Completed;
- early timed completion denied;
- early all-day completion denied;
- repeat complete conflict;
- audit atomicity;
- no duplicate audit records;
- deterministic 409 response;
- cross-club object protection.

## 23. Acceptance criteria

3FN passes only when:

- this document and its governance test are the only changed files;
- all mandatory decisions are explicit;
- no runtime Events implementation is added;
- no source, migration, route, seeder, model, frontend, generated, or private-data file changes in 3FN;
- the targeted governance test passes;
- `git diff --check` passes;
- changed-file audit shows only the two authorized 3FN files;
- the branch is committed with `Approve events meetings authorization contract`;
- the commit is pushed normally;
- local and remote hashes match;
- final working tree is clean and neither ahead nor behind its remote branch.

## 24. Final 3FN decision

GO - Milestone 3FO may open for Events & Meetings Database and Read API Foundation after 3FN is committed, pushed, remotely verified, clean, and tracking its remote with no ahead/behind state.

This verdict authorizes 3FO only. It does not authorize 3FP mutation workflows, 3FQ frontend integration, 3FR browser role verification, or 3FS closure review until their prerequisite milestone commits are actually produced and remotely verified.
