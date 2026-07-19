# 04 — Physical Cash Count Schedule

**Template state:** Blank, non-authoritative, and dry-run-only. Do not enter real cash values in Git.

## Count identity

| Field | Blank value |
|---|---|
| Package ID | `[PACKAGE-ID]` |
| Cutover date | `[CUTOVER-DATE]` |
| Count time | `[COUNT-TIME]` |
| Primary custodian/preparer | `[TREASURER/PREPARER]` |
| Internal verifier | `[SECRETARY/INTERNAL-VERIFIER]` |
| Evidence reference | `[EVIDENCE-REFERENCE]` |

## Denomination arithmetic

| Denomination value in integer paise | Quantity | Computed subtotal in integer paise | Verification |
|---:|---:|---:|---|
| `[DENOMINATION-IN-PAISE]` | `[QUANTITY]` | `[DENOMINATION-IN-PAISE × QUANTITY]` | `[PASS/FAIL]` |

`Total physical cash in integer paise = exact sum of all computed denomination subtotals`

No floating-point calculation or rounded balance is permitted.

## Temporary custody and handover

| Temporary collector reference | Collection purpose | Amount in integer paise | Collection date/time | Receipt evidence | Handover date/time | Treasurer acknowledgement | Difference/unresolved handover | Evidence reference |
|---|---|---:|---|---|---|---|---|---|
| `[COLLECTOR-REFERENCE]` | `[PURPOSE]` | `[AMOUNT-IN-PAISE]` | `[DATE-TIME]` | `[EVIDENCE-REFERENCE]` | `[DATE-TIME]` | `[PENDING]` | `[NONE/UNRESOLVED]` | `[EVIDENCE-REFERENCE]` |

Temporary collectors do not create permanent cash accounts. Handover amounts must be separately supported and must not be silently included or netted.

## Exact reconciliation

| Control | Blank result |
|---|---|
| Total physical cash | `[AMOUNT-IN-PAISE]` |
| Separately supported handover amount | `[AMOUNT-IN-PAISE]` |
| Proposed `CASH-ON-HAND` opening | `[AMOUNT-IN-PAISE]` |
| Exact difference | `[AMOUNT-IN-PAISE]` |
| Activation result | `[PASS/BLOCKED]` |

Any difference, including ₹0.01, is recorded as an activation blocker. Missing evidence is Unavailable or Unresolved, never zero.

## Role confirmations

- `[TREASURER/PREPARER]`: `[PENDING]`
- `[SECRETARY/INTERNAL-VERIFIER]`: `[PENDING]`
- `[INDEPENDENT-ACCOUNTING-REVIEWER]`: `[PENDING]`
