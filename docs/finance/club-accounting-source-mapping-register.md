# Club Accounting Source Mapping Register

**Status:** Proposed design register. All mappings are **Pending** unless they restate an approved DR-001–DR-025 rule. No integration or posting is authorized.

## 1. Source-truth classes

- **Operational source:** current integer-paise `ClubCharge`, `MemberCharge`, `MemberPayment`, `PaymentAllocation`, and `PaymentReceipt` workflows.
- **Internal capability:** inert provisioning, posting, reversal, close, activity, and period-movement services; no routes or source hooks.
- **Read model only:** legacy member-ledger and club finance report services; not journal sources.
- **Prototype/unsupported:** frontend Fellowship, Collection Box, fund, donation, Special/Voluntary Collection, and expense concepts unless an authoritative backend workflow is separately approved.

Every future integration must run atomically with or reliably outbox-bind the authoritative source transition, use same-club opaque identities, post exactly once, and preserve source and journal histories.

## 2. Proposed mapping register

| Workflow | Authoritative source / trigger | Proposed debit | Proposed credit | Effective date | Source / idempotency identity | Reversal or void | Approval and isolation | Readiness / blockers | Initial release |
|---|---|---|---|---|---|---|---|---|---|
| Member charge issuance | `FinanceWriteService::issue`; `ClubCharge` plus created `MemberCharge` obligations; transition to issued | `MEMBER-RECEIVABLES`, aggregated or supported per obligation | Approved due-category mapped income or liability | Approved charge issue/economic date; rule Pending | `club_charge:{id}:issued:v{lock}`; deterministic event key and UUID derived/stored by integration | Cancellation/correction requires linked journal reversal or approved adjustment semantics; no deletion | Due-category mapping owner and same-club member/account checks | Operational source exists; category map, atomic integration, effective-date rule Pending | Recommended after mapping approval |
| Member payment receipt | `FinanceWriteService::recordPayment`; recorded fully allocated `MemberPayment` | Selected real `CASH-ON-HAND` or `BANK-<ALIAS>` | `MEMBER-RECEIVABLES` | Actual `payment_date` after open-period validation | `member_payment:{id}:recorded`; reuse source idempotency plus journal-specific identity | Payment void creates linked opposite journal; replacement is a separate posting | Payment-mode-to-asset selection must be explicit, same club, and approved | Source is ready; real asset selection and integration absent | Recommended after asset mapping |
| Payment allocation | `PaymentAllocation` rows created atomically with payment | No second debit | No second credit | N/A | Referenced in payment metadata/reconciliation, not a posting identity | Follows payment reversal | Exact allocation total, obligation/member/club ownership | Operational and fully allocated | Included as subledger settlement evidence only |
| Payment void | `FinanceWriteService::void`; recorded → voided | Opposite of original payment credit | Opposite of original payment debit | Approved void effective date in open period | `member_payment:{id}:voided:v{lock}` | Existing payment remains; linked reversal; replacement posts independently | Void permission, reason, same-club replacement | Operational void exists; journal link absent | Required with payment integration |
| Charge cancellation/correction | `FinanceWriteService::cancel` or future correction event | Opposite original charge credit | Opposite `MEMBER-RECEIVABLES` debit | Cancellation/correction date under period policy | `club_charge:{id}:cancelled:v{lock}` | Linked full reversal; paid issued charges remain blocked under current source rules | Finance management approval and club scope | Source cancellation exists; journal semantics Pending | Required with charge integration |
| Expense payment | No approved authoritative expense-payment source | Approved expense account | Selected real cash/bank asset | Actual approved payment date | Future expense-payment ID/event/idempotency | Linked reversal and corrected payment; no payable accrual initially | Expense authorization, evidence, payee privacy, club scope | Blocked: workflow and hierarchy absent | Excluded |
| Unrestricted donation | No authoritative donation workflow | Selected real cash/bank asset | `INCOME-DONATION` | Actual receipt date | Future donation receipt event | Linked reversal/refund policy required | Donation approval, donor privacy, club scope | Blocked | Excluded |
| Restricted donation | No authoritative donation workflow | Selected real cash/bank asset | `LIABILITY-RESTRICTED-DONATION` | Actual receipt date | Future restricted-donation event | Linked reversal; release/use needs separate mapping | Restriction evidence and accounting approval | Blocked | Excluded |
| Collection Box | No authoritative counted/approved collection event | Selected real cash/bank asset | Pending approved income or restricted liability | Approved count/deposit date, not UI entry time | Future sealed count/approval event | Linked reversal with count correction evidence | Dual count/approval, custody, club scope | Blocked by DR-008 and absent source | Excluded |
| Special Collection charge | Existing UI label only; may later be an issued `ClubCharge` category | `MEMBER-RECEIVABLES` only if it creates enforceable member obligations | Pending: unrestricted income or restricted liability according to approved purpose | Approved issuance/economic date | Future approved charge-category event | Charge cancellation reversal | Business/accounting classification and category owner | Treatment Pending; must not be assumed ordinary dues | Excluded pending decision |
| Voluntary Collection | Existing UI/prototype only; future actual-receipt event | Selected real cash/bank asset | `INCOME-VOLUNTARY-COLLECTION` only if approved unrestricted income | Date actually received | Future voluntary-receipt event | Linked reversal/refund | Must remain voluntary; no member obligation or dues | Blocked by absent source and policy | Excluded |
| Fellowship / Monthly Family Fellowship | Frontend session state only; explicitly not shared backend records | Pending asset or receivable treatment | Pending income/liability treatment | Pending lifecycle | Future authoritative Fellowship event | Pending cancellation/finalization reversal rules | Membership privacy, sponsor lifecycle, expense and fund policy | Blocked by DR-009 and absent authoritative source | Excluded |
| Internal cash/bank transfer | Future approved transfer record with source and destination asset IDs | Destination asset | Source asset | Actual controlled transfer date | `asset_transfer:{id}:completed`; unique event and idempotency | Linked full reversal; never delete one side | Both accounts active and same club; maker/checker approval | Journal mapping approved in DR-012; source workflow absent | Excluded until source exists |
| Opening balance | Future approved immutable opening package / activation event | Approved debit opening accounts | Approved credit opening accounts | Exactly approved 1 July | `accounting_opening_package:{package-id}:activated`; package-bound UUID | Linked full reversal and separately approved replacement package | Full cutover gate, manifest, separation of duties, same club | Contract only; approval schema and package absent | One-time, only after activation approval |
| Manual journal | No source; deliberately absent | N/A | N/A | N/A | N/A | N/A | Separate high-control contract would be mandatory | Recommended prohibited initially | Excluded |

**Source mappings: 15.** Only the charge, payment, allocation, void, and cancellation source domains currently exist. None is connected to the internal journal.

## 3. Required mapping controls

Each approved mapping must identify its owner, mapping version, debit and credit account IDs, effective-date rule, source transition, idempotency identity, reversal event, permission, approval threshold, and coverage start. Mapping changes are prospective; they never rewrite posted history. A source event is rejected if any account is inactive, foreign-club, wrong currency, unmapped, or ambiguous.

Payment mode (`cash`, `bank`, `UPI`) is not sufficient to select a ledger account. The recording workflow must explicitly resolve the real destination asset. UPI routed to a bank is posted to that bank, not to a synthetic UPI account, unless accounting review approves a separately reconcilable clearing account.

## 4. Illustrative postings only

### Member charge

| Account | Debit paise | Credit paise |
|---|---:|---:|
| `MEMBER-RECEIVABLES` | 50,000 | 0 |
| `INCOME-MEMBER-DUES` | 0 | 50,000 |

### Fully allocated payment

| Account | Debit paise | Credit paise |
|---|---:|---:|
| `BANK-OPERATING` | 50,000 | 0 |
| `MEMBER-RECEIVABLES` | 0 | 50,000 |

The allocation creates no additional cash or bank line.

### Internal transfer

| Account | Debit paise | Credit paise |
|---|---:|---:|
| `BANK-OPERATING` | 20,000 | 0 |
| `CASH-ON-HAND` | 0 | 20,000 |

### Linked reversal of the illustrative payment

| Account | Debit paise | Credit paise |
|---|---:|---:|
| `MEMBER-RECEIVABLES` | 50,000 | 0 |
| `BANK-OPERATING` | 0 | 50,000 |

All examples are fictional, balanced, integer-paise, and do not imply approval.

## Unsafe shortcuts rejected

- Dynamically combining charges, payments, allocations, receipts, and legacy ledgers into an authoritative statement.
- Treating a receipt as income or an allocation as a second cash entry.
- Posting unallocated receipts while DR-007 rejects them.
- Mapping payment mode to a generic bank account without explicit asset identity.
- Treating Special Collection as ordinary dues, or Voluntary Collection as a receivable.
- Posting unsupported donations, Collection Box, Fellowship, or expenses.
- Backfilling incomplete historical events or activating before idempotency is proven.

## 5. Pending decisions

Due-category ownership, Special/Voluntary Collection, donation restrictions, Fellowship, expense hierarchy, asset selection, effective-date rules, atomic delivery mechanism, historical backfill, and all initial-release mappings remain Pending.
