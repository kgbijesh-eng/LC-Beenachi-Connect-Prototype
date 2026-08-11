# Milestone 3IIQ — Announcements Domain and Authorization Contract

## Identity and result

- Source branch: `milestone-3iip-personal-profile-documents-navigation-alignment`
- Exact baseline: `af15258bad78f9e384b7855317fb93cdf5368897`
- Milestone branch: `milestone-3iiq-announcements-domain-authorization-contract`
- Scope: documentation only

This milestone prepares the announcement domain and business-owner decision contract. It records no approval and authorizes no runtime implementation.

## Repository evidence

The audit reviewed the current announcement navigation, screens, detail scaffolding, fabricated arrays and unsupported-state tests; authenticated club and role context; permissions and middleware; member privacy; Events, Meetings, Projects, Reminders and Notifications boundaries; audit logging and optimistic-locking conventions; multi-club isolation; roadmap and prior announcement requirements.

Findings:

- Announcements are in the original V1 plan but have no authoritative backend implementation.
- No announcement model, schema, API, route, permission, service, or operational source exists.
- The frontend truthfully reports that announcements are not connected and suppresses legacy fabricated rows.
- Prototype values such as `Published`, `Draft / Pending`, `High Priority`, `Members`, and `Sponsors` are not approved domain rules.
- Existing `/api/v1/clubs/{club}/...`, active-membership, permission middleware, nested ownership, resource shaping, optimistic locking, transactional audit, and synthetic-test patterns are reusable engineering evidence only.
- Publication, notification delivery, visibility, acknowledgement, attachments, scheduled execution, and related domain records require explicit separation.

No operational data was accessed.

## Documents created

- `backend/docs/announcements/club-announcements-domain-authorization-contract.md`
- `backend/docs/announcements/club-announcements-decision-register.md`
- `backend/docs/milestone_3iiq_announcements_domain_authorization_contract.md`

No existing file, runtime file, configuration, test, migration, model, permission, route, controller, service, frontend file, or accounting contract is changed.

## Decision status

The companion register contains AN-001 through AN-025 exactly once. Every decision is `Pending`. Recommendations are not approvals and do not establish implementation authority.

The contract specifically preserves these unresolved boundaries:

- distinct drafter, checker, and publisher authority;
- distinct maker/checker actors bound to the same immutable version when applicable;
- semantic `club-members` and `officers` audiences without role-label inference;
- explicit Draft, Published, Withdrawn, and Archived lifecycle proposals;
- Expired as a proposed effective visibility state whose persistence remains Pending;
- scheduled time never being proof of publication;
- Urgent priority never being proof of delivery;
- plain Unicode text and proposed Unicode-character limits remaining unapproved recommendations;
- UTC authoritative instants and club-timezone display, with `Asia/Kolkata` expected only for LC Beenachi and never hard-coded globally;
- external links, attachments, notifications, read receipts, and retention remaining separately controlled decisions.

## Recommended conditional sequence

1. Business-owner review of AN-001 through AN-025.
2. Separate documentation correction recording only explicit approvals.
3. Announcement database/read-API scope gate.
4. Explicit schema, permission, and read-API authorization.
5. Database and read-only API foundation using synthetic SQLite `:memory:` data.
6. Mutation and lifecycle authorization.
7. Draft, approval, publication, withdrawal, replacement, and archive workflow.
8. Frontend source-truth integration and removal of fabricated announcement data.
9. Browser role, privacy, failure-state, and cross-club verification.
10. Optional notification-delivery contract only if separately approved.
11. Attachments or read receipts only through separate future contracts.

## Explicit exclusions

Milestone 3IIQ does not implement or authorize announcements, notification delivery, WhatsApp, SMS, email, push, schedulers, attachments, shared-document storage, operational-data access, accounting work, merge, or deployment. Legacy fabricated rows remain suppressed.

## Validation contract

Closure validation must confirm that only these three Markdown files changed; AN-001 through AN-025 exist exactly once and all are Pending; recommendations are never called approved; plain Unicode/no-HTML and title/body recommendations are documented; UTC storage is separated from club-timezone display; `Asia/Kolkata` is not a global default; scheduled time is not publication; effective expiry is not scheduler execution; Urgent is not delivery; maker/checker actors are distinct when applicable; no existing/runtime/test file changed; the source branch remained unmodified; and `git diff --check` passes.
