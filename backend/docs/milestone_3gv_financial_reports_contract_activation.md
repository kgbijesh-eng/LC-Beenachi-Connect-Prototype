# Milestone 3GV - Financial Reports Contract Activation

Base: verified 3GU commit `653cd5448108094e8314570cb681a2b6fad2c249`.

3GV is contract-only. No runtime implementation, schema, route, permission, frontend, export, or persisted report change is authorized.

## Activation decision

The first release approves these fixed keys:

1. `collections-summary`
2. `member-outstanding-balances`
3. `charge-collection-status`
4. `payment-register`
5. `receipt-register`

Every value is computed on demand from the verified Finance collection tables and model rules. Reports must not create a second ledger, persist generated output, accept arbitrary SQL, or aggregate frontend display strings. Currency is always `INR`; authoritative amounts are signed 64-bit integer paise and display strings are backend-formatted.

## Provenance matrix

| Report key | Finance source | Included / excluded statuses | Paise calculation and void treatment | Date basis and filters | Summary fields | Row fields | Roles / privacy | Decision |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `collections-summary` | `MemberCharge`, `PaymentAllocation`, `MemberPayment`, `ClubCharge` | Issued or closed obligations; only allocations whose payment is `recorded`. Draft/cancelled charges and voided-payment allocations are excluded from active collected totals. | Assessed = sum `member_charges.original_amount_paise`; collected = sum allocations joined to recorded payments; outstanding = max per obligation of assessed minus active allocations; voided payment value may be shown separately from `member_payments.amount_paise`. | Obligation basis is charge `due_date`; collection basis is `payment_date`. `date_from`, `date_to`, category. | assessed, collected, outstanding, voided payment value, charged-member count, fully-paid, partially-paid, unpaid member counts, currency. | Aggregate only. | President/Admin, Secretary, Treasurer through existing `reports.view` plus `finance.club.view`; Normal Member denied. No profile/authentication fields. | Approved. |
| `member-outstanding-balances` | `Member`, `MemberCharge`, active `PaymentAllocation`/`MemberPayment` | Issued/closed obligations; recorded payments only. Draft/cancelled obligations and voided allocations excluded. | Per member assessed, paid, and max(0, assessed-paid), all integer paise. | Charge `due_date`; `date_from`, `date_to`, category, member search, page, per-page. | assessed, paid, outstanding, member count, currency. | stable member ID, member code, display name, assessed, paid, outstanding, obligation count. | Same roles. Exclude phone, email, address, birth/family, blood, user/authentication and private notes. | Approved. |
| `charge-collection-status` | `ClubCharge`, `MemberCharge`, active allocations/payments | Charge lifecycle `issued` or `closed` by default; explicit safe charge-status filter may include draft/cancelled, whose unissued obligations must never be fabricated. Recorded payments only for collection. | Assessed = obligation originals for the charge; collected = recorded allocation sum; outstanding = max per obligation; integer paise. | Charge `due_date`; `date_from`, `date_to`, category, charge status, charge search, page, per-page. | assessed, collected, outstanding, charge count, currency. | stable charge ID, title, category, lifecycle status, member count, assessed, collected, outstanding, due date. | Same roles. Exclude descriptions, cancellation reasons, actor IDs, assignment payloads, audit and lock metadata. | Approved. |
| `payment-register` | `MemberPayment` with `Member`, `PaymentReceipt` | Both `recorded` and `voided` payments remain visible. Active-collected summary includes recorded only; voided value is separate. | Row amount is `member_payments.amount_paise`; recorded and voided totals are separate integer sums. | `payment_date`; `date_from`, `date_to`, payment status, payment mode, member search, page, per-page. | recorded amount, voided amount, payment count, currency. | stable payment ID, stable member ID, member name, payment date, mode, amount, status, safe external reference, receipt stable ID. | Same roles. Exclude idempotency key, request hash, internal note, void reason, actor IDs, credentials and audit payloads. | Approved. |
| `receipt-register` | `PaymentReceipt`, `MemberPayment`, `Member` | Issued and voided receipts remain visible. A voided payment's original receipt remains stable and is clearly `voided`. | Amount comes from the linked payment's integer `amount_paise`; issued and voided totals remain separate. | `receipt_date`; `date_from`, `date_to`, payment/receipt status, member search, page, per-page. | issued amount, voided amount, receipt count, currency. | stable receipt ID, receipt number, stable payment ID, stable member ID, receipt date, amount, active/voided state. | Same roles. Exclude financial-year internals where unnecessary, actor IDs, private payment metadata and member private fields. | Approved. |

## Exact rules

- Club scoping is applied before filters and joins. Cross-club rows must be impossible.
- Duplicate member names and duplicate charge titles are disambiguated by stable IDs.
- `MemberCharge::allocatedPaise()` is authoritative precedent: only allocations whose payment status is `recorded` count.
- Fully paid means a charged member's aggregate outstanding is zero and assessed is positive; partially paid means paid is positive and outstanding is positive; unpaid means assessed is positive and paid is zero.
- A multi-allocation payment counts once in payment summaries but each allocation contributes exactly once to its obligation and charge.
- Unknown keys return 404. Irrelevant or unknown filters return 422 rather than being ignored.
- Ordering is deterministic with stable ID tie-breakers. `per_page` defaults to 25 and is capped at 100.
- Date ranges are inclusive, `YYYY-MM-DD`, limited to 366 days, and use the source date named above.
- Empty approved reports return truthful zero summaries and empty rows.

## Browser contract

Financial entries appear in the existing Reports catalog only for an active same-club President/Admin, Secretary, or Treasurer satisfying both existing permissions. Normal Members receive neither menu entries nor API data. A role downgrade clears catalog, summary, rows, pagination and selected report state.

Before every request the UI clears stale summary, rows and pagination and enters loading. HTTP, network, forbidden, validation and conflict outcomes keep stale financial data cleared. The UI renders backend totals and never computes authoritative money. No export, PDF, Excel, CSV, schedule, email, WhatsApp, public, or multi-club control is added.

## Implementation boundary

3GW may extend the existing Reports catalog, request validation, report service/controller response, focused Finance read support where strictly necessary, routes only if the existing Reports route cannot serve the contract, `app/page.tsx`, tests, and 3GW evidence. It must not add finance source tables, mutations, report persistence, arbitrary SQL, full-accounting reports, or unrelated changes.

GO - 3GW may open after the focused and relevant Finance/Reports contract tests pass, the commit is pushed normally, local and remote hashes match, tracking is `0/0`, and the tree is clean.
