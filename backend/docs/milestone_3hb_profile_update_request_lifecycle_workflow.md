# Milestone 3HB - Profile Update Request Submission and Review Lifecycle

Base: remotely verified 3HA commit `ac6b6d43b63791c94e29c8d597cde187ccf7920a`.

Implemented server-derived self submission, one-Pending protection, normalized allowlisted field changes, requester withdrawal, President/Admin and Secretary approval/rejection, required rejection reason, optimistic request locks, source Member/original-value stale protection, transactional approval, and audit.

Approval locks request and Member, verifies the source timestamp and every original value, applies all proposed values, marks Approved, records reviewer/time, and audits in one transaction. Stale Member returns 409, leaves request Pending and Member unchanged, and records a stale-conflict audit. Rejection and withdrawal never mutate Member. Terminal/repeated/stale-lock transitions return 409. No DELETE or generic status update exists.

PASS - 3HC may create the isolated environment and connect the frontend after remote verification.
