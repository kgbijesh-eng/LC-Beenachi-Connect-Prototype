# 10 — Proposed Opening Journal

**Template state:** Blank, non-authoritative, and dry-run-only. This file creates no journal and selects no opening-equity account.

## Header

| Field | Blank value |
|---|---|
| Package ID | `[PACKAGE-ID]` |
| Club | `[CLUB-ID]` |
| Lions Year | `[LIONS-YEAR]` |
| Exact 1 July effective date | `[CUTOVER-DATE]` |
| Proposed posting date | `[POSTING-DATE]` |
| Dedicated source type | `[ACCOUNTING-OPENING-PACKAGE]` |
| Opaque source ID | `[SOURCE-ID]` |
| Source event | `[ACTIVATED-ONLY-AFTER-AUTHORIZATION]` |
| Proposed idempotency identity | `[IDEMPOTENCY-ID]` |
| Narration | `[NARRATION]` |
| Chart version | `[CHART-VERSION]` |
| Mapping version | `[MAPPING-VERSION]` |
| Package version | `[PACKAGE-VERSION]` |
| Checksum | `[SYSTEM-GENERATED-CHECKSUM]` |
| Actor role | `[TECHNICAL-OPERATOR-IF-AUTHORIZED]` |
| Approval reference | `[PENDING]` |
| State | `[DRY-RUN-ONLY/NON-AUTHORITATIVE]` |

## Proposed lines

| Unique deterministic line number | Account code | Stable account reference | Debit in integer paise | Credit in integer paise | Duplicate-account reason, if validated | Evidence reference | Line validation |
|---:|---|---|---:|---:|---|---|---|
| `[LINE-NUMBER]` | `[ACCOUNT-CODE]` | `[ACCOUNT-REFERENCE]` | `[POSITIVE-INTEGER-PAISE-OR-BLANK]` | `[POSITIVE-INTEGER-PAISE-OR-BLANK]` | `[NOT-APPLICABLE-WITH-REASON]` | `[EVIDENCE-REFERENCE]` | `[PASS/FAIL]` |

Every line must contain exactly one positive integer-paise debit or one positive integer-paise credit: never both, never neither, no negative amount, and no zero amount.

## Journal controls

- [ ] At least two lines exist.
- [ ] At least one debit line exists.
- [ ] At least one credit line exists.
- [ ] Every line is exactly one-sided and positive.
- [ ] Line numbers are unique and deterministic.
- [ ] Line order is deterministic by approved account code and stable account reference.
- [ ] No account is duplicated unless a documented and validated reason permits it.
- [ ] Total debits equal total credits exactly in integer paise.
- [ ] No zero-value or negative line exists.
- [ ] No suspense or balancing plug exists.
- [ ] DR-033 remains Pending and no equity account or amount is selected by this template.

| Total | Amount in integer paise |
|---|---:|
| Total debits | `[TOTAL-DEBITS-IN-PAISE]` |
| Total credits | `[TOTAL-CREDITS-IN-PAISE]` |
| Exact equality | `[PASS/FAIL]` |

Posting requires separate joint activation authorization after mandatory independent accounting review. Template creation satisfies neither requirement.
