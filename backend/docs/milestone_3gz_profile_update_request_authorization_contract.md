# Milestone 3GZ - Profile Update Request Authorization and API Contract

Base: remotely verified 3GY commit `dab66744bf820b3b7ae525fb475828485790e29a`.

3GZ is contract-only. No permission seeding, schema, model, route, controller, service, frontend, or runtime behavior is added here.

Permissions are `profile_updates.self.submit`, `profile_updates.self.view`, `profile_updates.review`, and `profile_updates.approve`. President/Admin and Secretary receive all four. Treasurer and Normal Member receive self.submit and self.view only: `Treasurer | Yes | Yes | No | No`; `Normal Member | Yes | Yes | No | No`. All require active same-club membership; self routes require a linked same-club Member. Existing role labels, `members.manage`, and `audit_logs.view` grant nothing implicitly.

Allowed keys are exactly `full_name`, `salutation`, `first_name`, `middle_name`, `last_name`, `gender`, `birth_date`, `occupation`, `spouse_name`, `wedding_anniversary`, `phone`, `home_phone`, `alternate_phone`, `address_line_1`, `address_line_2`, `city_locality`, `district`, `state`, `postal_code`, `country`, `blood_group`, and `blood_donor_consent`. Birth/family, phone, address, blood group, and consent values are restricted; other allowed values are standard. Unknown/prohibited keys return 422. `email`, IDs, member code, user linkage, status, joined date, membership classifications/program/sponsor, role, permissions, finance, audit, timestamps, credentials, documents, photos, and uploads are prohibited.

`POST /api/v1/clubs/{club}/profile-update-requests` accepts `{ "changes": { "phone": "+91 90000 00000" } }`. Changes are required, associative, 1-20 entries, allowed keys only, normalized with existing Member rules, and must differ after normalization. Club, target Member, requester, source version, reviewer, status, timestamps, audit fields, and lock version are server-derived/prohibited. One Pending request per Member; duplicate returns 409. Success stores deterministic field rows and returns 201 requester detail.

Own reads are `GET /api/v1/clubs/{club}/profile-update-requests/me` and `/me/{profileUpdateRequest}`. Review reads are `GET /api/v1/clubs/{club}/profile-update-requests` and `/{profileUpdateRequest}`. Own filters are status/date/page/per-page; queue adds member search and changed field. Unknown/irrelevant filters return 422. Dates are inclusive and limited to 366 days. `per_page` defaults to 25 and is capped at 100. Ordering is `submitted_at desc, id desc`. Nested scope mismatches return 404.

List rows expose stable request/member IDs, code/name, status, submitted/decision timestamps, and field keys—never restricted values. Authorized detail adds ordered field key, classification, typed original/proposed values, requester ID, reviewer display name, rejection reason, lock version, source Member timestamp/fingerprint, and timestamps. Own routes expose only own records. Review values are President/Admin and Secretary only. No authentication, unrelated Member, document, finance, credential, IP/user-agent, or audit payload is returned.

Withdraw is `POST .../{profileUpdateRequest}/withdraw` with `lock_version`; requester-only Pending transition and no Member mutation. Approve is `POST .../{profileUpdateRequest}/approve` with `lock_version`; requires `profile_updates.approve`, locks request and Member, verifies Pending/lock/source timestamp/every original value, applies all fields transactionally, decides, increments lock, and audits. Reject is `POST .../{profileUpdateRequest}/reject` with lock version and a required explanation, 3-500 characters; no Member mutation. There is no generic request update and no DELETE route. Terminal states never reopen.

Conflicts are 409: stale request lock; duplicate Pending; stale Member (`Member profile changed after submission. Withdraw and resubmit.`); or terminal transition. Stale-member conflict leaves Member unchanged and request Pending. Approval rolls back Member, request, and audit together on failure.

3HA should add `profile_update_requests` and `profile_update_request_changes`. Request fields include club, Member, requester, status, source updated-at/fingerprint, reviewer, submitted/decided timestamps, rejection reason, unsigned lock version, and timestamps. Change rows include request, field key, classification, JSON original/proposed values, timestamps, and unique request/field key. Arbitrary payload persistence is prohibited.

Audit actions are `profile_updates.submitted`, `profile_updates.withdrawn`, `profile_updates.approved`, `profile_updates.rejected`, and `profile_updates.stale_conflict`. 3HA may implement schema, models, permissions, reads, filters, resources, and tests only—no POST mutation route. 3HB may add only the contracted lifecycle. 3HC may connect the UI only after backend verification.

GO - 3HA may implement the database and read foundation from this exact contract.
