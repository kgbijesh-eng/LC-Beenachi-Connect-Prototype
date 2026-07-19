# Club Accounting Cutover and Opening Balance Contract

**Status:** Proposed validation design only. The current decision register is authoritative: DR-026–DR-032 and DR-034–DR-052 are approved with their recorded qualifications, while DR-033 alone remains **Pending**. Opening values, package-specific evidence, accounting review, approval, posting, and activation remain unapproved. No operational data was inspected.

**Pure validation foundation:** package structure `CUTOVER-EVIDENCE-PACKAGE-2026-01` and validation policy `OPENING-BALANCE-VALIDATION-2026-01` produce only deterministic `PROPOSED-UNVERIFIED`, activation-`BLOCKED` structural reports. They fetch no evidence, create no records, and never satisfy independent accounting review. Every report retains DR-033, operational-collision, and activation-authorization blockers. The dry-run fingerprint is a comparison tool, not the approved-package checksum required by DR-044.

**Authorization contract boundary:** `CUTOVER-AUTHORIZATION-CONTRACT-2026-01` distinguishes the 3IIM structural comparison fingerprint, a future final persisted package fingerprint, and the separately controlled DR-044 evidence-package checksum. Any material change invalidates their binding and dependent decisions. A future activation authorization is single-use, exact-package-bound, separately approved, concurrency-protected, revocable before posting, and consumed only with successful creation or exact authorized replay. This is a design contract only; DR-033 remains Pending and no persistence or authorization exists.

## 1. Approved boundary and cutover alternatives

DR-002 and DR-003 require a Lions Year from 1 July through 30 June and permit cutover only on 1 July of an explicitly approved Lions Year. The current Lions Year is 2026–27 and began on 1 July 2026, but that fact does not approve it as the cutover.

| Option | Contract treatment | Risk |
|---|---|---|
| A — reconciled historical cutover on 1 July 2026 | Permitted only if every opening position can be reconstructed as of that date, independently evidenced, reconciled, approved, and protected against duplicate later source postings | Highest reconstruction and duplication risk |
| B — future cutover on 1 July 2027 | Existing workflows remain outside the journal until activation; opening evidence is prepared prospectively | Lowest evidence risk, but authoritative journal statements are delayed |
| C — another future 1 July | Permitted only by explicit approval with a corresponding Lions Year | Further delay but may be safer if readiness is insufficient |

**Approved target with qualification:** Option B, 1 July 2027, subject to readiness. If readiness fails, postpone only to a later approved 1 July. Current evidence proves internal journal mechanics but does not prove complete opening schedules or source integration. A mid-year cutover is prohibited.

## 2. Cutover lifecycle

1. **Propose:** identify the Lions Year, chart, source scope, evidence format, actors, and rollback boundary.
2. **Prepare:** assemble supporting schedules without posting.
3. **Reconcile:** agree each control total to independent evidence and resolve every difference.
4. **Verify:** an independent reviewer reproduces totals, mappings, paise conversion, and non-duplication checks.
5. **Approve:** authorized approvers sign the immutable package and its manifest hash.
6. **Dry run:** validate in an isolated non-operational context; create no authoritative posting.
7. **Authorize activation:** a separate explicit go/no-go decision identifies the exact approved package.
8. **Post once:** post the opening journal atomically through a dedicated idempotent source identity.
9. **Reconcile after posting:** prove journal totals and lines equal the approved package.
10. **Monitor:** prevent backfill or source integrations from duplicating pre-cutover effects.

Failure at any stage before posting returns the package to preparation. Failure after posting stops activation and requires an approved linked reversal and replacement; posted history is never edited or deleted.

## 3. Mandatory prerequisites

- Approved Lions Year and cutover date exactly on 1 July.
- Approved chart of accounts and every opening account mapped.
- Cash physically counted and signed by custodian, preparer, and verifier.
- Each bank balance reconciled to independent bank evidence, with uncleared items separately scheduled.
- Member receivables reconciled to member-level obligations and approved due categories.
- Every liability and restricted balance independently supported.
- Opening equity difference explained and approved; no unexplained suspense amount.
- Proof that historical effects will not be posted twice.
- Named preparer, independent verifier, accounting reviewer, business approver, activation authority, and rollback authority.
- Verified backup, restoration procedure, dry-run evidence, rollback plan, and explicit activation authorization.
- Sensitive schedules stored in an approved restricted repository, not Git.

## 4. Opening package and journal structure

The package must have one immutable package ID and manifest hash, one club, one Lions Year, one effective date exactly on 1 July, independent creation/posting timestamps, package version, source evidence inventory, mapping version, integer-paise totals, exception register, actor identities, sign-offs, and activation decision.

The proposed opening journal requires:

- one club and the matching open Lions Year period;
- effective date exactly 1 July and posting date recorded independently;
- immutable human reference allocated by the journal service;
- dedicated `source_type = accounting_opening_package`, opaque package ID as `source_id`, `source_event = activated`, and a package-bound UUID idempotency key;
- positive integer-paise lines, at least two lines, exact debit/credit equality, and deterministic line order by approved account code then stable account ID;
- actor, narration, approved package fingerprint, approval decision reference, and cutover version metadata;
- no editing after posting; correction only through one linked full reversal and a separately approved replacement package.

### Schema sufficiency finding

The current header can store source identity, idempotency, actor IDs, dates, narration, and JSON metadata. It cannot independently enforce a normalized multi-party approval lifecycle, separation of preparer/verifier/approver, evidence-manifest integrity, package version, activation authority, or rollback state. JSON metadata alone is not a safe substitute for enforceable approval records.

**Future prerequisite:** an additive, club-scoped opening-package/approval schema (or an equivalently controlled approval domain) with immutable evidence references, actor roles, timestamps, status transitions, uniqueness, separation-of-duty checks, and a foreign-key link to the posted opening entry. This finding does not authorize that schema.

## 5. Opening offset

| Candidate | Appropriate only when | Risk |
|---|---|---|
| Opening Balance Equity | A temporary or permanent opening bridge is explicitly approved and its later disposition is defined | Can conceal unreconciled differences if used mechanically |
| Accumulated Surplus/Deficit | Accounting review proves the amount represents cumulative unrestricted results | Meaning may be incorrect where restricted/designated balances exist |
| Another accountant-approved equity account | Its legal/accounting meaning and reconciliation are documented | Adds catalog complexity and may require fund treatment |

**Recommendation (Pending):** accounting review should choose the economically correct equity account after all assets and liabilities are supported. The offset must never be selected or altered merely to force equality. Every residual difference blocks approval.

## 6. Member receivables control

The opening `MEMBER-RECEIVABLES` journal line is a control total, not a replacement for the member subledger. The approved supporting schedule must contain opaque/stable member identity, obligation identity, approved due category, original amount, historical allocations, outstanding paise, state, dispute/write-off classification, and evidence reference.

Requirements:

- member-by-member and due-category totals agree exactly to the control line;
- historical payments already applied are not recreated;
- credit balances are separately identified and mapped only after accounting approval, not silently netted against other members;
- disputed and proposed written-off amounts remain separately disclosed and excluded/included only by an explicit decision;
- inactive members remain in the private schedule where legally and operationally required;
- the verifier reproduces the total from line-level integer paise;
- access is limited to authorized finance/accounting reviewers; member schedules never enter source control or a club-wide statement response.

## 7. Cash and bank openings

Each physically distinct cash holding and each real bank account has a separate ledger account and independent opening evidence. A combined bank balance is prohibited. Real account numbers, statements, credentials, and identifying bank details remain outside source control.

Cash requires a signed physical count as of the cutover boundary. Bank balances require independent statements and a reconciliation of book balance, statement balance, deposits in transit, uncleared payments, bank charges, and other timing items. Uncleared items must be scheduled and must not be duplicated when later clearing. All values convert exactly to INR integer paise under a reviewed conversion report. Named custody, preparation, verification, approval, and reconciliation responsibility is mandatory.

## 8. Liabilities, restrictions, equity, and zero balances

Every liability and restricted amount requires an independent schedule and source restriction. Missing evidence is “unavailable,” never zero. No suspense account may absorb an unexplained amount.

An approved zero opening position is documented in the package but omitted from the opening journal because current journal lines require positive values. A required account may be provisioned active with no opening line when its approved position is exactly zero. Optional accounts may remain inactive until an approved workflow needs them. Explicit zero journal lines are rejected.

## 9. Failure, correction, and rollback

Pre-posting failures invalidate the dry run and create no journal. Activation must stop for mismatched hashes, totals, mappings, evidence, actors, dates, source coverage, or duplicate-risk checks.

After posting:

- no line or header is edited or deleted;
- activation is paused and downstream integrations remain disabled;
- the incident is assessed against the approved rollback threshold;
- a full linked reversal is prepared through the existing reversal semantics;
- a corrected opening package receives a new version, approvals, source identity, and idempotency key;
- database restoration is reserved for separately authorized disaster recovery, never ordinary correction.

Rollback does not erase history. A reversed opening package cannot be silently reactivated.

## 10. Illustrative examples only

All examples are fictional, integer-paise, balanced, and unapproved.

### Balanced opening journal

| Line | Account | Debit paise | Credit paise |
|---:|---|---:|---:|
| 1 | `CASH-ON-HAND` | 25,000 | 0 |
| 2 | `BANK-OPERATING` | 175,000 | 0 |
| 3 | `MEMBER-RECEIVABLES` | 50,000 | 0 |
| 4 | `LIABILITY-RESTRICTED-COLLECTION` | 0 | 30,000 |
| 5 | `OPENING-BALANCE-EQUITY` | 0 | 220,000 |
| **Total** |  | **250,000** | **250,000** |

The equity value is illustrative and would require independent approval; it is not a plug authorization.

### Rejected incomplete opening package

A fictional package proposes bank 100,000 paise and receivables 25,000 paise but supplies no bank reconciliation, no member schedule, and a 125,000-paise suspense credit. It is rejected before dry run because evidence is incomplete and suspense is being used to force balance.

## Unsafe shortcuts rejected

- Assuming missing openings are zero or using a suspense amount to balance.
- One generic bank account or frontend fund totals as opening evidence.
- Mid-year cutover, undocumented backdating, or incomplete historical import.
- Editing or deleting a posted opening entry.
- Activating before source integrations are idempotent and non-duplicative.
- Showing authoritative opening/running/closing balances before completeness approval.
- Treating the journal control line as the member subledger.

## 11. Remaining approval boundary

The decision register records qualified approvals for the target cutover, chart semantics, evidence format, roles, external review requirement, dry-run outcomes, activation authority, rollback policy, historical-backfill prohibition, and provisional privacy retention. Those policy approvals do not approve package-specific evidence, account instances, opening values, mappings, accounting review, posting, or activation. DR-033 remains Pending and prevents selection of any opening-equity account or completion of an activation-ready package.
