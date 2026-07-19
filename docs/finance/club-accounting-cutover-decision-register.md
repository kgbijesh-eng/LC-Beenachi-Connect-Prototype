# Club Accounting Cutover Decision Register

**Status:** Proposed decisions only. DR-001 through DR-025 in `club-account-statement-decision-register.md` remain unchanged and approved. Every new decision below is **Pending**.

**Approval boundary:** Recommendations are advice, not approval. “Owner” identifies who must approve, not who has approved.

| ID | Question and options | Recommendation and reason | Accounting risk | Compatibility impact | Historical-data impact | Approval owner | Status |
|---|---|---|---|---|---|---|---|
| DR-026 | Exact cutover Lions Year: 2026–27, 2027–28, or later | 2027–28; allows prospective evidence and source readiness | Wrong boundary makes statements incomplete | Determines coverage/period activation | Avoids risky reconstruction | Business owner + accounting reviewer | Pending |
| DR-027 | Reconstruct 1 July 2026 or use a future 1 July | Future 1 July unless complete reconstruction is independently proven | Duplication and omitted history | Controls opening-package and integration sequencing | Historical 2026–27 remains non-authoritative | Business owner + accounting reviewer | Pending |
| DR-028 | Initial chart template: full catalog or minimal activated subset | Approve full catalog semantics, activate only required mapped accounts | Over-provisioning encourages unsupported posting | Provisioning catalog/version required | Determines opening schedule scope | Business owner + accounting reviewer | Pending |
| DR-029 | Who owns account-code catalog changes | Named accounting catalog custodian with dual approval | Code drift and wrong classifications | Requires controlled catalog workflow | No rewriting historical codes | Business owner | Pending |
| DR-030 | Bank provisioning: generic account, alias per real account, or sensitive details | Non-sensitive alias per real account; evidence stored outside Git | Combined or exposed bank data | Existing schema supports separate accounts | Separate opening/reconciliation per account | Treasurer + accounting reviewer | Pending |
| DR-031 | Cash structure: one holding or multiple custody/location accounts | One account unless holdings are independently controlled and reconciled | Unreconciled custody differences | May add accounts only | Separate opening counts if multiple | Treasurer + accounting reviewer | Pending |
| DR-032 | Fund treatment: separate accounts, future dimension, or presentation grouping | Dimension only if funds are genuinely controlled; otherwise explicit presentation grouping | False restricted-fund claims | Dimension would require schema/API/report migration | No fixture fund balances may be imported | Business owner + accounting reviewer | Pending |
| DR-033 | Opening offset: opening equity, accumulated surplus, or other equity | Choose only after assets/liabilities reconcile and accounting meaning is approved | Plug balance conceals error | Determines chart and opening line | Requires supported historical explanation | Accounting reviewer | Pending |
| DR-034 | Member receivables control reconciliation method | Exact private member/obligation schedule by due category | Control/subledger mismatch | Requires reconciliation service and privacy controls | Identifies disputes, credits, write-offs | Treasurer + accounting reviewer | Pending |
| DR-035 | Due-category mapping ownership | Named finance catalog owner with accounting approval and versioning | Wrong revenue/liability recognition | Required before charge integration | Prospective mappings; historical map retained | Business owner + accounting reviewer | Pending |
| DR-036 | Special Collection: income, restricted liability, or unsupported | Decide per approved purpose; default unsupported | Misclassifying restricted money | Requires category/source mapping | No legacy inference | Business owner + accounting reviewer | Pending |
| DR-037 | Voluntary Collection: income, liability, or member receivable | Recognize only actual approved receipt; never member receivable | Coercive dues or premature income | Requires new source workflow | No UI fixture import | Business owner + accounting reviewer | Pending |
| DR-038 | Donation treatment: unrestricted income vs restricted liability | Require restriction captured by authoritative donation workflow | Donor restriction breach | Requires donation source and mapping | No receipt-only reconstruction | Business owner + accounting reviewer | Pending |
| DR-039 | Fellowship treatment and lifecycle | Keep outside journal until authoritative shared workflow and classification are approved | Fabricated receivables/income/expense | Requires backend domain and privacy design | Prototype session state excluded | Business owner + accounting reviewer | Pending |
| DR-040 | Expense hierarchy: fixed detailed chart or controlled categories | Approve a small hierarchy with controlled expansion | Miscellaneous buckets obscure spending | Requires expense workflow and mapping | No inference from UI labels | Accounting reviewer | Pending |
| DR-041 | Opening-balance preparer | Name a finance operator who cannot be sole verifier/approver | Self-certification | Approval domain must record role | Preparation evidence retained | Business owner | Pending |
| DR-042 | Independent verifier | Name a person independent of preparation | Unreproduced totals | Separation-of-duty validation required | Verification artifacts retained | Business owner + accounting reviewer | Pending |
| DR-043 | Final opening-package approver | Business owner jointly with accounting reviewer | Unauthorized activation | Approval gate before posting | Locks approved historical boundary | Business owner | Pending |
| DR-044 | Required evidence/sign-off format | Immutable manifest, per-account schedules, hashes, exceptions, dated signatures | Evidence substitution or loss | Requires package/approval schema or controlled external system | Defines audit retention | Business owner + accounting reviewer | Pending |
| DR-045 | Is external accounting review mandatory | Yes before first activation | Internal policy may misstate accounting | Adds approval dependency, no journal semantic change | Reviews reconstruction/opening meaning | Business owner | Pending |
| DR-046 | Dry-run acceptance criteria | Exact account/subledger totals, balanced journal, stable hash, zero duplicate candidates, all negative checks pass | Defective package reaches production | Requires validation/dry-run services | Proves package without posting | Accounting reviewer + technical owner | Pending |
| DR-047 | Who may authorize activation | Joint business-owner and accounting-reviewer go/no-go; technical operator executes only | Premature posting | Requires explicit activation state | Establishes authoritative-from date | Business owner + accounting reviewer | Pending |
| DR-048 | Rollback threshold | Any material amount, mapping, evidence, identity, duplication, or privacy mismatch pauses activation and triggers reviewed reversal | Continuing from incorrect opening | Requires incident/reversal runbook | Reversal remains in history | Business owner + accounting reviewer | Pending |
| DR-049 | Historical transaction backfill | Prohibit by default; exceptional batch only after independent completeness/non-duplication proof | Double counting and gaps | Requires separate importer, identity, reconciliation | Could add pre/post-cutover detail only if approved | Business owner + accounting reviewer | Pending |
| DR-050 | Zero-balance accounts: zero lines, provisioned omission, or inactive | Provision required accounts but omit zero lines; keep optional accounts inactive | Fake zero entries or misleading activation | Compatible with positive-only lines | Approved zero recorded in package | Accounting reviewer | Pending |
| DR-051 | Manual-journal policy | Prohibit initially; later separate maker/checker contract if needed | Uncontrolled bypass of sources | No route/permission initially | No manual backfill shortcut | Business owner + accounting reviewer + security | Pending |
| DR-052 | Cutover evidence retention and privacy | Permanent cutover manifest; restricted supporting schedules with least privilege and defined retention | Privacy breach or lost audit trail | Requires secure evidence references/read controls | Member/bank schedules remain outside Git | Business owner + privacy/security reviewer | Pending |

## 1. Decision dependencies

DR-026–DR-027 must be decided before any package is prepared. DR-028–DR-040 must be decided before chart/source mapping approval. DR-041–DR-048 and DR-052 must be decided before a dry run. DR-049–DR-051 constrain implementation and cannot be overridden by operational convenience.

## 2. Contradiction review

No new recommendation contradicts DR-001–DR-025. In particular, this register preserves Lions Year boundaries, 1 July cutover, separate real assets, fully allocated payments, immutable linked reversals, no synthetic statement balance, no default historical backfill, and DR-025 code normalization.

## 3. Approval recording rule

A future approval must record decision ID, selected option, rationale, owner, accounting reviewer where required, date, compatibility effect, historical-data effect, and document versions. Until then, every row remains Pending. Partial verbal agreement does not activate an account, mapping, package, journal, API, or UI.

## Unsafe shortcuts rejected

The pending status cannot be bypassed by using frontend fund sections, receipt totals, current member ledgers, missing-as-zero assumptions, suspense balancing, generic bank accounts, mid-year dates, manual journals, or implementation defaults as business approval.
