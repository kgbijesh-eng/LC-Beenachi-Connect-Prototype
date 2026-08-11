# Milestone 3IJAD — Ordinary Non-Member Income Receipt Correction Policy Resolution

**Status:** Policy and architecture resolved for a future implementation. Documentation only; no production capability, permission, schema, route, API, UI, or test is implemented or authorized by this milestone.

## 1. Purpose and authority boundary

This milestone resolves how a materially incorrect posted `ClubOrdinaryIncomeReceipt` introduced in 3IIZZ may later be corrected by one immutable linked full reversal and, optionally, one immutable linked replacement. It applies existing DR-013, DR-021, DR-023, DR-062, and DR-064; it does not create a new decision-register number or claim accounting, legal, Chartered Accountant, production, merge, or deployment approval.

The policy is deliberately source-specific. No generic journal-reversal endpoint or universal finance permission is authorized.

## 2. Evidence reviewed

- `milestone-3iizz-tenant-scoped-ordinary-non-member-income-receipt.md`
- `milestone-3ijab-cross-source-replay-provenance-hardening.md`
- `club-accounting-source-mapping-register.md`
- `club-accounting-milestone-decision-traceability.md`
- `milestone-3iizw-expense-history-correction-linked-reversal.md`
- `milestone-3iizx-internal-cash-bank-transfer.md`
- `milestone-3iizy-non-member-receipt-architecture-policy-resolution.md`
- DR-013, DR-021, and DR-023 in `club-account-statement-decision-register.md`
- DR-062 and DR-064 in `club-accounting-cutover-decision-register.md`
- Existing ordinary-income migration, model, posting/replay service, history service, request/resource/controller, permission seeding, routes, and frontend record/history/detail components

Current implementation evidence establishes a public receipt UUID, tenant, subtype, accounting date, receiving and income account bindings, canonical fund, positive INR paise amount, description/provenance fields, tenant-scoped idempotency UUID, request digest, creator, journal binding, timestamps, and an exact posted two-line journal. Existing replay already validates source type, source ID/event, journal state, INR, accounts, directions, amounts, fund, and idempotency linkage.

## 3. Scope and exclusions

Correction applies only to a posted ordinary non-member income receipt whose source type is `club_ordinary_income_receipt` and whose complete persisted provenance is canonical.

It excludes donations, restricted or conditional receipts, member payments and allocations, charges, expenses, internal transfers, opening/cutover journals, manual journals, Collection Box, Fellowship, grants, deposits/advances, asset disposals, tax-enabled receipts, refunds/recoveries, and every other accounting source. Those sources retain their own lifecycle and authorization contracts.

Correction means correcting the books because the original ordinary-income posting was wrong. It does not mean that money was physically refunded, repaid, transferred, or paid out. The correction workflow must never create a cash-disbursement instruction, payment record, refund status, bank instruction, or evidence that a real-world refund occurred.

## 4. Resolved authority and permissions

| Existing club role | View correction history/detail | Reverse | Submit optional replacement in correction |
|---|---:|---:|---:|
| President/Admin | Yes | Yes | Yes |
| Treasurer | Yes | Yes | Yes |
| Secretary | Yes | No | No |
| Normal Member | No | No | No |

Future implementation must add one narrow permission, proposed as `finance.club.ordinary_income_receipts.reverse`, assigned only to President/Admin and Treasurer. Existing `finance.club.ordinary_income_receipts.view` continues to govern original and correction history/detail. Existing create permission remains necessary for a replacement; reverse permission must not silently imply unrelated receipt creation.

President/Admin or Treasurer may act independently. Dual approval is not required because the established same-club expense-reversal boundary already permits those roles independently and no repository decision requires maker/checker approval for this bounded source correction. This does not authorize broad `finance.manage`, generic reversal, or universal finance powers. Backend tenant membership and permission middleware are authoritative; frontend visibility is not authorization.

## 5. Eligibility and fail-closed provenance contract

A correction may proceed only when all of the following are reloaded under lock and agree:

1. The route club, source receipt, original journal, journal lines, accounts, accounting period, and actor membership belong to the same club.
2. The receipt exists, is posted, is not already reversed, has one journal binding, and has source type `club_ordinary_income_receipt`.
3. The original journal is posted, INR, and identifies the receipt public UUID with source event `recorded`.
4. The original journal has exactly two ordered lines: debit the bound receiving account and credit the bound income account.
5. Both lines equal the receipt amount, use its canonical fund, and bind the exact persisted accounts.
6. The receiving account is the same-club asset used by the original posting; the income account is the same-club income account used by the original posting. No current remapping may substitute another account.
7. Receipt currency is INR; amount is positive integer paise; source/journal public identities, creator, and immutable request provenance are present.
8. No existing correction record or reversal journal already targets the receipt.

Account deactivation or later catalogue changes do not rewrite the original. The reversal copies the original posted line identities and fails closed if the canonical reversal service cannot legally reverse them. Unsupported source types, inconsistent or missing lines, cross-club identities, corrupted source/journal binding, currency/fund/account mismatch, or broken provenance reject the entire operation without repair or partial persistence.

The original receipt, journal, and journal lines are never updated or deleted.

## 6. Accounting treatment and period rule

Only a complete reversal is supported. Partial amount, selected-line reversal, caller-supplied lines, balancing plugs, current account remapping, currency override, and fund override are prohibited.

For an original receipt of INR 10,000.00:

| Posting | Account | Debit | Credit | Fund |
|---|---|---:|---:|---|
| Original | Bound receiving cash/bank | INR 10,000.00 | — | Original fund |
| Original | Bound ordinary-income account | — | INR 10,000.00 | Original fund |
| Reversal | Bound ordinary-income account | INR 10,000.00 | — | Original fund |
| Reversal | Bound receiving cash/bank | — | INR 10,000.00 | Original fund |

The reversal copies each original line's account, amount, currency, fund, and ordering while reversing direction under DR-062. It uses a new journal identity and source event specific to ordinary-income correction.

The caller supplies a reversal accounting date. It must be a real date within an available open same-club Lions Year period and within authoritative accounting coverage. If the original period is closed, it is never reopened and the original is never backdated or mutated; the correction must use an explicitly selected later open covered period under DR-021. If no eligible period exists, correction is rejected.

An immutable non-empty correction reason is mandatory, together with correction public UUID, source receipt binding, reversal journal binding, actor user, reversal date, idempotency identity/digest, and created timestamp. Reason length and sensitive-data guidance must be bounded in the future request contract.

## 7. Future domain model and immutable relationships

The proposed future `ClubOrdinaryIncomeReceiptCorrection` is a source wrapper, not a mutable status log. It requires:

- public correction UUID and `club_id`;
- unique original receipt binding within the club;
- original journal binding and unique reversal journal binding;
- optional replacement receipt binding;
- reversal accounting date and immutable reason;
- tenant-scoped UUID idempotency key and canonical request digest;
- actor user and timestamps.

Immutable lineage is:

```text
original receipt -> original posted journal
original receipt -> correction -> reversal posted journal
correction -> optional replacement receipt -> replacement posted journal
```

Every relationship uses internal foreign keys for integrity and public UUIDs/references for authorized representation. A replacement may carry an immutable `corrected_from_receipt_id` or equivalent correction-owned link, but must not mutate the original receipt to add lineage. Delete is prohibited. A database uniqueness rule permits at most one correction/reversal for an original receipt within its club.

## 8. Reversal-only and optional replacement

Reversal-only correction is permitted. This is necessary when the original should simply not have been recorded as ordinary income or when correct replacement facts are not yet available.

The future correction operation may optionally create a corrected replacement in the same database transaction. The replacement is not an edit: it receives a new receipt public UUID, new receipt idempotency identity owned by the correction operation, new request digest, new journal public identity/reference, new `recorded` source event, creator/timestamps, and immutable lineage to the correction/original.

Replacement facts must pass the normal `ClubOrdinaryIncomeReceiptService` canonicalization, subtype qualification, payer requirements, account eligibility, same-club isolation, open-period/coverage, fund, positive INR paise, and posting rules. It may differ in any legitimately corrected business fact. It cannot inherit validity merely because the original was valid.

The correction API owns one atomic unit of work: validate and post reversal, then validate and post the optional replacement, then bind all lineage. Any replacement validation, authorization, account, period, posting, or persistence failure rolls back the correction record, reversal journal/lines, replacement receipt/journal/lines, and lineage. No reversal is committed from a failed reversal-plus-replacement request. A caller wanting reversal despite an invalid replacement must submit a distinct reversal-only request with a new idempotency key.

## 9. Idempotency and concurrency contract

The correction request requires a UUID idempotency key normalized like existing finance UUIDs. Ownership is bound to the club, operation `ordinary_income_receipt_correction`, original receipt public UUID, reversal date, normalized reason, mode (`reversal_only` or `reversal_plus_replacement`), and the complete normalized replacement payload when present. The canonical digest includes every material fact; omission versus null follows the ordinary-receipt canonicalization rules.

- Exact same-club replay returns the canonical existing correction, reversal, and optional replacement without another record, journal, line, or statement effect.
- Same key with any changed material payload conflicts.
- Another club may independently use the same UUID, but cannot observe this club's existence or result.
- Concurrent exact retries produce one correction and one reversal; all successful responses resolve to the same canonical identities.
- Different keys concurrently targeting one original still permit only one correction. The loser receives a source-specific already-reversed/conflict response without learning foreign-tenant facts.
- Reversal-plus-replacement is one idempotent result. Replay cannot return only one half.

Future implementation must lock the club, original receipt, original journal and lines, target period, existing correction/idempotency row, and relevant replacement accounts in deterministic order. Database uniqueness on tenant idempotency and original-receipt correction ownership is mandatory. The 3IJAB rule extends to correction replay: matching request digest alone is insufficient; persisted original, reversal, correction, and optional replacement provenance must all remain canonical. Corruption is not repaired during replay.

## 10. Refund boundary

The reversal journal negates the accounting recognition of the original receipt. It does not prove cash left the club, the payer received money, a bank transaction occurred, or a legal refund obligation was discharged.

A genuine refund or payment-out after a correct receipt is a separate economic event requiring its own approved source, authorization, payee/bank evidence, accounting mapping, idempotency, reconciliation, and lifecycle. It remains unsupported. A correction endpoint must not accept refund method, payee bank details, payment reference, disbursement status, or an instruction to transfer money, and must not describe a reversal as “refunded.”

## 11. History, detail, and statement presentation

Authorized history retains the original row and derives an immutable lifecycle status:

- `posted`: no correction exists;
- `reversed`: a canonical linked correction/reversal exists without replacement;
- `replaced`: a canonical linked correction/reversal and replacement exist.

Status is derived from immutable relationships, not written over historical facts. List/detail may group related records for navigation but may not hide original or reversal effects. Authorized detail exposes public correction, original receipt, reversal journal, and optional replacement receipt/journal identities; original and reversal dates; amount/currency/accounts/fund; reason; original creator; correction actor; creation timestamps; and complete lineage. Internal numeric IDs remain private.

The replacement also appears as its own ordinary-income receipt with its own facts and journal, linked back to the correction. Filters and pagination remain tenant-scoped and deterministic. Secretary may see the same ordinary non-sensitive correction provenance already allowed by the view permission but cannot reverse or replace.

The Club Account Statement continues to consume canonical journals rather than correction-specific arithmetic. The original remains visible; the reversal appears on its effective date and nets the original economic effect to zero; an optional replacement supplies its own effect on its own accounting date. Each committed posting participates in transactionally consistent totals/provenance and changes `snapshot_fingerprint`; rolled-back attempts change neither statement nor fingerprint.

## 12. Failure matrix

| Condition | Required outcome |
|---|---|
| Unauthenticated or non-member actor | Reject before source access |
| Missing view/reverse/create permission | Reject without revealing whether a foreign receipt exists |
| Route club/source tenant mismatch | Tenant-safe not-found/authorization response |
| Missing, non-posted, unsupported, or already-reversed source | Reject; no journal or lineage |
| Original journal/line/account/fund/amount/currency provenance mismatch | Source-specific conflict; fail closed without repair |
| Invalid or closed reversal period; missing authoritative coverage | Reject entire transaction |
| Invalid replacement subtype, payer, account, fund, amount, currency, or period | Roll back reversal and all replacement work |
| Partial reversal or caller-supplied journal lines | Validation rejection |
| Exact idempotent retry | Return canonical existing complete result |
| Changed retry using the same key | Conflict; no mutation |
| Concurrent different keys for one original | One winner; all others conflict/already reversed |
| Journal or persistence failure | Transaction rollback; no orphan correction, journal, line, receipt, or lineage |
| Refund/payment-out fields or intent | Reject as unsupported separate workflow |

Errors must use bounded source-specific codes in a future API contract. Validation order and wording must not disclose another club's receipt, journal, account, actor, or correction state.

## 13. Future implementation acceptance criteria

A later implementation milestone must provide, at minimum:

1. Migration/model constraints for immutable correction identity, tenant ownership, one reversal per original, unique reversal journal, optional replacement, actor, reason, and idempotency.
2. Narrow view/reverse/create authorization matching the matrix above and adversarial tenant tests.
3. A source-specific service that revalidates full original provenance, invokes canonical line-inverting reversal, and optionally invokes normal ordinary-receipt posting atomically.
4. No generic reversal route and no caller-supplied journal lines.
5. Exact/changed replay and persisted-provenance validation covering original, reversal, correction, and replacement.
6. Disposable PostgreSQL independent-worker proof for exact retry, changed retry, different-key same-source race, cross-tenant UUID independence/isolation, and controlled rollback at reversal and replacement stages.
7. SQLite finance regressions plus model immutability, permissions, request, resource, history/detail, statement/fingerprint, and frontend behavior tests.
8. History/detail UI that preserves original facts and clearly distinguishes corrected, reversed, replaced, and refund-unsupported states.
9. Open-period, closed-original-period/later-open-period, inactive/corrupt account, and broken-line fail-closed coverage.
10. No mutation or deletion of original source/journal/lines and no protected financial-data mutation outside isolated test contexts.

## 14. Explicitly deferred

- Real-world refunds, repayments, recoveries, chargebacks, payment-out, and bank execution
- Partial reversal, partial refund, multi-currency, tax decomposition, fees, exchange differences, and reconciliation
- Dual approval, maker/checker, workflow queues, attachments, receipt reissuance, exports, notifications, and external accounting integration
- Correction policies for donations, member payments/allocations, charges, expenses, transfers, opening/cutover journals, and all other sources
- Any implementation, migration, permission seeding, API contract, UI, or automated test

## 15. Files changed and verification

This policy milestone creates this document and makes narrow traceability updates to the source-mapping and milestone-decision registers. Verification is documentation-only: complete diff inspection, `git diff --check`, intended-file scope, clean staging area, and absence of generated artifacts.
