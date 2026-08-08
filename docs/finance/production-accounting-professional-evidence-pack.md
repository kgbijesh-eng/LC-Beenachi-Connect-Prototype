# Production Accounting Professional Evidence Pack

## Status and use

This is a non-sensitive preparation template. It does not contain professional evidence, approve DR-033, appoint an authority, or authorize accounting activation. Real documents remain outside Git; only opaque references, digests, dates, versions, and status metadata may enter the application through its approved evidence-reference mechanism.

Status vocabulary: `REQUIRED`, `AVAILABLE`, `MISSING`, `NOT YET APPROVED`, `NOT APPLICABLE`.

## A. Engagement identity

| Field | Value | Status |
|---|---|---|
| Club opaque reference | `[UNSET]` | MISSING |
| Lions Year | `[UNSET]` | MISSING |
| Proposed cutover boundary | `[UNSET]` | MISSING |
| Cutover package public ID | `[UNSET]` | MISSING |
| Package version | `[UNSET]` | MISSING |
| Chart version | `[UNSET]` | MISSING |
| Canonical checksum | `[UNSET]` | MISSING |
| Preparation date | `[UNSET]` | MISSING |

The implemented package contract currently binds the canonical synthetic design to Lions Year `2027-28`, cutover date `2027-07-01`, chart `CLUB-COA-2026-01`, INR, a positive package version, and the exact package checksum. A real package must be verified against the then-current approved boundary rather than copying this template.

## B. DR-033

Canonical decision-register wording:

> Opening offset: opening equity, accumulated surplus, or other equity

Canonical recommendation: choose only after assets/liabilities reconcile and accounting meaning is approved. A plug balance conceals error; the decision determines the chart and opening line and requires a supported historical explanation.

| Item | Value | Status |
|---|---|---|
| Current decision status | Pending | NOT YET APPROVED |
| Decision authority | Accounting reviewer; implemented transition requires an appointed independent CA with exact permission | REQUIRED |
| Permitted implemented classifications | `OPENING-BALANCE-EQUITY` or `ACCUMULATED-SURPLUS` | REQUIRED |
| Reconciled supporting evidence | `[UNSET]` | MISSING |
| Written rationale | `[UNSET]` | MISSING |
| Package/version/checksum binding | `[UNSET]` | MISSING |
| Decision date and opaque signed-evidence reference | `[UNSET]` | MISSING |

Pending DR-033 makes preview return `BLOCKED_DR033`, prevents a valid package, and prevents the lifecycle reaching accounting review, President approval, dual authorization, or execution. No default, inference, balancing plug, suspense account, or fallback is permitted.

## C. Opening schedule checklist

Every row requires a unique non-sensitive row key, integer-paise amount, direction, canonical fund attribution where posted, and an eligible same-club active INR account where mapped.

| Canonical schedule type | Required treatment | Status |
|---|---|---|
| `cash_on_hand` | Debit; mapped asset; `GENERAL_CLUB_FUND`; cash-count evidence | NOT VERIFIED |
| `bank_account` | Debit; one mapped asset per verified real bank alias; fund code; reconciliation evidence | NOT VERIFIED |
| `member_receivables` | Debit; mapped control account; private obligation schedule reconciles exactly | NOT VERIFIED |
| `liability` | Credit; mapped liability; supporting evidence | NOT VERIFIED |
| `external_restriction` | Credit; mapped supported restriction/liability; restriction evidence | NOT VERIFIED |
| `other_asset` | Debit; mapped supported asset; evidence | NOT VERIFIED |
| `general_club_fund` | Presentation group `GENERAL-CLUB-FUND`; no ledger account | NOT VERIFIED |
| `service_activity_fund` | Presentation group `SERVICE-ACTIVITY-FUND`; no ledger account | NOT VERIFIED |
| `fellowship_member_activity_fund` | Presentation group `FELLOWSHIP-MEMBER-ACTIVITY-FUND`; no ledger account | NOT VERIFIED |
| `equity_preview` | Credit; canonical fund; account derived only from resolved DR-033 | NOT VERIFIED |

## D. Reconciliation checklist

| Required evidence type | Opaque reference | Version/date | Digest | Status |
|---|---|---|---|---|
| `cash_count` | `[UNSET]` | `[UNSET]` | `[UNSET]` | MISSING |
| `bank_reconciliation` | `[UNSET]` | `[UNSET]` | `[UNSET]` | MISSING |
| `member_receivables` | `[UNSET]` | `[UNSET]` | `[UNSET]` | MISSING |
| `liabilities_restrictions` | `[UNSET]` | `[UNSET]` | `[UNSET]` | MISSING |
| `reconciliation` | `[UNSET]` | `[UNSET]` | `[UNSET]` | MISSING |

All five references must be `verified`. Debits must equal credits, the unexplained difference must be zero paise, each canonical fund must balance, and the opening preview must be `BALANCED_PREVIEW`. Zero-valued schedule rows are permitted but create no journal line.

## E. Mapping checklist

| Gate | Evidence | Status |
|---|---|---|
| Same-club active INR ledger accounts | `[UNSET]` | NOT VERIFIED |
| Unique canonical account/fund mappings | `[UNSET]` | NOT VERIFIED |
| Canonical fund attribution on every posting row | `[UNSET]` | NOT VERIFIED |
| Exact production charge/payment mapping proposal and independent approval | `[UNSET]` | NOT YET APPROVED |
| Current configuration/version and coverage boundary | `[UNSET]` | NOT VERIFIED |
| No expired, revoked, overlapping, wrong-club, inactive, or fallback mapping | `[UNSET]` | NOT VERIFIED |

Production integration configuration is a separate dual-control workflow. A valid opening package does not approve production source mappings.

## F. Independent CA review checklist

| Field | Value | Status |
|---|---|---|
| Reviewer identity/reference | `[UNSET]` | MISSING |
| Professional capacity | `[UNSET]` | MISSING |
| Independence confirmed outside the application | `[UNSET]` | MISSING |
| Current package-scoped `independent_ca` appointment | `[UNSET]` | NOT VERIFIED |
| Current `finance.cutover.package.accounting-review` assignment | `[UNSET]` | NOT VERIFIED |
| Package/version/checksum reviewed | `[UNSET]` | MISSING |
| Evidence references reviewed | `[UNSET]` | MISSING |
| Exceptions | `[UNSET]` | MISSING |
| DR-033 opinion/decision | `[UNSET]` | NOT YET APPROVED |
| Written rationale | `[UNSET]` | MISSING |
| Decision date | `[UNSET]` | MISSING |
| Opaque signed-evidence reference | `[UNSET]` | MISSING |
| Digest/hash | `[UNSET]` | MISSING |

The application enforces appointment, permission, package binding, actor separation from preparer/verifier and President, and one-time immutable classification. It cannot establish professional qualification or real-world independence; those require external verification.

## G. President approval checklist

| Field | Value | Status |
|---|---|---|
| Unique active same-club President identity/reference | `[UNSET]` | NOT VERIFIED |
| Current `finance.cutover.package.business-approve` assignment | `[UNSET]` | NOT VERIFIED |
| Package/version/checksum | `[UNSET]` | MISSING |
| Trusted CA review reference | `[UNSET]` | MISSING |
| DR-033 state | Pending | NOT YET APPROVED |
| Decision | `[UNSET]` | NOT YET APPROVED |
| Conditions/exceptions | `[UNSET]` | MISSING |
| Decision date | `[UNSET]` | MISSING |
| Opaque evidence reference | `[UNSET]` | MISSING |
| Digest/hash | `[UNSET]` | MISSING |

The President decision alone cannot activate accounting. The same President and CA must separately accept the dry run and separately authorize activation against the exact immutable binding.

## H. Activation-authority checklist

| Requirement | Status |
|---|---|
| One current package-scoped technical-operator appointment | NOT VERIFIED |
| One current package-scoped `finance.cutover.package.execute` assignment | NOT VERIFIED |
| Operator is not preparer, verifier, CA, or another conflicting package authority | NOT VERIFIED |
| Current CA appointment and review permission revalidated | NOT VERIFIED |
| Unique active President and all stage-specific permissions revalidated | NOT VERIFIED |
| Separate current President/CA dry-run decisions | NOT VERIFIED |
| Separate current President/CA activation decisions | NOT VERIFIED |
| No revocation, invalidation, expiry, stale lock, stale checksum, prior consumption, or prior opening journal | NOT VERIFIED |
| Open matching accounting period and balanced current preview | NOT VERIFIED |
| Exact execution reference and idempotency key approved | NOT VERIFIED |

## I. Final GO/NO-GO checklist

| Mandatory item | Status |
|---|---|
| DR-033 resolved by real qualified review | FAIL |
| Reconciled real opening evidence complete | FAIL |
| Exact package/version/checksum frozen | NOT VERIFIED |
| Independent CA review recorded | FAIL |
| President approval recorded | FAIL |
| Dry run accepted by both authorities | NOT VERIFIED |
| Production mappings/configuration approved and current | FAIL |
| Activation authorized by both authorities | FAIL |
| Technical operator appointment and permission current | FAIL |
| Production technical/operational gates closed | FAIL |

Current result: **NO-GO**. This pack cannot itself change that result.

## Evidence boundary

In repository: this template, non-sensitive checklists, opaque references, SHA-256 digests, dates, versions, status metadata, and procedural evidence.

Outside repository in approved restricted storage: bank statements and account numbers, member-level receivables, CA working papers and credentials, signed confidential opinions, identity documents, private signatures, raw production exports, passwords, tokens, and database dumps.
