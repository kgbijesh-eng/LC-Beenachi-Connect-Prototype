# Cutover Evidence Package Template

**Status:** Blank, reusable, non-authoritative, documentation-only, and dry-run-only. This directory contains no operational data and authorizes no access, provisioning, posting, activation, implementation, merge, or deployment.

## Purpose and boundary

Use these files only to structure a future LC Beenachi accounting cutover evidence package. Sensitive completed schedules remain outside Git in approved restricted storage; Git retains only reusable structure and non-sensitive evidence-reference fields. Missing evidence is recorded as **Unavailable** or **Unresolved**, never zero.

Template creation does not provision any account and does not authorize activation.

DR-033 remains **Pending**. No template selects or approves an opening-equity account or amount. No suspense, balancing plug, historical backfill, generic Other account, or manual journal is permitted. Posted journals are never edited or deleted; a material posted error requires a linked reversal and separately approved replacement.

Independent Chartered Accountant/accounting review remains mandatory before activation. Creating these templates does not perform, replace, or satisfy that review, and no reviewer is appointed or inferred here. This documentation is not Chartered Accountant certification, statutory advice, tax advice, legal advice, an audit, a review engagement, or assurance.

Actual role assignments are governed by the approved finance decision register. Reusable files use only these role placeholders:

- `[TREASURER/PREPARER]`
- `[SECRETARY/INTERNAL-VERIFIER]`
- `[PRESIDENT/BUSINESS-APPROVER]`
- `[INDEPENDENT-ACCOUNTING-REVIEWER]`
- `[TECHNICAL-OPERATOR-IF-AUTHORIZED]`

## Package lifecycle

`Draft → Prepared → Internally Verified → Accounting Reviewed → Business Approved → Dry-Run Accepted → Activation Authorized → Posted → Reversed/Replaced (if applicable)`

No transition is automatic. Preparation and dry-run acceptance do not authorize activation. Each completed package requires one immutable package ID and version. Any post-approval change invalidates the prior checksum and requires a new version, checksum, and renewed approval.

## Template inventory

| File | Purpose |
|---|---|
| `01-cover-checklist.md` | Package identity, versions, roles, and status |
| `02-evidence-inventory.md` | Non-sensitive references to supporting evidence |
| `03-chart-and-mapping-register.md` | Verified launch subset and approved mappings |
| `04-cash-count-schedule.md` | Exact integer-paise physical cash count |
| `05-bank-reconciliation-schedule.md` | One exact reconciliation per real bank account |
| `06-member-receivables-reconciliation.md` | Private subledger-to-control reconciliation |
| `07-liabilities-and-restrictions-schedule.md` | Liabilities, restrictions, and custodial balances |
| `08-other-opening-accounts-schedule.md` | Evidence for another already-approved opening account |
| `09-opening-equity-review.md` | Pending DR-033 review without selection |
| `10-proposed-opening-journal.md` | Blank, non-authoritative, dry-run-only journal preview |
| `11-exceptions-and-unresolved-items.md` | Activation-blocking exceptions and resolutions |
| `12-privacy-isolation-and-access-checklist.md` | Least privilege, club scope, and tenant isolation |
| `13-dry-run-results.md` | DR-046 outcome record |
| `14-sign-off-and-activation-record.md` | Separated future review and authorization roles |
| `15-reversal-readiness-checklist.md` | Linked-reversal and replacement readiness |

## Permitted placeholders

Use placeholders such as `[PACKAGE-ID]`, `[LIONS-YEAR]`, `[CUTOVER-DATE]`, `[ACCOUNT-CODE]`, `[AMOUNT-IN-PAISE]`, `[EVIDENCE-REFERENCE]`, `[ACTOR-ID]`, `[PENDING]`, and `[NOT-APPLICABLE-WITH-REASON]`. Do not place real people, bank identifiers, member records, donor or beneficiary details, balances, signatures, credentials, private storage paths, or operational identifiers in this directory.
