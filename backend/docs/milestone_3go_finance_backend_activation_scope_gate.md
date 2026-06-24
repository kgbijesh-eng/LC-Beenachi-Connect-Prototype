# Milestone 3GO - Finance Backend Activation Scope Gate

Base: remotely verified Reports closure commit `2ab1e9d46a91b2a1580cdad7e6a7761b78a0df01`.

Decision: GO for 3GP contract approval only. This milestone adds no runtime Finance implementation.

## Repository findings

The repository contains a legacy member-ledger, payment, receipt, summary, PDF, club-finance-report, and frontend prototype lane. It proves useful authorization, audit, receipt-number, member identity, and INR conventions, but it is not the approved collections architecture:

- `payments.amount` and `member_ledger_entries.amount` are decimal columns, while several services convert them to PHP `float`, use `round`, `array_sum`, and floating comparisons.
- Existing payments are not allocated to stable member obligations and have no idempotency key or optimistic version.
- Existing receipts are generated separately rather than transactionally with an allocated payment.
- Existing payment statuses and modes include legacy values outside this roadmap.
- The frontend contains disconnected arrays and JavaScript-number finance demonstrations.
- Existing Finance Reports use float aggregation and remain unsupported. Reports `collections-summary` stays deferred.
- Receipt numbering already establishes club code plus April–March financial year (`LCB/2026-27/RCP/0001`).

The new collection domain must not silently reinterpret legacy rows as obligations or report totals. Compatibility reads may remain available until the new frontend replaces them, but new authoritative collection balances come only from the approved tables and services.

## First-release boundary

Finance means member collections, not full accounting. It supports controlled club charges, member obligations, allocated payments, outstanding balances, receipts, void-and-replacement correction, member self-service reads, President/Secretary/Treasurer operations, audit, locking, and idempotency.

It excludes expenses, vendors, budgets, ledger journals, double entry, profit and loss, balance sheets, cashbooks, bank import/reconciliation, payment gateways, automatic UPI or cheque verification, refunds, online links, recurring debit, fees, interest, discounts, tax, inventory, payroll, multi-currency, attachments, receipt PDF/email/WhatsApp, exports, financial Reports, and deletion.

## Approved domain

### ClubCharge

Stable backend ID; same-club scope; category, title, description, amount in paise, due date, financial year, lifecycle timestamps, actors, and `lock_version`.

Lifecycle:

- Draft -> Issued
- Draft -> Cancelled
- Issued -> Closed
- Issued -> Cancelled only before any allocation

Draft financial terms are editable. Issued amount, category, member set, and financial year are immutable. Closed and Cancelled are terminal. Generic update cannot change status. No deletion.

Controlled categories:

- `membership_dues`
- `installation_contribution`
- `event_contribution`
- `service_project_contribution`
- `other_contribution`

### MemberCharge

One stable member obligation generated transactionally when a ClubCharge is issued. It stores original paise, same-club member and charge IDs, and versioning. Allocated and outstanding paise and `unpaid`, `partially_paid`, or `paid` state are backend-computed from non-voided allocations.

### MemberPayment

Stable payment ID with member, payment date, controlled mode, optional external reference/internal note, total paise, idempotency key, actor, status, replacement linkage, void metadata, and `lock_version`.

Lifecycle is Recorded -> Voided. Member, amount, date, mode, and allocations are immutable. Correction is void plus an optional replacement payment; the original remains visible. No deletion.

Controlled modes are `cash`, `upi`, `bank_transfer`, `cheque`, and `other`. References are required for UPI, bank transfer, and cheque. No credentials or secrets are stored.

### PaymentAllocation

Stable allocation ID linking one payment to one open MemberCharge for the same club and member. Positive paise only; no over-allocation; total allocation must exactly equal payment total; no unallocated credit. Payment, allocations, receipt, ledger effects, and audit are one transaction with obligation row locks.

### PaymentReceipt

Stable receipt ID created transactionally with every payment. Receipt number is unique within the club, immutable, never reused, and follows the established club-code/April–March-year sequence. A voided payment leaves its receipt readable with a clear void state. No deletion or editable amount.

## Money, currency, and year

All new persisted and calculated money uses signed 64-bit integer minor units (`amount_paise`). Input and output contracts must provide unambiguous paise integers and formatted two-decimal INR strings. JavaScript and PHP floating-point arithmetic are forbidden for authoritative totals.

Currency is club-scoped single-currency `INR`; no conversion. The repository has no reusable club-term entity. First release therefore stores validated `financial_year` as `YYYY-YY`, follows the existing April–March receipt convention, and attaches historical records permanently to that value. A future club-term model may replace validation only through a separate migration.

## Permissions

Approved slugs:

- `finance.self.view`
- `finance.club.view`
- `finance.manage`
- `finance.void`

President/Admin: all four. Secretary: self, club, manage; no void. Treasurer: all four. Normal Member: self only. Inactive memberships and cross-club access fail closed. Backend permissions, never frontend labels, authorize actions.

## Privacy and member-profile workflow

Management reads expose collection fields required for operations, not addresses, birth data, bank credentials, UPI secrets, identity documents, or private member notes. Normal Members see only their own obligations, payments, receipts, and balance and cannot enumerate another member.

Authorized management member profiles show assessed, paid, outstanding, open obligations, recent payments, receipts, and Record Payment preselected by stable member ID. Normal Member sees the equivalent own read-only section with no record/void controls. Member names are display labels only.

## Integrity, correction, and audit

Mutations use database transactions, row locking, `lock_version`, deterministic 409 conflicts, and rollback without success audit. Payment recording requires a unique club-scoped idempotency key; an identical replay returns the original result, while a changed payload using the same key conflicts without a duplicate payment or receipt.

Audit actions:

- `finance.charge.created`
- `finance.charge.updated`
- `finance.charge.issued`
- `finance.charge.closed`
- `finance.charge.cancelled`
- `finance.payment.recorded`
- `finance.payment.voided`
- `finance.payment.replaced`

Audit metadata uses IDs, states, paise amounts, and safe before/after values; it excludes credentials and unrestricted notes.

## Milestone boundaries

- 3GP: contract documentation/tests only.
- 3GQ: schema, models, controlled values, permissions, and GET APIs only; no mutation routes.
- 3GR: transactional charge/payment/allocation/receipt/void workflows; no frontend or Reports changes.
- 3GS: isolated SQLite and Finance/member-profile frontend integration.
- 3GT: full browser, role, privacy, identity, failure, and exact-paise verification with focused corrections.
- 3GU: two-file closure review only.

Financial Reports remain disabled throughout 3GO–3GU. Activating `collections-summary` requires a separate post-3GU milestone.

## Governance

3GO may change only this document and `Milestone3goFinanceBackendActivationScopeGateTest.php`. Before the commit, the test inspects the current diff; afterward it locates the exact subject `Add finance backend activation scope gate` and verifies historical changed-file scope without network access.
