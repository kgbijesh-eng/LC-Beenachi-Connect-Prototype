# Milestone 3GA - Reminders Backend Activation Scope Gate

## Milestone identity and scope

Milestone 3GA starts from `milestone-3fz-projects-activities-closure-review` at verified commit `2a7ce32a15a9d53b3e49716ebe3613d3a6518d1a` and opens `milestone-3ga-reminders-backend-activation-scope-gate`.

3GA is a documentation-and-governance scope gate only. It creates only:

- `backend/docs/milestone_3ga_reminders_backend_activation_scope_gate.md`
- `backend/tests/Feature/Milestone3gaRemindersBackendActivationScopeGateTest.php`

No runtime Reminders authorization, route, controller, request, resource, service, model, migration, factory, seeder, policy, middleware, frontend integration, operational data, database, environment, cache, log, or generated file is authorized in 3GA.

## Repository inspection evidence

- `app/page.tsx` contains the truthful message `Reminders can be created only after a verified persistence workflow is connected.`
- `verifiedOperationalSourceConnected("reminders")` remains false.
- Prototype arrays and preview controls exist, including `clubReminders` and reminder history, but they are not verified operational records and must not be restored as real data.
- `backend/routes/api.php` uses `/api/v1/clubs/{club}/...`, Sanctum, and `club.permission:<slug>,club`.
- `ClubPermissionService.php` requires active same-club membership.
- `EnsureClubPermission.php` provides the established 401/403 permission boundary.
- `AuditLog.php` provides club, actor, action, auditable identity, and metadata.
- Events and Projects provide the nearest patterns for stable numeric IDs, explicit resources, read/write services, lifecycle routes, transactions, row locks, `lock_version`, and audit.
- No `ClubReminder` model, `club_reminders` table, Reminders API controller, request, resource, service, policy, factory, permission, or `/reminders` route exists.
- Reports are outside this roadmap.

## Aggregate and stable identity

Approve one club-scoped aggregate: `App\Models\ClubReminder`, backed by `club_reminders`.

This entity represents reminder records displayed inside the application. It does not send notifications.

Every reminder belongs to exactly one club. Route-derived club scope, active same-club membership, nested ownership checks, and stable numeric backend IDs are mandatory. Title, due value, list index, card order, or frontend-generated keys must never identify a reminder. Duplicate titles are valid and must remain distinct.

## Lifecycle

Approved statuses:

- `draft`
- `active`
- `completed`
- `cancelled`

Approved transitions:

- Draft -> Active
- Draft -> Cancelled
- Active -> Completed
- Active -> Cancelled

There are no backward transitions and no reopening. Completed and Cancelled are terminal. There is no delete or soft-delete workflow. Status changes use explicit lifecycle routes; generic update cannot mutate status. Completion before the due instant is allowed because a reminder may be completed early.

Cancellation requires a nonblank management-only reason. Lifecycle timestamps and actors are server-controlled.

## Visibility

Approved values:

- `members`
- `management`

Rules:

- Draft is always management-only.
- Active `members` reminders are readable by authorized active same-club members.
- Active `management` reminders are management-only.
- Completed preserves visibility.
- Cancelled Draft remains management-only.
- Cancelled Active `members` reminders remain member-readable.
- Cancelled Active `management` reminders remain management-only.
- Cancellation reason is omitted from member-facing resources.

The schema must retain `activated_at` so cancelled records can distinguish cancellation before and after activation.

## Permissions and roles

Approved permission slugs:

- `reminders.view`
- `reminders.manage`

| Role | reminders.view | reminders.manage |
| --- | ---: | ---: |
| President/Admin (`president-admin`) | Yes | Yes |
| Secretary (`secretary`) | Yes | Yes |
| Treasurer (`treasurer`) | Yes | No |
| Normal Member (`member`) | Yes | No |

Management rights must never be inferred from display names, frontend labels, prototype sections, reminder categories, or role text. Permission never overrides authentication, active membership, club ownership, lifecycle, or visibility.

## Due-date representation and overdue calculation

Support all-day and timed reminders without mixed payloads.

All-day:

- `is_all_day: true`
- required `due_date` in ISO `YYYY-MM-DD`
- `due_at` must be null

Timed:

- `is_all_day: false`
- required offset-bearing ISO-8601 `due_at`
- stored in UTC and serialized canonically
- displayed in the owning club's IANA timezone
- `due_date` must be null

Overdue is computed, never stored as a mutable status. An Active all-day reminder becomes overdue after the end of its `due_date` in the club timezone. An Active timed reminder becomes overdue when the current instant is later than `due_at`. Draft, Completed, and Cancelled reminders are not overdue.

## Priority and first-release fields

Approved priorities are `normal`, `high`, and `urgent`.

Approved fields:

- stable numeric `id`;
- required `club_id`;
- required `title`;
- optional `description`;
- controlled `priority`, `visibility`, and `status`;
- `is_all_day`, mutually exclusive `due_date` and `due_at`;
- `activated_at`, `completed_at`, and `cancelled_at`;
- management-only `cancellation_reason`;
- created, updated, activated, completed, and cancelled actor IDs;
- optimistic concurrency `lock_version`, starting at 1;
- Laravel timestamps.

No recipient list, delivery channel, delivery status, personal assignment, or notification-provider state belongs in this first release.

## API direction

Later milestones may implement:

- `GET /api/v1/clubs/{club}/reminders`
- `GET /api/v1/clubs/{club}/reminders/{reminder}`
- `POST /api/v1/clubs/{club}/reminders`
- `PATCH /api/v1/clubs/{club}/reminders/{reminder}`
- `POST /api/v1/clubs/{club}/reminders/{reminder}/activate`
- `POST /api/v1/clubs/{club}/reminders/{reminder}/complete`
- `POST /api/v1/clubs/{club}/reminders/{reminder}/cancel`

No DELETE route is approved. Create always produces Draft. Payloads cannot control club, status, actor IDs, lifecycle timestamps, lock version, or audit fields.

## Audit and concurrency

Future successful mutations must use a transaction and create exactly one same-transaction audit event:

- `reminders.created`
- `reminders.updated`
- `reminders.activated`
- `reminders.completed`
- `reminders.cancelled`

Update and lifecycle actions require `lock_version`. Services must lock the target row and return deterministic 409 responses for stale versions or invalid/repeated transitions. Rejected requests create no success audit record and no partial mutation.

## Explicit first-release non-goals

Deferred:

- email;
- WhatsApp;
- SMS;
- push notifications;
- background delivery jobs;
- recurring reminders;
- personal reminders;
- member-specific assignments;
- read/unread tracking;
- acknowledgement tracking;
- snooze;
- attachments;
- comments;
- Events linkage;
- Projects linkage;
- payment linkage;
- calendar synchronization;
- public reminders;
- reports;
- deletion.

No delivery or notification claim is authorized.

## Frontend data truth

The current not-connected state remains authoritative until the isolated frontend-integration milestone. Do not restore dummy reminders, local-only persistence, simulated scheduling, false success, or failure-to-empty conversion. Loading, empty, unavailable, forbidden, conflict, and success must later be distinct, and success may appear only after backend confirmation.

## Milestone sequence and governance lifecycle

Approved sequence: 3GB authorization contract; 3GC database/read API; 3GD lifecycle backend; 3GE isolated frontend integration; 3GF browser/role verification; 3GG closure review.

3GA authorizes 3GB only. It does not authorize runtime implementation.

Before the unique commit `Add reminders backend scope gate` exists, the governance test verifies the exact base and current two-file diff. After commit, it locates that subject through local Git and verifies the historical changed-file scope and historical document. It does not require a later checkout of `app/page.tsx` to remain disconnected.

## Decision

GO - Milestone 3GB may open only after this branch is tested, committed, pushed normally, remotely verified, tracking-clean, and the local/remote hashes match.
