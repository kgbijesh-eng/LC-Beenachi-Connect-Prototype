# 05 — Bank Reconciliation Schedule

**Template state:** Blank, non-authoritative, and dry-run-only. Repeat this complete section once per verified real bank account. Never enter real account numbers, protected bank details, or statement content in Git. Combined multi-bank balances are prohibited.

## Bank-account section

| Field | Blank value |
|---|---|
| Package ID | `[PACKAGE-ID]` |
| Non-sensitive ledger alias | `[BANK-ALIAS]` |
| Private evidence reference | `[EVIDENCE-REFERENCE]` |
| Account purpose | `[APPROVED-PURPOSE]` |
| Statement date | `[DATE]` |
| Preparer | `[TREASURER/PREPARER]` |
| Internal verifier | `[SECRETARY/INTERNAL-VERIFIER]` |
| Independent reviewer | `[INDEPENDENT-ACCOUNTING-REVIEWER]` |

## Required formula and sign convention

```text
Statement balance
+ deposits/receipts recorded in books but not yet reflected by the bank
- payments recorded in books but not yet reflected by the bank
± validated bank-only items awaiting book treatment
= reconciled book/opening balance
```

| Reconciliation component | Sign | Amount in integer paise | Evidence reference | Validation status |
|---|---:|---:|---|---|
| Statement balance | Base | `[AMOUNT-IN-PAISE]` | `[EVIDENCE-REFERENCE]` | `[PENDING]` |
| Book-recorded deposit/receipt not yet reflected by bank | `+` | `[AMOUNT-IN-PAISE]` | `[EVIDENCE-REFERENCE]` | `[PENDING]` |
| Book-recorded payment not yet reflected by bank | `-` | `[AMOUNT-IN-PAISE]` | `[EVIDENCE-REFERENCE]` | `[PENDING]` |
| Validated bank-only item awaiting book treatment | `+/-` | `[AMOUNT-IN-PAISE]` | `[EVIDENCE-REFERENCE]` | `[PENDING]` |
| Reconciled book/opening balance | `=` | `[AMOUNT-IN-PAISE]` | `[EVIDENCE-REFERENCE]` | `[PENDING]` |

Every adjustment requires an evidence reference and exact integer-paise arithmetic. Timing items remain individually identifiable until cleared and never become permanent balancing amounts.

## Difference and decision

| Control | Blank result |
|---|---|
| Proposed ledger opening | `[AMOUNT-IN-PAISE]` |
| Reconciled book/opening balance | `[AMOUNT-IN-PAISE]` |
| Exact unresolved difference | `[AMOUNT-IN-PAISE]` |
| Activation result | `[PASS/BLOCKED]` |
| Exception reference | `[EXCEPTION-ID]` |

An unresolved difference of ₹0.01 or more blocks activation. Missing evidence is Unavailable or Unresolved, never zero.
