# Milestone 3HA - Profile Update Request Database and Read Foundation

Base: remotely verified 3GZ commit `c2c79d64aef423fbfecb5b7755d6cb6e9d6d9d71`.

Implemented normalized `profile_update_requests` and `profile_update_request_changes` tables, stable models/relationships, portable one-Pending guard, source timestamp/fingerprint, lifecycle lock version, exact JSON before/after values, and field classification. Four contract permissions are seeded: all roles receive own submit/view; only President/Admin and Secretary receive review/approve.

Read-only routes now provide own paginated history/detail and President/Secretary same-club queue/detail. Filters are status, inclusive date range, member search, changed field, page, and capped per-page as applicable. Unknown/irrelevant filters fail 422. Ordering is newest submitted then stable ID.

List rows expose only stable request/member identity, status/timestamps, and changed field keys. Exact restricted values appear only in requester-owned or authorized reviewer detail. Cross-club and nested ownership mismatches fail closed. Missing linked Member, inactive membership, Normal Member queue access, and Treasurer queue access are denied.

No submission, withdrawal, approval, rejection, generic update, or DELETE route exists in 3HA. No dummy request is seeded. No frontend is changed.

PASS - 3HB may implement only the contracted lifecycle mutations after this read foundation is remotely verified.
