# Milestone 3IIR — Announcements Database and Read-API Scope Gate

## 1. Identity, status, and authority

- Source branch: `milestone-3iiq-announcements-domain-authorization-contract`
- Exact baseline: `4815f9f76c16c33151647c28d36a4abfdeec474f`
- Scope-gate branch: `milestone-3iir-announcements-database-read-api-scope-gate`
- Scope: documentation only

All 25 business-owner decisions are **Approved with qualification**. AN-001 through AN-025 are accepted. The accepted decision register supersedes historical Pending wording in the original domain-contract prose; this scope gate does not rewrite that historical file.

This document defines the smallest safe future database and read-only API implementation. It does not implement or authorize schema, permissions, APIs, tests, frontend behavior, authoring, approval, publication, scheduling, withdrawal, replacement, archival, deletion, notifications, attachments, accounting, merge, or deployment.

## 2. Audit findings

The read-only audit covered the announcement contract and decision register; club, Events, Projects, and Reminders migrations/models/read APIs; permission middleware and seeding; active-membership, optimistic-locking, audit, pagination, resource, timezone, synthetic SQLite, and cross-club test conventions; and frontend fabricated-data suppression.

Findings:

- No authoritative announcement migration, model, permission, route, controller, request, resource, service, factory, or runtime test exists.
- Existing club-scoped GET APIs provide reusable engineering patterns, but no announcement runtime authority.
- Schema plus collection/detail reads are feasible without any mutation workflow.
- No authoritative general officer-audience policy exists. Names, free-text positions, frontend labels, and hard-coded LC Beenachi identities are not authority.
- `clubs.timezone` is the per-club timezone source. Announcement runtime must not globally hard-code `Asia/Kolkata`.
- Legacy static announcement rows remain unreachable behind the truthful not-connected frontend boundary.
- No operational records were accessed.

## 3. Smallest future implementation boundary

A later separately authorized implementation may add only:

- one additive `club_announcements` table;
- one `ClubAnnouncement` model with controlled constants and read-time integrity methods;
- two read permissions and default assignments;
- one list Form Request;
- one explicit API Resource;
- one club-scoped read service;
- one controller with collection and detail actions;
- exactly two GET routes;
- a synthetic factory/test helper;
- focused SQLite `:memory:` tests.

It must add no write or lifecycle route, write service, audit write, scheduler, notification delivery, receipt, attachment, external-link navigation, frontend integration, seed data, or operational record.

## 4. Exact proposed schema

Table: `club_announcements`

| Field | Type | Nullable | Contract |
| --- | --- | ---: | --- |
| `id` | unsigned big integer | No | Primary key. |
| `club_id` | foreign ID | No | References `clubs.id`; `restrictOnDelete()`. |
| `title` | string(160) | No | Trimmed, non-blank plain Unicode; 1–160 Unicode characters. |
| `body` | text | No | Trimmed, non-blank plain Unicode; 1–5,000 Unicode characters. |
| `priority` | string(20) | No | Default `normal`; controlled constant. |
| `audience` | string(30) | No | Controlled semantic audience. |
| `status` | string(20) | No | Default `draft`; explicit stored lifecycle state. |
| `scheduled_publish_at` | timestamp | Yes | Metadata only; never proves publication. |
| `published_at` | timestamp | Yes | Authoritative UTC publication instant when Published/Withdrawn. |
| `expires_at` | timestamp | Yes | Optional UTC instant; effective expiry is derived. |
| `withdrawn_at` | timestamp | Yes | Future explicit withdrawal instant. |
| `withdrawal_reason` | text | Yes | Required and non-blank for Withdrawn; management-only. |
| `archived_at` | timestamp | Yes | Future explicit archive instant. |
| `replaces_announcement_id` | self foreign ID | Yes | References `club_announcements.id`; `restrictOnDelete()`. |
| `published_by_user_id` | foreign ID | Yes | References `users.id`; `restrictOnDelete()`. |
| `published_by_role` | string(30) | Yes | Controlled publication-time role snapshot. |
| `created_by_user_id` | foreign ID | **No** | Required authenticated creating actor; references `users.id`; `restrictOnDelete()`. |
| `updated_by_user_id` | foreign ID | Yes | References `users.id`; `restrictOnDelete()`; may remain null until later mutation. |
| `withdrawn_by_user_id` | foreign ID | Yes | References `users.id`; `restrictOnDelete()`. |
| `archived_by_user_id` | foreign ID | Yes | References `users.id`; `restrictOnDelete()`. |
| `lock_version` | unsigned integer | No | Default `1`; future optimistic concurrency foundation only. |
| `created_at`, `updated_at` | Laravel timestamps | Yes | Repository convention. |

No anonymous or system-inferred announcement creation is authorized. Every future row must identify an authenticated creating actor.

Controlled constants:

- Status: `draft`, `published`, `withdrawn`, `archived`.
- Priority: `normal`, `high`, `urgent`.
- Audience: `club-members`, `officers`.
- Publisher-role snapshot: `president`, `secretary`.

`expired` is not a stored status.

Required indexes:

- `(club_id, status, published_at)`
- `(club_id, audience, status, published_at)`
- `(club_id, priority, published_at)`
- `(club_id, expires_at)`
- `(club_id, scheduled_publish_at)`
- `(club_id, updated_at)`
- `(club_id, replaces_announcement_id)`

Do not add `deleted_at`, external-link columns, attachment/file fields, delivery/read-receipt fields, scheduler state, HTML/Markdown fields, or accounting fields. AN-014 permits a possible later structured link, but its validation and use remain outside this first read implementation.

## 5. Read-time lifecycle integrity

The database foreign keys establish structural existence, but the later read layer must validate the complete row before treating it as authoritative.

### Draft

- `published_at`, `published_by_user_id`, `published_by_role`, `withdrawn_at`, `withdrawal_reason`, `withdrawn_by_user_id`, `archived_at`, and `archived_by_user_id` must be null.
- `scheduled_publish_at` may exist but does not publish the row.

### Published

- `published_at`, `published_by_user_id`, and a valid `published_by_role` are required.
- Withdrawal and archive fields must be null.
- If `expires_at` exists, it must be strictly later than `published_at`.

### Withdrawn

- `published_at`, `published_by_user_id`, a valid `published_by_role`, `withdrawn_at`, non-blank `withdrawal_reason`, and `withdrawn_by_user_id` are required.
- Archive fields must be null.

### Archived

- `archived_at` and `archived_by_user_id` are required.
- Archive visibility is management-only.
- Exact prior-state preservation and archive mutation rules remain for the later lifecycle/audit contract.

### Every status

- `created_by_user_id` is required.
- Club, actor, and replacement relationships must be structurally valid.
- Relationship actors must be valid for the owning club under the later mutation contract; no cross-club identity may be exposed.
- `replaces_announcement_id` cannot reference the same row.
- Status, priority, audience, and publisher-role values must be controlled constants.
- Title and body must be non-blank and within the approved Unicode-character limits.
- No row is authoritative merely because it can be hydrated by the ORM.

A collection containing any malformed or internally contradictory row must fail closed with a truthful server error and return no partial list. An invalid, concealed, or unauthorized detail returns 404 or the repository-approved concealed response. The read layer must not coerce invalid state, silently drop an invalid row, treat it as another lifecycle state, or optimistically render partial data.

## 6. Expiry and timezone design

Store authoritative instants in UTC. Derive effective expiry only when:

```text
stored status = published
AND expires_at is not null
AND frozen/current UTC instant >= expires_at
```

Effective expiry:

- does not mutate stored status;
- creates no audit record;
- proves no scheduler execution;
- does not imply notification delivery.

Reaching `scheduled_publish_at` never changes visibility and is not proof of publication.

Use the owning club's authoritative timezone for local-date interpretation and display context. Return canonical UTC timestamps with `Z` plus `club_timezone`. `Asia/Kolkata` may be test data for LC Beenachi, but announcement logic must work with other timezones and must not hard-code `Asia/Kolkata` globally.

For `published_from` and `published_to`:

- interpret `YYYY-MM-DD` in the owning club timezone;
- convert query boundaries to UTC;
- make `published_from` inclusive at local start-of-day;
- make `published_to` inclusive as a calendar date by querying strictly before the UTC instant corresponding to the start of the following local day;
- reject `published_to` earlier than `published_from`;
- apply both only to `published_at`;
- test with a frozen clock around timezone and daylight-offset boundaries where applicable.

## 7. Version and replacement boundary

Each row is a stable announcement version. A future authorized Draft workflow may change a draft using `lock_version`; a Published version must later become substantively immutable. `replaces_announcement_id` provides future correction/replacement linkage but authorizes no replacement creation now.

The self foreign key enforces existence only. It cannot enforce same-club ownership or prevent self-reference by itself. A future mutation workflow must validate same-club linkage and prohibit self-reference. The read service must never expose a cross-club replacement relationship. Ordinary resources suppress replacement IDs. No uniqueness constraint is placed on `replaces_announcement_id` until abandoned-draft and replacement-chain rules are approved.

Maker/checker persistence is not designed here. `lock_version` does not itself represent checker approval.

## 8. Permissions and role assignments

Propose exactly:

- `announcements.view`
- `announcements.management.view`

| Product role | Existing system slug | view | management read |
| --- | --- | ---: | ---: |
| President | `president-admin` | Yes | Yes |
| Secretary | `secretary` | Yes | Yes |
| Treasurer | `treasurer` | Yes | No |
| Normal Member | `member` | Yes | No |

The product role label is President; no Admin product role is introduced or displayed. `president-admin` is only the existing internal slug.

`announcements.management.view` grants management read only. It does not grant drafting, checker approval, publication, withdrawal, correction, archival, deletion, notification, or attachment authority. `audit_logs.view` does not substitute for it. All access also requires active same-club membership.

## 9. Officer-audience boundary

No authoritative general officer-audience policy exists. Therefore ordinary officer-audience consumption is deferred:

- President and Secretary management readers may inspect authorized same-club `officers` records.
- Treasurer and Normal Member ordinary readers receive no `officers` record.
- Do not infer officer status from names, titles, role labels, designations, or frontend state.
- Do not create `announcements.officers.view` in the first implementation.
- An `officers` record is not automatically also a `club-members` record.

## 10. Exact read routes

Only these later routes are in scope:

- `GET /api/v1/clubs/{club}/announcements`
- `GET /api/v1/clubs/{club}/announcements/{announcement}`

Both use Sanctum, active same-club membership, and `announcements.view`. Wrong-club, concealed, malformed, and unauthorized nested detail identities fail closed. There is no POST, PATCH, DELETE, publish, approve, withdraw, correct, replace, archive, schedule, notify, acknowledge, or attachment route.

## 11. Exact scope semantics

All evaluation uses the same frozen/current instant.

### Ordinary reader

`scope=current`:

- same club;
- stored status Published;
- audience `club-members`;
- not effectively expired.

`scope=history`:

- same club;
- stored status Published;
- audience `club-members`;
- effectively expired.

### Management reader

`scope=current`:

- same-club Draft records;
- plus same-club Published records that are not effectively expired;
- exclude Withdrawn and Archived.

`scope=history`:

- same-club Withdrawn records;
- plus same-club Published records that are effectively expired;
- exclude Archived.

`scope=all`:

- every authorized same-club Draft, Published, Withdrawn, and Archived record.

Archived records appear only through management `scope=all`, optionally narrowed by `status=archived`. No record may appear in both management current and history for the same frozen instant.

## 12. Filters, composition, ordering, and pagination

Mandatory club, membership, lifecycle, and audience visibility scope applies before caller filters. Additional filters may only narrow that authorized base scope. No filter may broaden visibility.

Common filters:

- `scope=current|history`, default `current`;
- `priority=normal|high|urgent`;
- `q`, trimmed title search, maximum 100 Unicode characters;
- `published_from=YYYY-MM-DD`;
- `published_to=YYYY-MM-DD`;
- `page`, integer minimum 1;
- `per_page`, default 20, maximum 50;
- `sort=published_at`;
- `direction=asc|desc`, default `desc`.

Management-only filters:

- `scope=all`;
- `status=draft|published|withdrawn|archived`;
- `audience=club-members|officers`;
- `effective_state=current|expired`;
- `scheduled=true|false`.

Rules:

- Ordinary use of any management-only filter returns 403.
- Unknown filters and unsupported enum values return 422.
- Incompatible combinations return 422 rather than an invented empty result.
- `effective_state` applies only to Published records; combining it with a non-Published `status` is invalid.
- `scheduled=true|false` describes only whether `scheduled_publish_at` is present; it never implies publication.
- Status/audience/effective filters cannot escape the selected scope.
- Echo only normalized, authorized filters in response metadata.
- Date filters apply only to `published_at` using the local-date rules above.

Ordinary ordering is `published_at <direction>, id <direction>`. Management ordering must be deterministic and scope-aware, with `updated_at desc, id desc` for Draft/unpublished records and stable numeric-ID tie-breaking. Pagination is server-side.

## 13. Response envelopes and resource minimization

Collection:

```json
{
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 0,
    "last_page": 1,
    "filters": {}
  }
}
```

Detail:

```json
{ "data": {} }
```

Ordinary member fields:

- `id`
- `title`
- `body`
- `priority`
- `effective_status`: `published` or `expired`
- `published_at` as canonical UTC
- `expires_at` as canonical UTC or null
- `club_timezone`
- `issued_by`, derived from authoritative club configuration/name
- `published_by_role`, displayed as President or Secretary

Do not expose to ordinary readers: `club_id`, audience, scheduled metadata, stored internal status, withdrawal/archive fields, replacement IDs, internal actor/audit/authentication IDs, lock version, or internal created/updated timestamps.

Management adds:

- `club_id`
- `audience`
- `stored_status`
- `effective_status`
- `scheduled_publish_at`
- `published_at`
- `expires_at`
- `withdrawn_at`
- `withdrawal_reason`
- `archived_at`
- `replaces_announcement_id`, only when structurally valid and same-club
- `published_by_user_id`
- `published_by_role`
- `created_by_user_id`
- `updated_by_user_id`
- `withdrawn_by_user_id`
- `archived_by_user_id`
- `lock_version`
- `created_at`
- `updated_at`
- `club_timezone`
- `issued_by`

Use an explicit Resource; never blind-serialize the model. `issued_by` must derive from club configuration and must not hard-code Lions Club Beenachi globally.

## 14. Required later synthetic tests

All later implementation tests use isolated SQLite `:memory:` data and cover:

- exact fields, types, lengths, nullability, defaults, indexes, and foreign keys;
- non-null `created_by_user_id` and authenticated-actor intent;
- controlled constants and absence of stored `expired`;
- absence of delete, attachment, notification, delivery, receipt, HTML, link, scheduler, and accounting fields/routes;
- exact permission seeding and role matrix;
- active membership, unauthenticated 401, permission 403, and concealed 404 behavior;
- President/Secretary management read and Treasurer/Member ordinary read;
- cross-club list/detail isolation;
- every lifecycle invariant failing closed;
- Malformed collection rows producing no partial render;
- malformed/concealed detail behavior;
- ordinary current/history visibility;
- management current/history mutual exclusivity at one frozen instant;
- Archived appearing only under management `scope=all`;
- Withdrawn hidden from ordinary readers;
- officer-audience concealment from Treasurer and Member;
- authorized filters never broadening scope;
- ordinary management-filter attempts returning 403;
- incompatible combinations, unknown filters, and bad enums returning 422;
- `effective_state` only for Published records;
- `scheduled` describing metadata only;
- scheduled metadata never creating publication visibility;
- club-local inclusive-day `published_from`/`published_to` semantics;
- frozen-clock tests around timezone and daylight-offset boundaries where applicable;
- at least `Asia/Kolkata` and one different timezone without global hard-coding;
- exact pagination, ordering, normalized filter echo, and genuine empty list;
- exact member/management envelopes and field minimization;
- actor/audit privacy in ordinary resources;
- replacement self-reference rejection in future validation expectations;
- no cross-club replacement identity leakage;
- no write routes and no records created by resolving read services;
- continued frontend fabricated-data suppression;
- Events, Projects, Reminders, permission, and cross-club regression suites.

No test is added or changed during 3IIR.

## 15. Risks and stop conditions

Risks include treating historical Pending prose as current; conflating management read with mutation authority; storing Expired; treating scheduled time as publication or Urgent as delivery; inferring officer status; allowing filters to broaden scope; overlapping management current/history; leaking Archived records, actor IDs, or cross-club replacement identity; partially rendering malformed rows; using incorrect local-date boundaries; hard-coding LC Beenachi/timezone; restoring fabricated frontend rows; or adding a write route.

Stop the later implementation if:

- operational-data access is required;
- the test runtime is not isolated SQLite `:memory:`;
- an authoritative officer policy materially changes this boundary;
- lifecycle invariants cannot fail closed;
- a filter can broaden authorization scope;
- frontend, mutation, notification, attachment, external-link, scheduler, read-receipt, accounting, merge, or deployment work enters scope.

## 16. Recommended sequence

1. Accept and close this documentation-only scope gate.
2. Prepare a separate database/read-API implementation authorization.
3. Implement only schema, model/constants, permissions, request, resource, read service/controller, two GET routes, factory, and synthetic tests.
4. Validate and close the read API.
5. Scope frontend source-truth integration separately.
6. Contract maker/checker persistence and mutation permissions separately.
7. Implement mutations/lifecycle only after explicit authorization.
8. Consider notifications, external links, or attachments only through separate future contracts.

## 17. Scope-gate closure conditions

This scope gate is complete only when its single Markdown file is validated, committed once, pushed to its authorized branch, local/upstream equality is confirmed, and the repository is clean. No operational data, schema, permission, API, test, frontend, mutation, notification, attachment, accounting, merge, or deployment change may occur.
