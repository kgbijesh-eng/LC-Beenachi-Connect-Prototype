# Milestone 3GY - Profile Update Request Scope Gate

Base: remotely verified 3GX commit `346a3750f1996b92c50f7bf5d72d4bc570811452`.

3GY is scope-only. No schema, permission, route, service, controller, frontend connection, mutation, or dummy record change is authorized.

## Current-state findings

The People > Profile Update Pending and Member Reports > Profile Update Pending surfaces truthfully state that the workflow is not connected. A separate prototype detail still contains hard-coded before/after examples and alert-based approval simulation; these must be replaced, never promoted as data. The existing Member management route is officer-only and directly updates records, so it is not a safe substitute for member self-service approval.

Members currently store profile, membership, status, contact, family, address, blood-consent, club/user linkage, and finance-adjacent identity. Existing club permission middleware fails closed for inactive and cross-club memberships. Existing `AuditLog` supports club, actor, action, auditable type/ID, and JSON metadata.

## Approved requestable field allowlist

The first release may request only these existing, non-membership-authority Member fields:

- identity presentation: `full_name`, `salutation`, `first_name`, `middle_name`, `last_name`, `gender`;
- personal profile: `birth_date`, `occupation`, `spouse_name`, `wedding_anniversary`;
- contact: `phone`, `home_phone`, `alternate_phone`;
- address: `address_line_1`, `address_line_2`, `city_locality`, `district`, `state`, `postal_code`, `country`;
- blood profile: `blood_group`, `blood_donor_consent`.

Null is a valid proposed value where the existing Member column is nullable. Values are normalized and validated using the existing Member semantics before storage.

## Prohibited and deferred fields

Requests must reject unknown fields and must never accept `id`, `club_id`, `user_id`, `member_code`, `status`, `joined_at`, `membership_type`, `membership_category`, `membership_program`, `membership_sponsor_name`, role, permissions, membership status, office assignment, finance/payment/receipt data, audit fields, timestamps, lock versions, requester/reviewer IDs, lifecycle status, password, authentication tokens, or payload-selected target member.

Both Member contact `email` and authentication email are deferred until ownership verification and login-identity migration are explicitly designed. Photo, profile document, attachment, and upload changes are deferred. Generic Member update routes must not transition request status.

## Lifecycle and duplicate rule

Statuses are exactly `pending`, `approved`, `rejected`, and `withdrawn`.

- Pending -> Approved by an authorized reviewer.
- Pending -> Rejected by an authorized reviewer with a required reason.
- Pending -> Withdrawn only by the requester.
- Terminal states cannot reopen, repeat, transition, or be deleted.

A target member may have at most one Pending request. A request may contain multiple allowed field changes so the member can submit one coherent profile correction.

## Role decision

| Role | Submit own | View own history/detail | Review queue/detail | Approve/reject |
| --- | ---: | ---: | ---: | ---: |
| President/Admin | Yes | Yes | Yes | Yes |
| Secretary | Yes | Yes | Yes | Yes |
| Treasurer | Yes | Yes | No | No |
| Normal Member | Yes | Yes | No | No |

All permissions require an active same-club membership and a Member row linked to the authenticated user for self actions. No role label alone grants access.

## Privacy and response boundary

A requester may see only their own requests and exact own before/after values. President/Admin and Secretary may see the same-club review queue and exact values in detail. Treasurer and Normal Member receive no queue data. Queue summaries expose stable request/member IDs, member code/display name, status, submitted timestamp, and changed field keys; sensitive values appear only in authorized detail. Other Member fields, user/authentication data, documents, finance, and unrelated audit payloads are excluded.

## Stale-member and optimistic-lock rule

Each request records a source Member snapshot fingerprint/version, exact original values, exact proposed values, submitted timestamp, requester, target Member, club, lifecycle lock version, reviewer/decision timestamp, and rejection reason where applicable.

Approval locks the request and Member in one transaction and verifies every relevant current Member value still equals the stored original value. Any mismatch returns 409 conflict, leaves the Member and request Pending, writes no approval audit, and requires withdrawal/resubmission. No silent overwrite or automatic rebase is approved.

## Audit and review process

Submission, withdrawal, approval, rejection, and stale-conflict attempts are auditable. Approval applies all changes and marks the request Approved in one transaction. Rejection and withdrawal never mutate Member data. Audit metadata stores safe stable IDs, field keys, lifecycle before/after, and exact before/after values only where the existing audit visibility is authorized; credentials and unrelated private fields are never copied.

## API direction

Approved route direction is the orchestration route set under `/api/v1/clubs/{club}/profile-update-requests`, with `/me` history/detail, self submission/withdrawal, and officer queue/detail/approve/reject. No DELETE route exists. Club, requester, target Member, reviewer, status, timestamps, audit fields, and source version are server-derived.

Queue filters may include status, member search, changed field, date range, page, and per-page. Ordering is newest submitted first with stable request ID tie-breaker. Own history uses the same deterministic order and pagination.

## Implementation boundaries and non-goals

3GZ may finalize exact permissions, route payloads, response shapes, masking, validation, filters, and conflict responses. 3HA may add only schema/models/permissions/read routes. 3HB may add the approved lifecycle mutations. 3HC may replace disconnected/dummy frontend surfaces with the real API.

No direct member self-update, multiple Pending requests, reopen, deletion, reviewer impersonation, email change, authentication migration, upload, document change, role/status/membership change, finance change, public queue, cross-club review, bulk approval, or notification delivery is approved.

GO - 3GZ may finalize the authorization and API contract from this scope.
