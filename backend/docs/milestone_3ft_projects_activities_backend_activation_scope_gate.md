# Milestone 3FT - Projects & Activities Backend Activation Scope Gate

## Milestone identity

Milestone 3FT is a documentation-and-governance scope gate only for Projects & Activities backend activation. It starts from `milestone-3fs-events-meetings-closure-review` at `2ea81ac78790caaa81a926e3f568637e94613ae7` and opens branch `milestone-3ft-projects-activities-backend-activation-scope-gate`.

3FT creates only:

- `backend/docs/milestone_3ft_projects_activities_backend_activation_scope_gate.md`
- `backend/tests/Feature/Milestone3ftProjectsActivitiesBackendActivationScopeGateTest.php`

No runtime Projects code, frontend integration, route, controller, request, resource, service, model, migration, factory, seeder, policy, middleware, config, package, database, environment, cache, log, or generated file is authorized in 3FT.

## Repository inspection evidence

Repository inspection found:

- `app/page.tsx` contains the current Projects / Activities surface and the truthful message: `The Projects & Activities workflow is not connected to a verified operational source yet.`
- `verifiedOperationalSourceConnected("projects")` remains false, so real project and activity records must not be faked by the frontend.
- Legacy prototype arrays include `Activity` UI preview data, but those labels are not a verified backend operational source.
- `backend/routes/api.php` already uses `/api/v1/clubs/{club}/...` route families and `club.permission:<slug>,club` middleware.
- `DefaultRolesAndPermissionsSeeder.php` currently seeds `events.view` and `events.manage` but no Projects permissions yet.
- `ClubPermissionService.php` grants permissions only through active club memberships.
- `EnsureClubPermission.php` returns 401 for unauthenticated users and 403 for authenticated users missing the route permission.
- `ClubMembership.php`, `Role.php`, `Permission.php`, and `Member.php` provide stable user, role, member, and same-club membership foundations.
- `AuditLog.php` supports auditable type, auditable id, action, actor, club, and JSON metadata.
- Events & Meetings conventions now provide the nearest backend pattern: model constants, read service, write service, form requests, API resources, transaction-safe audit, lock version, and stable backend IDs.
- No backend `ClubProject`, `club_projects`, Projects controller, Projects request, Projects resource, Projects service, or `/projects` route exists yet.
- No stable backend committee assignment model was found that can safely grant committee-chair Projects management.

## Aggregate decision

3FT selects Option A - unified Projects & Activities entity.

Use `App\Models\ClubProject` backed by table `club_projects` with controlled `record_type` values:

- `project`
- `activity`

Option B - parent `ClubProject` plus child `ClubProjectActivity` - is rejected for the first release because repository evidence does not prove a required multiple-child activity model yet. A unified aggregate keeps the schema smaller, simplifies authorization, avoids false parent-child relationships, permits standalone activities, and leaves linked activity timelines deferred.

## Status and lifecycle decision

Approved status values:

- `draft`
- `planned`
- `active`
- `completed`
- `cancelled`

Approved lifecycle transitions:

- Draft -> Planned
- Planned -> Active
- Planned -> Completed
- Active -> Completed
- Draft -> Cancelled
- Planned -> Cancelled
- Active -> Cancelled

Rules:

- No backward transitions.
- Completed and Cancelled are terminal.
- Completed -> another status is forbidden.
- Cancelled -> another status is forbidden.
- Generic update must not mutate status.
- Explicit lifecycle routes are required.
- No delete or soft delete is authorized.
- Planned -> Completed is allowed for short activities that do not require an explicit Active phase.

## Visibility decision

Approved visibility values:

- `members`
- `management`

Visibility rules:

- Draft is management-only regardless of visibility.
- Planned `members` is visible to authorized active same-club members with `projects.view`.
- Planned `management` is management-only.
- Active and Completed preserve visibility.
- Cancelled Draft remains management-only.
- Cancelled `members` records are member-readable only if they had previously reached Planned or Active.
- Cancellation reason remains management-only in member-facing responses.
- The schema must preserve cancelled-history evidence with `planned_at` so the read service can distinguish cancelled before visible from cancelled after visible.

## Permission model decision

Approved permission slugs:

- `projects.view`
- `projects.manage`

Preferred role assignments:

| Role | projects.view | projects.manage |
| --- | ---: | ---: |
| President/Admin (`president-admin`) | Yes | Yes |
| Secretary (`secretary`) | Yes | Yes |
| Treasurer (`treasurer`) | Yes | No |
| Normal Member (`member`) | Yes | No |

Permission never overrides authentication, active membership, club scope, lifecycle status, or visibility.

Committee-chair management is deferred. It must not be inferred from display name, leadership roster text, member name matching, frontend label, committee card text, or prototype UI. Committee-chair management may be added only after a stable backend committee assignment model with permission-bearing membership exists.

## First-release data fields

Approved first-release `club_projects` fields:

| Field | Decision |
| --- | --- |
| `id` | stable numeric backend identity |
| `club_id` | required same-club scope |
| `record_type` | `project` or `activity` |
| `title` | required descriptive name |
| `description` | optional narrative |
| `objective` | optional objective |
| `service_category` | optional controlled/service category string for first release |
| `visibility` | `members` or `management` |
| `status` | Draft, Planned, Active, Completed, Cancelled |
| `planned_start_date` | optional/required by lifecycle as date-only |
| `planned_end_date` | optional date-only |
| `actual_start_date` | optional date-only |
| `actual_end_date` | optional date-only |
| `location` | optional location or venue |
| `lead_member_id` | optional stable same-club member FK |
| `expected_beneficiary_count` | optional nonnegative integer |
| `actual_beneficiary_count` | optional nonnegative integer |
| `impact_summary` | optional completion/impact text |
| `cancellation_reason` | management-only reason |
| `planned_at`, `started_at`, `completed_at`, `cancelled_at` | lifecycle timestamps |
| actor IDs | created, updated, planned, started, completed, cancelled by user IDs |
| `lock_version` | optimistic concurrency integer starting at 1 |
| Laravel timestamps | created/updated timestamps |

Do not store beneficiary names, beneficiary contact details, sensitive health, financial, or personal information in the first release.

## Temporal decision

3FT approves date-only representation for Projects & Activities. Use ISO `YYYY-MM-DD` API values and these fields:

- `planned_start_date`
- `planned_end_date`
- `actual_start_date`
- `actual_end_date`

Timed activities, all-day/timed mixing, and timestamp-based project schedules are not approved for the first release because the current Projects UI and repository evidence do not require time-of-day.

## Stable identity and club scoping

Every record belongs to exactly one club. `{project}` route records must belong to `{club}`. Payload `club_id` must be rejected or ignored and never trusted. Stable backend numeric IDs identify records. Title, category, date, location, list index, card order, frontend generated key, or duplicate title must never identify a record.

Cross-club list access must fail closed. Cross-club object access must not leak another club's record existence.

## API route family for later milestones

Future milestones may implement the route family:

- `GET /api/v1/clubs/{club}/projects`
- `GET /api/v1/clubs/{club}/projects/{project}`
- `POST /api/v1/clubs/{club}/projects`
- `PATCH /api/v1/clubs/{club}/projects/{project}`
- `POST /api/v1/clubs/{club}/projects/{project}/plan`
- `POST /api/v1/clubs/{club}/projects/{project}/start`
- `POST /api/v1/clubs/{club}/projects/{project}/complete`
- `POST /api/v1/clubs/{club}/projects/{project}/cancel`

No DELETE route is approved.

## Audit, concurrency, and integrity

Future write workflows must use database transactions, row locking where needed, `lock_version`, deterministic 409 stale-version responses, and same-transaction audit events.

Approved audit actions:

- `projects.created`
- `projects.updated`
- `projects.planned`
- `projects.started`
- `projects.completed`
- `projects.cancelled`

Rejected requests must not create success audit events.

## First-release non-goals

Deferred features:

- budgets;
- expenses;
- payments;
- fundraising linkage;
- attendance;
- volunteer rosters;
- beneficiary identities;
- photos;
- attachments;
- documents;
- tasks;
- comments;
- approvals beyond lifecycle;
- reminders;
- notifications;
- WhatsApp;
- email;
- recurring activities;
- public publication;
- reports;
- LCIF reporting;
- district reporting;
- service-hour aggregation;
- impact dashboards;
- Events linkage;
- deletion.

## Milestone implementation sequence

Approved sequence:

- 3FU - Projects & Activities Authorization and API Contract Approval.
- 3FV - Projects & Activities Database and Read API Foundation.
- 3FW - Projects & Activities Create, Update and Lifecycle Backend.
- 3FX - Projects & Activities Isolated Environment and Frontend Integration.
- 3FY - Projects & Activities Browser and Role Verification.
- 3FZ - Projects & Activities Closure Review.

3FT authorizes 3FU only. 3FT does not authorize Projects runtime implementation.

## Frontend data-truth guard

The Projects & Activities truthful not-connected state remains correct until a later approved frontend integration milestone connects the UI to verified backend APIs. Do not restore hardcoded Projects or Activities operational cards as real data. Do not introduce fake persistence, local-only saves, false success, dummy beneficiary counts, failure-to-empty conversion, or backend error masking.

## Governance-test lifecycle safety

The 3FT governance test uses lifecycle-safe verification. Before the 3FT commit exists, it verifies current HEAD equals `2ea81ac78790caaa81a926e3f568637e94613ae7` and the current changed-file scope is exactly the two authorized 3FT files. After the commit exists, it locates the unique commit subject `Add projects activities backend scope gate`, inspects that historical commit with local Git, and verifies the historical changed-file scope.

The test does not require current `app/page.tsx` to remain disconnected after later frontend milestones.

## 3FT verdict

GO - Milestone 3FU may open after 3FT validation passes, the 3FT branch is committed with subject `Add projects activities backend scope gate`, pushed normally, local and remote hashes match, and the working tree is clean.