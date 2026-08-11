# Milestone 3FW - Projects & Activities Create, Update and Lifecycle Backend

## Milestone identity

Milestone 3FW implements Projects & Activities create, update, and lifecycle backend workflows. It starts from `milestone-3fv-projects-activities-database-read-api-foundation` at `c4c557a956bb082d2dbb842974bc5390a4a91426` and opens branch `milestone-3fw-projects-activities-lifecycle-backend-workflow`.

3FW does not implement frontend integration, browser evidence, uploads, reports, committee-chair management, attachments, or deletion.

## Implemented files

3FW changes:

- `backend/app/Http/Requests/Projects/StoreClubProjectRequest.php`
- `backend/app/Http/Requests/Projects/UpdateClubProjectRequest.php`
- `backend/app/Http/Requests/Projects/TransitionClubProjectRequest.php`
- `backend/app/Services/Projects/ClubProjectWriteService.php`
- `backend/app/Http/Controllers/Api/V1/Projects/ClubProjectController.php`
- `backend/routes/api.php`
- `backend/tests/Feature/Milestone3fvProjectsActivitiesDatabaseReadApiFoundationTest.php`
- `backend/docs/milestone_3fw_projects_activities_lifecycle_backend_workflow.md`
- `backend/tests/Feature/Milestone3fwProjectsActivitiesLifecycleBackendWorkflowTest.php`

The 3FV test change is a lifecycle-safe governance repair: after 3FW, mutation routes are expected in the current checkout, so 3FV verifies its no-mutation-route boundary against the historical 3FV commit.

## Workflows

Create uses route-derived club, validated `record_type`, validated visibility, date-only fields, optional same-club lead member, Draft status only, lock version 1, and same-transaction `projects.created` audit. Payload cannot control `club_id`, status, lifecycle timestamps, actor IDs, or lock version.

Update requires same-club ownership, `projects.manage`, nonterminal lifecycle state, `lock_version`, state-specific allowed fields, same-club lead member validation, date ordering, atomic persistence, lock version increment, and `projects.updated` audit. Generic update cannot mutate status.

Plan transitions Draft -> Planned, requires `planned_start_date`, sets `planned_at`, actor, lock version, and `projects.planned` audit.

Start transitions Planned -> Active, sets `started_at`, actor, actual start date, lock version, and `projects.started` audit.

Complete transitions Planned -> Completed or Active -> Completed, enforces actual date ordering, future completion denial, beneficiary count validation, terminal state, lock version, and `projects.completed` audit.

Cancel transitions Draft -> Cancelled, Planned -> Cancelled, or Active -> Cancelled, requires nonblank cancellation reason, preserves `planned_at` history for read visibility, sets actor/timestamp, increments lock version, and writes `projects.cancelled` audit.

## Authorization and integrity

President/Admin and Secretary can manage. Treasurer and Normal Member remain read-only. Unauthenticated users receive 401, inactive members and users without target-club management permission receive 403, and wrong-club object identity fails closed.

All mutation workflows use `ClubProjectWriteService`, database transactions, row locking through `lockForUpdate`, optimistic `lock_version`, deterministic 409 conflicts for stale version or invalid lifecycle transitions, and rollback/no success audit on rejected requests.

## Route surface

3FW adds:

- `POST /api/v1/clubs/{club}/projects`
- `PATCH /api/v1/clubs/{club}/projects/{project}`
- `POST /api/v1/clubs/{club}/projects/{project}/plan`
- `POST /api/v1/clubs/{club}/projects/{project}/start`
- `POST /api/v1/clubs/{club}/projects/{project}/complete`
- `POST /api/v1/clubs/{club}/projects/{project}/cancel`

No DELETE route exists.

## Governance lifecycle safety

The 3FW governance test verifies current changed-file scope before commit and historical changed-file scope after commit by locating `Add projects activities lifecycle workflows`. It does not require current `app/page.tsx` to remain disconnected.

## 3FW verdict

GO - Milestone 3FX may open after 3FW tests, 3FV/3FU/3FT regressions, diff checks, commit, normal push, local/remote hash verification, and clean tracking pass.