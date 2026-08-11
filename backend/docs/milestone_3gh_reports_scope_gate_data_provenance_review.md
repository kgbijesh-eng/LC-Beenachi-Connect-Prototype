# Milestone 3GH - Reports Scope Gate and Data-Provenance Review

## Identity and scope

3GH starts from `milestone-3gg-reminders-closure-review` at the actual remotely verified commit `980f4e3a82891d0be378db4064ceb79462c1be1d` and opens `milestone-3gh-reports-scope-gate-data-provenance-review`.

This is a documentation-and-governance scope gate only. It creates only this document and `backend/tests/Feature/Milestone3ghReportsScopeGateDataProvenanceReviewTest.php`. No Reports runtime route, controller, request, resource, service, permission seeding, migration, frontend integration, operational query, export, cache, database mutation, or browser mutation is authorized.

## Repository inspection

- `app/page.tsx` exposes many prototype report labels, including member, finance, Fellowship, Events, Activities, celebrations, blood donor, and document reports.
- The truthful operational state says `Operational reports are not connected to a verified source yet.`
- Prototype report cards, arrays, dashboard totals, and labels are not approved data sources.
- Members are backend-connected, club-scoped, permission-protected, regression-tested, and stored in `members`.
- Events & Meetings are backend-connected through `ClubEvent`/`club_events`, club-scoped, role-filtered, lifecycle-tested, and browser-verified.
- Projects & Activities are backend-connected through `ClubProject`/`club_projects`, club-scoped, role-filtered, lifecycle-tested, and browser-verified.
- Reminders are backend-connected through `ClubReminder`/`club_reminders`, club-scoped, role-filtered, lifecycle-tested, and browser-verified through 3GG.
- Finance has verified ledger, payment, receipt, and existing club-finance read APIs. However, `ClubFinanceReportService` casts database amounts to PHP `float` and sums them through floating-point paths. Its controller authorizes through hardcoded role slugs rather than a dedicated Reports finance permission.
- No verified Fellowship persistence model, celebration entity, club-document aggregate, attendance source, district/LCIF submission source, or generalized Reports backend exists.
- Existing dashboard values are not reusable report provenance merely because they are displayed.

## Architecture

Approved first-release architecture:

- fixed report catalog in backend code;
- computed on demand from verified source tables/services;
- backend-only summaries, filters, ordering, pagination, and date boundaries;
- no persisted `reports` or `generated_reports` table;
- no saved snapshot, lifecycle, delete flow, arbitrary SQL, formulas, custom columns, asynchronous generation, or persistent result cache.

Stable report keys, not labels, identify reports.

## Data-provenance matrix

| Key | Title | Purpose | Verified source | Permission | Sensitive fields excluded | Filters | Summary/rows | Inclusion/exclusion and date basis | Decision |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `membership-summary` | Membership Summary | Aggregate club membership status counts | `members` table and `Member` model | `reports.view` | names, phone, email, address, DOB, blood group, identity and private profile fields | membership status only | aggregate counts; no rows | route club only; count actual Member records by backend status; do not count standalone role-test User accounts | Approved |
| `events-register` | Events & Meetings Register | Management register of verified event/meeting records | `club_events`, `ClubEvent`, Events read conventions | `reports.view` | cancellation reason, actor IDs, audit metadata, private descriptions | date range, status, type, search, page, per-page | exact row count and stable-ID rows | route club only; include approved statuses; club-timezone inclusive date boundaries; UTC timestamps | Approved |
| `projects-register` | Projects & Activities Register | Management register of verified project/activity records | `club_projects`, `ClubProject`, Projects read conventions | `reports.view` | cancellation reason, actor IDs, audit metadata, beneficiary identities | date range, status, record type, service category, search, page, per-page | exact counts and stable-ID rows | route club only; approved lifecycle history; date-only planned/actual basis stated in response | Approved |
| `reminders-register` | Reminders Register | Management register of verified reminder records | `club_reminders`, `ClubReminder`, Reminders read conventions | `reports.view` | cancellation reason, actor IDs, audit metadata | due-date range, status, priority, all-day/timed, search, page, per-page | exact counts, computed overdue, stable-ID rows | route club only; all-day uses club date; timed uses UTC converted from inclusive club boundaries | Approved |
| `collections-summary` | Collections Summary | Financial collection totals | ledger/payment/receipt tables and existing finance service | `reports.finance` | bank, UPI, payment credentials, private notes | date range and approved status | decimal totals only if repaired | existing implementation casts/sums with `float`; new permission contract absent | Deferred |

## Financial decision

`collections-summary` is not approved for this release. Although source finance records and existing finance APIs are real, the current report service uses floating-point aggregation and a role-slug authorization path. A financially sensitive Reports catalog must not copy or approximate that behavior.

3GH does not alter the existing finance feature. A future focused finance-report provenance and decimal-safety milestone may approve `collections-summary` after exact monetary handling, status/date inclusion, reversal/refund rules, and `reports.finance` authorization are proven. The Reports catalog must not advertise it or return unsupported zero totals.

## Permissions

Approved future permission slugs:

- `reports.view`
- `reports.finance` reserved for a future approved financial report; it is not sufficient to expose a deferred key.

| Role | reports.view | reports.finance |
| --- | ---: | ---: |
| President/Admin (`president-admin`) | Yes | Reserved/Yes when a financial key is approved |
| Secretary (`secretary`) | Yes | No in this release |
| Treasurer (`treasurer`) | Yes | Reserved/Yes when a financial key is approved |
| Normal Member (`member`) | No | No |

Reports is a management function. Normal Member receives no Reports API or menu access even when an individual source record might be member-visible. Permission never overrides active membership, route club scope, source privacy, or report-key authorization.

## Privacy and accuracy rules

Return only fields required by each approved purpose. Exclude residential address, phone, email, DOB, blood group, identity documents, bank/UPI details, credentials, private notes, cancellation reasons, and audit payload internals.

All totals and row counts must reconcile exactly against synthetic fixtures. Cross-club records are excluded before filtering. Unknown and unsupported report keys fail closed. Successful empty reports may return meaningful zero counts only for an approved verified source; unsupported reports never return fake zero output.

## Explicit non-goals

Deferred: saved reports, scheduled reports, email/WhatsApp delivery, PDF/spreadsheet/CSV export, background generation, snapshots, arbitrary columns/formulas/SQL, dashboards, charts, public reports, district or LCIF submission, multi-club consolidation, cross-year comparison, AI narrative, attendance reports, Fellowship reports, celebration reports, document reports, donor reports, finance reports, and deletion.

## Implementation sequence

- 3GI finalizes authorization, API, filters, response, privacy, date rules, and exact 3GJ/3GK boundaries.
- 3GJ implements catalog/shared query infrastructure only.
- 3GK implements the four approved non-financial reports only.
- 3GL integrates the frontend after isolated synthetic API totals reconcile.
- 3GM verifies accuracy, roles, privacy, failures, and responsive behavior.
- 3GN performs closure review.

## Governance and verdict

Before `Add reports scope gate and provenance review` exists, the governance test verifies the exact 3GG base and two authorized files. After commit, it verifies the unique historical commit and historical scope through local Git. It does not require later frontend checkouts to remain disconnected.

GO - 3GI may open after 3GH validation, separate commit/push, matching remote hash, clean tracking, and clean working tree.
