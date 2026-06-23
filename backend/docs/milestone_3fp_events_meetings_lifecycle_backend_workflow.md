# Milestone 3FP - Events & Meetings Lifecycle Backend Workflow

## 1. Milestone identity

- Base branch: milestone-3fo-events-meetings-database-read-api-foundation.
- Verified base commit: 360c6f6a60f91bf406ce71101d17e23228ad3176.
- Implementation branch: milestone-3fp-events-meetings-lifecycle-backend-workflow.
- Commit message: Add events meetings lifecycle workflows.

Milestone 3FP implements only backend create, update, publish, cancel, and complete workflows for the 3FO `club_events` foundation. It does not implement frontend integration, browser verification, delete, attendance, RSVP, meeting minutes, recurrence, reminders, notifications, files, reports, public publication, or external calendar integration.

## 2. Implemented mutation routes

3FP adds explicit management-only routes:

- `POST /api/v1/clubs/{club}/events`
- `PATCH /api/v1/clubs/{club}/events/{event}`
- `POST /api/v1/clubs/{club}/events/{event}/publish`
- `POST /api/v1/clubs/{club}/events/{event}/cancel`
- `POST /api/v1/clubs/{club}/events/{event}/complete`

No DELETE route and no generic `PATCH status` workflow exists.

## 3. Authorization

All mutation routes use Sanctum and `club.permission:events.manage,club`. The controller also confirms the active same-club role has `events.manage`. President/Admin and Secretary can mutate. Treasurer, Normal Member, inactive memberships, unauthenticated users, and cross-club users cannot mutate.

## 4. Create workflow

Create derives `club_id`, creator, updater, status, timestamps, and `lock_version` from the route/authenticated actor/server. The request cannot set client-owned fields. Create always creates Draft with `lock_version=1` and writes exactly one `events.created` audit row in the same transaction.

## 5. Update workflow

Update requires `lock_version`, operates by stable backend ID, confirms route-club ownership, rejects stale versions with 409, rejects terminal records, rejects arbitrary status mutation, increments `lock_version`, sets updater, and writes exactly one `events.updated` audit row with before/after metadata.

## 6. Lifecycle workflows

Publish allows only Draft -> Published, sets `published_at`, increments `lock_version`, and writes `events.published`.

Cancel allows Draft -> Cancelled and Published -> Cancelled, requires `cancellation_reason`, preserves visibility, increments `lock_version`, and writes `events.cancelled`.

Complete allows only Published -> Completed, enforces early-completion denial using `end_at`, `start_at`, or inclusive all-day end date in the club timezone, increments `lock_version`, and writes `events.completed`.

Completed and Cancelled are terminal. Repeated lifecycle requests return deterministic 409 conflicts and create no duplicate audit records.

## 7. Transaction, audit and integrity

`ClubEventWriteService` wraps every successful create, update, publish, cancel, and complete action in a database transaction. It locks the event row for updates/transitions, verifies club ownership and `lock_version`, mutates state, and creates an `AuditLog` with actor, club, action, target type/id, and before/after metadata. Failed authorization, validation, stale version, cross-club, repeated transition, terminal-state, and early-completion requests do not create success audit rows.

## 8. Validation

3FP adds Form Requests for create, update, and lifecycle transitions. Requests reject client-owned `club_id`, status, lifecycle timestamps, actor fields, audit fields, and arbitrary status mutation. Timed records use `is_all_day=false`, `start_at`, and optional `end_at`. All-day records use `is_all_day=true`, `start_date`, and optional inclusive `end_date`. Mixed date/timestamp representations are rejected.

## 9. Data safety

All tests use synthetic RefreshDatabase records. No genuine operational Events & Meetings records are mutated. Frontend remains fail-closed and not connected until 3FQ.

## 10. Validation summary

The 3FP feature test covers authorized create/update/lifecycle success, unauthorized roles, inactive membership, unauthenticated access, cross-club protection, invalid type/visibility/all-day/timed payloads, club override attempts, stale lock conflicts, terminal update denial, arbitrary status mutation denial, publish/cancel/complete state guards, required cancellation reason, early timed and all-day completion denial, audit creation, audit before/after metadata, no duplicate audit on repeated transitions, and no DELETE route.

## 11. Next milestone boundary

Milestone 3FQ may open only after 3FP is committed, pushed, remotely verified, clean, and tracking its remote with no ahead/behind state. 3FQ may integrate the frontend with verified backend APIs; 3FP does not edit frontend runtime files.
