# Milestone 3FPA - Events & Meetings Isolated Browser Permission Alignment

Base commit: 9e27a7ab75d10cf2a9affab3b4d85fd576c43617

Milestone 3FPA is a scope-gate and operational-proof milestone. It does not change runtime source code. Its purpose is to prove that the Events & Meetings backend from 3FM-3FP can be exercised by API and browser-style requests against an isolated local database without touching the genuine local club database.

## Authorized files

3FPA is limited to these tracked files:

- `backend/docs/milestone_3fpa_events_meetings_isolated_browser_permission_alignment.md`
- `backend/tests/Feature/Milestone3fpaEventsMeetingsIsolatedBrowserPermissionAlignmentTest.php`

No frontend files, backend runtime files, migrations, seeders, controllers, services, models, routes, environment files, or generated files are changed by this milestone.

## Protected database boundary

The local backend `.env` identifies the ordinary local database as:

- `DB_CONNECTION=pgsql`
- `DB_DATABASE=lc_beenachi_connect`

That database is treated as protected because it may contain the genuine 47-member club dataset. 3FPA synthetic setup must not run migrations, seeders, writes, or browser/API smoke data against that database.

All 3FPA synthetic operational checks use an isolated SQLite database outside the repository, for example:

- `%TEMP%\lc_beenachi_3fpa_isolated.sqlite`

The isolated file is disposable, untracked, and intentionally outside the project tree. Test coverage also uses Laravel's `RefreshDatabase` trait so PHPUnit proof data is created and removed inside the test database lifecycle.

## Required isolated environment alignment

When running manual API or browser smoke checks, the backend server must be started with explicit isolated database variables. The recommended local alignment is:

- Backend API: `http://127.0.0.1:8010/api/v1`
- Browser origin used for direct API smoke: `http://127.0.0.1:8010`
- Frontend dev origin, when needed by later milestones: `http://localhost:3000`
- Database: `%TEMP%\lc_beenachi_3fpa_isolated.sqlite`

The backend process must inherit:

```powershell
$env:APP_ENV = 'local'
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = "$env:TEMP\lc_beenachi_3fpa_isolated.sqlite"
```

Before any synthetic writes, confirm that the running API reports the expected isolated database by using `/api/v1/health` and/or a seeded sentinel record query against the isolated file. If a stale server responds from another database, stop or avoid that process before continuing.

## Synthetic roster and permissions to prove

The isolated database may contain only synthetic 3FPA records such as:

- `3FPA Isolated Alignment Club`
- `3FPA Other Synthetic Club`
- `3FPA ISOLATED ALIGNMENT SENTINEL`
- synthetic users under `@3fpa.local.test`

The permission matrix must remain:

- President/admin and secretary can view and manage events.
- Treasurer and member can view member-visible events but cannot create, update, publish, cancel, or complete events.
- Inactive memberships cannot view or manage events.
- Cross-club users cannot read or mutate another club's events.
- Management-only draft events stay hidden from non-management readers.
- No delete route is introduced.

## Browser/API smoke contract

Browser smoke for 3FPA is not a frontend implementation test. The browser may be placed on the isolated backend origin and issue same-origin `fetch` calls to:

- `POST /api/v1/auth/login`
- `GET /api/v1/auth/me`
- `GET /api/v1/clubs/{club}/events`
- `POST /api/v1/clubs/{club}/events`

The smoke must verify the same token, role, club, and event data visible to backend API requests. This proves browser-session/backend/database alignment before 3FQ reconnects the Events & Meetings UI.

## Stop conditions

Stop 3FPA immediately if:

- the current branch or HEAD differs from the 3FP base,
- the genuine database is selected for synthetic writes,
- the isolated database path is inside the repository,
- stale backend or frontend processes cannot be distinguished from the isolated process,
- an authorization result differs from the matrix above,
- tracked changes include anything other than the two authorized 3FPA files.

## Closure requirements

3FPA can be closed only after:

- committed 3FM-3FP tests still pass unchanged,
- this 3FPA test passes,
- manual isolated API/browser smoke has been run or any skipped manual step is explicitly reported,
- `git diff --name-only` contains only the two authorized 3FPA files before commit,
- the milestone branch is committed, pushed, and the remote hash is verified.
