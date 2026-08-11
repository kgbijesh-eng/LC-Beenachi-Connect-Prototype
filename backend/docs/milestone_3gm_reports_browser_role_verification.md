# Milestone 3GM - Reports Browser and Role Verification

Base: remotely verified 3GL commit `153d923a0310abb8ebb71dc223feccf1ce753781`.

All browser execution used `%TEMP%\lc_beenachi_3gl_reports_isolated.sqlite`. The genuine PostgreSQL database remained untouched.

## Role and catalog evidence

- President/Admin, Secretary, and Treasurer each loaded exactly `membership-summary`, `events-register`, `projects-register`, and `reminders-register`.
- Normal Member lost the Reports navigation and all previously visible report output immediately after role downgrade.
- Unauthenticated, inactive-member, Normal Member, and cross-club denial remain covered by the Reports feature regression suite.
- `collections-summary` remained absent because financial provenance was deferred.

## Accuracy, privacy, and navigation evidence

- Membership summary displayed 3 total, 2 active, and 1 inactive.
- Events displayed the sentinel `REPORT-SENTINEL-EVENT`; Projects displayed `REPORT-SENTINEL-PROJECT`; Reminders displayed `REPORT-SENTINEL-REMINDER`.
- Synthetic pagination used 12 same-club Events: page 1 showed 10 rows and page 2 showed 2, with total 12.
- Filtering to `NO-SUCH-REPORT-ROW` returned total 0 and no rows.
- Stable numeric IDs kept duplicate titles distinguishable.
- Cross-club fixtures were excluded and report rows contained no contact, address, birth, banking, UPI, credential, private-note, cancellation-reason, or audit-payload fields.

## Failure, recovery, and responsive evidence

The known local PHP listener was stopped after a populated Events report. The next request immediately removed the sentinel, rows, totals, and pagination, then displayed `Report unavailable. Previous rows and totals were cleared.` After the same isolated backend restarted, Retry restored the exact four-key catalog.

The first empty-result browser run exposed a focused defect: a valid zero-row response was labeled as loaded rather than empty. `app/ReportsBackend.tsx` now classifies a response as empty only when it has no rows and no meaningful backend summary value. The repeated browser run displayed `This verified report is empty.` while retaining the truthful backend total of 0.

At 1440, 900, and 390 pixel viewport widths, document scroll width remained below viewport width. Catalog cards, filters, summaries, row cards, and pagination remained readable and reachable.

GO - 3GN may open after Reports/source regressions, production build, commit/push, remote match, clean tracking, and no unresolved browser defect.
