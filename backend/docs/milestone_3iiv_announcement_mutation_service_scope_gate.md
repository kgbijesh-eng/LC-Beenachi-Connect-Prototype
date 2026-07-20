# Milestone 3IIV — Announcement Mutation Service Scope Gate

## 1. Identity, status, and authority

- Source branch: `milestone-3iiu-announcement-decision-permission-foundation`
- Exact baseline: `0785a5d660273dfcbf74d3efc45cbeec26a0a96b`
- Scope-gate branch: `milestone-3iiv-announcement-mutation-service-scope-gate`
- Scope: documentation only

Milestone 3IIU is accepted as the publication-decision, fingerprint, and permission foundation. This document defines the exact boundary for a later internal announcement mutation-service implementation. It authorizes no service, request, controller, route, migration, model, permission, test, resource, read behavior, frontend behavior, scheduler, notification, read receipt, attachment, external link, accounting operation, deletion, operational mutation, merge, or deployment.

All future implementation requires separate explicit authorization. No operational data was accessed while preparing this gate.

## 2. Read-only audit findings

The audit covered the accepted 3IIT mutation/lifecycle contract; accepted 3IIU decision, fingerprint, and permission foundation; approved AN-001–AN-025; `club_announcements`; `club_announcement_publication_decisions`; `ClubAnnouncementFingerprint`; DefaultRolesAndPermissionsSeeder; ClubPermissionService; active membership and role relationships; AuditLog; Events, Projects, Reminders, Profile Updates, and finance mutation precedents; announcement read service/resource/controller/routes/tests; and SQLite/PostgreSQL portability.

Findings:

- The existing two announcement tables contain every persistence field needed by the future service. No migration or permission change is required.
- The announcement route surface remains exactly two GET routes. The internal service can be implemented and tested without exposing a runtime mutation endpoint.
- Existing mutation services establish the repository pattern: authorize, enter one transaction, reload by club and target identity, `lockForUpdate()`, check `lock_version`, validate lifecycle, mutate, increment the lock where applicable, insert one AuditLog row, and commit everything or nothing.
- The 3IIU decision model provides ordinary-Eloquent append-only guards, but raw SQL/query-builder writes can bypass them. The future service must insert through the model and must never update or delete a decision row.
- Decision-table foreign keys and uniqueness do not enforce same-club relationships, exact-version compatibility, maker/checker separation, decision finality, valid supersession, or lifecycle compatibility. The future service must enforce those rules while holding the announcement lock.
- ClubPermissionService establishes active same-club permission checks but does not return the acting-role snapshot. The future service must resolve the single active qualifying membership and fail closed when role classification is missing, unsupported, or ambiguous.
- AuditLog remains supporting history, not authoritative approval persistence.
- Draft creation has no guaranteed network-replay idempotency. Existing non-finance lifecycle services reject stale or duplicate actions rather than promising replay-safe creation.
- Replacement publication changes the truth returned by existing reads. It is unsafe to publish a replacement unless replacement-aware read-service/resource behavior is delivered and tested in the same implementation milestone.
- SQLite `:memory:` tests can verify deterministic lock acquisition code paths and all invariants, but cannot prove competing PostgreSQL transaction behavior. Real contention-safety claims require an approved PostgreSQL-compatible concurrency test.

## 3. Exact future service boundary

The future implementation may add exactly one internal service:

```text
App\Services\Announcements\ClubAnnouncementWriteService
```

It must be container-resolvable but must have no route, controller, request, command, scheduler, listener, job, seeder, frontend caller, or operational invocation in its first implementation.

The service must authorize every public operation internally. A future controller must not become the sole authorization boundary.

## 4. Exact public method contract

```php
public function createDraft(
    Club $club,
    User $actor,
    array $attributes
): ClubAnnouncement;

public function updateDraft(
    Club $club,
    ClubAnnouncement $announcement,
    User $actor,
    int $lockVersion,
    array $attributes
): ClubAnnouncement;

public function createReplacementDraft(
    Club $club,
    ClubAnnouncement $source,
    User $actor,
    array $attributes
): ClubAnnouncement;

public function approve(
    Club $club,
    ClubAnnouncement $announcement,
    User $actor,
    int $lockVersion,
    ?string $reason = null
): ClubAnnouncementPublicationDecision;

public function rejectApproval(
    Club $club,
    ClubAnnouncement $announcement,
    User $actor,
    int $lockVersion,
    string $reason
): ClubAnnouncementPublicationDecision;

public function revokeApproval(
    Club $club,
    ClubAnnouncement $announcement,
    User $actor,
    int $lockVersion,
    ClubAnnouncementPublicationDecision $approval,
    string $reason
): ClubAnnouncementPublicationDecision;

public function publish(
    Club $club,
    ClubAnnouncement $announcement,
    User $actor,
    int $lockVersion
): ClubAnnouncement;

public function withdraw(
    Club $club,
    ClubAnnouncement $announcement,
    User $actor,
    int $lockVersion,
    string $reason
): ClubAnnouncement;

public function archive(
    Club $club,
    ClubAnnouncement $announcement,
    User $actor,
    int $lockVersion
): ClubAnnouncement;
```

No generic status transition, generic mutation array, delete method, scheduler method, notification method, or raw decision-creation method is approved.

## 5. Exact permission mapping

| Service method | Required permission |
| --- | --- |
| `createDraft`, `updateDraft` | `announcements.drafts.manage` |
| `createReplacementDraft` | `announcements.replace` |
| `approve`, `rejectApproval`, `revokeApproval` | `announcements.approve` |
| `publish` | `announcements.publish` |
| `withdraw` | `announcements.withdraw` |
| `archive` | `announcements.archive` |

Every permission is independent. Possessing one must not imply another.

## 6. Active membership and acting-role resolution

For every mutation, the service must resolve exactly one active same-club membership for the authenticated actor, load its role and permissions, verify the exact permission, and classify the qualifying role as:

- internal `president-admin` → snapshot `president`;
- internal `secretary` → snapshot `secretary`.

No role, club, actor, permission, or snapshot is accepted from caller attributes. Fail closed if membership is absent or inactive, the exact permission is absent, the role is unsupported, or role resolution is ambiguous.

Historical membership need not remain active for later reads of already persisted actor identities and role snapshots.

## 7. Draft normalization and caller boundary

The service must enforce its own domain validation rather than relying exclusively on a later Form Request.

Draft fields:

- Unicode-trim `title` before persistence; require 1–160 Unicode characters.
- Normalize body CRLF and CR to LF.
- Do not trim or otherwise alter leading, trailing, or internal body whitespace.
- Do not apply Unicode NFC, NFD, or other normalization.
- Validate body nonblank with a separate Unicode-whitespace probe without rewriting it.
- Require the normalized stored body to contain 1–5,000 Unicode characters.
- Treat leading and trailing body whitespace as material content.
- Require priority `normal`, `high`, or `urgent`.
- Require audience `club-members` or `officers`.
- Normalize scheduling and expiry instants to UTC.
- When both Draft timestamps exist, require `expires_at` strictly later than `scheduled_publish_at`.

Create at stored status Draft and lock version 1. Record the authenticated actor as creator and updater.

A Draft update may change title, body, priority, audience, `scheduled_publish_at`, or `expires_at`. After canonical normalization, at least one material value must differ. Reject a no-op update with 422 and no audit. A successful update records the actor as updater and increments `lock_version`.

For a replacement Draft, audience is inherited from the source and is not caller-controlled or mutable.

Prohibit caller-controlled club, status, actor IDs, lifecycle instants, role snapshots, fingerprint, replacement foreign key, decision fields, audit fields, and resulting lock version.

## 8. Transaction, locking, and optimistic concurrency

Every public method uses one database transaction. Any domain mutation, decision insert, or audit failure rolls back everything.

For an existing announcement, the service must discard the caller model's mutable state and reload by both target ID and owning club ID with `lockForUpdate()`. Wrong-club nested identity is concealed as not found. After locking, verify exact `lock_version`, lifecycle, relationships, membership, role, and permission.

Create needs no pre-existing announcement lock. Replacement creation locks its source before inspecting direct replacements or ancestry. Decision actions lock the adjudicated Draft. Ordinary publication, withdrawal, and archive lock their target row.

Material Draft updates and lifecycle mutations increment `lock_version`. Approval, rejection, and revocation insert append-only decision rows without incrementing the announcement lock. Their exact adjudicated version remains the current Draft version.

Stale locks, duplicate actions, invalid transitions, adjudicated versions, revoked approvals, invalid expiry, branches, and cycles return conflict and create no success evidence.

## 9. Maker/checker and maker-role accuracy

Maker/checker applies exactly when:

```text
priority = urgent OR audience = officers
```

Normal or High `club-members` Drafts are routine and must reject approval, rejection, or revocation actions because no checker decision is required.

For an adjudicated version:

- `maker_user_id` is exactly the Draft's current `updated_by_user_id`.
- The maker must still have one unambiguous active same-club President or Secretary membership at decision time.
- `maker_role` records that qualifying role resolved at decision time.
- This is a decision-time role classification. It is not proof of the maker's historical role when the content was edited.
- The checker is the current actor, must hold `announcements.approve`, and must be a different user from the maker.
- The checker role snapshot is also derived from the checker's active membership at decision time.
- Fail closed when either maker or checker role cannot be resolved unambiguously.

Do not infer historical maker-role data from current membership or generic AuditLog metadata. If true edit-time role provenance becomes required, stop and separately scope authoritative persistence for it.

While holding the Draft lock, compute `ANNOUNCEMENT-FINGERPRINT-2026-01` from the current authoritative row. The caller cannot submit or override it.

Decision finality:

- The first valid approval or rejection adjudicates the exact lock version and fingerprint.
- A rejected version cannot later be approved.
- An approved version cannot receive another approval or rejection.
- Revocation is allowed only while the record remains the same unchanged Draft, before publication, by the original checker, against the exact current approval.
- A revoked version cannot be approved again.
- Duplicate decision actions return conflict.
- Publication permanently closes decision actions for that announcement record.
- A material Draft update increments the lock version and leaves every older decision as append-only history.

Approval reason may be null or nonblank. Rejection and revocation require a nonblank reason. Whitespace-only is invalid. Validation must not rewrite a stored decision reason.

Each decision action inserts exactly one authoritative decision row and exactly one AuditLog row in the same transaction. Never update or delete a decision row and never use query-builder/raw SQL mutation for decision history.

## 10. Publication invariants

Publication is an explicit action from Draft only.

Routine Normal/High `club-members` Drafts may publish without a decision. Urgent or officers Drafts require one valid, unrevoked approval bound to the current announcement ID, lock version, and fingerprint.

At publication the service must:

- use authoritative server UTC now for `published_at`;
- record authenticated publisher identity;
- record the derived `president` or `secretary` publisher-role snapshot;
- require `expires_at` null or strictly later than actual `published_at`;
- retain `scheduled_publish_at` as non-operative planning metadata;
- set stored status Published;
- record the publisher as updater;
- increment `lock_version`;
- insert exactly one publication audit.

Reaching `scheduled_publish_at` never mutates or publishes. Published content and replacement linkage are immutable.

## 11. Withdrawal

Withdrawal is permitted only from stored Published, including effectively Expired Published. Require a nonblank 3–1,000 Unicode-character reason. Record authenticated actor, server UTC time, updater, stored status Withdrawn, and incremented lock version. Retain content, publication provenance, decision history, replacement history, and audit history. No deletion is permitted.

## 12. Archive

Archive is permitted from Draft, Published, effectively Expired Published, or Withdrawn. Before mutation, capture the exact prior stored and effective state for audit metadata.

Preserve one valid prior-state signature:

- Archived Draft: no publication or withdrawal provenance;
- Archived Published: complete publication provenance and no withdrawal provenance;
- Archived Withdrawn: complete publication and withdrawal provenance.

Record authenticated actor, server UTC archive time, updater, stored status Archived, and incremented lock version. Archived is terminal, management-retained, removed from ordinary visibility, and never deletion.

## 13. Replacement Draft creation

`createReplacementDraft` must lock the direct source before checking replacement state. The source must be same-club Published or Withdrawn. Draft and Archived sources are invalid.

While the source is locked:

- preserve the source audience;
- prohibit caller audience;
- reject more than one non-Archived direct replacement Draft;
- require an abandoned replacement Draft to be Archived before retry;
- reject an existing direct Published successor;
- validate the complete same-club ancestor chain;
- reject self-reference, cycles, and branching;
- create one linked Draft with immutable linkage.

Source-row locking is the serialization mechanism for competing replacement-Draft creation.

## 14. Replacement publication locking and revalidation

Publishing a replacement must lock both the replacement and its direct predecessor/source in a deterministic order. The service must derive the two database identities first and acquire both row locks in ascending announcement-ID order to avoid inconsistent lock order and reduce deadlock risk. Locking only the replacement row is insufficient.

While both rows remain locked, revalidate:

- both records belong to the same route club;
- the replacement link still points immutably to that direct predecessor;
- the predecessor remains an eligible Published or Withdrawn source;
- predecessor and replacement audiences match;
- no competing direct Published successor exists;
- the complete relationship remains free of branch and cycle;
- the replacement's checker approval and fingerprint remain valid when the gate applies;
- replacement-aware read integrity is implemented and active for the same release.

Only after all checks pass may the ordinary publication invariants be applied to the replacement.

SQLite `:memory:` tests may verify deterministic lock ordering code paths and all revalidation invariants. They cannot prove competing PostgreSQL transaction behavior. Any claim of real contention safety requires a separately approved PostgreSQL-compatible concurrency test using synthetic, non-operational data.

## 15. Mandatory truthful replacement-aware reads

Option A is binding: a replacement may be published only when replacement-aware `ClubAnnouncementReadService`, `ClubAnnouncementResource`, and tests are included in the same future implementation milestone.

A partial service implementation may create replacement Drafts, but must reject replacement publication until the read behavior exists. It must not create authoritative state that the existing GET API presents untruthfully.

After a successor becomes Published:

- its Published predecessor is removed from ordinary current results;
- the predecessor remains available in ordinary history with derived `effective_status` equal to `replaced`;
- ordinary history/detail may expose a safe same-club `replaced_by_announcement_id` only when the Published successor is visible to that reader;
- the successor may expose `replaces_announcement_id` only when doing so does not leak a concealed predecessor;
- management reads retain the complete chain;
- the predecessor remains immutable;
- no predecessor is automatically withdrawn or archived.

Replacement suppression must never hide a predecessor from a reader who cannot see the successor. Authorization and audience visibility are applied before suppression or linkage projection.

## 16. Audit contract and atomicity

Use exactly these successful actions:

- `announcements.created`
- `announcements.updated`
- `announcements.replacement_draft_created`
- `announcements.approval_granted`
- `announcements.approval_rejected`
- `announcements.approval_revoked`
- `announcements.published`
- `announcements.withdrawn`
- `announcements.archived`

Every successful logical mutation creates exactly one success AuditLog row. Approval, rejection, and revocation additionally create exactly one authoritative decision row. No success audit or decision row remains after any validation, authorization, concurrency, persistence, or audit failure.

Minimize audit metadata. Include applicable before/after lifecycle, prior/new lock version, acting-role snapshot, changed field names, before/after fingerprints, decision ID, replacement source/successor IDs, and archive prior stored/effective state. Do not duplicate full body text in generic audit JSON without separate approval.

## 17. Error and replay behavior

- Unauthenticated use is unavailable until an HTTP surface exists; later HTTP integration maps it to 401.
- Inactive membership, unsupported role, ambiguous role, or missing exact permission maps to 403.
- Wrong-club or concealed nested identity maps to 404.
- Malformed attributes, invalid Unicode limits, invalid reason, and canonical no-op update map to 422.
- Stale lock, invalid lifecycle, duplicate/replayed action, invalid decision, invalid approval, expired publication deadline, replacement conflict, branch, or cycle maps to 409.

Rejected operations create no success audit or decision. Replaying a successful action with its pre-success lock version returns conflict and creates no duplicate success record.

The first implementation does not guarantee network-replay-safe Draft creation. If that guarantee becomes required, stop and separately scope dedicated idempotency persistence. Do not use AuditLog as an idempotency store.

## 18. Exact future implementation boundary

A later separately authorized implementation should change only:

- new `app/Services/Announcements/ClubAnnouncementWriteService.php`;
- narrow replacement-aware changes to `app/Services/Announcements/ClubAnnouncementReadService.php`;
- narrow safe-link/effective-state changes to `app/Http/Resources/Announcements/ClubAnnouncementResource.php`;
- one focused synthetic mutation-service feature test;
- necessary narrow updates to existing 3IIS/3IIU read and route-regression tests identified by repository-wide search;
- one implementation milestone record.

The later authorization must list every exact regression file discovered before editing. It must stop rather than expand the allowlist one file at a time.

Do not include migration, permission, request, controller, route, frontend, scheduler, notification, read receipt, attachment, storage, external-link, accounting, or deletion changes.

## 19. Future isolated test requirements

The future implementation must use synthetic isolated data and cover:

- every service method and its exact independent permission;
- active same-club membership and acting-role classification;
- absent, inactive, unsupported, and ambiguous role failure;
- maker identity from `updated_by_user_id`;
- maker decision-time role classification and explicit absence of historical-role claims;
- title trimming, body line-ending normalization, material outer/internal body whitespace, Unicode limits, and no Unicode normalization;
- timestamp normalization and Draft schedule/expiry consistency;
- no-op updates, stale locks, cross-club concealment, and immutable published content;
- routine/gated decision combinations;
- maker/checker separation, fingerprint binding, first-decision finality, rejection lockout, approval finality, revocation ownership, and revocation lockout;
- atomic decision-plus-audit rollback;
- routine publication without decision;
- server publication time and expiry conflict;
- withdrawal reason and retained provenance;
- all archive source states, prior-state signatures, and terminal behavior;
- replacement audience preservation, chain, cycle, branch, and abandoned-Draft rules;
- source-lock serialization path for competing replacement creation;
- deterministic two-row replacement-publication lock ordering;
- all replacement publication revalidation under both locks;
- SQLite invariant coverage without claiming PostgreSQL contention proof;
- required PostgreSQL-compatible synthetic concurrency evidence before any real contention-safety claim;
- predecessor ordinary-current suppression after successor publication;
- predecessor history `effective_status=replaced`;
- safe `replaced_by_announcement_id` and `replaces_announcement_id` projection;
- no suppression when the successor is concealed;
- management full-chain retention and no automatic withdrawal/archive;
- exactly one success audit per logical mutation and none after failure;
- no request, controller, route, command, job, listener, scheduler, or frontend caller;
- resolving the service creates no records;
- exactly two GET-only announcement routes remain;
- accepted 3IIS/3IIU behavior and complete backend regressions.

## 20. Risks and stop conditions

Risks include controller-only authorization; caller-controlled provenance; stale-object mutation; ambiguous role selection; falsely treating decision-time role as edit-time history; relying on AuditLog for historical maker role; self-approval; stale fingerprint approval; readjudicating rejected/revoked versions; mutating decision history; partial audit commit; treating scheduled time as publication; silently editing Published content; replacement races; locking only the replacement during replacement publication; inconsistent lock order; branch/cycle creation; concealing a predecessor from a reader who cannot see the successor; publishing a replacement before truthful read behavior; claiming PostgreSQL contention safety from SQLite; or adding a runtime route prematurely.

Stop a future implementation if:

- operational data or a persistent database is required;
- a new schema or permission is required;
- role resolution cannot fail closed;
- true edit-time maker-role provenance is required without separately scoped persistence;
- decision finality cannot be serialized under the locked Draft;
- replacement creation cannot serialize on the source;
- replacement publication does not lock both rows deterministically;
- replacement-aware reads cannot ship with replacement publication;
- a replacement link can leak or broaden visibility;
- real PostgreSQL contention safety is claimed without an approved compatible concurrency test;
- guaranteed creation replay safety is demanded without idempotency persistence;
- request, controller, route, frontend, scheduler, notification, read receipt, attachment, external link, accounting, deletion, merge, or deployment enters scope.

## 21. Recommended implementation sequence

1. Accept and close this documentation-only scope gate.
2. Perform a repository-wide read-only regression/allowlist audit.
3. Prepare a separate implementation authorization listing every exact file.
4. Implement the internal write service and shared acting-role/content helpers inside that service boundary.
5. Implement replacement-aware read service/resource behavior before enabling replacement publication.
6. Add focused isolated service and read-consistency tests.
7. Validate deterministic replacement lock ordering and invariants under SQLite.
8. Obtain separate approval for PostgreSQL-compatible concurrency testing before claiming real contention safety.
9. Run accepted announcement, permission, route, cross-club, and complete backend regressions.
10. Validate and close the internal service foundation.
11. Scope HTTP requests/controllers/routes separately.
12. Scope frontend, notifications, links, attachments, or scheduling separately.

## 22. Documentation-only closure conditions

Milestone 3IIV changes exactly this Markdown file. Validation is limited to required-text inspection, one-file containment, `git diff --check`, staged diff checks, branch/upstream verification, and clean Git status.

Do not invoke PHP, tests, migrations, a database, or any operational workflow. This scope gate authorizes no implementation or runtime mutation.
