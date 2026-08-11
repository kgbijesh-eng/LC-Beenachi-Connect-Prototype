# Club Announcements Domain and Authorization Contract

## Status and authority

This is a proposed documentation contract for business-owner review. It records no approval and authorizes no implementation. Every announcement decision is Pending in the companion decision register.

No authoritative announcement model, table, API, route, permission, controller, service, or operational data source exists. The frontend's disconnected state is truthful, and all legacy fabricated announcement rows must remain suppressed.

## Domain boundary

An announcement is a club-scoped, authoritative in-app publication record. It is not an event, meeting, project, reminder, notification delivery, message, document, attachment, or read receipt.

- Publication controls whether an announcement is authoritatively visible in the application.
- Notification delivery is a separate, optional future capability. Publication does not prove email, WhatsApp, SMS, push, or other delivery, and delivery failure must not silently change publication truth.
- Visibility does not prove delivery, acknowledgement, or reading.
- An optional stable reference to another domain may be considered later, but must not copy or replace that domain's authoritative record.
- Scheduled publication describes a requested future instant only. Merely reaching `scheduled_publish_at` does not make a record Published and is not proof that a scheduler or explicit publication action ran.
- Urgent priority does not imply notification delivery.

## Proposed lifecycle semantics — Pending

The proposed explicit lifecycle states are:

- **Draft:** editable, unpublished management record.
- **Published:** explicitly published authoritative record.
- **Withdrawn:** previously published record removed from current publication with a recorded reason, while history is retained.
- **Archived:** retained historical record removed from ordinary current workflows by an explicit authorized action.

**Expired** is a proposed effective visibility state, not unquestionably a stored mutation. A published record may become effectively expired when the current instant reaches `expires_at`. That read-time result does not imply a scheduler ran. Whether Expired is persisted through a controlled transition or derived by read queries remains Pending under AN-025. The contract must distinguish stored lifecycle status from derived visibility status.

Publication, withdrawal, and archival must be explicit actions if later approved. No DELETE route, generic status patch, silent substantive rewrite, or automatic scheduled transition is approved. A substantive published correction is recommended to use a linked replacement so prior meaning remains auditable.

## Proposed authority model — Pending

Authoring authority and publication authority are separate decisions. The current recommendation is that President and Secretary may draft and that President or Secretary may publish, subject to AN-003.

Whenever maker/checker applies:

- the same actor cannot both complete the draft and provide independent publication approval;
- both decisions must bind the same immutable announcement version;
- a material content, audience, priority, structured link, or timing change invalidates prior checker approval;
- routine announcements may use a single publisher only if AN-003 is explicitly approved that way.

No authority is inferred from frontend labels, names, prototype navigation, another module's permissions, or this recommendation.

## Proposed audience semantics — Pending

- `club-members`: visible to eligible active members of the owning club when lifecycle visibility permits.
- `officers`: restricted to members satisfying a future authoritative, club-scoped officer-audience policy.

Officer status must not be inferred from frontend labels, free-text positions, or hard-coded LC Beenachi names. An officer-audience announcement is not automatically visible to all club members. Whether audiences may be combined remains subject to a future approved schema contract.

Public, sponsor, Board-only, committee, named-member, district, cross-club, and guest audiences are deferred. Board-only publication is unsafe until an authoritative Board-membership source exists.

## Proposed content contract — Pending

The first-release recommendation is plain Unicode text with preserved line breaks:

- Malayalam and English Unicode text must be supported.
- No raw HTML or Markdown rendering.
- No embedded scripts, images, iframes, tracking content, or content-generated links.
- Any later-approved external link remains a separate structured field under AN-014.
- Rich formatting requires a separate sanitization and security contract.

Recommended fields are a required trimmed title of 1–160 Unicode characters and required trimmed body of 1–5,000 Unicode characters. Limits mean Unicode characters, not bytes. Whitespace-only content is invalid. Callers cannot bypass server validation. Optional structured external-link label and HTTPS URL exist only if AN-014 is later approved. Filename, attachment, HTML, embedded media, and recipient data do not belong inside the body.

## Time contract — Pending

The recommendation is to store authoritative publication, scheduling, expiry, withdrawal, and archival instants in UTC and interpret or display them through the owning club's approved timezone. LC Beenachi is expected to use `Asia/Kolkata`, but no future multi-club SaaS implementation may hard-code that timezone globally.

Expiry must be strictly later than actual publication. Scheduled time does not prove publication. Effective expiry may be derived at read time and does not prove scheduler execution. The persistence strategy for Expired remains Pending under AN-025.

## Links, attachments, and storage

External links are not approved. The Pending recommendation is an optional, visibly labelled, deterministically validated HTTPS structured field. It must not be interpreted as an attachment, filename, storage identifier, or evidence that content is stored by LC Connect.

Attachments, uploads, downloads, previews, shared-document storage, file content, OCR, embedded media, and storage URLs are unsupported. Any future attachment capability requires a separate storage, malware, authorization, privacy, retention, and delivery contract.

## Privacy, tenancy, and authorization requirements

Any future implementation must:

- derive club identity from authenticated route/application context;
- require active same-club membership;
- apply club, lifecycle, and audience scope before caller filters;
- fail closed for wrong-club, concealed, missing, or malformed identities;
- reject payload-controlled club, actor, lifecycle, audit, and authorization identities;
- avoid exposing Draft or officer-only records to ordinary members;
- keep private actor and audit metadata out of ordinary member responses;
- use stable backend identifiers rather than title, time, list order, or frontend keys;
- preserve tenant isolation in lists, details, search, audit, and retention handling.

Semantic audiences should later map through club policy rather than hard-coded LC Beenachi role names. A platform operator must receive no default access to announcement content. Club timezone and retention may be configurable within approved platform minimums.

## Concurrency and audit recommendation — Pending

Existing repository patterns support optimistic `lock_version` concurrency, explicit lifecycle actions, row ownership checks, and one same-transaction audit record for each successful mutation. Rejected or stale actions should write no success audit. Audit should preserve actor, target, version, material before/after state, action, and club scope without unnecessarily exposing content to unauthorized users.

These are reusable engineering patterns, not approved announcement implementation decisions.

## Explicit exclusions

This contract does not authorize runtime code, tests, schema, permissions, API routes, frontend changes, operational-data access, notification delivery, scheduler behavior, attachments, shared-document storage, read receipts, accounting work, merge, or deployment.
