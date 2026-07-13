# Club Account Statement Decision Register

**Approval owner:** Bijesh K. G., Business Owner

**Approval date:** 13 July 2026

**Status:** Approved with modifications for documentation and future scoping only. No schema, migration, posting service, historical-data entry, or operational finance implementation is authorized.

| ID | Approved decision | Implementation and compatibility effect | Approval status |
|---|---|---|---|
| DR-001 | Use an append-only, club-scoped double-entry journal. | Every posted journal balances. Controlled single-entry and dynamic current-table composition are not selected. | Approved |
| DR-002 | The accounting and reporting year is the Lions Year, 1 July to 30 June. The Indian April-March financial year is prohibited for accounting periods, statement periods, annual grouping, closing, and journal references. | Replace proposed financial-year defaults with Lions Year semantics. Existing receipt-year behavior does not establish journal policy. | Approved with modification |
| DR-003 | Cutover occurs only on 1 July of an approved Lions Year after all opening balances are fully reconciled and approved. | No mid-year or implementation-date cutover. Coverage starts at the approved 1 July boundary. | Approved with modification |
| DR-004 | Represent cash and each bank account separately. | Accounts are independently reconcilable; payment mode alone is not an account. | Approved |
| DR-005 | Issued member charges create receivables; the approved due category determines the corresponding credit account. | Requires deterministic category-to-account mapping and reversal behavior for cancelled charges. | Approved |
| DR-006 | Post payments when actually received. Allocation settles receivables and never duplicates cash or bank. | Source payment and allocation integrations must be atomic and reconcile to the receivables subledger. | Approved |
| DR-007 | Reject unallocated payments in the initial implementation. | Current exact-allocation boundary is retained. Unallocated-receipt liability workflow is deferred. | Approved with modification |
| DR-008 | Collection Box does not post until separately contracted, implemented, tested, and approved. | No journal mapping or assumed income/liability treatment exists initially. | Approved with modification |
| DR-009 | Fellowship payments do not post until separately contracted, implemented, tested, and approved. | Legacy labels and UI concepts remain non-authoritative. | Approved with modification |
| DR-010 | Donations do not post until separately contracted, implemented, tested, and approved. | Donation income, restriction, donor privacy, and reversal rules remain outside initial scope. | Approved with modification |
| DR-011 | Initially recognize expenses only through an authorized and approved payment workflow. Invoice accruals are unsupported. | No payable/accrual posting until separately contracted. | Approved with modification |
| DR-012 | Record an internal transfer as one atomic journal: debit destination asset and credit source asset. | No income/expense effect and no unmatched transfer halves. | Approved |
| DR-013 | Never edit or delete posted entries. Correct with linked reversal and corrected posting. | Original and correction history remains immutable and auditable. | Approved |
| DR-014 | Initial activation uses approved opening balances by account. Historical transaction backfill is prohibited unless completeness and non-duplication are independently proven. | Default cutover is opening journals, not transaction import or dynamic legacy read-through. | Approved with modification |
| DR-015 | Incomplete historical periods do not display authoritative balances. | API returns machine-readable incomplete-history state with no authoritative opening, running, or closing values. | Approved |
| DR-016 | Human references use club code and Lions Year, for example `LCB/2026-27/JRN/000001`. | Sequence is unique within club and Lions Year; references are never reused. | Approved with modification |
| DR-017 | Use explicit permission `finance.club.statement.view`. | Permission and role assignment require a separately authorized implementation milestone; role names are not hard-coded. | Approved |
| DR-018 | Defer CSV and PDF exports. | Initial API and UI remain read-only interactive statement views. | Approved |
| DR-019 | Posted journals are never deleted. Additional retention and read-audit rules may be defined separately if required. | Journal retention is permanent under the initial contract; future policy cannot break balances or audit links. | Approved with modification |
| DR-020 | Use shared multi-club tables with mandatory `club_id` isolation and no cross-club financial references. | Composite tenant constraints, scoped jobs/caches, and adversarial isolation tests are mandatory. | Approved |
| DR-021 | Lions Year periods may close through a controlled process. Post post-close corrections in the next open period with explicit original links. | Requires future close permission, state, audit, and late-correction rules; reopening is not approved. | Approved with modification |
| DR-022 | Initially provide one real cash or bank account per statement. | No synthetic fund statement or ambiguous all-account scalar balance. | Approved with modification |
| DR-023 | Keep original and reversal entries visible and linked. | API totals include both effects; UI may group but not hide or alter them. | Approved |
| DR-024 | Statement reads are transactionally consistent and include version or fingerprint metadata. | Rows, pagination, running balances, and totals share one consistent snapshot identity. | Approved |

## Approval boundary

This decision review completes the business-owner policy gate for future technical scoping. It authorizes changes to these contract documents only. It does not authorize F2 schema/model work, migrations, permissions, routes, services, tests executing statement behavior, historical opening entries, operational data access, or frontend implementation.

Any change to an approved decision requires a new recorded decision with rationale, approval owner, date, compatibility analysis, historical-data impact, and corresponding contract revisions before implementation.
