# Milestone 3FY — Projects & Activities Browser and Role Verification

## Identity and environment

- Branch: `milestone-3fy-projects-activities-browser-role-verification`
- Base 3FX commit: `6b3a870f7b008bb63cac10a50f8e5b3e2990b0f1`
- Isolated database: temporary SQLite outside the repository.
- Genuine PostgreSQL database remained read-only and has no `club_projects` table.
- Sentinel observed only in the isolated backend: `3FX-SENTINEL-ISOLATED-20260624`, stable ID `13`.
- Browser target: `http://localhost:3000/`; isolated Laravel backend: `http://127.0.0.1:8000`.

## Role verification

| Role | Result | Evidence |
| --- | --- | --- |
| President/Admin | PASS | Backend list/detail, stable IDs, Draft creation and Planned-to-Active transition were executed in the browser. Create/edit/lifecycle controls were visible. |
| Secretary | PASS | Browser displayed `Backend management`, Draft records and `Create Draft`; direct API management request returned 201. |
| Treasurer | PASS | Read API returned 200; mutation returned 403; management-only filters and controls were not available to the read-only role. |
| Normal Member | PASS | Browser displayed `Backend read-only`, no Create control, no Draft, no management record, and the cancelled-after-planning member record remained visible. |
| Unauthenticated | PASS | Projects API returned 401. |
| Inactive member | PASS | Synthetic inactive user authenticated but Projects access returned 403. |
| Another-club user | PASS | Club 1 access returned 403 and a cross-club stable ID returned 404. |

## Management workflow verification

| Workflow | Result |
| --- | --- |
| Create Project Draft | PASS — browser backend confirmation returned stable record `#19`. |
| Create Activity Draft | PASS — direct isolated API returned 201. |
| Edit Draft exact ID | PASS — lifecycle feature test and isolated API returned 200. |
| Draft to Planned | PASS. |
| Planned to Active | PASS — browser changed stable record `#3` to Active and refreshed backend data. |
| Planned to Completed | PASS — isolated API returned 200. |
| Active to Completed | PASS — lifecycle regression passed. |
| Cancel Draft | PASS. |
| Cancel Planned | PASS. |
| Cancel Active | PASS. |
| Repeated lifecycle request | PASS — deterministic 409. |
| Early completion | PASS — denied with 409. |
| Stale lock version | PASS — deterministic 409. |
| Terminal edit/transition | PASS — denied. |

## Read matrix

All approved combinations passed: management sees all same-club states; Treasurer/Member see only members-visible Planned, Active, Completed, and Cancelled records with `planned_at`. Draft, management visibility, and Cancelled Draft remain hidden from read-only roles.

## Identity and navigation

- PASS — list and detail use numeric backend IDs.
- PASS — duplicate title records `#10` Project and `#11` Activity remained independently addressable.
- PASS — browser detail loaded the exact requested ID.
- PASS — edit and lifecycle requests include the selected ID and lock version.
- PASS — cross-club IDs fail closed.
- PASS — Back returns to the backend list.
- PASS — filters and pagination retain backend identity.
- PASS — role switching immediately cleared the privileged selected record and form state.
- PASS — Normal Member has a direct read-only Projects navigation entry.

## Data truth

- PASS — no dummy Projects fallback and no local-only persistence.
- PASS — success appears only after a successful backend response.
- PASS — empty filters show a genuine empty state.
- PASS — HTTP 403 and 409 have explicit forbidden/conflict states.
- PASS — stopping the known isolated backend cleared stale rows and produced a genuine error state.
- PASS — mutations refresh from the backend.

## Responsive verification

| Width | Result |
| --- | --- |
| 1440px | PASS — document client/scroll width `1440/1440`. |
| 900px | PASS — document client/scroll width `885/885`. |
| 390px | PASS — document client/scroll width `375/375`. |

No document-level horizontal overflow was present. Long titles and locations wrapped; cards, filters, forms and lifecycle controls remained reachable.

## Findings

### IN-SCOPE DEFECT — FIXED

- Role/workflow/viewport: all roles, detail/save/lifecycle, all widths.
- Expected: network failure must end loading/submitting with truthful error text and no false success.
- Observed: detail and mutation `fetch` calls did not catch connection-level rejection.
- Root cause: only HTTP responses were handled.
- Files changed: `app/page.tsx`.
- Retest: build and source integration test pass; the shared `safeProjectFetch` path now returns an explicit error state.

### OUT-OF-SCOPE FINDING — DEFERRED

- The existing Reports & Tools activity cards remain prototype report content. They are outside the connected Projects screen and were not presented as Projects operational records.

### BLOCKED — NOT SAFELY TESTABLE

- None.

## Regression and decision

- Milestone 3FT through 3FX Projects tests pass.
- Default roles and permissions regression passes.
- Production frontend build passes.
- No environment, database, cache, log or genuine operational data is committed or mutated.
- No unresolved in-scope defect remains.

**Decision: PASS — Milestone 3FZ closure review may open.**
