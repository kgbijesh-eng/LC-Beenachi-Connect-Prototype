# Club Chart of Accounts Contract

**Status:** Approved template semantics only under DR-028, with qualifications. The 24-row catalog is inactive and unprovisioned. Exact account instances, mappings, opening values, and activation remain unapproved. Nothing here provisions a record or authorizes a posting.

## 1. Contract boundary

This contract refines the approved append-only, club-scoped, INR double-entry direction in DR-001 through DR-025. It separates:

- **Approved policy:** account types, normal-balance semantics, Lions Year, separate real cash/bank accounts, immutable codes, and club isolation.
- **Implemented internal capability:** `ledger_accounts` and the inert `LedgerAccountProvisioningService` support club-scoped accounts, INR, canonical codes, active state, replay safety, and type-compatible normal balances. The pure, immutable internal catalogue version `CLUB-COA-2026-01` represents the approved 24 template semantics without invoking provisioning or creating records. No route, command, trigger, operational account instance, mapping, opening value, or activation is exposed or approved.
- **Proposed-plan validation boundary:** immutable policy `INACTIVE-PROVISIONING-PLAN-2026-01` produces only deterministic `PROPOSED-UNVERIFIED` descriptions derived from catalogue `CLUB-COA-2026-01`. Plans remain inactive, unprovisioned, unauthorized for creation, and unauthorized for activation. Opaque references are neither fetched nor verified and confer no approval. Validation performs no database access, and the existing provisioning service remains unchanged and unauthorized for this path.
- **Existing operational source:** charges, member obligations, fully allocated payments, allocations, and receipts exist independently of the journal.
- **Proposed future design:** the catalog below.
- **Unsupported workflow:** any source not listed as operationally ready in the source-mapping register.
- **Approved template policy:** the corrected catalog semantics, catalog ownership, cash structure, presentation grouping, and expense reporting categories are approved with the qualifications recorded in DR-028, DR-029, DR-031, DR-032, and DR-040.
- **Pending or future-gated detail:** exact account instances, bank aliases, source mappings, opening values, opening-equity selection, provisioning, and activation.

An account code is trimmed, converted to ASCII uppercase, 2–40 characters, and must match `^[A-Z][A-Z0-9]*(?:-[A-Z0-9]+)*$` under DR-025. Codes are immutable and club-scoped. Names may change only through a future controlled process that preserves history. No real bank account number, branch detail, credential, or supporting schedule belongs in source control.

## 2. Proposed coding convention

Codes use a semantic prefix (`BANK`, `INCOME`, `EXPENSE`, `LIABILITY`) and stable approved short segments. Codes do not encode account numbers, member identities, balances, or mutable organizational labels. Bank codes use an approved non-sensitive alias, for example `BANK-OPERATING`, never the real account number.

## 3. Proposed account catalog

All values are proposals. “Required” means required if the associated workflow is approved for initial activation. “Configurable” means the club may select a name or instantiate additional accounts only through an approved catalog process. Direct manual posting is prohibited for every row.

| Proposed code | Proposed name | Type / normal | Currency | Purpose and supported source workflows | Initial activation | Club-configurable | Approval | Privacy | Reconciliation | Manual posting |
|---|---|---|---|---|---|---|---|---|---|---|
| `CASH-ON-HAND` | Cash on Hand | Asset / Debit | INR | One physically controlled cash holding; receipts, approved expenses, transfers, opening | Required if verified cash exists | One initial holding approved | Approved template; inactive/unprovisioned | High | Signed count to evidence | Prohibited |
| `BANK-OPERATING` | Operating Bank Account | Asset / Debit | INR | One non-sensitive alias for one real bank holding; payments, expenses, transfers, opening | Required if verified bank exists | Exact alias Pending | Approved template; inactive/unprovisioned | High | Independent statement reconciliation | Prohibited |
| `BANK-<APPROVED-SHORT-CODE>` | Additional Bank Account | Asset / Debit | INR | Separate account for each additional real bank holding | Conditional on private inventory | Yes, controlled | Approved template; inactive/unprovisioned | High | Per-account evidence | Prohibited |
| `MEMBER-RECEIVABLES` | Member Receivables Control | Asset / Debit | INR | Issued member charges, allocated payment settlement, opening control total | Required for approved charge integration | No | Approved template; inactive/unprovisioned | High | Exact member subledger control | Prohibited |
| `OTHER-RECEIVABLES` | Other Receivables | Asset / Debit | INR | Only a future approved non-member receivable workflow | No | Controlled | Approved template; inactive/unprovisioned | High | Independent schedule | Prohibited |
| `ADVANCES-PAID` | Advances Paid | Asset / Debit | INR | Only a future authorized advance-payment and settlement workflow | No | Controlled | Approved template; inactive/unprovisioned | High | Advance register | Prohibited |
| `LIABILITY-RESTRICTED-COLLECTION` | Restricted Collection Liability | Liability / Credit | INR | Restricted collections whose use is constrained | Conditional | Controlled | Approved template; inactive/unprovisioned | High | Restriction-by-source schedule | Prohibited |
| `LIABILITY-RESTRICTED-DONATION` | Restricted Donation Liability | Liability / Credit | INR | Restricted donation receipts after workflow approval | No | Controlled | Approved template; inactive/unprovisioned | High | Donor restriction schedule | Prohibited |
| `ACCOUNTS-PAYABLE` | Accounts Payable | Liability / Credit | INR | Only a future approved accrual/payable workflow | No | Controlled | Approved template; inactive/unprovisioned | High | Supplier obligation schedule | Prohibited |
| `OPENING-BALANCE-EQUITY` | Opening Balance Equity | Equity / Credit | INR | Candidate one-time opening offset; not a balancing shortcut | Conditional | No | Approved template; inactive/unprovisioned | High | Full opening package | Prohibited |
| `ACCUMULATED-SURPLUS` | Accumulated Surplus or Deficit | Equity / Credit | INR | Candidate retained historical result if accounting review approves meaning | Conditional | No | Approved template; inactive/unprovisioned | High | Approved historical reconciliation | Prohibited |
| `INCOME-MEMBER-DUES` | Membership Dues Income | Income / Credit | INR | Issued ordinary membership dues mapped by approved category | Conditional | No | Approved template; inactive/unprovisioned | Medium | Charge-category reconciliation | Prohibited |
| `INCOME-SPECIAL-COLLECTION` | Special Collection Income | Income / Credit | INR | Only if Special Collection is approved as unrestricted earned income | No | Controlled | Approved template; inactive/unprovisioned | Medium | Charge/receipt schedule | Prohibited |
| `INCOME-VOLUNTARY-COLLECTION` | Voluntary Collection Income | Income / Credit | INR | Voluntary amounts only when actually received; never member dues | No | Controlled | Approved template; inactive/unprovisioned | Medium | Receipt/source reconciliation | Prohibited |
| `INCOME-DONATION` | Unrestricted Donation Income | Income / Credit | INR | Unrestricted donations after authoritative donation workflow approval | No | Controlled | Approved template; inactive/unprovisioned | High | Donation register | Prohibited |
| `INCOME-FELLOWSHIP` | Fellowship and Member Activity Income | Income / Credit | INR | Only after Fellowship lifecycle and classification approval | No | Controlled | Approved template; inactive/unprovisioned | High | Fellowship source schedule | Prohibited |
| `EXPENSE-ADMINISTRATION` | Administration Expense | Expense / Debit | INR | Candidate category for future approved expense-payment workflow | No | Controlled | Approved template; inactive/unprovisioned | Medium | Payment and authorization evidence | Prohibited |
| `EXPENSE-SERVICE-ACTIVITY` | Service Activity Expense | Expense / Debit | INR | Candidate service-project expenditure category | No | Controlled | Approved template; inactive/unprovisioned | Medium | Project/payment evidence | Prohibited |
| `EXPENSE-FELLOWSHIP` | Fellowship and Member Activity Expense | Expense / Debit | INR | Candidate only after Fellowship expense policy approval | No | Controlled | Approved template; inactive/unprovisioned | Medium | Fellowship/payment evidence | Prohibited |
| `EXPENSE-DISTRICT-OBLIGATION` | District and International Obligations | Expense / Debit | INR | Candidate assessed obligations paid through approved workflow | No | Controlled | Approved template; inactive/unprovisioned | Medium | Assessment/payment evidence | Prohibited |
| `EXPENSE-MEETINGS-EVENTS` | Meetings and Events Expense | Expense / Debit | INR | Candidate event expenditure category | No | Controlled | Approved template; inactive/unprovisioned | Medium | Event/payment evidence | Prohibited |
| `EXPENSE-BANK-CHARGES` | Bank Charges Expense | Expense / Debit | INR | Bank charges supported by independent bank evidence and approved workflow | No | Controlled | Approved template; inactive/unprovisioned | High | Bank reconciliation | Prohibited |
| `EXPENSE-FUNDRAISING` | Fundraising Expense | Expense / Debit | INR | Approved costs of fundraising activities; future approved fundraising expense-payment workflow | No | Controlled | Approved template; inactive/unprovisioned | Medium | Fundraising payment and authorization evidence | Prohibited |
| `EXPENSE-CLUB-PROPERTY-FACILITIES` | Club Property, Hall and Facilities Expense | Expense / Debit | INR | Utilities, routine maintenance, and ordinary repairs; future approved property/facilities expense-payment workflow. Property acquisition, equipment, construction, and major improvements require separate asset/capital assessment and must not automatically post here | No | Controlled | Approved template; inactive/unprovisioned | Medium | Property/facilities payment and authorization evidence | Prohibited |

**Proposed account count: 24.** Conditional templates do not authorize instantiation. No active unallocated-receipt account is proposed because DR-007 rejects unallocated payments initially. No suspense account is proposed; unresolved differences block activation.

## 4. Account lifecycle and ownership

DR-028 approves the corrected catalog as template semantics only. Treasurer Ranjith M. R. is the catalog custodian under DR-029; material accounting changes require President approval and accounting validation. Provisioning remains separated from approval and activation. Inactivation prevents future posting but preserves historical reads. Accounts with approved zero opening positions may be provisioned and omitted from the opening journal; positive-only journal lines make explicit zero entries invalid.

Bank provisioning requires a non-sensitive alias, account purpose, independent evidence reference stored outside source control, preparer, verifier, approver, and activation date. Cash structure must identify each physically distinct controlled holding; the default recommendation is one `CASH-ON-HAND` account unless custody or location requires separately reconcilable accounts.

## 5. Fund treatment decision

The labels General Club, Service Activity, and Fellowship & Member Activity are not verified accounting balances and the current journal schema has no fund dimension.

| Option | Advantages | Limitations and migration/reporting impact |
|---|---|---|
| A — separate ledger accounts per fund | Works in current schema; simple account-level statements | Multiplies accounts, obscures natural classification, makes transfers and consolidated reporting cumbersome, and risks treating presentation labels as legal restrictions |
| B — future fund/dimension model | Preserves natural accounts while enabling fund reporting, restrictions, and consolidated views | Requires schema, posting, validation, mapping, migration, privacy, reconciliation, and reporting changes before use |
| C — presentation groupings only | No schema change; can group approved accounts in UI | Derived groupings can misstate restrictions or balances and cannot enforce fund integrity |

**Approved with qualification under DR-032:** use Option C initially. General Club is reporting only; Service Activity and Fellowship & Member Activity are internally designated reporting groupings, except that separately evidenced donor or external restrictions retain their genuine restricted character. The groupings create no separate ledgers, asset balances, automatic liabilities, legal restrictions, or opening balances. A future dimension requires separately demonstrated operational need and approval. No frontend fixture balance may establish a fund balance.

## 6. Activation controls

An account is eligible for activation only after its code, name, type, normal balance, purpose, source mappings, privacy, reconciliation owner, opening position, and required evidence are approved. Provisioning and activation must be idempotent, club-scoped, audited, and reversible only before any posting. After first posting, the account and its history are retained permanently; corrections occur through journal reversals, not account deletion.

## Unsafe shortcuts rejected

- Using the member ledger as the general ledger.
- Treating frontend fund sections as accounting accounts or balances.
- Assuming missing opening balances are zero.
- Combining real bank holdings in one generic bank account.
- Provisioning unallocated-receipt or suspense shortcuts to absorb unexplained differences.
- Inventing exact expense categories before policy approval.
- Allowing direct manual journals during initial activation.
- Treating receipt records as complete income.

## 7. Approval boundary

The corrected 24-row catalog is approved only as inactive template semantics and is represented internally by immutable catalogue version `CLUB-COA-2026-01`. This version is not a Lions Year, operational chart version, activation approval, or club-account instance. Initial activation is limited to the smallest verified subset: real asset accounts, one member-receivable control, explicitly approved and mapped income/liability accounts, exactly one reviewed opening-equity choice, and expense accounts only when an approved payment workflow exists. Exact bank instances and aliases, mappings, opening values, the DR-033 equity selection, provisioning, and activation remain unapproved.
