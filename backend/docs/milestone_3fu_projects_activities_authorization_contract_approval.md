# Milestone 3FU - Projects & Activities Authorization and API Contract Approval

## Milestone identity

Milestone 3FU is a documentation-and-governance contract approval milestone for Projects & Activities. It starts from `milestone-3ft-projects-activities-backend-activation-scope-gate` at `a18c77616ff4ac806920e75474c4f1765e970e53` and opens branch `milestone-3fu-projects-activities-authorization-contract-approval`.

3FU creates only:

- `backend/docs/milestone_3fu_projects_activities_authorization_contract_approval.md`
- `backend/tests/Feature/Milestone3fuProjectsActivitiesAuthorizationContractApprovalTest.php`

No runtime Projects code may be added in 3FU. 3FU does not implement migrations, models, seeders, factories, routes, controllers, requests, resources, services, policies, frontend integration, database mutation, or browser workflows.

## Repository convention evidence

3FU follows repository conventions already proven by club, member, finance, documents, and Events & Meetings work:

- `backend/routes/api.php` uses `/api/v1/clubs/{club}/...` nested club routes.
- `club.permission:<slug>,club` middleware protects club-scoped route families.
- `ClubPermissionService.php` requires active same-club memberships before a permission counts.
- `EnsureClubPermission.php` returns 401 unauthenticated and 403 when the authenticated user lacks a route permission.
- `DefaultRolesAndPermissionsSeeder.php` is the correct future location for seeded `projects.view` and `projects.manage` permissions.
- `AuditLog.php` is the audit destination for Projects lifecycle success events.
- Events & Meetings uses explicit GET routes for read, management routes for mutations, Form Requests, API Resources, services, transactions, `lock_version`, and concealed 404 for wrong-club object identity.

3FU preserves the 3FT aggregate decision: unified `ClubProject` with `record_type = project|activity` served by `/api/v1/clubs/{club}/projects`.

## Final permission decision

Approved permission slugs:

- `projects.view`
- `projects.manage`

Do not introduce separate permissions for plan, start, complete, cancel, reports, committee chair, uploads, or deletion in this release.

Permission never overrides authentication, active membership, club scope, status, visibility, stable object ownership, or lifecycle state.

Final role assignments:

| Role | projects.view | projects.manage |
| --- | ---: | ---: |
| President/Admin (`president-admin`) | Yes | Yes |
| Secretary (`secretary`) | Yes | Yes |
| Treasurer (`treasurer`) | Yes | No |
| Normal Member (`member`) | Yes | No |
| Unauthenticated | No | No |
| Inactive member | No active membership | No active membership |
| Another-club user | No target-club permission | No target-club permission |

Committee-chair management remains deferred and must not be inferred from member names, display labels, leadership text, committee-card text, frontend role labels, or roster matching.

## Final role-action matrix

| Action | President/Admin | Secretary | Treasurer | Normal Member | Unauthenticated | Inactive member | Another-club user |
| --- | --- | --- | --- | --- | --- | --- | --- |
| List | Yes | Yes | Yes, member-visible only | Yes, member-visible only | 401 | 403 | 403 for target-club list |
| Detail | Yes | Yes | Yes if readable | Yes if readable | 401 | 403/404 | 404 for undisclosed object |
| Read Draft | Yes | Yes | No | No | No | No | No |
| Read Planned members | Yes | Yes | Yes | Yes | No | No | No target club |
| Read Planned management | Yes | Yes | No | No | No | No | No |
| Read Active members | Yes | Yes | Yes | Yes | No | No | No target club |
| Read Active management | Yes | Yes | No | No | No | No | No |
| Read Completed members | Yes | Yes | Yes | Yes | No | No | No target club |
| Read Completed management | Yes | Yes | No | No | No | No | No |
| Read Cancelled Draft | Yes | Yes | No | No | No | No | No |
| Read Cancelled previously visible members | Yes | Yes | Yes without private reason | Yes without private reason | No | No | No target club |
| Create Draft | Yes | Yes | 403 | 403 | 401 | 403 | 403 |
| Edit Draft | Yes | Yes | 403 | 403 | 401 | 403 | 404/403 |
| Edit Planned | Yes, limited corrections | Yes, limited corrections | 403 | 403 | 401 | 403 | 404/403 |
| Edit Active | Yes, operational/impact corrections | Yes, operational/impact corrections | 403 | 403 | 401 | 403 | 404/403 |
| Plan | Yes | Yes | 403 | 403 | 401 | 403 | 404/403 |
| Start | Yes | Yes | 403 | 403 | 401 | 403 | 404/403 |
| Complete | Yes | Yes | 403 | 403 | 401 | 403 | 404/403 |
| Cancel | Yes | Yes | 403 | 403 | 401 | 403 | 404/403 |
| Delete | No route | No route | No route | No route | No route | No route | No route |
| View private audit metadata | Yes when authorized by management response | Yes when authorized by management response | No | No | No | No | No |

## Final API routes

Route family: `/api/v1/clubs/{club}/projects`.

Approved endpoints:

- `GET /api/v1/clubs/{club}/projects`
- `GET /api/v1/clubs/{club}/projects/{project}`
- `POST /api/v1/clubs/{club}/projects`
- `PATCH /api/v1/clubs/{club}/projects/{project}`
- `POST /api/v1/clubs/{club}/projects/{project}/plan`
- `POST /api/v1/clubs/{club}/projects/{project}/start`
- `POST /api/v1/clubs/{club}/projects/{project}/complete`
- `POST /api/v1/clubs/{club}/projects/{project}/cancel`

No DELETE route is approved. No generic `PATCH status` contract is approved. The `projects` route family serves both `project` and `activity` record types.

## Create contract

Create requires `projects.manage` and active same-club membership. Create always produces Draft.

Approved create request fields:

- `record_type`: required, `project` or `activity`.
- `title`: required, trimmed string, max 200.
- `description`: nullable string, max 5000.
- `objective`: nullable string, max 2000.
- `service_category`: nullable string, max 100.
- `visibility`: required, `members` or `management`.
- `planned_start_date`: nullable ISO `YYYY-MM-DD`.
- `planned_end_date`: nullable ISO `YYYY-MM-DD`, not before planned start.
- `location`: nullable string, max 255.
- `lead_member_id`: nullable stable same-club member ID.
- `expected_beneficiary_count`: nullable integer, min 0.

Payload must not control `club_id`, `status`, actor IDs, lifecycle timestamps, audit fields, `lock_version`, `planned_at`, `started_at`, `completed_at`, `cancelled_at`, `actual_start_date`, `actual_end_date`, or `actual_beneficiary_count` on create.

## Update contract

Generic update requires `projects.manage`, same-club ownership, active membership, exact stable ID, and `lock_version`.

Generic update must not accept arbitrary status.

Editable fields by state:

- Draft: all approved descriptive and planning fields.
- Planned: limited corrections to title, description, objective, service category, visibility, planned dates, location, lead member, and expected beneficiary count.
- Active: limited operational corrections to description, location, actual start date, actual end date where valid, actual beneficiary count, and impact summary.
- Completed and Cancelled: terminal and non-editable in the first release.

Unknown/client-owned fields are rejected with 422. Stale `lock_version` returns 409.

## Lifecycle contracts

### Plan

`POST /api/v1/clubs/{club}/projects/{project}/plan` transitions Draft -> Planned.

Requires `lock_version`, planned start date, valid planned date ordering, and all required planning fields. It sets `planned_at`, `planned_by_user_id`, increments lock version, and writes `projects.planned` audit.

### Start

`POST /api/v1/clubs/{club}/projects/{project}/start` transitions Planned -> Active.

Requires `lock_version`. It sets `started_at`, `started_by_user_id`, actual start date if provided or appropriate, increments lock version, and writes `projects.started` audit.

### Complete

`POST /api/v1/clubs/{club}/projects/{project}/complete` transitions Planned -> Completed or Active -> Completed.

Requires `lock_version`, valid actual date ordering, nonnegative beneficiary counts, approved completion fields, and impact summary where required by validation. It sets `completed_at`, `completed_by_user_id`, increments lock version, and writes `projects.completed` audit. Early completion is denied when planned or actual date rules prove the work cannot yet be complete.

### Cancel

`POST /api/v1/clubs/{club}/projects/{project}/cancel` transitions Draft -> Cancelled, Planned -> Cancelled, or Active -> Cancelled.

Requires `lock_version` and nonblank `cancellation_reason`, max 1000. It sets `cancelled_at`, `cancelled_by_user_id`, preserves `planned_at` history, increments lock version, and writes `projects.cancelled` audit.

Repeat lifecycle requests and invalid state transitions return deterministic 409 conflicts and create no duplicate success audit entries.

## Read contract and filters

List and detail require `projects.view`, active same-club membership, and nested club ownership.

Read-only users see only:

- Planned `members` records;
- Active `members` records;
- Completed `members` records;
- Cancelled `members` records only when `planned_at` is not null.

Management users see same-club Draft, Planned, Active, Completed, and Cancelled records for both `members` and `management` visibility.

Approved filters:

- `scope=planned|active|completed|cancelled|all`, default `all` for management and member-visible active/planned/completed/cancelled for read-only users.
- `record_type=project|activity`.
- `status=draft|planned|active|completed|cancelled` for management only.
- `visibility=members|management` for management only.
- `service_category` exact filter.
- `from` and `to` date filters using ISO `YYYY-MM-DD` against planned/actual dates as defined by service.
- `q` title/objective/location search.
- `per_page`, default 20, max 50.
- `page`.

Ordering: planned/active upcoming-style records sort by `planned_start_date asc, id asc`; completed/cancelled historical records sort by lifecycle date desc then `id desc`; `all` uses status priority plus relevant date and stable id.

## Response contract

List returns top-level `data` array plus pagination metadata. Detail returns top-level `data` object.

Approved response fields:

- stable `id`;
- `club_id` only where appropriate;
- `record_type`;
- title;
- description;
- objective;
- service category;
- visibility when authorized;
- status;
- planned dates;
- actual dates;
- location;
- lead member summary where authorized and safe;
- expected beneficiary count;
- actual beneficiary count;
- impact summary;
- cancellation information where authorized;
- lifecycle timestamps;
- actor summaries where authorized;
- `lock_version`;
- created and updated timestamps.

Do not expose private member information unnecessarily. Member-facing responses do not expose private cancellation reasons or private audit metadata.

## Club scoping and error contract

Require nested ownership verification, active same-club membership, and route-derived club. Payload cannot override club. Cross-club IDs fail closed. Route binding must not disclose another club's record. Stable backend IDs are the only object identity.

Error contract:

- 401 unauthenticated.
- 403 authenticated but missing route/action permission or inactive target-club membership.
- 404 nonexistent, wrong-club, undisclosed, or unauthorized object identity where existence must not leak.
- 405 for DELETE/no route.
- 422 validation errors and unknown/client-owned fields.
- 409 stale lock version, repeated lifecycle action, invalid transition, terminal-state mutation, or deterministic conflict.

## Validation ownership

Laravel Form Requests own request shape, allowed fields, primitive validation, date format validation, and unknown/client-owned field rejection. Services own lifecycle state, same-club lead member validation, visibility rules, date-order rules that depend on state, row locking, concurrency, audit, and conflict decisions.

## Audit and concurrency contract

Success audit actions:

- `projects.created`
- `projects.updated`
- `projects.planned`
- `projects.started`
- `projects.completed`
- `projects.cancelled`

Require atomic mutation and audit persistence in one database transaction. Use `lock_version`, row locking for transitions, 409 for stale version or invalid conflict, deterministic repeated lifecycle behaviour, rollback on failure, and no duplicate audit entries.

Rejected requests create no success audit.

## 3FV boundary

3FV may implement only:

- `club_projects` migration;
- `App\Models\ClubProject`;
- controlled constants;
- `projects.view` and `projects.manage` seeded permissions and role assignments;
- read authorization;
- list endpoint;
- detail endpoint;
- list query request;
- API resource;
- read service;
- GET routes;
- factory if needed for tests;
- 3FV feature tests;
- 3FV documentation and lifecycle-safe governance test.

3FV must not implement create, update, plan, start, complete, cancel, write service, mutation Form Requests, frontend integration, browser workflows, uploads, reports, or deletion.

## 3FW boundary

3FW may implement only after 3FV is committed, pushed, remotely verified, and clean. 3FW may implement create Draft, update, plan, start, complete, cancel, write Form Requests, transactional write services, audit, concurrency, role authorization, lifecycle tests, and 3FW documentation/governance.

3FW must not implement frontend integration, browser milestone evidence, unrelated modules, committee-chair management, attachments, reports, or deletion.

## Frontend data-truth guard

The Projects & Activities frontend remains truthful not-connected until 3FX. No milestone may restore hardcoded Projects or Activities as operational truth. No dummy fallback, local-only save, false success, fake beneficiary count, or failure-to-empty conversion is authorized.

## Lifecycle-safe governance

The 3FU governance test uses the same precommit/historical pattern. Before the 3FU commit exists, it verifies current HEAD equals `a18c77616ff4ac806920e75474c4f1765e970e53` and only the authorized 3FU files are changed. After commit, it locates the unique commit subject `Approve projects activities authorization contract`, inspects the historical commit with local Git, and verifies the historical changed-file scope.

The test never inspects current `app/page.tsx` to prove frontend exclusion.

## 3FU verdict

GO - Milestone 3FV may open after 3FU validation and 3FT regression pass, the 3FU branch is committed with subject `Approve projects activities authorization contract`, pushed normally, local and remote hashes match, and the working tree is clean.