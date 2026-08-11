# Milestone 3GP - Finance Authorization and API Contract

Base: verified 3GO commit `65b675a0d1e01dd0834bb42b629635cee1a12e59`.

Decision: GO for 3GQ read foundation only. No runtime implementation is authorized in 3GP.

## Authorization

| Role | `finance.self.view` | `finance.club.view` | `finance.manage` | `finance.void` |
| --- | --- | --- | --- | --- |
| President/Admin | yes | yes | yes | yes |
| Secretary | yes | yes | yes | no |
| Treasurer | yes | yes | yes | yes |
| Normal Member | yes | no | no | no |

Every route requires Sanctum, active same-club membership, route-derived club, and the explicit permission. Self routes resolve the authenticated user's same-club Member linkage and never accept another member ID. Inactive, missing-member-link, cross-club, and nested-ownership failures are concealed or forbidden according to existing club middleware; no data is returned.

## Money and shared response rules

- Persist and calculate integer `amount_paise`; minimum charge/payment/allocation is 1.
- Currency is `INR`.
- Responses expose both `*_paise` integers and backend-formatted `*_display` strings such as `1250.50`; clients never derive authoritative totals.
- Financial year is validated `YYYY-YY` and must match the April–March year containing the charge date or payment date where applicable.
- Dates are `YYYY-MM-DD` interpreted in club timezone; timestamps are ISO-8601 UTC.
- List endpoints use `page` and `per_page` (1–100), deterministic date/ID ordering, and standard pagination metadata.
- IDs, not titles or names, identify charges, obligations, payments, allocations, receipts, and members.

## Controlled values

Charge categories: `membership_dues`, `installation_contribution`, `event_contribution`, `service_project_contribution`, `other_contribution`.

Charge statuses: `draft`, `issued`, `closed`, `cancelled`.

Computed member-charge states: `unpaid`, `partially_paid`, `paid`.

Payment modes: `cash`, `upi`, `bank_transfer`, `cheque`, `other`.

Payment statuses: `recorded`, `voided`.

Receipt statuses: `issued`, `voided`.

## GET routes authorized for 3GQ

Management (`finance.club.view`):

```text
GET /api/v1/clubs/{club}/finance/charges
GET /api/v1/clubs/{club}/finance/charges/{charge}
GET /api/v1/clubs/{club}/finance/members/{member}/summary
GET /api/v1/clubs/{club}/finance/members/{member}/charges
GET /api/v1/clubs/{club}/finance/members/{member}/payments
GET /api/v1/clubs/{club}/finance/payments/{payment}
GET /api/v1/clubs/{club}/finance/receipts/{receipt}
```

Self (`finance.self.view`):

```text
GET /api/v1/clubs/{club}/finance/me/summary
GET /api/v1/clubs/{club}/finance/me/charges
GET /api/v1/clubs/{club}/finance/me/payments
```

Filters:

- charges: `status`, `category`, `financial_year`, `due_from`, `due_to`, `q`, `page`, `per_page`;
- member charges: `state`, `status`, `category`, `financial_year`, `due_from`, `due_to`, `page`, `per_page`;
- payments: `status`, `mode`, `date_from`, `date_to`, `page`, `per_page`.

Unknown or unsupported filters return 422. Date ranges are inclusive and may not exceed 366 days.

## Mutation routes authorized only for 3GR

```text
POST  /api/v1/clubs/{club}/finance/charges
PATCH /api/v1/clubs/{club}/finance/charges/{charge}
POST  /api/v1/clubs/{club}/finance/charges/{charge}/issue
POST  /api/v1/clubs/{club}/finance/charges/{charge}/close
POST  /api/v1/clubs/{club}/finance/charges/{charge}/cancel
POST  /api/v1/clubs/{club}/finance/members/{member}/payments
POST  /api/v1/clubs/{club}/finance/payments/{payment}/void
```

There is no DELETE, receipt-create route, generic status mutation, refund, reversal-to-bank, export, PDF, gateway, or Reports route.

## Charge payloads and lifecycle

Create Draft:

```json
{
  "title": "Annual dues 2026-27",
  "description": "Optional operational description",
  "category": "membership_dues",
  "amount_paise": 125000,
  "due_date": "2026-07-31",
  "financial_year": "2026-27",
  "member_ids": [12, 15],
  "assign_all_active_members": false
}
```

Exactly one assignment mode is required. Member IDs must be unique, active, and same-club. Create rejects club, status, actors, totals, sequence, timestamps, or initial version overrides. Draft update accepts the same editable business fields plus required `lock_version`; it never accepts status.

Issue requires `lock_version` and creates one MemberCharge per selected member in one transaction. Repeat issue conflicts. Close requires Issued, zero outstanding across all obligations, and `lock_version`. Cancel requires reason and `lock_version`; an Issued charge may cancel only with zero non-voided allocations. Closed/Cancelled are terminal.

## Payment and receipt contract

```json
{
  "payment_date": "2026-07-10",
  "payment_mode": "upi",
  "external_reference": "UPI-REF-100",
  "amount_paise": 75000,
  "allocations": [
    {"member_charge_id": 41, "amount_paise": 50000},
    {"member_charge_id": 44, "amount_paise": 25000}
  ],
  "internal_note": "Optional management note",
  "idempotency_key": "5aa66f62-4fd7-4a0e-93bf-429c7348074d"
}
```

The route member is authoritative. Allocations must be unique, positive, same-club, same-member, open, and not exceed locked outstanding amounts. Their exact integer sum equals payment amount. References are required for `upi`, `bank_transfer`, and `cheque`; cash/other may omit them. Credentials and secrets are rejected.

One transaction locks obligations and creates payment, allocations, receipt, ledger effects, and audit. Receipt number is `{NORMALIZED_CLUB_CODE}/{YYYY-YY}/RCP/{0001}`, unique per club, monotonic within financial year, immutable, and never reused.

The idempotency key is required and unique within a club. Store a canonical request hash. An identical replay returns HTTP 200 with the original payment/receipt and no new audit; first creation returns 201. Reuse with a different canonical payload returns 409.

Void payload:

```json
{
  "reason": "Recorded against wrong member",
  "lock_version": 1,
  "replacement_payment_id": null
}
```

Only `finance.void`; Secretary is denied. Void locks the payment and obligations, requires Recorded state and a nonblank reason, releases all allocation effects transactionally, marks payment and receipt voided, increments version, and audits. Receipt remains readable. Repeat void conflicts. A separately recorded same-club/member replacement may be linked once; no record is deleted.

## Response shapes

Member summary:

```json
{"data":{"member_id":12,"currency":"INR","assessed_paise":200000,"assessed_display":"2000.00","paid_paise":75000,"paid_display":"750.00","outstanding_paise":125000,"outstanding_display":"1250.00","open_obligations":2}}
```

Payment detail includes stable payment/member IDs, status, mode, safe reference, amount, allocations with MemberCharge IDs, receipt ID/number/status, idempotency key only when management-authorized, replacement links, version, actor IDs, and timestamps. It excludes bank/UPI credentials and unrestricted audit payloads.

## Status and error behavior

- 200 successful reads, replayed idempotent payment, and successful transitions.
- 201 created Draft charge or first payment.
- 401 unauthenticated.
- 403 inactive membership, missing permission, or prohibited self/cross-member access.
- 404 unknown stable ID, nested ownership mismatch, or concealed cross-club resource.
- 422 field validation, unsupported filter, invalid category/mode/reference, assignment, amount, or allocation sum.
- 409 stale version, invalid/repeated lifecycle transition, changed outstanding balance/allocation race, terminal record, already-voided payment, or idempotency-key payload mismatch.

Failures create no partial rows, receipt, ledger effect, or success audit.

## Audit, privacy, and deferrals

All successful mutations write the 3GO-approved audit action in the same transaction. Read routes follow existing audit policy and add no new read audit. Internal notes are management-only. Normal Member responses omit actor, idempotency, internal-note, and void-reason internals not needed for own history.

Legacy Finance Reports and `/finance/reports`, balance-sheet UI claims, financial exports, receipt PDF activation, and Reports `collections-summary` are outside this domain and must not be presented as activated by 3GQ–3GU.

## Implementation boundaries

3GQ adds schema/models/permissions/GET routes only. 3GR adds the exact mutation routes and services. 3GS integrates the central Finance and member-profile UI only after isolated API accuracy passes. 3GP changes only this document and its governance test.
