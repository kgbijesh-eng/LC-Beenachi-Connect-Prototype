# Milestone 3FZ — Projects & Activities Closure Review

## 1. Milestone identity

Documentation-only closure review on `milestone-3fz-projects-activities-closure-review`.

## 2. Base 3FY commit

`1f6c4ffddb50018ea5d04b8a92541c8d5f2a7ce9`

## 3. Full milestone chain

| Milestone | Branch | Verified hash |
| --- | --- | --- |
| 3FT | `milestone-3ft-projects-activities-backend-activation-scope-gate` | `a18c77616ff4ac806920e75474c4f1765e970e53` |
| 3FU | `milestone-3fu-projects-activities-authorization-contract-approval` | `f584592e5c0873d4ab94a8b7a76167b0bf7ccef0` |
| 3FV | `milestone-3fv-projects-activities-database-read-api-foundation` | `c4c557a956bb082d2dbb842974bc5390a4a91426` |
| 3FW | `milestone-3fw-projects-activities-lifecycle-backend-workflow` | `096eb072b66376cd2c05540c8f39aa9df2477cb0` |
| 3FX | `milestone-3fx-projects-activities-frontend-integration` | `6b3a870f7b008bb63cac10a50f8e5b3e2990b0f1` |
| 3FY | `milestone-3fy-projects-activities-browser-role-verification` | `1f6c4ffddb50018ea5d04b8a92541c8d5f2a7ce9` |

## 4. Final aggregate model

One club-scoped `ClubProject` aggregate represents both first-release record types without inventing a parent/child relationship.

## 5. Final permission model

`projects.view` is assigned to President/Admin, Secretary, Treasurer and Member. `projects.manage` is assigned only to President/Admin and Secretary. Permission never overrides authentication, active membership, club scope, lifecycle or visibility.

## 6. Final role matrix

President/Admin and Secretary read and manage same-club records. Treasurer and Member are read-only. Unauthenticated, inactive and another-club access fail closed. Committee-chair management remains deferred because no stable permission-bearing assignment model exists.

## 7. Final schema

Stable numeric ID, club ID, `record_type`, descriptive fields, `visibility`, `status`, date-only planned/actual fields, location, optional stable lead-member ID, non-sensitive beneficiary counts, impact summary, cancellation reason, lifecycle timestamps/actors, audit actors, `lock_version`, and timestamps.

## 8. Final API routes

`GET/POST /api/v1/clubs/{club}/projects`, `GET/PATCH /api/v1/clubs/{club}/projects/{project}`, and explicit POST routes for `plan`, `start`, `complete`, and `cancel`. No DELETE route exists.

## 9. Final request contracts

Create always produces Draft and cannot accept club, status, lifecycle or audit ownership. Generic update requires `lock_version` and cannot mutate status or record type. Lifecycle requests are explicit and state-bound.

## 10. Final response contract

Stable ID, record type, permitted descriptive/temporal/impact data and lifecycle timestamps are returned. Management-only visibility, cancellation reason, actor IDs, lock version and audit metadata are omitted from read-only responses.

## 11. Final lifecycle

Draft→Planned; Planned→Active or Completed; Active→Completed; Draft/Planned/Active→Cancelled. Completed and Cancelled are terminal. No backward transitions or deletion.

## 12. Final visibility matrix

Draft is management-only. Members-visible Planned, Active and Completed records are readable by active same-club read roles. Management visibility remains management-only in every state.

## 13. Cancelled-history behaviour

Cancelled Draft remains management-only. A members-visible cancelled record is member-readable only when `planned_at` proves it previously reached a visible state. Cancellation reason remains management-only.

## 14. Audit result

Create, update and every lifecycle transition write atomic before/after audit evidence with the stable aggregate ID and actor.

## 15. Concurrency result

Every update/transition uses row locking and optimistic `lock_version`; stale requests receive deterministic 409 without duplicate audit or partial mutation.

## 16. Stable-ID result

Routes, frontend keys, list/detail navigation, edit and lifecycle actions use numeric backend IDs rather than titles or array position.

## 17. Duplicate-title result

Project `#10` and Activity `#11` shared the same title and remained independently addressable throughout browser verification.

## 18. Cross-club result

Another-club membership cannot access club 1; a cross-club stable ID fails with 404 in the club 1 route family.

## 19. Frontend result

Backend list/detail, approved filters, pagination, create/edit and lifecycle controls are connected. President/Secretary manage; Treasurer/Member read only. Loading, empty, error, forbidden, conflict and backend-confirmed success are truthful. No Projects dummy fallback or local-only save exists.

## 20. Browser-role result

President, Secretary, Treasurer and Member surfaces were exercised in the app. Unauthenticated, inactive and another-club boundaries were verified against the same isolated backend. No unresolved in-scope defect remains.

## 21. Navigation result

List/detail/Back, stable-ID selection, filters, pagination, duplicate titles, role switching and privileged-state clearing pass. Member has a direct read-only Projects entry.

## 22. Data-truth result

Every displayed operational record comes from the backend. Outage testing clears stale rows; connection and HTTP failures do not produce false success. Mutations refresh backend state.

## 23. Responsive result

1440px, 900px and 390px passed with equal document client/scroll widths, no horizontal overflow, wrapping content and reachable controls.

## 24. Genuine database protection

The genuine PostgreSQL database was inspected read-only. It retained 50 member rows, had no `club_projects` table and never contained the isolated sentinel. All mutations used temporary SQLite outside the repository.

## 25. Deferred features

Budgets, expenses, payments, fundraising, attendance, volunteer rosters, beneficiary identities, media, attachments, documents, tasks, comments, extra approvals, reminders, notifications, messaging, recurrence, public publication, reports, district/LCIF reporting, service-hour aggregation, dashboards, Events linkage and deletion remain deferred.

## 26. Known limitations

The frontend uses local development test credentials and a fixed local API base. Committee-specific management is unavailable. Reports & Tools retains unrelated prototype report cards outside the connected Projects operational screen.

## 27. Rollback position

Each milestone is isolated on a separate branch and commit. Rollback is the verified 3FS base `2ea81ac78790caaa81a926e3f568637e94613ae7`, or any verified milestone boundary above, without history rewriting.

## 28. Closure-critical findings

None. Authorization, privacy, cross-club isolation, stable identity, concurrency, data truth, build and browser gates have no unresolved closure-critical defect.

## 29. Final closure decision

**PASS — Projects & Activities is backend-backed, role-safe, club-scoped, browser-verified, genuine-data-safe and formally closed.**
