# Milestone 3FM - Events & Meetings Backend Activation Scope Gate

## 1. Milestone identity

- Base branch: milestone-3fl-operational-data-truth-broken-control-remediation.
- Verified base commit: 67687dcf4bc24f8e9035ab63671b6207a4d11676.
- Scope-gate branch: milestone-3fm-events-meetings-backend-activation-scope-gate.
- Authorized files: backend/docs/milestone_3fm_events_meetings_backend_activation_scope_gate.md and backend/tests/Feature/Milestone3fmEventsMeetingsBackendActivationScopeGateTest.php.

Milestone 3FM is a documentation-and-governance scope gate only. It does not authorize or implement Events & Meetings runtime code, routes, controllers, requests, resources, services, policies, models, migrations, seed changes, frontend changes, or operational data. 3FM must not start 3FN or any implementation milestone.

Decision: GO for Milestone 3FN authorization and contract approval only. No runtime implementation is authorized in 3FM.

## 2. Scope-gate purpose and smallest safe slice

This gate fixes the architecture, authorization, data, lifecycle, API, integrity, test, rollout, and rollback boundaries before implementation. The smallest safe first backend release is one club-scoped typed record supporting list, detail, create, draft update, publish, cancel, and complete. Attendance, reminders, minutes, files, recurrence, public publication, and integrations are excluded.

## 3. Current frontend state

Read-only inspection of app/page.tsx found:

- EventMeeting remains a frontend type with title, date, optional time, venue, type, section, visibility, and status.
- eventsMeetings remains a legacy hardcoded array, but verifiedOperationalSourceConnected("events") is false and EventsMeetings returns before the array or its controls render.
- Menu entries are Upcoming Events, Upcoming Meetings, Past Events, and Meeting Notices. President/Admin and Secretary see Operations; Treasurer sees Club Visibility; Normal Member has direct Events & Meetings access.
- View state retains events-meetings, event-meeting-detail, eventMeetingSection, and selectedEventMeeting. Return context returns to the selected list or Operations context.
- Unreachable remnants include title-based keys, View details, Create Meeting Notice, Send Meeting Reminder, and attendance/report labels. They are not an operational source or approved contract.
- Previously displayed fields were title, date, time, venue, type, visibility/audience, section, and status. Prototype coupling exists with reminders, attendance reports, announcements, documents, Fellowship, and activities.
- Browser inspection of localhost confirmed the only reachable workspace control is return navigation and the accepted wording remains:

  The Events & Meetings workflow is not connected to a verified operational source yet.

  Real events and meeting records will appear here only after a verified source is connected.

The 3FL fail-closed frontend remains authoritative through 3FM, 3FN, 3FO, and 3FP. Hardcoded fallback must never return.

## 4. Repository inspection findings

### Existing backend capability inventory

Searches under backend/app, backend/routes, backend/database, and backend/tests/Feature found no Event, Meeting, or Calendar model; no events/meetings table or migration; no route, controller, request, resource, service, policy, permission, factory, or operational feature test; and no operational schedule or attendance source. Seeder/test mentions are descriptive or exclusions only. Current backend capability is NOT CONNECTED.

Inspected evidence includes:

- backend/routes/api.php
- backend/database/seeders/DefaultRolesAndPermissionsSeeder.php
- backend/app/Services/Access/ClubPermissionService.php
- backend/app/Http/Middleware/EnsureClubPermission.php
- backend/app/Models/AuditLog.php
- backend/database/migrations/2026_06_06_213536_create_audit_logs_table.php
- backend/database/migrations/2026_06_06_213532_create_clubs_table.php
- backend/app/Http/Controllers/Api/V1/Members/MemberProfileDocumentController.php
- backend/app/Services/Members/MemberProfileDocumentWriteService.php
- backend/app/Services/Finance/PaymentRecordingService.php
- backend/tests/Feature/Milestone3dbMemberProfileDocumentsCreateUpdateNarrowImplementationTest.php
- app/page.tsx

### Existing authorization inventory

Existing role slugs are president-admin, secretary, treasurer, and member. Existing permissions include club.view, club.manage, members.view, members.manage, payments.record, and audit_logs.view. No event permission exists. Protected routes use Sanctum plus club.permission middleware. ClubPermissionService requires active membership and filters by club_id. Controllers explicitly compare nested records to the route club and return 404 for ownership mismatch.

3FN should approve these permission slugs, matching repository naming:

- events.view: all four roles, always filtered by status and visibility.
- events.manage: President/Admin and Secretary only.

Existing audit_logs.view does not confer Events management. Event audit detail requires both events.manage and audit_logs.view, preventing Treasurer from gaining management detail merely through the foundation audit permission.

### Similar-module patterns to reuse

- Numeric stable IDs, club_id foreign keys, compound club indexes, actor foreign keys, and restrictive deletion from Payments and Member Profile Documents.
- Sanctum, permission middleware, active membership, and explicit nested ownership checks from routes/api.php, EnsureClubPermission, and MemberProfileDocumentController.
- Laravel Form Requests and explicit Resource shaping; never blind model serialization.
- Service boundaries and DB transactions from PaymentRecordingService.
- AuditLog fields club_id, user_id, action, polymorphic target, metadata, and timestamps. The audit foundation exists, but automatic domain audit writing is not yet an established service and must be implemented explicitly in 3FP.
- Synthetic RefreshDatabase feature tests, cross-club denial, ownership checks, and route assertions from the Member Profile Documents lane.
- clubs.timezone as the display timezone source; its migration default is Asia/Kolkata.

## 5. Unified versus separate entity analysis

### Option A - unified Events & Meetings entity (selected)

Use one ClubEvent model/table with controlled type values event and meeting. The frontend already treats both as a typed list/detail surface. Both need identical club ownership, schedules, venue, visibility, lifecycle, actor, audit, filters, and stable identity. One surface minimizes duplicated authorization and calendar/list logic.

Trade-off: meeting minutes, attendance, quorum, event guests, and service metrics will differ later. Those should become separately authorized related records, not speculative nullable first-release columns.

### Option B - separate Event and Meeting entities (rejected for first release)

Separate entities could isolate future meeting minutes/attendance from event publication and guests. None of those capabilities currently exists and all are excluded. Separate models would duplicate routes, permissions, lifecycle, timezone, filters, audit, and tests, increasing direct-object authorization risk without current evidence.

### Final architecture decision

Select Option A. Use App\Models\ClubEvent, table club_events, and /events endpoints while retaining “Events & Meetings” as the product label. Future meeting-specific records may reference club_events.id or be split by an explicit migration. Identity must never split or depend on title/date.

## 6. Proposed minimum data model

No migration is authorized in 3FM.

| Field | Type / nullability | Rule and purpose | Release |
|---|---|---|---|
| id | big integer PK | stable backend identity | now |
| club_id | required FK, restrict delete | owning club and query boundary | now |
| type | required controlled string | event or meeting | now |
| title | required string(200) | trimmed, 1-200 | now |
| description | nullable text | maximum 5000 | now |
| starts_at | required UTC timestamp | offset-bearing ISO-8601 input | now |
| ends_at | nullable UTC timestamp | at or after start | now |
| all_day | boolean, default false | preserves date-only intent | now |
| venue | nullable string(255) | physical or concise virtual venue label | now |
| status | controlled string, default draft | draft, published, completed, cancelled | now |
| visibility | required controlled string | members or management | now |
| published_at | nullable UTC timestamp | server-set on publish | now |
| cancelled_at | nullable UTC timestamp | server-set on cancel | now |
| cancellation_reason | conditionally required text | 1-1000 on cancel | now |
| completed_at | nullable UTC timestamp | server-set on complete | now |
| created_by_user_id | required FK, restrict delete | stable creator | now |
| updated_by_user_id | required FK, restrict delete | last successful actor | now |
| lock_version | unsigned integer, default 1 | optimistic concurrency | now |
| created_at, updated_at | UTC timestamps | repository convention | now |

Include all_day, lifecycle timestamps/reason, and lock_version now because they make date intent, lifecycle, and integrity explicit. Defer online link, contact person, internal notes, per-record timezone, recurrence, soft deletion, and physical deletion. A link field requires separate privacy/security approval. Club timezone is sufficient.

Indexes: (club_id, starts_at), (club_id, status, starts_at), (club_id, type, starts_at), and (club_id, visibility, status). Database constraints should restrict type/status/visibility where supported; application validation remains mandatory.

## 7. Date, time, and timezone rule

- Store starts_at, ends_at, lifecycle timestamps, and Laravel timestamps as UTC instants.
- API input/output is ISO-8601/RFC 3339 with explicit offset; normalized output uses UTC Z.
- Display converts UTC to the owning clubs.timezone. Never derive the authoritative timezone from browser locale.
- For all_day=true, clients submit local club dates. The API returns explicit local start_date and optional end_date plus canonical UTC boundaries. End date is inclusive.
- Reject end before start with 422. Equal timed values are permitted as point-in-time records; all-day end cannot precede start date.
- Use valid IANA timezones. Ambiguous/nonexistent DST local times require an explicit offset or are rejected, even though Asia/Kolkata currently has no DST.
- Changing club timezone does not rewrite stored UTC instants.

## 8. Controlled lifecycle

Permitted transitions:

- Draft -> Published
- Draft -> Cancelled
- Published -> Completed
- Published -> Cancelled

Published cannot return to Draft. Completed and Cancelled are terminal and immutable. Generic create/update never accepts status; create defaults to Draft and explicit transition actions own status.

Draft permits type, title, description, schedule, all-day flag, venue, and visibility edits. Published permits management correction of title, description, schedule, venue, and visibility only with lock_version, transaction, and before/after audit. Type, club, creator, status, and published_at are immutable. Completed/Cancelled cannot be edited.

Cancellation requires a nonblank reason and records actor/time. Completion is allowed only from Published. 3FN must decide whether completion before starts_at is always forbidden or supports a narrow override. Delete and soft delete are excluded; incorrect records are cancelled.

Repeated or stale transitions return 409 and create no audit. A repeated request may return the original representation only when an approved idempotency key identifies the same successful action.

## 9. Authorization matrix

Backend enforcement is mandatory; menu hiding is not authorization.

| Action | President/Admin | Secretary | Treasurer | Normal Member |
|---|---|---|---|---|
| List own-club records | all statuses/visibility | all statuses/visibility | published + members only | published + members only |
| Read Draft | yes | yes | no | no |
| Read Published | yes | yes | own-club members only | own-club members only |
| Read Completed | yes | yes | no in first release | no in first release |
| Read Cancelled | yes | yes | no | no |
| Create | yes | yes | no | no |
| Edit Draft | yes | yes | no | no |
| Edit Published allowed fields | yes | yes | no | no |
| Publish | yes | yes | no | no |
| Cancel | yes | yes | no | no |
| Complete | yes | yes | no | no |
| Delete | no | no | no | no |
| View event audit information | with events.manage + audit_logs.view | with both permissions | no | no |

Read requires events.view; management requires events.manage. Unauthenticated is 401. Missing action permission for a known club is 403. Hidden Draft/management-only, nonexistent, or wrong-club object IDs return 404 to prevent enumeration.

## 10. Club scoping and stable identity

Every record belongs to exactly one club. Middleware authorizes the route club through active membership. Lists apply club_id before filters. Detail/update/transition uses scoped route binding plus an explicit service/controller assertion that event.club_id equals club.id.

All UI keys, selection, detail, update, and transitions use the stable backend id. Title, date, list index, or array position are forbidden identities. Client club_id, actor IDs, status timestamps, and audit identity are rejected; route/authentication supply them. Cross-club ID tampering returns 404 and leaks no record.

Audit retains actor user_id, club_id, ClubEvent type, and target id.

## 11. Proposed API contract

All routes are under /api/v1, Sanctum-authenticated, JSON, and not implemented in 3FM.

| Method and path | Purpose / roles | Request | Success |
|---|---|---|---|
| GET /clubs/{club}/events | authorized list, all roles with filtering | query contract below | 200 data + meta |
| GET /clubs/{club}/events/{event} | exact authorized detail | none | 200 data |
| POST /clubs/{club}/events | President/Admin, Secretary | create fields only | 201 Draft data |
| PATCH /clubs/{club}/events/{event} | President/Admin, Secretary | allowed fields + lock_version | 200 data |
| POST /clubs/{club}/events/{event}/publish | President/Admin, Secretary | lock_version; optional idempotency header | 200 Published data |
| POST /clubs/{club}/events/{event}/cancel | President/Admin, Secretary | lock_version + cancellation_reason | 200 Cancelled data |
| POST /clubs/{club}/events/{event}/complete | President/Admin, Secretary | lock_version | 200 Completed data |

Use REST for resource operations and explicit lifecycle-transition routes. Never expose PATCH status or DELETE.

Management resource fields may include actor IDs and lifecycle timestamps. Public/read-only shapes omit actor IDs, cancellation reason, and audit metadata. List meta includes current_page, per_page, total, last_page, and applied filters.

Statuses: 200 reads/updates/transitions; 201 create; 401 unauthenticated; 403 permission failure; 404 wrong-club/nonexistent/undisclosed record; 409 stale or conflicting transition; 422 Laravel field validation. Unexpected failures return 500 without record leakage or simulated success.

## 12. List and filtering contract

First release supports:

- scope=upcoming|past|all, default upcoming; all is management-only.
- optional type=event|meeting.
- optional status for management; non-management is forced to published.
- optional range-limited from/to ISO-8601 boundaries.
- optional trimmed q title search, maximum 100.
- page and per_page 1-50, default 20.
- sort=starts_at and direction=asc|desc only.
- Upcoming default: starts_at asc, id asc. Past: starts_at desc, id desc.

Role and visibility constraints apply before client filters. No calendar engine, recurrence expansion, arbitrary sort, attendance filter, or report aggregate is included.

## 13. Validation strategy

Use Laravel Form Requests for request shape and coarse authorization plus service/domain transition guards inside the transaction.

- Reject unknown/client-owned club, actor, status, timestamp, and audit fields.
- Validate title, description, venue lengths; type event|meeting; visibility members|management.
- Require offset-bearing schedule and valid club timezone; reject end-before-start and inconsistent all-day dates.
- Create is always Draft. Update cannot mutate lifecycle or immutable fields.
- Cancellation reason is required/max 1000 on cancel.
- lock_version is required and must match for update/transition.
- Actor needs active own-club membership and action permission.
- Malformed IDs fail safely as 404.
- Do not infer duplicates by title/date. Optional idempotency keys may deduplicate exact create/transition requests.

## 14. Data-truth state requirements

Later frontend integration distinguishes loading, records, genuine empty, failure, unauthenticated, forbidden, and rollout not-connected. It must not use the legacy array, convert failure to empty, convert unknown to zero, invent Published, expose controls without verified APIs, simulate persistence, or report success before backend 2xx. Failed refresh must not present stale data as current.

The 3FL state remains until 3FO read APIs pass tests and an authorized local read is observed. Management controls remain absent until 3FP transitions and authorization tests pass.

## 15. Audit and accountability

3FP must create one AuditLog in the same transaction as each successful events.created, events.updated, events.published, events.cancelled, and events.completed action. Deletion has no action.

Audit evidence includes actor user_id, club_id, ClubEvent target type/id, action, relevant prior/new state including lock version, and timestamp. Avoid unnecessary private descriptions. Rejected authorization, validation, stale, repeated, and cross-club requests create no success audit. Tests prove mutation and audit both commit or both roll back.

## 16. Concurrency and integrity

- Use a DB transaction for mutation plus audit.
- Require lock_version and lock the target row before rechecking club, version, and lifecycle.
- Increment version exactly once per success.
- Stale/conflicting updates return 409 with no partial state or audit.
- Do not add title/date uniqueness; legitimate similar records are possible.
- If 3FN approves idempotency keys, scope uniqueness by club, actor, action, and key.
- Foreign keys and controlled values protect club, actors, type, status, and visibility.
- Lifecycle timestamps are server-set only.

## 17. Explicit non-goals

| Deferred feature | Reason / dependency | Earliest lane |
|---|---|---|
| Attendance, RSVP, guests | identity/privacy/count model | post-3FS scope gate |
| Meeting minutes | document privacy/version/approval | post-3FS Documents gate |
| Recurring events | recurrence/timezone exceptions | calendar gate |
| Reminders, email, WhatsApp, push | consent/provider/retry/audit | Notifications lane |
| Attachments/upload | storage/malware/access/retention | Documents lane |
| Calendar sync/Google Calendar | OAuth/revocation/conflicts | integration lane |
| Public website publication | public privacy/moderation | publication lane |
| Service-project linkage | Projects backend absent | after Projects activation |
| Automated reports | requires verified operational data | after 3FS |
| Collection/payment linkage | separate accounting boundary | finance scope gate |
| Committee visibility | committee authorization unresolved | governance lane |
| Online link/contact/notes | privacy exposure not approved | schema extension |
| Delete/soft delete | cancellation sufficient; no retention policy | retention gate |

Excluded fields/endpoints cannot silently enter 3FO/3FP.

## 18. Testing strategy

Database tests: schema/rollback, foreign keys, indexes, ownership, type/status/visibility, UTC/all-day/date integrity, actors, version, and existing-module safety.

Authorization tests: President/Admin and Secretary management; Treasurer and Member published member-visible read-only; Draft/management denial; inactive/unauthenticated; cross-club and direct-object denial; audit visibility.

API tests: list, detail, create, update, publish, cancel, complete, empty, filters, pagination/order, malformed IDs, 401/403/404/409/422, resource exposure, and absence of DELETE/status patch/excluded routes.

Lifecycle/integrity tests: all valid/invalid transitions, terminal states, reason, published edits, repeated/stale/conflicting transitions, idempotency behavior, transaction rollback, and no rejected audit.

Audit tests: exact actor, club, target, action, before/after, one entry per success, no rejected duplicate, atomicity.

Later frontend tests: loading, real records, empty, failure, unauthorized/forbidden, not-connected rollout, stable-ID navigation, return context, role switch, responsive behavior, privacy, no dummy fallback, and backend-confirmed success.

3FM implements only a deterministic governance test. It creates no records, runs no migrations, and calls no service.

## 19. Proposed 3FN-3FS sequence

1. 3FN - Events & Meetings Backend Authorization and Contract Approval: approve unified ClubEvent, permissions, role matrix, resources, lifecycle, API, all-day and idempotency decisions.
2. 3FO - Database and Read API Foundation: migration/model/relationships, permissions/policy foundation, list/detail APIs/resources, read and club/privacy tests. Frontend stays not connected.
3. 3FP - Create, Update and Lifecycle Backend Workflow: Form Requests, service, explicit transitions, audit, concurrency/idempotency, management tests.
4. 3FQ - Frontend Integration: verified loading/list/detail, stable-ID navigation, truthful states, authorized controls, no legacy fallback.
5. 3FR - Human/Browser Verification: every role, direct access, navigation, privacy, responsive/data-truth/mutation safety.
6. 3FS - Closure Review: regression, remote evidence, rollback readiness, deferred items.

Retain this sequence; repository evidence does not justify merging or skipping gates. 3FN may open but must approve bounded open questions before 3FO.

## 20. Risk register

| Risk | Likelihood / impact | Mitigation | Owner |
|---|---|---|---|
| Cross-club exposure | medium / critical | scoped middleware/query/ownership/404 tests | 3FO |
| Unauthorized Draft access | medium / high | events permissions and visibility filter | 3FO |
| Unauthorized publish/cancel | medium / critical | events.manage + transition service | 3FP |
| Wrong stable identity | medium / high | backend ID only | 3FQ |
| Timezone/date errors | medium / high | UTC + IANA timezone + offset validation | 3FO |
| Invalid lifecycle | medium / high | matrix, transaction, terminal guards | 3FP |
| Dummy fallback returns | medium / high | keep 3FL gate, integration tests | 3FQ/3FR |
| False frontend success | medium / high | success only after 2xx | 3FQ |
| Reminder/attendance scope creep | high / medium | schema/route exclusion guards | 3FN-3FS |
| Duplicate actions | medium / medium | row lock, version, optional idempotency | 3FP |
| Stale updates | medium / high | lock_version and 409 | 3FP |
| Privacy leakage | medium / high | explicit resource shapes | 3FO |
| Audit gaps | medium / high | transactional AuditLog tests | 3FP |
| Fail-closed regression | medium / high | staged activation and truthful rollback | 3FQ-3FS |

## 21. Rollout and rollback strategy

- Verify migration/read backend before frontend activation, including authorized list/detail, empty, failure, and cross-club behavior.
- Keep current not-connected frontend until 3FO is proven. Never use dummy fallback.
- Migration rollback removes only the new club_events table and explicitly approved permission changes when safe. Production planning must preserve/export operational records before destructive rollback.
- Read API can be disabled at route/feature-flag boundary; frontend returns to truthful not-connected.
- Introduce lifecycle actions only after 3FP authorization, integrity, and audit tests.
- Activate each frontend management control only after its endpoint is verified.
- On integration regression, disable integration and restore exact 3FL unavailable wording, never legacy arrays or simulated success.

## 22. Acceptance criteria

3FM closes only when this document and its governance test are the only changed files; no runtime/frontend/generated changes exist; architecture, data, timezone, lifecycle, role policy, club scope, stable identity, API, filters, validation, data truth, audit, concurrency, exclusions, tests, sequence, risks, rollback, and bounded questions are explicit; targeted test and diff checks pass; and no major decision is implicit.

## 23. Open questions for 3FN

1. Confirm permission slugs events.view and events.manage and seeded assignments.
2. Confirm visibility values members and management; do not reuse ambiguous Board Only/PST without an audience model.
3. Confirm all-day start_date/end_date plus canonical UTC representation and inclusive end.
4. Decide whether create/transitions require idempotency keys or lock-version conflict handling is sufficient.
5. Confirm whether completion before starts_at is forbidden or has a narrow override.

These are bounded contract approvals, not unresolved architecture. Rejected choices require contract revision before 3FO.

## 24. Final scope-gate decision

GO - Milestone 3FN may open for Events & Meetings Backend Authorization and Contract Approval.

This verdict authorizes 3FN only. It does not authorize Events & Meetings runtime implementation, migration, permissions/seeder changes, routes, operational data, or frontend activation in 3FM. The current 3FL fail-closed frontend remains correct until verified later rollout.
