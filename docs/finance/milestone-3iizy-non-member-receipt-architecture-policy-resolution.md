# Milestone 3IIZY — Non-Member Receipt Architecture and Ordinary Income Policy Resolution

**Status:** Policy resolved under DR-064. Documentation only; no production capability is implemented or authorized.

## 1. Core decision

Adopt **shared posting infrastructure plus specialized source-domain workflows**. A universal incoming-money form is unsafe because the credit side depends on why money was received. Completely duplicated ledger infrastructure per receipt domain would repeat tenant, period, fund, currency, idempotency, concurrency and journal controls. The shared internal component may enforce those invariants, but only an approved specialized domain source may supply economic meaning and provenance.

A journal alone is insufficient: source records retain user-facing history, subtype and payer provenance, lifecycle, privacy boundary, correction linkage, domain reporting, and the evidence explaining why the credit account was valid.

## 2. Receipt taxonomy

| Category | Treatment |
|---|---|
| A — Ordinary non-member income | Future bounded generic source under DR-064 |
| B — Dedicated domain receipt | Donation, Fellowship and Collection Box remain separate sources |
| C — Restricted/liability receipt | External restrictions, custodial money, refundable deposits/advances and conditional funding require separate contracts and reviewed mappings |
| D — Settlement/correction receipt | Member receivable settlement remains the member-payment source; vendor refunds, reimbursements and recoveries require linked source/correction policy |
| E — Complex accounting event | Asset disposal and similar gain/loss events require their own accounting contract |

Incoming cash is the debit-side asset fact only. It never establishes that the credit is income.

## 3. Ordinary Non-Member Income Receipt

An ordinary receipt is actual money received that:

- economically belongs to the club and is earned, unrestricted and non-refundable;
- settles no member or other receivable;
- creates no liability, custody, repayment, beneficiary or continuing restriction;
- is posted to one tenant-selected eligible income account whose meaning matches the receipt;
- uses one approved canonical fund on both lines;
- occurs in INR on a valid date within open authoritative accounting coverage; and
- is not assigned to a separately governed source below.

The bounded subtype catalogue is `sponsorship`, `advertising`, `event_proceeds`, `interest`, `facility_income`, and `other_approved_ordinary_income`. `Other approved` is not a universal Other Income account: it requires an explicit description and a valid tenant-selected income account. Subtype aids provenance and reporting but never determines or overrides the ledger account.

### Included only when the definition is satisfied

- Unrestricted sponsorship after any earning condition is satisfied and with no refund or restricted-use obligation.
- Earned advertising consideration; tax-enabled or unearned arrangements remain excluded.
- Ordinary event participation, ticket/coupon or stall proceeds that belong unrestrictedly to the club. Event identity is optional metadata, not an accounting classification.
- Bank interest credited to an eligible club bank account.
- Earned facility or hall income without deposit, advance, tax or refund complexity.
- Another specifically described ordinary receipt mapped to an eligible income account and not captured by an excluded domain.

Sponsorship is not automatically a donation. A gratuitous sponsor contribution follows the donation policy; a restricted, refundable, conditional or unearned sponsorship is outside ordinary income. A single fundraising event may produce sponsorship, advertising, sales, donations, Collection Box receipts and restricted money; event provenance never collapses those sources into one accounting type.

## 4. Explicit exclusions

- **Donations:** DR-010/DR-038 require donor provenance, privacy, anonymity, restriction, acknowledgement, refund and beneficiary controls. Shared posting primitives may be reused later, but Donation remains a separate source.
- **Restricted donations or sponsorship:** treatment may be restricted income, liability or another reviewed classification; ordinary receipt cannot decide it.
- **Fellowship:** DR-009/DR-039 lifecycle, member obligation, carry-forward, surplus and refund questions require a dedicated source.
- **Collection Box:** DR-008 custody, count, approval and evidence cannot be bypassed.
- **Grants:** excluded unless a later contract resolves unrestricted, restricted, deferred and project conditions.
- **Asset sales:** derecognition and gain/loss prevent a two-line generic income assumption.
- **Deposits and advances received:** refundable or unearned money is not ordinary income.
- **Member payments:** canonical settlement of member receivables remains the member-payment/allocation workflow.
- **Refunds and recoveries:** correction of a prior expense and a later business recovery are distinct and require a linked policy; neither defaults to income.
- **Voluntary/Special Collections:** DR-036/DR-037 continue to govern member obligation, purpose and restriction; they do not enter this non-member source by relabelling.
- **Tax-enabled receipts:** tax decomposition is deferred until a tax contract exists.

## 5. Accounting and fund treatment

```text
Debit  receiving active same-club CASH_AND_BANK asset
Credit active same-club eligible INCOME account
```

Both lines carry the same single canonical fund and amount. The source cannot submit different line funds or raw journal lines, and the posting service cannot add an automatic balancing entry or hidden inter-fund movement. This increases cash/bank, income and net assets within that fund; it creates no special statement arithmetic and changes the snapshot fingerprint. It is not an inter-fund transfer mechanism.

The destination asset and income account must be active, same-club, INR, valid for posting and canonically classified. No account code is hard-coded. The income account must have account type `income` and canonical statement classification `INCOME`; cash/bank must be an asset classified `CASH_AND_BANK`. Cash/bank, receivable, liability, equity/net-assets and expense accounts are explicitly ineligible as the credit account.

## 6. Canonical source contract

Required facts:

- public receipt UUID, club, subtype and accounting/receipt date;
- receiving cash/bank public ID and income-account public ID;
- one canonical fund, positive integer-paise amount and current currency constraint;
- non-empty description, tenant-scoped idempotency UUID, creator, resulting journal and timestamps.

Optional facts:

- payer/source name; required by product validation for sponsorship, advertising and facility income, optional for interest and genuinely anonymous ordinary cash proceeds;
- external/bank/invoice/agreement reference;
- non-sensitive event/project label where useful for provenance.

Prohibited facts include caller-supplied club ID, currency override, debit/credit direction, raw journal lines, donation/restriction flags used to force ordinary treatment, internal numeric IDs, tax breakdown, customer/vendor master identity and sensitive bank data.

UUIDs and public account IDs are trimmed and lowercase-normalized; subtype and fund use exact canonical values; text is trimmed and empty optional values become null. Date, accounts, fund, amount, subtype, description, payer when present, external reference and event label participate in the canonical digest. Club scope is enforced by the tenant uniqueness key and every lookup, not accepted from payload.

## 7. Authority, history and privacy

Future permissions are resolved as:

| Role | Record ordinary receipt | View ordinary non-sensitive history |
|---|---:|---:|
| President/Admin | Yes | Yes |
| Treasurer | Yes | Yes |
| Secretary | No | Yes |
| Normal Member | No | No |

Recording is limited to the two roles already authorized for internal asset movement and source-specific expense reversal. Secretary receives read-only ordinary financial traceability, not posting authority. Donation permissions and privacy remain independent and cannot be inferred from this matrix.

History/detail must be tenant-scoped, read-only, paginated and deterministically ordered by accounting date then stable source ID. Bounded filters may cover date, subtype, receiving account, income account, fund, exact reference and text without cross-tenant leakage. Public representation may include source UUID, date, accounts, fund, amount, currency, subtype, description/reference, permitted payer display, creator display, journal reference and timestamps; never internal numeric IDs.

## 8. Immutability, correction and refund boundary

A posted ordinary receipt cannot be edited or deleted. An accounting error uses a source-specific linked full reversal preserving original account, fund and amount, followed optionally by a new replacement source under DR-013, DR-021, DR-023 and DR-062. A valid business refund after a correct original receipt is a different economic event and remains separately contracted; it must not masquerade as error correction.

UUID plus journal reference is sufficient for initial public traceability. Printable/statutory receipt numbering, donation acknowledgements and exports remain later contracts.

## 9. Idempotency, atomicity and concurrency

Exact same-club key and canonical payload returns the original source and journal. A materially changed replay conflicts and cannot mutate or repost. The same UUID may be reused independently by another club.

Future implementation must create source, journal, lines and source-to-journal binding in one transaction; use tenant-scoped database uniqueness plus locking; and prove on disposable PostgreSQL that simultaneous exact retries create one source/journal/effect, changed retries produce one winner and one conflict, cross-tenant UUID reuse succeeds, and controlled posting failure leaves no source, journal or lines.

## 10. Minimum next implementation

**Milestone 3IIZZ — Tenant-Scoped Ordinary Non-Member Income Receipt**

Objective: implement only the DR-064 ordinary receipt source.

In scope: migration/model; options, create, list and detail API; eligible cash/bank, income-account and same-fund validation; open-period and activation controls; immutable source; exact/changed replay; atomic canonical journal; officer UI; statement/fingerprint integration; SQLite and disposable-PostgreSQL concurrency/rollback proof; documentation.

Out of scope: donations, restrictions/liabilities, Fellowship, Collection Box, grants, member receipts, refunds/recoveries, asset sales, deposits/advances, tax, attachments, approvals, reconciliation, printable receipt numbering/export, multi-currency and manual journals.

Acceptance requires tenant isolation; President/Admin and Treasurer recording; President/Admin, Secretary and Treasurer ordinary-history viewing; debit receiving asset and credit eligible income with equal same-fund INR paise; no raw lines; open authoritative coverage; one immutable source and journal; replay/concurrency safety; statement cash/income/net-assets increase and fingerprint change; full regressions; and no specialized-source bypass.

## 11. Remaining questions

No question blocks the bounded ordinary-income implementation. Non-blocking deferred questions are the accounting mappings and lifecycle rules for restricted sponsorship, donations, grants, deposits, tax-enabled receipts, business refunds/recoveries, asset disposal, Fellowship and Collection Box. Exclusion keeps those uncertainties outside DR-064 rather than resolving them by unsafe default.
