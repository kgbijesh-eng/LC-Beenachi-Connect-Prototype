# Milestone 3GB - Reminders Authorization and API Contract Approval

## Identity and boundary

3GB starts from `milestone-3ga-reminders-backend-activation-scope-gate` at remotely verified commit `dc72dd039c5f4112efc1944e72b3f86f040fd98a` and opens `milestone-3gb-reminders-authorization-contract-approval`.

This is a documentation-and-governance contract milestone. Only this document and `backend/tests/Feature/Milestone3gbRemindersAuthorizationContractApprovalTest.php` are authorized. No migration, model, permission seeding, route, controller, request, resource, service, factory, frontend, database mutation, or runtime implementation is authorized.

## Aggregate, permissions, and role assignments

The unified aggregate remains `App\Models\ClubReminder`, table `club_reminders`.

Approved permissions:

- `reminders.view`
- `reminders.manage`

| Role | reminders.view | reminders.manage |
| --- | ---: | ---: |
| President/Admin (`president-admin`) | Yes | Yes |
| Secretary (`secretary`) | Yes | Yes |
| Treasurer (`treasurer`) | Yes | No |
| Normal Member (`member`) | Yes | No |

Unauthenticated requests return 401. Inactive or permissionless target-club membership returns 403. Wrong-club, nonexistent, or concealed nested reminder identity returns 404. Management is never inferred from labels or names.

## Read matrix

| Status | Visibility | Management | Treasurer/Member |
| --- | --- | ---: | ---: |
| Draft | either | Visible | Hidden |
| Active | members | Visible | Visible |
| Active | management | Visible | Hidden |
| Completed | members | Visible | Visible |
| Completed | management | Visible | Hidden |
| Cancelled Draft | either | Visible | Hidden |
| Cancelled Active | members | Visible | Visible |
| Cancelled Active | management | Visible | Hidden |

Cancelled history is classified by the server-controlled `activated_at`. Member-facing resources omit cancellation reason, actor details, audit metadata, and management-only fields.

## API routes

- `GET /api/v1/clubs/{club}/reminders`
- `GET /api/v1/clubs/{club}/reminders/{reminder}`
- `POST /api/v1/clubs/{club}/reminders`
- `PATCH /api/v1/clubs/{club}/reminders/{reminder}`
- `POST /api/v1/clubs/{club}/reminders/{reminder}/activate`
- `POST /api/v1/clubs/{club}/reminders/{reminder}/complete`
- `POST /api/v1/clubs/{club}/reminders/{reminder}/cancel`

No DELETE route and no generic status patch are approved.

## Create contract

Create requires `reminders.manage`, active same-club membership, and produces Draft only.

Allowed fields: `title` (required, trimmed, max 200), `description` (nullable, max 5000), `priority` (`normal|high|urgent`), `visibility` (`members|management`), `is_all_day`, and the matching due field.

For `is_all_day=true`, `due_date` is required ISO `YYYY-MM-DD` and `due_at` is forbidden. For `is_all_day=false`, offset-bearing ISO-8601 `due_at` is required and `due_date` is forbidden. Timed instants are stored in UTC.

Payload must not control `club_id`, `status`, actor IDs, lifecycle timestamps, `lock_version`, overdue, or audit fields. Unknown and client-owned fields return 422.

## Update contract

Update requires `reminders.manage`, route-derived same-club ownership, and exact `lock_version`.

- Draft: title, description, priority, visibility, all-day/timed mode, and due values.
- Active: title, description, priority, visibility, and a future due correction. Changing all-day/timed mode is permitted only when the resulting schedule is valid and future.
- Completed/Cancelled: terminal and non-editable.

Generic update rejects status and all server-owned fields. Stale version returns 409.

## Lifecycle contract

Activate performs Draft -> Active and requires `lock_version` plus a valid due schedule.

Complete performs Active -> Completed and requires `lock_version`. Completion before due is explicitly allowed.

Cancel performs Draft -> Cancelled or Active -> Cancelled, requires `lock_version` and nonblank `cancellation_reason` (max 1000).

Completed and Cancelled are terminal. Repeated actions, stale versions, and invalid transitions return deterministic 409 without duplicate audit.

## Filters, ordering, pagination, and dates

Approved list filters:

- `status=draft|active|completed|cancelled`;
- `priority=normal|high|urgent`;
- management-only `visibility=members|management`;
- `overdue=true|false`;
- `date_from` and `date_to` as ISO `YYYY-MM-DD`;
- `is_all_day=true|false`;
- trimmed title `q`, max 100;
- `page`;
- `per_page`, default 25, maximum 100.

Irrelevant, unknown, or unauthorized filters return 422 rather than being silently ignored.

Default deterministic order:

1. Active overdue reminders;
2. Active upcoming reminders;
3. recently Completed or Cancelled reminders;
4. Draft management records;
5. numeric ID tie-breaker.

Date-only boundaries use the owning club timezone and are inclusive. Timed UTC values are compared against safely converted club-timezone boundaries. All-day overdue begins after the local end of `due_date`; timed overdue begins after `due_at`. Only Active records can be overdue.

## Response contract

List returns `data` plus `meta` containing `current_page`, `last_page`, `per_page`, and `total`. Detail returns one `data` object.

Safe fields include stable `id`, title, description, priority, authorized visibility, status, `is_all_day`, `due_date`, canonical UTC `due_at`, club-timezone display value, computed `overdue`, lifecycle timestamps as authorized, `lock_version`, and timestamps. Stable backend ID, never title, is identity.

## Club scope, validation, audit, and concurrency

The route supplies club identity. Active same-club membership and nested ownership are mandatory. Payload cannot override club. Cross-club IDs fail closed.

Form Requests own field allowlists, primitive validation, due-shape validation, and unknown-field rejection. Read/write services own visibility, lifecycle, club scope, future-correction rules, row locking, conflict decisions, and audit.

Successful actions write exactly one same-transaction audit record: `reminders.created`, `reminders.updated`, `reminders.activated`, `reminders.completed`, or `reminders.cancelled`. Mutation and audit are atomic. Rejected actions write no success audit.

## Implementation boundaries

3GC may implement only schema, model/constants, permissions, read authorization, list/detail GET routes, query request, resource, read service/controller, factory, tests, and milestone documentation. It must not implement mutation routes or frontend changes.

3GD may begin only from remotely verified clean 3GC and may implement create, update, activate, complete, cancel, mutation requests, transactional write service, audit, concurrency, authorization, and focused tests. It must not implement frontend integration.

The frontend remains truthfully disconnected until 3GE. No dummy fallback, local-only save, simulated success, or stale-data masking is authorized.

## Lifecycle-safe governance and verdict

Before `Approve reminders authorization contract` exists, the governance test verifies the exact 3GA base and two-file working scope. After commit, it verifies that unique historical commit and its historical files using local Git. It never requires the current frontend to remain disconnected after later milestones.

GO - 3GC may open after 3GB and 3GA tests pass, this milestone is committed separately, pushed normally, remotely verified, tracking-clean, and the working tree is clean.
