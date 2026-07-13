# Club Account Statement Read-Only API Contract

**Status:** Proposed and unimplemented. This document registers no route and authorizes no runtime work.

## 1. Endpoint and access

Proposed route:

```text
GET /api/v1/clubs/{club}/finance/account-statements/{account}
```

Proposed permission: `finance.club.statement.view` (pending DR-017). Authentication, active same-club membership, route-bound club authorization, and account ownership by the same club are mandatory. Normal Members are denied. A foreign-club account identifier must fail closed as `404` after route permission succeeds; responses must not disclose another club's account or transaction existence.

The endpoint is read-only and must not post, correct, reverse, allocate, issue a receipt, mutate an audit record, or lazily backfill data.

## 2. Query contract

| Parameter | Type | Proposed rule |
|---|---|---|
| `date_from` | `YYYY-MM-DD` | Inclusive; defaults only after financial-year decision approval |
| `date_to` | `YYYY-MM-DD` | Inclusive; must be on/after `date_from` |
| `page` | integer | Default 1, minimum 1 |
| `per_page` | integer | Default 50, range 1-100 |
| `direction` | enum | Optional `debit` or `credit` |
| `source_type` | enum | Optional allowlisted source type; no free-form class names |
| `reference` | string | Optional exact normalized human/source reference |
| `member_id` | integer | Optional only when caller is authorized for member finance detail and member belongs to route club |

The maximum requested span is proposed as one financial year and requires approval with DR-002. No request may silently truncate a period. Filters apply before opening/movement/closing calculation except that opening always includes all posted account lines before `date_from`; transaction-only filters such as direction, reference, source, or member affect returned rows and filtered movement metadata but must not redefine the account's authoritative opening or closing. The API must clearly return both account totals and filtered-row totals when transaction-only filters are used.

Ordering is fixed and not client-selectable: posting date, effective date, journal transaction ID, line sequence, journal line ID, all ascending. Cursor pagination may replace page pagination before implementation if it preserves stable ordering and absolute running balances; that is a compatible contract refinement only before activation.

## 3. Success response

Every monetary field is integer paise. Display strings are optional presentation conveniences and must never replace paise fields.

```json
{
  "data": {
    "statement_id": "stmt_v1_opaque",
    "club_id": 10,
    "account": {
      "id": "acct_opaque",
      "code": "BANK-PRIMARY",
      "name": "Primary Bank Account",
      "category": "asset",
      "normal_balance": "debit"
    },
    "currency": "INR",
    "period": {
      "date_from": "2027-04-01",
      "date_to": "2027-04-30",
      "financial_year": "2027-28"
    },
    "coverage": {
      "status": "complete",
      "authoritative_from": "2027-04-01",
      "message": null
    },
    "opening_balance_paise": 125000,
    "transactions": [
      {
        "journal_transaction_id": "jtxn_opaque",
        "journal_line_id": "jline_opaque",
        "reference": "CAS/2027-28/000001",
        "posting_date": "2027-04-03",
        "effective_date": "2027-04-03",
        "description": "Sanitized illustrative member receipt",
        "debit_paise": 50000,
        "credit_paise": 0,
        "signed_movement_paise": 50000,
        "running_balance_paise": 175000,
        "source": {
          "type": "member_payment",
          "id": "source_opaque",
          "receipt_id": "receipt_opaque",
          "receipt_number": "EXAMPLE/2027-28/RCP/0001",
          "links_available": true
        },
        "reversal": {
          "is_reversal": false,
          "reverses_transaction_id": null,
          "reversed_by_transaction_id": null
        }
      }
    ],
    "period_debit_paise": 50000,
    "period_credit_paise": 0,
    "period_signed_movement_paise": 50000,
    "closing_balance_paise": 175000
  },
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 50,
    "total": 1,
    "order": "posting_date,effective_date,journal_transaction_id,line_sequence,journal_line_id",
    "generated_at": "2027-05-01T00:00:00Z",
    "contract_version": "proposed-v1"
  }
}
```

The example is synthetic and conveys no operational data. `statement_id` must be a deterministic opaque fingerprint of club, account, period, filters, coverage version, and contract version, not a persisted mutable statement record. Source and receipt links are returned only when independently authorized; otherwise IDs, numbers, and links are `null` or omitted without changing journal values.

## 4. Empty and exceptional responses

### Complete but empty

Return `200` with an empty `transactions` array, truthful opening and closing balances, zero period debit/credit/movement, and `coverage.status = "complete"`. Empty rows do not imply a zero opening balance.

### Partial history

Return `409 Conflict` with code `statement_history_incomplete` unless an approved contract decision permits `200` with non-authoritative balances omitted. Recommended response:

```json
{
  "message": "Authoritative statement history is incomplete for the requested period.",
  "error": {
    "code": "statement_history_incomplete",
    "authoritative_from": "2027-04-01",
    "requested_from": "2026-04-01"
  }
}
```

No opening or closing value is returned as zero.

### Unavailable

Return `503 Service Unavailable` with code `statement_source_unavailable`, retry metadata where appropriate, and no monetary payload. A disabled or uninitialized journal may return `409 statement_not_initialized` rather than `404`; final choice requires API review.

### Standard errors

| Status | Meaning |
|---|---|
| `401` | Missing or invalid authentication |
| `403` | Active route-club membership or statement permission absent |
| `404` | Route club/account absent, inactive, or foreign to authorized club; fail closed |
| `422` | Malformed dates, reversed range, unsupported filter, invalid page size, or out-of-club member filter |
| `409` | Historical coverage or journal initialization prevents authoritative response |
| `503` | Authoritative statement service temporarily unavailable |

Validation errors follow Laravel's established `message` and `errors` shape. Error details must not contain SQL, model classes, account existence across clubs, internal notes, private member data, or secrets.

## 5. Request examples

```http
GET /api/v1/clubs/10/finance/account-statements/acct_opaque?date_from=2027-04-01&date_to=2027-04-30&page=1&per_page=50
Accept: application/json
```

```http
GET /api/v1/clubs/10/finance/account-statements/acct_opaque?date_from=2027-04-01&date_to=2027-04-30&direction=credit
Accept: application/json
```

## 6. Balance and pagination guarantees

- Opening and closing use all posted account lines required by the authoritative period, independent of page.
- Each returned running balance is absolute after that row in complete statement order; page two does not restart.
- Reversed originals and reversal entries remain visible according to a single approved presentation rule. Net effects always reconcile.
- Draft and voided-before-posting records never affect values.
- The endpoint executes no write and emits no financial mutation audit event. Read-access auditing, if required by DR-019, must not alter journal data.
- Concurrent postings may change later requests. A response must use a transactionally consistent snapshot or return a snapshot/version token so rows and totals cannot disagree.

## 7. Privacy, compatibility, and versioning

Descriptions must exclude internal notes by default. Member identity is absent unless specifically authorized and requested. Source links use opaque IDs and permission-aware URLs. Export is deferred and not implied by this API.

The first implemented response must be versioned under `/api/v1` and carry `contract_version`. Fields may be added compatibly; meaning, sign conventions, ordering, coverage semantics, and integer-paise fields cannot change silently. Breaking changes require a new API version and migration plan. Before activation, reviewers may refine this proposal without compatibility guarantees.

## 8. Current-state citations

- Existing route and middleware conventions: `backend/routes/api.php`.
- Existing paginated financial filters and stable register rows: `FinancialReportService` and `ReportCatalog`.
- Existing self/club privacy split: `FinanceReadService::memberPayments`, `ownPayments`, and `paymentRow`.
- Existing same-club and permission enforcement: `EnsureClubPermission`; `ClubPermissionService`.
- Existing monetary and idempotency conventions: finance collection migration and `FinanceWriteService::recordPayment`.

None of these citations means the proposed endpoint or journal currently exists.
