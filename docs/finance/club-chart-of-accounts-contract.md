# Club Chart of Accounts Contract

**Status:** Proposed design only. Every account and new business choice in this document is **Pending** until explicit business-owner and accounting-review approval. Nothing here provisions a record or authorizes a posting.

## 1. Contract boundary

This contract refines the approved append-only, club-scoped, INR double-entry direction in DR-001 through DR-025. It separates:

- **Approved policy:** account types, normal-balance semantics, Lions Year, separate real cash/bank accounts, immutable codes, and club isolation.
- **Implemented internal capability:** `ledger_accounts` and the inert `LedgerAccountProvisioningService` support club-scoped accounts, INR, canonical codes, active state, replay safety, and type-compatible normal balances. They expose no route and contain no approved LC Beenachi chart.
- **Existing operational source:** charges, member obligations, fully allocated payments, allocations, and receipts exist independently of the journal.
- **Proposed future design:** the catalog below.
- **Unsupported workflow:** any source not listed as operationally ready in the source-mapping register.
- **Pending business decision:** every catalog row, fund treatment, ownership rule, and activation choice below.

An account code is trimmed, converted to ASCII uppercase, 2–40 characters, and must match `^[A-Z][A-Z0-9]*(?:-[A-Z0-9]+)*$` under DR-025. Codes are immutable and club-scoped. Names may change only through a future controlled process that preserves history. No real bank account number, branch detail, credential, or supporting schedule belongs in source control.

## 2. Proposed coding convention

Codes use a semantic prefix (`BANK`, `INCOME`, `EXPENSE`, `LIABILITY`) and stable approved short segments. Codes do not encode account numbers, member identities, balances, or mutable organizational labels. Bank codes use an approved non-sensitive alias, for example `BANK-OPERATING`, never the real account number.

## 3. Proposed account catalog

All values are proposals. “Required” means required if the associated workflow is approved for initial activation. “Configurable” means the club may select a name or instantiate additional accounts only through an approved catalog process. Direct manual posting is prohibited for every row.

| Proposed code | Proposed name | Type / normal | Currency | Purpose and supported source workflows | Initial activation | Club-configurable | Approval | Privacy | Reconciliation | Manual posting |
|---|---|---|---|---|---|---|---|---|---|---|
| `CASH-ON-HAND` | Cash on Hand | Asset / Debit | INR | One physically controlled cash holding; receipts, approved expenses, transfers, opening | Required if cash exists | Structure Pending | Pending | High | Signed count to evidence | Prohibited |
| `BANK-OPERATING` | Operating Bank Account | Asset / Debit | INR | One fictional alias for one real bank holding; payments, expenses, transfers, opening | Required if approved bank exists | Alias Pending | Pending | High | Independent statement reconciliation | Prohibited |
| `BANK-<APPROVED-SHORT-CODE>` | Additional Bank Account | Asset / Debit | INR | Separate account for each additional real bank holding | Conditional | Yes, controlled | Pending | High | Per-account evidence | Prohibited |
| `MEMBER-RECEIVABLES` | Member Receivables Control | Asset / Debit | INR | Issued member charges, allocated payment settlement, opening control total | Required for charge integration | No | Pending | High | Exact member subledger control | Prohibited |
| `OTHER-RECEIVABLES` | Other Receivables | Asset / Debit | INR | Only a future approved non-member receivable workflow | No | Controlled | Pending | High | Independent schedule | Prohibited |
| `ADVANCES-PAID` | Advances Paid | Asset / Debit | INR | Only a future authorized advance-payment and settlement workflow | No | Controlled | Pending | High | Advance register | Prohibited |
| `LIABILITY-RESTRICTED-COLLECTION` | Restricted Collection Liability | Liability / Credit | INR | Restricted collections whose use is constrained | Conditional | Controlled | Pending | High | Restriction-by-source schedule | Prohibited |
| `LIABILITY-RESTRICTED-DONATION` | Restricted Donation Liability | Liability / Credit | INR | Restricted donation receipts after workflow approval | No | Controlled | Pending | High | Donor restriction schedule | Prohibited |
| `ACCOUNTS-PAYABLE` | Accounts Payable | Liability / Credit | INR | Only a future approved accrual/payable workflow | No | Controlled | Pending | High | Supplier obligation schedule | Prohibited |
| `OPENING-BALANCE-EQUITY` | Opening Balance Equity | Equity / Credit | INR | Candidate one-time opening offset; not a balancing shortcut | Conditional | No | Pending | High | Full opening package | Prohibited |
| `ACCUMULATED-SURPLUS` | Accumulated Surplus or Deficit | Equity / Credit | INR | Candidate retained historical result if accounting review approves meaning | Conditional | No | Pending | High | Approved historical reconciliation | Prohibited |
| `INCOME-MEMBER-DUES` | Membership Dues Income | Income / Credit | INR | Issued ordinary membership dues mapped by approved category | Conditional | No | Pending | Medium | Charge-category reconciliation | Prohibited |
| `INCOME-SPECIAL-COLLECTION` | Special Collection Income | Income / Credit | INR | Only if Special Collection is approved as unrestricted earned income | No | Controlled | Pending | Medium | Charge/receipt schedule | Prohibited |
| `INCOME-VOLUNTARY-COLLECTION` | Voluntary Collection Income | Income / Credit | INR | Voluntary amounts only when actually received; never member dues | No | Controlled | Pending | Medium | Receipt/source reconciliation | Prohibited |
| `INCOME-DONATION` | Unrestricted Donation Income | Income / Credit | INR | Unrestricted donations after authoritative donation workflow approval | No | Controlled | Pending | High | Donation register | Prohibited |
| `INCOME-FELLOWSHIP` | Fellowship and Member Activity Income | Income / Credit | INR | Only after Fellowship lifecycle and classification approval | No | Controlled | Pending | High | Fellowship source schedule | Prohibited |
| `INCOME-OTHER-APPROVED` | Other Approved Club Income | Income / Credit | INR | Explicitly approved income types with a controlled source mapping | No | Controlled | Pending | Medium | Source-specific schedule | Prohibited |
| `EXPENSE-ADMINISTRATION` | Administration Expense | Expense / Debit | INR | Candidate category for future approved expense-payment workflow | No | Controlled | Pending | Medium | Payment and authorization evidence | Prohibited |
| `EXPENSE-SERVICE-ACTIVITY` | Service Activity Expense | Expense / Debit | INR | Candidate service-project expenditure category | No | Controlled | Pending | Medium | Project/payment evidence | Prohibited |
| `EXPENSE-FELLOWSHIP` | Fellowship and Member Activity Expense | Expense / Debit | INR | Candidate only after Fellowship expense policy approval | No | Controlled | Pending | Medium | Fellowship/payment evidence | Prohibited |
| `EXPENSE-DISTRICT-OBLIGATION` | District and International Obligations | Expense / Debit | INR | Candidate assessed obligations paid through approved workflow | No | Controlled | Pending | Medium | Assessment/payment evidence | Prohibited |
| `EXPENSE-MEETINGS-EVENTS` | Meetings and Events Expense | Expense / Debit | INR | Candidate event expenditure category | No | Controlled | Pending | Medium | Event/payment evidence | Prohibited |
| `EXPENSE-BANK-CHARGES` | Bank Charges Expense | Expense / Debit | INR | Bank charges supported by independent bank evidence and approved workflow | No | Controlled | Pending | High | Bank reconciliation | Prohibited |
| `EXPENSE-OTHER-APPROVED` | Other Approved Expense | Expense / Debit | INR | Controlled exception after classification approval; not a default bucket | No | Controlled | Pending | Medium | Specific approval and evidence | Prohibited |

**Proposed account count: 24.** Conditional templates do not authorize instantiation. No active unallocated-receipt account is proposed because DR-007 rejects unallocated payments initially. No suspense account is proposed; unresolved differences block activation.

## 4. Account lifecycle and ownership

The business owner and accounting reviewer must approve the initial catalog. A named accounting catalog custodian, still Pending, owns subsequent code proposals. Provisioning must be separated from approval and activation. Inactivation prevents future posting but preserves historical reads. Accounts with approved zero opening positions may be provisioned and omitted from the opening journal; positive-only journal lines make explicit zero entries invalid.

Bank provisioning requires a non-sensitive alias, account purpose, independent evidence reference stored outside source control, preparer, verifier, approver, and activation date. Cash structure must identify each physically distinct controlled holding; the default recommendation is one `CASH-ON-HAND` account unless custody or location requires separately reconcilable accounts.

## 5. Fund treatment decision

The labels General Club, Service Activity, and Fellowship & Member Activity are not verified accounting balances and the current journal schema has no fund dimension.

| Option | Advantages | Limitations and migration/reporting impact |
|---|---|---|
| A — separate ledger accounts per fund | Works in current schema; simple account-level statements | Multiplies accounts, obscures natural classification, makes transfers and consolidated reporting cumbersome, and risks treating presentation labels as legal restrictions |
| B — future fund/dimension model | Preserves natural accounts while enabling fund reporting, restrictions, and consolidated views | Requires schema, posting, validation, mapping, migration, privacy, reconciliation, and reporting changes before use |
| C — presentation groupings only | No schema change; can group approved accounts in UI | Derived groupings can misstate restrictions or balances and cannot enforce fund integrity |

**Recommendation (Pending):** choose Option B only if accounting review confirms that the three labels represent genuine separately controlled funds; otherwise use Option C with explicit “presentation grouping” language. Do not choose Option A merely to avoid a schema change. No frontend fixture balance may establish this decision.

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

The safest proposed catalog is deliberately small: real asset accounts, one member-receivable control, explicitly mapped income/liability accounts, a reviewed opening-equity choice, and expense accounts only when an approved payment workflow exists. Final chart approval, account owners, bank aliases, fund treatment, expense hierarchy, and all activation decisions remain Pending.
