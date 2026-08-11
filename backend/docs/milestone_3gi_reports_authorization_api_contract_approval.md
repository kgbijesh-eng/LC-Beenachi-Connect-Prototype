# Milestone 3GI - Reports Authorization and API Contract Approval

Base: verified 3GH commit `cff5b5e573bd6bf16b91f169d4c46a53befed3bd`.

3GI is contract-only. No runtime Reports implementation is authorized.

## Catalog and permissions

Approved keys: `membership-summary`, `events-register`, `projects-register`, `reminders-register`. Deferred and absent: `collections-summary` and every unsupported prototype report.

`reports.view` is assigned to President/Admin, Secretary, and Treasurer. Normal Member has no Reports permission, API, or menu. `reports.finance` is reserved but not seeded or used until a financial report passes a separate decimal/provenance gate.

## Routes and identity

- `GET /api/v1/clubs/{club}/reports`
- `GET /api/v1/clubs/{club}/reports/{reportKey}`

Fixed whitelist only. Unknown/deferred keys return 404. Report labels never identify reports. No POST, PATCH, or DELETE route exists.

## Filters

Common fields are accepted only when listed for the selected key:

- `date_from`, `date_to` as inclusive `YYYY-MM-DD` club-timezone dates;
- `status`;
- Events `type`;
- Projects `record_type` and `service_category`;
- Reminders `priority` and `is_all_day`;
- `q`, `page`, and `per_page` (default 25, maximum 100).

Membership Summary supports only `status`. Irrelevant or unknown filters return 422.

Club date boundaries are inclusive in the club timezone. Timestamp sources convert those boundaries to UTC. All-day source values remain date-only. Maximum date range is 366 days.

## Response

```json
{
  "data": {
    "key": "projects-register",
    "title": "Projects & Activities Register",
    "description": "Verified club-scoped register.",
    "generated_at": "UTC ISO-8601",
    "club_timezone": "Asia/Kolkata",
    "date_basis": "documented per report",
    "applied_filters": {},
    "summary": {},
    "rows": []
  },
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 25,
    "total": 0
  }
}
```

Membership Summary is aggregate-only and omits row pagination. Approved empty reports return 200, meaningful zero counts, and empty rows. Unsupported-source zeros are forbidden.

## Privacy and authorization

Active same-club membership, route-derived club, `reports.view`, fixed key, and key-specific field shaping are mandatory. Cross-club requests fail closed. Rows expose stable source IDs and purpose-minimum fields only.

Never expose contact details, addresses, DOB, blood group, identity documents, bank/UPI details, private descriptions, cancellation reasons, actor IDs, or audit metadata.

## Caching, persistence, audit, and finance

No report table, saved result, snapshot, scheduled job, persistent cache, export, or mutation. Compute against current verified source records.

General report reads follow existing non-audited read conventions. No new read-audit mechanism is introduced. Financial reporting remains deferred; `collections-summary` is absent and no JavaScript or PHP floating-point report total is approved.

## Boundaries

3GJ may implement catalog definitions, `reports.view` seeding, active club authorization, fixed key resolution, common validation/date utilities, catalog endpoint, shared response contract, and tests. It must not execute complete report queries.

3GK may implement only the four approved report services and report endpoint after 3GJ is remotely verified. It must not implement finance, frontend, exports, persistence, or arbitrary query construction.

GO - 3GJ may open after 3GI and 3GH tests pass, separate commit/push, matching remote hash, and clean tracking.
