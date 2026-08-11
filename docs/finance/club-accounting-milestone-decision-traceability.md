# Club Accounting Milestone Decision and Code Traceability

**Review date:** 3 August 2026
**Baseline:** `e4e3e40a3851076ad701f9e3483576143bd48bc4` on `milestone-3iizc-announcement-read-state-browser-verification`
**Purpose:** Required preliminary decision-to-code review for the Club Account Statement and double-entry accounting milestone. This note changes no approved accounting policy and authorizes no operational cutover.

## 1. DR-033 decision note

### Decision required

After every opening asset, liability, restriction, and member-receivable position has been independently evidenced and reconciled, select exactly one economically correct equity classification for the opening journal:

1. `OPENING-BALANCE-EQUITY`; or
2. `ACCUMULATED-SURPLUS`.

The selection must bind an exact approved chart/package version and include independent accounting-review and business-approval references. The amount needed to make the journal balance is evidence of neither option and must never determine the classification.

### Consequences

| Option | Appropriate only when | Consequence |
|---|---|---|
| `OPENING-BALANCE-EQUITY` | The cutover needs a clearly identified one-time opening position and the evidence does not establish that it is cumulative retained operating results | Keeps the migration boundary explicit; future movements must not be posted to it as ordinary income or expense |
| `ACCUMULATED-SURPLUS` | Reconciled historical evidence and the independent accounting reviewer establish that the amount is cumulative historical surplus/deficit | Carries the historical result as equity; it must not absorb unexplained differences or restricted liabilities |

**Recommendation:** Do not preselect either code. Complete the restricted cutover package and obtain the independent Chartered Accountant's classification. If the evidence cannot establish cumulative historical surplus, prefer a specifically approved one-time opening-equity classification over falsely labelling the residual as accumulated surplus—but only after the required reviewer and business owner approve that conclusion.

### Current code and tests affected

- `ApprovedLedgerAccountTemplateCatalog` contains both inactive candidates and selects neither.
- `InactiveLedgerAccountProvisioningPlanValidator` rejects equity planning while DR-033 is Pending.
- `OpeningBalanceValidationFoundation` always reports the pending-decision/equity-line blockers and prevents an activation-ready result.
- `Milestone3iik`, `Milestone3iil`, and `Milestone3iim` tests enforce that boundary.
- Cutover package persistence, accounting review, activation authorization, opening posting, authoritative coverage, source integrations, reconciliation certification, and the public statement remain blocked.

No production implementation currently assumes an answer. The blocking tests and contracts agree with the Pending state.

## 2. Decision status review

| Decisions | Status | Implementation trace | Test trace / gap |
|---|---|---|---|
| DR-001, 013, 019, 020, 023-025 | Approved | Journal schema, canonical posting service, immutable-model/database controls, linked reversal, scoped activity ordering | Covered by `Milestone3iib`-`3iig`; public authorization remains unimplemented |
| DR-002, 003, 016, 021 | Approved or approved with qualification | Lions Year periods, 1 July validation foundation, period close, next-open-period reversal | Covered by `Milestone3iib`, `3iid`, `3iif`, `3iim`; activated cutover coverage is absent |
| DR-004, 022, 028-032 | Approved with qualifications | Catalogue supports separate real asset templates and presentation groupings; no real club chart is activated | Catalogue/plan tests exist; exact bank aliases, mappings, and fund projections remain gated |
| DR-005-012 | Approved, several workflows explicitly deferred | Legacy charges/payments exist but are not canonical-journal integrations | Integration tests are intentionally absent; exact mappings, real receiving assets, and lifecycle contracts remain required |
| DR-014, 015, 026-027, 034, 041-051 | Approved with qualifications | Pure opening validation and package/activation design contracts exist | Persistence, approvals, activation, posting, coverage and reconciliation tests remain blocked by DR-033/evidence |
| DR-017-018 | Approved | Permission name and GET-only v1 API contract exist in documentation | Permission is not seeded; route/controller/policy/API tests and frontend integration are absent |
| DR-033 | **Pending** | Both candidates fail closed | Tests correctly require blockers; resolution requires external evidence and approval |
| DR-035-040, 052 | Approved principles with future accounting/privacy gates | No unsafe source hooks or protected evidence storage implemented | Exact mappings/workflows/retention implementation remain deliberately absent |

## 3. Contradiction and completeness findings

- DR-033 is the only decision row whose approval status is Pending.
- DR-052 contains a provisional eight-completed-Lions-Year evidence-retention baseline; it is not sufficient to implement evidence deletion and requires independent accounting/privacy validation before activation.
- Approved-with-qualification rows are not equivalent to implementation authorization. Exact account instances, mappings, opening values, economic dates, activation authority, and protected evidence storage remain package-specific gates.
- The requested three fund sections are approved presentation groupings under DR-032, not independent ledgers, cash balances, legal restrictions, or a fund dimension. Implementations must not manufacture fund balances from the UI labels.
- Donations, Collection Box, Fellowship posting, accruals, Kuri/Chit, and generic Other/suspense accounts remain excluded or separately gated.

## 4. Safe continuation boundary

Work that may continue without resolving DR-033 is limited to fail-closed, non-authoritative foundations: integrity validation, deterministic journal projections, error contracts, permission scaffolding, and tests proving that uninitialized/incomplete coverage returns no balances. The following must not be activated until DR-033 and its evidence gates are satisfied:

- a real opening-equity account selection or value;
- operational cutover package approval or activation;
- opening-journal posting;
- authoritative coverage declaration;
- source-event posting hooks;
- statement responses containing authoritative opening, running, or closing balances;
- period-close certification based on an unactivated cutover.

## 5. Required evidence to unblock DR-033

1. Reconciled signed cash count and separate reconciliation for every real bank account.
2. Private member-and-obligation receivables schedule agreeing exactly to the control account.
3. Supported liabilities and genuine external restrictions, without netting or suspense.
4. Complete exceptions register with zero unexplained residual.
5. Independent Chartered Accountant classification and rationale.
6. President/business approval referencing the same immutable package/chart version.
7. Updated DR-033 row containing option, rationale, approvers, dates, compatibility effect, historical-data effect, and bound document versions.

## 6. Later ordinary-income correction trace

Milestone 3IJAD is a documentation-only application of existing approved decisions; it does not assign a new DR number or alter the cutover boundary above. DR-013 requires immutable correction by linked reversal and corrected posting, DR-021 requires a later open period when the original period is closed, DR-023 keeps original and reversal visible, DR-062 preserves original account/fund/amount attribution while inverting direction, and DR-064 limits the source to bounded ordinary non-member income and keeps business refunds separate.

3IJAD resolves future source-specific architecture as follows: President/Admin and Treasurer may independently post one full reversal under a narrow ordinary-income reversal permission; Secretary retains view-only correction provenance; reversal-only is allowed; one optional replacement may be created atomically but remains a new ordinary-income source and journal under normal validation; exact replay returns one canonical correction; changed replay and competing reversals conflict; and corrupt or cross-tenant provenance fails closed. Correction implementation, migration, permission seeding, service, API, UI, tests, production authorization, and refund/payment-out remain absent.

## 7. Later implementation trace — 3IJAE

Milestone 3IJAE implements the bounded 3IJAD architecture without a new DR. It applies DR-013 immutability, DR-021 open-period correction, DR-023 visible original/reversal history, DR-062 exact fund/account/amount-preserving inversion, and DR-064's ordinary non-member income boundary.

The implementation adds an immutable tenant-scoped correction source, the narrow reversal permission for President/Admin and Treasurer, provenance validation, one complete linked reversal, optional atomic replacement through normal ordinary-income validation, derived history/detail statuses, and a focused UI. Secretary remains view-only. A reversal is explicitly not refund/payment-out evidence, and no other source gains correction support. Focused frontend, backend acceptance, and disposable-PostgreSQL concurrency proof passed on 11 August 2026. 3IJAF reconciles this completed evidence into the finance release gate without altering the decision or implementation boundary.
