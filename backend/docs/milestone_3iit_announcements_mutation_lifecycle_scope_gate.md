# Milestone 3IIT — Announcements Mutation and Lifecycle Scope Gate

## 1. Identity, status, and authority

- Source branch: `milestone-3iis-announcements-database-read-api-foundation`
- Exact baseline: `cc4a935cf12245597d27ac2e516aa23579dda426`
- Scope-gate branch: `milestone-3iit-announcements-mutation-lifecycle-scope-gate`
- Scope: documentation only

Milestone 3IIS is accepted as PASS. This document resolves the future mutation and lifecycle contract for club announcements. It authorizes no migration, permission, model, request, resource, service, controller, route, test, frontend, scheduler, notification, attachment, external-link, accounting, deletion, merge, deployment, seed-data, or operational-data change.

All future implementation remains subject to a separate explicit authorization. Historical Pending prose is not runtime authority; the approved AN-001–AN-025 decision register, the 3IIR scope gate, and the accepted 3IIS foundation control this scope.

## 2. Read-only audit findings

The audit covered the approved AN-001–AN-025 qualifications; the 3IIR read scope; the complete 3IIS schema, model, permissions, request, resource, read service, controller, routes, factory, and tests; profile-update maker/checker, fingerprint, and optimistic-locking patterns; Events, Projects, and Reminders mutation services; finance idempotency and replacement precedents; AuditLog conventions; role and permission architecture; explicit lifecycle-action routes; transaction and row-locking conventions; and frontend fabricated-announcement suppression.

Findings:

- `club_announcements` already contains the lifecycle, publication, withdrawal, archive, replacement, actor, time, and optimistic-lock fields required for future mutations.
- `expired` is correctly derived at read time and must not become a stored status or scheduled mutation.
- Events, Projects, and Reminders establish the repository pattern of explicit Form Requests, explicit lifecycle POST actions, same-club `lockForUpdate()`, required `lock_version`, one transaction, and one success AuditLog record.
- Profile updates demonstrate exact-source fingerprinting and reviewer decisions, but their mutable request row is not an adequate append-only announcement approval design.
- AuditLog is generic supporting evidence, not a sufficient authoritative approval record: it has no enforced version/fingerprint binding or structured decision finality, its club and actor references are nullable, and its JSON metadata cannot be the approval state machine.
- Finance contains genuine idempotency-key precedents. Ordinary lifecycle services instead reject stale or duplicate actions. Creation replay safety therefore requires separate persistence if it is later demanded.
- The existing internal President role slug is `president-admin`; the product role remains President. No Admin product role is approved.
- The frontend continues to suppress fabricated announcement rows and actions. This scope gate does not connect or change the frontend.
- No operational data was accessed.

## 3. Exact future permissions and assignments

Define exactly these six future mutation permissions:

| Permission | Purpose | President (`president-admin`) | Secretary (`secretary`) | Treasurer | Member |
| --- | --- | ---: | ---: | ---: | ---: |
| `announcements.drafts.manage` | Create and edit ordinary Drafts | Yes | Yes | No | No |
| `announcements.replace` | Create a correction/replacement Draft from an eligible source | Yes | Yes | No | No |
| `announcements.approve` | Approve, reject, or revoke approval for an exact gated Draft version | Yes | Yes | No | No |
| `announcements.publish` | Explicitly publish an eligible Draft | Yes | Yes | No | No |
| `announcements.withdraw` | Withdraw a Published or effectively Expired announcement | Yes | Yes | No | No |
| `announcements.archive` | Archive an eligible Draft, Published, effectively Expired, or Withdrawn record | Yes | Yes | No | No |

Existing `announcements.view` and `announcements.management.view` assignments remain unchanged. Each permission is independent: possessing one must not imply another.

Every mutation also requires Sanctum authentication, active same-club membership, concealed nested ownership, and an unambiguous qualifying principal role. Acting role is derived from the authenticated membership and permission context, never accepted from the payload. The only mutation role snapshots are `president` and `secretary`. Fail closed if no qualifying principal role exists or role resolution is ambiguous; do not select the caller-favourable role. Historical actor membership need not remain active for later reads.

## 4. Lifecycle and transition matrix

Stored lifecycle values remain `draft`, `published`, `withdrawn`, and `archived`. `expired` remains a derived effective state when stored status is Published, `expires_at` is non-null, and current UTC is greater than or equal to `expires_at`.

| Source | Target/action | Allowed | Exact rule |
| --- | --- | ---: | --- |
| No record | Draft | Yes | Authorized creation only |
| Draft | Draft | Yes | Material PATCH with current lock version |
| Draft | Published | Yes | Explicit authorized publish action and any applicable checker gate |
| Draft | Archived | Yes | Explicit authorized archive of an abandoned Draft |
| Published | Withdrawn | Yes | Explicit authorized action with reason |
| Effectively Expired Published | Withdrawn | Yes | Stored source remains Published until withdrawal |
| Published | Archived | Yes | Explicit authorized archive |
| Effectively Expired Published | Archived | Yes | Archive the stored Published record |
| Withdrawn | Archived | Yes | Explicit authorized archive |
| Published | Expired | Derived only | No write, job, scheduler, audit, or stored transition |
| Published or Withdrawn | Replacement Draft | Yes | New linked row; source remains immutable |
| Withdrawn | Published | No | Correction requires a new replacement Draft |
| Published | Draft | No | No unpublish or reopen |
| Archived | Any mutation | No | Archived is terminal |
| Any record | Deleted | No | No DELETE route, soft deletion, or hard deletion |

There is no generic status PATCH. Publication, withdrawal, archive, approval decisions, and replacement creation use explicit action routes.

## 5. Draft content, time, and ownership contract

Future Draft creation accepts only:

- required trimmed `title`, 1–160 Unicode characters;
- required trimmed `body`, 1–5,000 Unicode characters;
- required `priority`: `normal`, `high`, or `urgent`;
- required `audience`: `club-members` or `officers`;
- nullable `scheduled_publish_at` planning metadata;
- nullable `expires_at`.

Content is plain Unicode with preserved line breaks. Whitespace-only values are invalid. Character limits are Unicode-character limits, not byte limits. No HTML, Markdown, scripts, embedded media, tracking content, attachments, storage references, or content-generated links are authorized.

Time input must be ISO-8601 with an explicit offset and must be normalized to authoritative UTC. When both Draft timestamps exist, `expires_at` must be strictly later than `scheduled_publish_at`. Reaching `scheduled_publish_at` never publishes, mutates, audits, enqueues, or proves scheduler execution. Manual authorized publication may occur before, at, or after that timestamp.

The server exclusively controls `club_id`, status, replacement linkage except through the replacement route, all actor IDs, lifecycle timestamps, role snapshots, fingerprint, audit fields, and `lock_version`. Create at lock version 1 and record the authenticated creator as both creator and current updater. A successful material Draft PATCH updates `updated_by_user_id` and increments `lock_version`. Reject a no-op PATCH with 422 and no audit.

Every listed Draft field, including scheduling and expiry metadata, is material. Replacement audience is immutable and cannot be patched.

## 6. Publication invariants and routine publication

Publication is always an explicit authorized action from Draft. The transaction must lock the same-club announcement, verify current `lock_version`, derive an unambiguous acting role, evaluate the current maker/checker gate, and revalidate all lifecycle and time invariants.

The server sets:

- stored status `published`;
- `published_at` from server UTC now;
- `published_by_user_id` from authentication;
- `published_by_role` as the derived `president` or `secretary` snapshot;
- `updated_by_user_id` from authentication;
- incremented `lock_version`.

At actual publication, `expires_at` must be null or strictly later than server `published_at`. A Draft with an expiry that is no longer valid returns 409 without mutation or audit. `scheduled_publish_at` remains non-operative metadata and is retained for provenance.

Published title, body, priority, audience, scheduled-publication metadata, expiry metadata, and replacement linkage are immutable. There is no silent published-content PATCH.

A `club-members` Draft with `normal` or `high` priority is routine and may be published by an authorized President or Secretary without a checker record. It still requires same-club authorization, server publication time, publisher identity and role snapshot, current optimistic lock, one transaction, and exactly one success audit record. Urgent never implies notification delivery.

## 7. Maker/checker persistence and finality

Maker/checker is required exactly when:

```text
priority = urgent OR audience = officers
```

High priority alone does not require it. Approval, rejection, and revocation endpoints must reject routine `normal`/`high` `club-members` Drafts with 409 because no checker decision is required.

The maker is the authenticated actor in `updated_by_user_id` for the exact Draft version. The checker must be a different authenticated active same-club user with `announcements.approve` and an unambiguous President or Secretary role.

### Additive authoritative decision table

The current schema requires exactly one additive approval/decision table in a later implementation: `club_announcement_publication_decisions`.

| Field | Null | Contract |
| --- | ---: | --- |
| `id` | No | Primary key |
| `club_id` | No | Restrictive foreign key to owning club |
| `club_announcement_id` | No | Restrictive foreign key to announcement |
| `announcement_lock_version` | No | Exact adjudicated version |
| `content_fingerprint` | No | Fixed 64-character lowercase SHA-256 |
| `maker_user_id` | No | Restrictive foreign key to exact-version maker |
| `maker_role` | No | `president` or `secretary` snapshot |
| `decision` | No | `approved`, `rejected`, or `revoked` |
| `decided_by_user_id` | No | Restrictive foreign key to decision actor |
| `decided_by_role` | No | `president` or `secretary` snapshot |
| `supersedes_decision_id` | Yes | Restrictive self-reference; allowed only for revocation |
| `reason` | Yes | Null allowed for approval; required trimmed reason for rejection/revocation |
| `decided_at` | No | Authoritative server UTC instant |
| `created_at` | No | Insert timestamp |

There is no `updated_at`, `deleted_at`, update workflow, or delete workflow. Decision history is append-only.

Required indexes:

- (`club_id`, `club_announcement_id`, `announcement_lock_version`)
- (`club_announcement_id`, `decision`, `decided_at`)
- (`content_fingerprint`)
- (`supersedes_decision_id`)

Require a unique constraint on non-null `supersedes_decision_id`, so an approval cannot be revoked more than once. Enforce other one-decision-per-version and decision/state compatibility rules transactionally under the locked announcement because portable partial uniqueness differs between SQLite and PostgreSQL.

### Fingerprint contract

The canonical contract version is exactly `ANNOUNCEMENT-FINGERPRINT-2026-01`.

Calculate SHA-256 over a fixed-key-order UTF-8 canonical serialization containing:

1. fingerprint contract version;
2. announcement ID as integer;
3. club ID as integer;
4. `lock_version` as integer;
5. trimmed title;
6. trimmed body after normalizing CRLF and CR to LF;
7. priority;
8. audience;
9. `scheduled_publish_at` as canonical UTC RFC3339 with fixed precision, or null;
10. `expires_at` in the identical format, or null;
11. `replaces_announcement_id` as integer, or null.

Use no locale-sensitive formatting, unrelated database timestamp, actor identity, or random value. Identical semantic input must produce an identical fingerprint. Any material field or lock-version change must alter it. The caller cannot submit or override the fingerprint.

### Decision finality

- Decision actions are valid only while the record is Draft and must serialize by locking the announcement row.
- The first valid approval or rejection adjudicates the exact lock version and fingerprint.
- A rejected version cannot later be approved; a material Draft change and new lock version are required.
- An approved version cannot receive another approval or rejection.
- Revocation is allowed only before publication, by the original checker, against the exact current approval and current unchanged version/fingerprint.
- Once revoked, that exact version cannot be approved again; a material Draft change and new lock version are required.
- Duplicate approval, rejection, or revocation attempts return 409.
- Publication permanently closes approval actions for that announcement record.
- Historical decisions remain readable to authorized management even when the actor's membership later becomes inactive.
- AuditLog accompanies every decision but is not the authoritative decision row.

## 8. Correction and replacement contract

A correction/replacement is a new Draft linked to exactly one same-club Published or Withdrawn source. It does not edit, withdraw, or archive the source.

The replacement must preserve the source audience. It may change title, body, priority, scheduling, and expiry. Changing audience creates a separate announcement, not a correction. The replacement linkage and inherited audience are immutable after creation.

Under a lock on the source announcement, future implementation must:

- validate same-club ownership;
- reject Draft or Archived sources;
- prohibit more than one non-Archived replacement Draft;
- require an abandoned replacement Draft to be Archived before another is created;
- prohibit more than one direct Published replacement;
- validate the complete ancestor chain;
- reject self-reference, cycles, and Published branching with 409.

Linear chains such as A ← B ← C are permitted. Competing replacement creation transactions must serialize on the source; sequential-only protection is insufficient.

When a replacement becomes Published:

- its Published predecessor remains immutable and keeps stored status Published;
- the predecessor leaves ordinary current results;
- the predecessor enters ordinary history with derived effective status `replaced`;
- ordinary history/detail may expose only a safe same-club `replaced_by_announcement_id` pointing to a successor visible to that reader;
- the successor may expose `replaces_announcement_id` only when that reference does not leak a concealed record;
- management retains the complete chain;
- no predecessor is automatically withdrawn or archived;
- Withdrawn and Archived visibility rules otherwise remain unchanged.

A replacement must never suppress a predecessor for a reader who cannot see the Published successor. The future read service and explicit resource projections must apply mandatory club, lifecycle, and audience visibility before deriving suppression or exposing either direction of linkage.

## 9. Withdrawal and archive

### Withdrawal

President or Secretary with `announcements.withdraw` may withdraw a same-club Published record, including one that is effectively Expired. Require a trimmed 3–1,000 Unicode-character reason. Within one transaction, lock and validate the row, set stored status Withdrawn, server UTC `withdrawn_at`, authenticated `withdrawn_by_user_id` and `updated_by_user_id`, increment the lock, and create one success audit. Retain all content, publication provenance, decisions, and replacement history. No deletion is authorized.

### Archive

Archive is authorized from Draft, Published, effectively Expired Published, or Withdrawn. It must preserve exactly one valid prior-state signature:

- Archived Draft: no publication or withdrawal provenance;
- Archived Published: complete publication provenance and no withdrawal provenance;
- Archived Withdrawn: complete publication and withdrawal provenance.

Before changing stored status to Archived, the audit metadata must record the exact prior stored state and prior effective state. The transaction then records server UTC `archived_at`, authenticated `archived_by_user_id` and `updated_by_user_id`, and incremented lock version.

Archived records leave ordinary visibility, remain retained through authorized management `scope=all`, and are terminal. They cannot be edited, approved, rejected, revoked, published, withdrawn, used as replacement sources, reopened, or deleted.

The existing announcement fields are adequate to retain prior archive provenance; no `previous_status` column is required.

## 10. Exact routes, permissions, and contracts

All paths are beneath `/api/v1`, Sanctum, active same-club membership, and concealed nested ownership.

| Method and path | Exact permission | Request | Success |
| --- | --- | --- | --- |
| `POST /clubs/{club}/announcements` | `announcements.drafts.manage` | Draft create fields | 201 management resource |
| `PATCH /clubs/{club}/announcements/{announcement}` | `announcements.drafts.manage` | `lock_version` plus one or more mutable Draft fields | 200 management resource |
| `POST /clubs/{club}/announcements/{announcement}/replacements` | `announcements.replace` | Draft fields except audience; audience inherited | 201 management resource |
| `POST /clubs/{club}/announcements/{announcement}/approve` | `announcements.approve` | `lock_version` | 200 resource plus decision summary |
| `POST /clubs/{club}/announcements/{announcement}/reject-approval` | `announcements.approve` | `lock_version`, required `reason` | 200 resource plus decision summary |
| `POST /clubs/{club}/announcements/{announcement}/revoke-approval` | `announcements.approve` | `lock_version`, `approval_decision_id`, required `reason` | 200 resource plus decision summary |
| `POST /clubs/{club}/announcements/{announcement}/publish` | `announcements.publish` | `lock_version` | 200 management resource |
| `POST /clubs/{club}/announcements/{announcement}/withdraw` | `announcements.withdraw` | `lock_version`, required `reason` | 200 management resource |
| `POST /clubs/{club}/announcements/{announcement}/archive` | `announcements.archive` | `lock_version` | 200 management resource |

Decision success responses contain a minimized `decision` object with decision ID, decision value, announcement lock version, fingerprint contract version, and canonical UTC decision time. They must not expose authentication identifiers or concealed cross-club data beyond the authorized management contract.

Caller-controlled `club_id`, audience on replacement, status, lifecycle timestamps, actor IDs, roles, fingerprint, replacement foreign key, audit content, and server lock result are prohibited request fields.

## 11. Transaction, audit, concurrency, and replay rules

Every logical mutation must:

1. enter one database transaction;
2. resolve the target using route club and target ID;
3. acquire the required announcement/source row locks;
4. verify membership, exact permission, unambiguous acting role, nested ownership, lifecycle, and supplied lock version;
5. validate decision or replacement constraints while the controlling row remains locked;
6. apply the domain mutation and increment `lock_version` where applicable;
7. insert exactly one success AuditLog record;
8. commit everything or nothing.

Approval, rejection, and revocation additionally insert exactly one authoritative decision row in the same transaction. If domain mutation, decision insert, or audit insert fails, all changes roll back.

Use exactly these audit action names:

- `announcements.created`
- `announcements.updated`
- `announcements.replacement_draft_created`
- `announcements.approval_granted`
- `announcements.approval_rejected`
- `announcements.approval_revoked`
- `announcements.published`
- `announcements.withdrawn`
- `announcements.archived`

Audit metadata is club-scoped and minimized. It includes relevant before/after lifecycle, prior/new lock version, derived actor role snapshot, decision ID/fingerprint where applicable, replacement linkage where applicable, and archive prior stored/effective state. Do not copy the full body into generic audit JSON without a separately approved need.

Response behavior:

- 401: unauthenticated;
- 403: inactive membership or missing exact permission;
- concealed 404: wrong-club or otherwise concealed nested identity;
- 422: malformed or prohibited input and no-op PATCH;
- 409: stale lock, duplicate/replayed action, invalid lifecycle, unnecessary routine decision action, self-approval, adjudicated/revoked version, invalid approval, invalid expiry at publication, replacement conflict, branch, or cycle.

No success audit or decision row may remain after 401, 403, 404, 409, 422, or transaction failure. A replay using the pre-success lock version returns 409 and creates no second success audit or decision. The first mutation implementation does not promise retry-safe Draft creation. If guaranteed network replay safety becomes required, stop and separately scope dedicated idempotency persistence; never use AuditLog as an idempotency store.

## 12. Schema sufficiency and unavoidable additions

Existing `club_announcements` fields are adequate for Draft mutation, actual publication time, publisher identity and role snapshot, scheduling and expiry metadata, withdrawal, archive, optimistic locking, correction linkage, and archive prior-state reconstruction.

The one unavoidable additive persistence structure is `club_announcement_publication_decisions`. No additional announcement column is approved by this gate. In particular, do not add stored Expired/Replaced status, previous-status, approval flags, checker columns on the announcement row, external-link columns, idempotency columns, attachment/storage fields, notification/delivery/read-receipt fields, scheduler state, HTML/Markdown fields, soft-deletion fields, or accounting fields.

AuditLog remains required supporting history but cannot replace the decision table.

## 13. Future synthetic test requirements

A separately authorized implementation must use isolated SQLite `:memory:` data and test:

- exact six permissions, exact route-to-permission mapping, and permission independence;
- President and Secretary default assignments, with no Admin product role;
- acting-role derivation, prohibited role input, and ambiguous-role failure;
- Unicode title/body limits, whitespace rejection, line-break preservation, and prohibited caller fields;
- Draft create/update, no-op PATCH, lock increments, stale locks, and immutable published content;
- every allowed and forbidden lifecycle transition;
- derived and unstored Expired behavior;
- scheduled time causing no mutation and manual publication before, at, or after it;
- Draft `expires_at > scheduled_publish_at` when both exist;
- publication `expires_at > actual server published_at`, including stale-expiry 409;
- routine Draft publication without decision and rejection of unnecessary decision endpoints;
- Urgent and officers gate combinations, including High officers requiring approval;
- distinct maker/checker, exact ID/version/fingerprint binding, and current-role authorization;
- exact `ANNOUNCEMENT-FINGERPRINT-2026-01` canonicalization, CRLF/CR normalization, UTC precision, fixed key order, deterministic cross-locale output, and caller prohibition;
- approval/rejection first-decision finality, rejected-version lockout, revocation by original checker only, revoked-version lockout, duplicate 409s, and publication closure;
- append-only decision history, required table columns, restrictive foreign keys, indexes, and unique supersedes constraint;
- atomic decision-plus-audit behavior and rollback when either insert fails;
- exactly one success AuditLog for every successful logical mutation and none for any rejected attempt;
- same-audience replacement, immutable linkage, one non-Archived Draft, one direct Published successor, abandoned-Draft archive requirement, full-chain validation, cycle/branch rejection, and cross-club concealment;
- genuinely competing replacement-creation transactions serialized on the source, not only sequential requests;
- replacement predecessor ordinary-current suppression, derived `replaced` history, safe visible successor/predecessor references, management full chain, and no suppression when the successor is concealed;
- withdrawal reason/provenance/history and no deletion;
- archive from Draft, Published, effectively Expired Published, and Withdrawn;
- all three archive prior-state signatures and exact prior stored/effective audit metadata;
- Archived terminal behavior and management-only retention;
- 401, 403, concealed 404, 409, and 422 behavior with no residual success evidence;
- no DELETE, scheduler, automatic publication, notification, acknowledgement, receipt, attachment, storage, external-link, accounting, or frontend mutation route;
- unchanged 3IIS reads, resource privacy, cross-club isolation, malformed-row failure, and frontend fabricated-announcement suppression;
- Events, Projects, Reminders, profile updates, role/permission, and cross-club regression suites.

## 14. Risks and stop conditions

Risks include treating AuditLog as approval authority; self-approval; approval of stale content; allowing a rejected or revoked version to be readjudicated; ambiguous role selection; permission implication; silent published edits; treating schedule or expiry as a scheduler; storing Expired or Replaced; invalid expiry at publication; cross-club or audience leakage through replacement links; suppressing a predecessor for a reader who cannot see its successor; replacement races, branches, or cycles; incomplete archive provenance; duplicate success audits; restoring fabricated frontend data; or introducing an Admin product role.

Stop a later implementation if:

- operational-data access is required;
- the runtime test database is not isolated SQLite `:memory:`;
- a second persistence table is required without separate scope approval;
- authoritative officer policy materially changes the approved audience boundary;
- approval cannot be append-only, deterministic, exact-version-bound, and transactionally serialized;
- competing replacement creation cannot be serialized;
- a filter or replacement link can broaden or leak visibility;
- guaranteed replay-idempotency is demanded without separately approved persistence;
- frontend integration, notifications, delivery, acknowledgements, read receipts, attachments, storage, external links, scheduler, automatic publication, accounting, deletion, merge, or deployment enters scope.

## 15. Recommended future implementation sequence

1. Accept and close this documentation-only scope gate.
2. Prepare a separate mutation implementation authorization.
3. Add only the approval/decision table and model relationships.
4. Add the six permissions and exact President/Secretary assignments.
5. Add explicit mutation Form Requests and management/decision response projections.
6. Implement Draft create/update and replacement-Draft creation with row locks.
7. Implement deterministic fingerprinting and append-only approval, rejection, and revocation.
8. Implement explicit publication and routine-publication exception.
9. Implement withdrawal and archive.
10. Update read visibility narrowly for replaced history and safe chain references.
11. Add isolated synthetic tests, including genuine replacement concurrency and atomic-failure tests.
12. Run announcement, permission, cross-club, Events, Projects, Reminders, profile-update, and fabricated-frontend regressions.
13. Validate and close backend mutation separately.
14. Scope frontend integration, notification delivery, external links, attachments, and any scheduler only through separate future gates.

## 16. Scope-gate closure conditions

Milestone 3IIT changes exactly this Markdown file. Documentation-safe validation consists only of required-text inspection, changed-file review, Git diff checks, local/upstream verification, and clean status. It does not require PHP, migrations, a database, operational data, or runtime tests.

This scope gate authorizes no migration, permission, route, request, model, resource, service, controller, test, frontend behavior, notification, delivery, acknowledgement, read receipt, attachment, storage, external link, scheduler, accounting, deletion, merge, or deployment.
