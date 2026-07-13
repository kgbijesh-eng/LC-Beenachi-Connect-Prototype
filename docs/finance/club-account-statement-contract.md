# Club Account Statement Domain and Accounting Contract

**Status:** Proposed and unimplemented. Business-owner approval is required before implementation.

## 1. Purpose and scope

This contract defines the proposed source truth and accounting semantics for a future read-only Club Account Statement. It does not assert that the current application has a club journal, complete historical balances, cash or bank accounts, expense records, or a statement API. Milestone 3II remains blocked until approved backend capabilities are implemented and validated.

The statement will describe club-wide financial position and movement. It is not a member dues statement, receipt register, payment register, statutory audit, bank statement, or replacement for source-domain records.

## 2. Verified current state

The repository currently contains two finance generations and several read models:

- `backend/database/migrations/2026_06_24_100001_create_finance_collection_tables.php` defines integer-paise club charges, member charges, member payments, allocations, and receipts. `FinanceWriteService` enforces same-club ownership, exact allocation totals, scoped idempotency, optimistic locking, payment voiding, replacement links, and audit events.
- `ClubCharge`, `MemberCharge`, `MemberPayment`, `PaymentAllocation`, and `PaymentReceipt` are operational sources for their own workflows. They are not a general ledger and do not represent expenses, account balances, or internal transfers.
- `backend/database/migrations/2026_06_07_202122_create_member_ledger_entries_table.php` and the legacy `Payment` and `Receipt` migrations use decimal amounts. `MemberLedgerEntry` is member-scoped and permits source labels such as dues, fellowship, payment, and adjustment, but it is not a club cash/bank journal.
- `ClubFinanceReportService` produces aggregate management views from posted legacy member-ledger entries, payments, and receipts. Its balance-sheet-style response explicitly identifies `existing_posted_ledger_entries_only`; it is not an authoritative balance sheet or statement.
- `FinancialReportService` and `ReportCatalog` expose collection summaries and charge, payment, and receipt registers. These are filtered read models, not a complete transaction stream.
- `AuditLog` provides club, actor, action, target, metadata, IP, user-agent, and timestamps. Existing finance writes emit selected audit events, but no journal audit contract exists.
- `backend/routes/api.php` has no Club Account Statement route. The frontend therefore truthfully says that statements are not connected and shows no balances or transactions.
- Collection Box, fellowship payments, donations, general income, expenses, cash/bank accounts, and internal transfers do not have verified authoritative posting workflows. UI labels or legacy source labels must not be treated as operational truth.

## 3. Design options

| Criterion | A. Double-entry club journal | B. Controlled single-entry ledger | C. Dynamic composition from current tables |
|---|---|---|---|
| Reliability | Strong: every posting balances | Moderate: balance depends on event classification | Weak: current sources are incomplete and overlap |
| Auditability | Strong linked journal, lines, reversals | Good append-only event trail, weaker reconciliation | Poor provenance across heterogeneous tables |
| Complexity | Highest initial schema and integration cost | Lower initial cost | Apparently low, but hidden reconciliation cost |
| Cash/bank transfers | Correct two-account movement | Requires special-case transfer pairs | Not authoritatively represented |
| Corrections | Linked reversing journal plus replacement | Linked reversal event plus replacement | Domain-specific states conflict |
| Reporting | Journal, account statements, trial balance, future balance sheet | Cashbook-style statements | Partial registers only |
| Historical data | Controlled opening journals or approved backfill | Controlled openings or event import | Cannot prove completeness |
| Duplication risk | Managed by source identity and idempotency | Managed by source identity and idempotency | High when combining legacy and current domains |
| Multi-club suitability | Strong with tenant-scoped accounts and journals | Moderate | Unsafe |
| Rollback risk | Additive until posting integrations activate | Additive until integrations activate | High because output may appear complete when it is not |

**Recommendation, pending approval:** Option A, an append-only double-entry club journal. It is safest because the requested statement spans assets, liabilities, income, expense, equity, member receivables, and internal transfers. Option B is viable only if the business owner intentionally limits the product to a controlled cashbook and accepts weaker balance-sheet and transfer guarantees. Option C must not be used for an authoritative statement.

## 4. Terminology and source truth

- **Source-domain event:** an approved business event such as an issued charge, recorded payment, approved expense, or completed transfer.
- **Journal transaction:** immutable club-scoped posting header with two or more journal lines whose debits equal credits.
- **Journal line:** amount posted to one account. Amounts are non-negative integer paise; direction is debit or credit.
- **Account:** club-scoped chart-of-accounts entry with category and stable identity.
- **Statement:** a read-only projection of posted journal lines for one selected account and period.
- **Posting date:** accounting date that places a transaction in a period.
- **Effective date:** date the underlying economic event occurred. It may differ from posting date only under an approved close-period policy.
- **Created timestamp:** immutable system timestamp recording when the journal transaction was created.
- **Source link:** stable link to a domain record. It does not replace journal identity.

Only posted journal transactions may be the authoritative source of future club account balances and statements. Existing domain tables remain authoritative for their own lifecycle. Reports and summaries are projections only. Legacy decimal member-ledger entries, UI fixture data, receipts, audit logs, and dynamically combined registers must not become journal source truth.

## 5. Proposed account structure

Each account belongs to exactly one club and has a stable opaque ID, unique club-scoped code, name, category, normal balance, active state, and optional parent account.

| Category | Normal balance | Required examples |
|---|---|---|
| Asset | Debit | Cash on hand, each bank account, member receivables |
| Income | Credit | Membership income, event contributions, donations, other approved income |
| Expense | Debit | Program, administration, fellowship, banking, and other approved expenses |
| Liability | Credit | Unallocated member receipts, restricted collections, payables |
| Equity | Credit | Opening equity / accumulated fund |

Internal transfers post debit to the destination asset account and credit to the source asset account in one journal transaction. They create no income or expense. Cash and each bank account must remain distinct; a generic payment mode is not an account.

## 6. Transaction anatomy and lifecycle

A proposed journal transaction contains: stable ID; club ID; human reference; posting date; effective date; description; status; currency; source type and source ID; source event key; idempotency key; reversal-of ID; corrected-by ID; created actor and timestamp; posted actor and timestamp; and two or more account lines. Each line contains stable ID, account ID, debit paise or credit paise (exactly one positive), optional member ID for receivable subledger reconciliation, description, and deterministic line sequence.

Proposed states are `draft`, `posted`, `reversed`, and `voided`:

- Drafts have no statement effect and may be edited or voided subject to authorization.
- Posted transactions are immutable and affect balances.
- Reversal creates a new posted transaction with opposite lines and links both records. The original remains posted historically and is marked reversed for presentation.
- Voided is reserved for drafts that never posted. A posted transaction must never be deleted or changed to erase its effect.
- Correction means a full linked reversal followed by a separately posted corrected transaction. Partial adjustment entries are permitted only through an approved policy and explicit reason.

Every posting must execute atomically. A source-domain event may post once only. A scoped idempotency key and a unique `(club_id, source_type, source_id, source_event)` constraint are proposed. Cross-club account, member, source, reversal, and replacement links must fail closed.

## 7. Debit, credit, and balance semantics

Debit and credit describe account-side journal entries, not generic money-in and money-out labels. Assets and expenses normally increase by debit. Liabilities, income, and equity normally increase by credit.

For an account statement, a signed movement is calculated from the account's normal balance:

- debit-normal account: debit paise minus credit paise;
- credit-normal account: credit paise minus debit paise.

**Opening balance** is the sum of all posted lines strictly before `date_from`, including an approved cutover opening journal. It is not a stored editable statement value.

**Running balance** starts at opening balance and applies ordered signed movement for every returned line. Pagination must not reset it; each row carries its absolute balance after that row.

**Closing balance** equals opening balance plus all posted movement from `date_from` through `date_to`, inclusive. It must be computed over the complete filtered period, not only the returned page.

For multi-account summaries, debit/credit and signed balance must be presented per account. A single cross-account scalar "club balance" is prohibited unless a separately approved metric defines it.

## 8. Period, financial year, and ordering

Statement dates are club-local calendar dates. The financial-year boundary is unresolved in Decision DR-002. Until approved, no default period or cutover may be implemented.

Proposed deterministic order is `(posting_date ASC, effective_date ASC, journal_transaction_id ASC, line_sequence ASC, journal_line_id ASC)`. Created timestamps must not determine accounting order. A closed-period and late-posting policy requires approval.

## 9. Currency and precision

Currency is INR for the initial contract. Every persisted and API monetary value is signed or unsigned integer paise as defined by the field; floating point and decimal strings are display concerns only. Journal lines use non-negative `debit_paise` and `credit_paise`. Exactly one must be positive and the other zero. Values must remain within database and application overflow limits. Conversion from legacy decimal records requires an approved, reconciled migration process and must never occur implicitly in a statement read.

## 10. Integration boundaries

- **Member charges:** an issued `ClubCharge` and its `MemberCharge` obligations may become source events. Whether issuance recognizes receivable and income is DR-005.
- **Member payments:** `MemberPayment` is the preferred current operational payment source. Posting timing, cash/bank destination, and treatment of unallocated money are DR-006 and DR-007.
- **Allocations:** allocations settle member subledger obligations; they must not create duplicate cash. Their accounting effect depends on whether receipt or allocation recognizes the payment.
- **Receipts:** `PaymentReceipt` is evidence and numbering, not financial identity. A receipt links to a journal transaction only where authorized.
- **Voids:** current payment voids and receipt status changes require a linked journal reversal once integration is active. Historical pre-journal voids must not be fabricated as postings.
- **Collection Box, fellowship, donations, income, expenses:** each requires an authoritative domain lifecycle, account mapping, posting trigger, actor, approval, and reversal rule before integration. Until then it is unavailable to the statement, not zero.
- **Transfers:** require source and destination asset accounts and one balanced journal transaction; payment-mode strings are insufficient.
- **Legacy `MemberLedgerEntry`, `Payment`, and `Receipt`:** may inform a separately approved backfill but must not be dual-posted or dynamically mixed with the journal.

## 11. Authorization, isolation, privacy, and audit

The proposed read permission is `finance.club.statement.view`, separate from `finance.club.view`, pending DR-017. It is intended for President/Admin, Secretary, and Treasurer only after explicit role approval. Normal Members must not read club-wide statements. Authentication is required.

Every account, transaction, line, source link, query, uniqueness constraint, and cache key must be scoped by route-bound `club_id`. Active membership and permission must be checked for that same club. Cross-club identifiers return fail-closed responses without revealing existence.

Posting and reversal operations require separate future permissions. Audit records must capture actor, club, action, journal identity, source identity, reason, request correlation, before/after status (not mutable monetary snapshots), IP/user agent where available, and timestamps. Audit records supplement journal immutability and never determine balances.

## 12. Empty, unavailable, partial, and historical states

- **Empty:** authoritative journal is available for the full requested period and contains no matching lines. Opening and closing may truthfully be zero or non-zero.
- **Unavailable:** journal/account service cannot establish authoritative data. No numeric balance is returned as if verified.
- **Partial history:** requested range begins before verified journal coverage. The response declares coverage start and omits authoritative opening/closing unless an approved opening journal establishes them.
- **Historical:** before cutover, statements are available only if an approved backfill was posted and reconciled. Source-domain records alone do not imply completeness.

Unavailable is never represented as zero. Partial coverage must be conspicuous and machine-readable.

## 13. Proposed invariants

- Every posted journal transaction balances; total debit paise equals total credit paise.
- Currency is INR and all monetary persistence uses integer paise.
- Posted transactions and lines are immutable and cannot be deleted.
- Corrections use linked reversals and replacement or adjustment postings.
- Opening plus complete period movement equals closing.
- Running balances reconcile to deterministic ordered entries and closing.
- Source events cannot post twice; scoped idempotency is unique.
- All identities and references are same-club; cross-club links are rejected.
- Statement reads require explicit permission; Normal Members are denied.
- Partial history is declared; unavailable data is not rendered as zero.
- Receipt and source links do not become journal identity.
- **Provisional:** charges, payments, allocations, unallocated payments, and close-period behavior follow the recognition decisions in the decision register.

## 14. Historical data and non-goals

Available cutover options are: no backfill plus approved opening journal; reconciled aggregate opening by account; or transaction-level backfill after source validation. Dynamic read-through of old tables is not a cutover strategy. The chosen method must record coverage date, reconciliation evidence, approver, import identity, exceptions, and rollback boundary.

This milestone does not implement accounting, tax, statutory reporting, bank reconciliation, budgets, exports, multi-currency, Kuri/Chit Fund accounting, period close, domain posting, backfill, UI, or API behavior.

## 15. Repository citations

- Routes and permission middleware: `backend/routes/api.php`; `EnsureClubPermission::handle`; `ClubPermissionService::userHasPermission`.
- Role boundaries: `DefaultRolesAndPermissionsSeeder` finance permissions and role mappings.
- Current integer-paise source: `2026_06_24_100001_create_finance_collection_tables.php`; `FinanceWriteService`; `FinanceReadService`.
- Legacy decimal lane: `2026_06_07_202122_create_member_ledger_entries_table.php`; `2026_06_08_000001_create_payments_table.php`; `MemberFinanceSummaryService`.
- Reports: `ClubFinanceReportService`; `FinancialReportService`; `ReportCatalog`.
- Audit foundation: `2026_06_06_213536_create_audit_logs_table.php`; `AuditLog`.
- Verified guards: `Milestone3gqFinanceDatabaseReadApiFoundationTest`; `Milestone3grFinanceCollectionsReceiptsWorkflowsTest`; `Milestone3bcClubFinanceReportsBalanceSheetReadApiNarrowImplementationTest`.
- Truthful frontend boundary: `app/page.tsx` function `ClubAccountStatementUnavailable`; `app/__tests__/MemberProfile.test.tsx` disconnected-statement role tests.
