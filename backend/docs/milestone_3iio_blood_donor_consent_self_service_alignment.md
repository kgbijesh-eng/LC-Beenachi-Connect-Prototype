# Milestone 3IIO — Blood Donor Consent Self-Service Alignment

## Status and baseline

Milestone 3IIO is a narrow frontend alignment based on `9a0cf9f18e5096c24ed16a4c25533e748739ae79` from `milestone-3iin-cutover-package-activation-contract`.

It connects the existing Blood Donor Consent screen to the already-authorized profile-update request workflow. It does not change backend runtime behavior, permissions, schema, audit behavior, accounting, or operational records.

## Authoritative identity and API boundary

The screen uses the authenticated session's club and unique member linkage, then reads only the existing own-member source at the client-relative path:

`GET /clubs/{club}/members/me`

It does not scan the member directory, accept caller-controlled identity, or fall back to another member.

The existing `authenticatedFetch` client supplies the `http://127.0.0.1:8000/api/v1` base. Therefore the client-relative submission:

`POST /clubs/{club}/profile-update-requests`

resolves to the single canonical backend endpoint:

`POST /api/v1/clubs/{club}/profile-update-requests`

These are not different endpoints, and no second fetch path was introduced.

## Change-set and review controls

- The payload contains only `{ "changes": { ... } }`.
- Club context comes from the authenticated session; member identity is resolved by the backend workflow.
- No `member_id`, `user_id`, or caller-controlled `club_id` is submitted.
- Unchanged and untouched fields are omitted.
- Boolean `false` is preserved as an intentional changed value.
- Submission is disabled when neither field changed.
- Current authoritative values remain displayed after successful submission.
- A successful submission is Pending until President or Secretary review and approval.
- Changing consent from Yes to No creates a new reviewed proposal; withdrawing an existing Pending request remains a separate existing workflow.
- An existing-Pending conflict is shown without merging, retrying, or adding a second withdrawal mechanism.

## Privacy and blocked scope

The implementation exposes only the authenticated member's own values. It adds no donor directory, cross-member lookup, contact details, query-selected identity, direct member PATCH, automatic approval, notification delivery, backend mutation path, accounting work, merge, or deployment.

## Validation

Focused frontend coverage proves own-member identity resolution, unchanged submission blocking, intentional `false`, omission of untouched and identity fields, canonical client-base resolution, review wording, absence of Admin wording, authoritative-value preservation, unlinked-member fail-closed behavior, and Pending conflict handling.

Backend regression validation remains isolated to SQLite `:memory:` as configured by `backend/phpunit.xml`.
