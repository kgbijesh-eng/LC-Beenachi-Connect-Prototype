# Milestone 3FV - Projects & Activities Database and Read API Foundation

## Milestone identity

Milestone 3FV implements only the Projects & Activities database and read API foundation. It starts from `milestone-3fu-projects-activities-authorization-contract-approval` at `f584592e5c0873d4ab94a8b7a76167b0bf7ccef0` and opens branch `milestone-3fv-projects-activities-database-read-api-foundation`.

3FV implements schema, model, controlled values, seeded permissions, read authorization, list endpoint, detail endpoint, resource shape, filters, ordering, pagination, club scoping, and tests.

3FV does not implement create, update, plan, start, complete, cancel, write services, mutation Form Requests, frontend integration, browser evidence, uploads, reports, committee-chair management, or deletion.

## Implemented files

3FV changes are limited to Projects read-foundation files and the seeded permission matrix test:

- `backend/database/migrations/2026_06_24_000001_create_club_projects_table.php`
- `backend/app/Models/ClubProject.php`
- `backend/app/Http/Requests/Projects/ListClubProjectsRequest.php`
- `backend/app/Http/Resources/Projects/ClubProjectResource.php`
- `backend/app/Services/Projects/ClubProjectReadService.php`
- `backend/app/Http/Controllers/Api/V1/Projects/ClubProjectController.php`
- `backend/routes/api.php`
- `backend/database/seeders/DefaultRolesAndPermissionsSeeder.php`
- `backend/tests/Feature/DefaultRolesAndPermissionsSeederTest.php`
- `backend/tests/Feature/Milestone3ftProjectsActivitiesBackendActivationScopeGateTest.php`
- `backend/tests/Feature/Milestone3fuProjectsActivitiesAuthorizationContractApprovalTest.php`
- `backend/docs/milestone_3fv_projects_activities_database_read_api_foundation.md`
- `backend/tests/Feature/Milestone3fvProjectsActivitiesDatabaseReadApiFoundationTest.php`

## Schema

The `club_projects` table includes stable `id`, `club_id`, `record_type`, `title`, `description`, `objective`, `service_category`, `visibility`, `status`, date-only planning and actual fields, `location`, optional `lead_member_id`, expected and actual beneficiary counts, `impact_summary`, `cancellation_reason`, lifecycle timestamps, actor IDs, `lock_version`, and Laravel timestamps.

Indexes support club/status/date, club/record type/date, club/visibility/status, club/service category, and cancelled-history reads through `planned_at`.

The table intentionally does not include deferred budgets, expenses, payments, attendance, volunteer rosters, beneficiary identities, photos, attachments, documents, tasks, comments, reminders, notifications, WhatsApp, email, recurring activities, public publication, reports, LCIF reporting, district reporting, service-hour aggregation, impact dashboards, Events linkage, or deletion fields.

## Model and controlled values

`App\Models\ClubProject` defines record types `project` and `activity`; statuses `draft`, `planned`, `active`, `completed`, and `cancelled`; visibilities `members` and `management`; permissions `projects.view` and `projects.manage`; and management role slugs `president-admin` and `secretary`.

Relationships include `club`, `leadMember`, `creator`, and `updater`.

## Seeded permissions

`DefaultRolesAndPermissionsSeeder.php` now seeds `projects.view` and `projects.manage`.

Role matrix:

| Role | projects.view | projects.manage |
| --- | ---: | ---: |
| President/Admin | Yes | Yes |
| Secretary | Yes | Yes |
| Treasurer | Yes | No |
| Normal Member | Yes | No |

The default role-permission matrix test was updated from 14 to 16 permissions and from 31 to 37 role-permission links because this milestone adds two approved Projects permissions and six approved assignments. The 3FT and 3FU governance tests were also repaired to inspect their historical docs/tests-only commits after commit, instead of asserting current-checkout absence of Projects runtime files.

## Read API

3FV adds only:

- `GET /api/v1/clubs/{club}/projects`
- `GET /api/v1/clubs/{club}/projects/{project}`

No `POST`, `PATCH`, lifecycle, or `DELETE` Projects routes exist in 3FV.

The list endpoint returns top-level `data` and `meta` with pagination. Detail returns top-level `data` object.

## Read authorization and visibility

Management readers are President/Admin and Secretary with active same-club membership and `projects.manage`. They can read same-club Draft, Planned, Active, Completed, and Cancelled records for both `members` and `management` visibility.

Treasurer and Normal Member can read only member-visible Planned, Active, Completed, and Cancelled records where `planned_at` is not null for Cancelled records. Cancelled Draft records remain hidden from non-management users.

Unauthenticated API access returns 401. Inactive membership and cross-club list access return 403 through club permission middleware. Wrong-club object identity and undisclosed records return 404 at the object boundary.

## Filters, ordering and pagination

Approved list filters implemented:

- `scope=planned|active|completed|cancelled|all`
- `record_type=project|activity`
- `status` for management only
- `visibility` for management only
- `service_category`
- `from` and `to` ISO date filters
- `q` search across title, objective, and location
- `page`
- `per_page`, default 20, max 50
- `direction=asc|desc`

Ordering is deterministic by status priority, relevant date, and stable `id`.

## Response privacy

Management responses include `club_id`, `visibility`, private cancellation reason, actor IDs, `lock_version`, and timestamps. Read-only member-facing responses omit private cancellation reason, private actor IDs, `lock_version`, and management-only visibility metadata.

Cancelled member-facing responses also suppress descriptive/impact text to avoid leaking private cancellation context.

## Stable ID and duplicate-title safety

The API returns stable backend `id` values. Duplicate titles remain separate records and are ordered by stable ID after status/date ordering. Title, category, location, date, list index, or frontend key is not an identity.

## Governance lifecycle safety

The 3FV governance test uses the lifecycle-safe precommit/historical pattern. Before commit it verifies current HEAD equals `f584592e5c0873d4ab94a8b7a76167b0bf7ccef0` and the current changed-file scope. After commit it locates the unique subject `Add projects activities read API foundation` and verifies the historical changed-file scope with local Git.

The test does not inspect current `app/page.tsx` to prove frontend exclusion.

## 3FV verdict

GO - Milestone 3FW may open after 3FV targeted tests, 3FU regression, 3FT regression, diff checks, commit, normal push, local/remote hash verification, and clean tracking pass.