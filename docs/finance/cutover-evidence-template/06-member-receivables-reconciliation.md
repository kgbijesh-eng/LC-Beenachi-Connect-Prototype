# 06 — Member Receivables Reconciliation

**Template state:** Blank, non-authoritative, and dry-run-only control structure. Real member identities and obligation rows must remain in approved restricted storage, never Git or a club-wide statement.

## Private schedule control

| Field | Blank value |
|---|---|
| Package ID | `[PACKAGE-ID]` |
| Private schedule reference | `[EVIDENCE-REFERENCE]` |
| Approved due-category mapping version | `[MAPPING-VERSION]` |
| Schedule version | `[SCHEDULE-VERSION]` |
| Preparer | `[TREASURER/PREPARER]` |
| Internal verifier | `[SECRETARY/INTERNAL-VERIFIER]` |
| Independent reviewer | `[INDEPENDENT-ACCOUNTING-REVIEWER]` |

## Exact control totals

| Control | Amount/result |
|---|---|
| Member-level total in integer paise | `[AMOUNT-IN-PAISE]` |
| Obligation-level total in integer paise | `[AMOUNT-IN-PAISE]` |
| `MEMBER-RECEIVABLES` control total in integer paise | `[AMOUNT-IN-PAISE]` |
| Member total = obligation total | `[PASS/FAIL]` |
| Obligation total = control total | `[PASS/FAIL]` |
| Verifier recomputation | `[PASS/FAIL]` |

## Required disclosures

| Control | Status | Restricted evidence reference | Exception reference |
|---|---|---|---|
| Historical allocations reproduced and not recreated | `[PASS/FAIL]` | `[EVIDENCE-REFERENCE]` | `[EXCEPTION-ID]` |
| Credit balances separately identified | `[PASS/FAIL]` | `[EVIDENCE-REFERENCE]` | `[EXCEPTION-ID]` |
| Disputed obligations separately identified | `[PASS/FAIL]` | `[EVIDENCE-REFERENCE]` | `[EXCEPTION-ID]` |
| Inactive-member balances separately identified | `[PASS/FAIL]` | `[EVIDENCE-REFERENCE]` | `[EXCEPTION-ID]` |
| Proposed write-offs separately identified | `[PASS/FAIL]` | `[EVIDENCE-REFERENCE]` | `[EXCEPTION-ID]` |
| No cross-member netting | `[PASS/FAIL]` | `[EVIDENCE-REFERENCE]` | `[EXCEPTION-ID]` |
| No duplicated historical payment | `[PASS/FAIL]` | `[EVIDENCE-REFERENCE]` | `[EXCEPTION-ID]` |
| Accounting-review status | `[PENDING/COMPLETE]` | `[EVIDENCE-REFERENCE]` | `[EXCEPTION-ID]` |

The journal control line does not replace the private member subledger. Any mismatch, missing schedule, unsupported treatment, or unexplained item blocks activation. Missing evidence is Unavailable or Unresolved, never zero.
