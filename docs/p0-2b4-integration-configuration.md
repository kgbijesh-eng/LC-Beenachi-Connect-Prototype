# P0.2B4 — coordinated configuration enforcement: blocked draft

Baseline: `6691e5d4b7df46bb636df39e68067afe10f2f437`.
Draft PR: https://github.com/kgbijesh-eng/LC-Beenachi-Connect-Prototype/pull/35.

**Implementation completion: NO-GO. Merge readiness: NO-GO.**
The approved two-service guard/context integration is implemented, but a new
financial regression exposes a source-coverage dependency outside the approved
application scope. Further implementation and full regression expansion stopped
under the explicit scope gate. Keep the complete unit draft and unmerged.

## Reviewed source and implemented contract

- Application source commit: `55d40444f12c96aeff2b7da45945d00b5ebfc12c`.
- Application tree: `23f694b8caac69339d7e7a344aca6295e9285474`.
- Fixture/diagnostic source: `741abd58f7a7ee3460cda96e3df1c1a98669b483`.
- Diagnostic full tree: `144b9ec09228b85ffeb5f17090628e987843ce78`.
- Later report-only head/tree and exact CI result are recorded in the PR body.

Application changes are restricted to:

1. `backend/app/Services/Finance/AccountingIntegrationConfigurationService.php`
2. `backend/app/Services/Finance/AccountingIntegrationProposalService.php`

Standalone `map`, `activate`, `revokeMapping` and `revokeActivation` use B1 roots
with one attempt. Their explicit `*Within` APIs require an issued live context and
retain a one-attempt nested savepoint. Shared private bodies preserve configuration
rules. Configuration locking is private and context-required; all known production
callers are migrated. Resource queries include organisation scope before locking.

Proposal creation and terminal decisions own one-attempt B1 roots. Terminal
decisions pass their context through `apply` into configuration mutations. Mapping
replacement retains the separate nested revoke/map savepoints and atomic outer
decision/audit transaction. Unsupported ambient standalone calls fail closed.

The existing authority predicates, independent decision-maker and withdrawal rules,
checksums, request-key binding, target snapshots, version conflicts, mapping
overlap rules, activation/revocation logic and audits are retained. No B1 policy,
B2/F1, B3 service, actor policy, schema, migration or production trigger is modified.
No new HTTP surface or permission grant is introduced. Pure input validation may
precede eligibility, as before; it grants no protected access or mutation.

Locking remains B1 authoritative club first, followed by each operation's existing
resource order. Nested operations do not acquire a new root or broaden retries.
Foreign account rejection retains its accounting-domain error without first
locking the foreign row; other foreign targets fail their scoped lookup.

## Closure-blocking financial regression

The existing, unchanged
`backend/app/Services/Finance/SourceAccountingIntegrationService.php` contains two
different activation-selection paths:

- `activation()` around line 365 filters out terminal activations before choosing
  a usable activation.
- `recordCoverageDecision()` around line 420 selects an unfiltered, unordered
  `first()` activation at line 426, including historical revoked activations.
  It may then persist `after_terminal_event` at line 433 instead of consulting the
  replacement current activation.

The new genuine-root PostgreSQL race keeps its original winner and financial
assertions. It starts from a posted opening, valid mappings, a revoked previous
activation and a draft source. Reactivation acquires the club lock first. The B3
source worker waits on that actual lock and runs after activation commits.

The first run failed its journal assertion. A focused repeat corrected the worker
clock to the same synthetic timestamp as the parent, preserved the assertion and
recorded the activation/current-slot/coverage identities before failing again:

| Evidence from focused repeat | Observed |
| --- | --- |
| Original activation | ID 1; terminal revocation event ID 1 |
| New current activation | ID 3; current-slot pointer is 3 |
| Both activation coverage start dates | `2027-07-01` |
| Source accounting date | `2027-08-12` |
| Synthetic activation/event/decision timestamps | `2027-08-12 10:00:00` |
| New persisted coverage activation | **ID 1**, the old revoked activation |
| New coverage reason / requirement | `after_terminal_event` / **false** |
| Worker outcomes | activation success, source success; both transaction depths 0 |
| Journal count | **1**, unchanged from the opening; assertion requires **2** |

Thus successful organisation admission and club ordering do not establish correct
coverage selection after reactivation. This is not a deadlock or timeout: a real
blocking edge was observed and both workers returned success. The PostgreSQL logs
contain zero deadlocks in all six runs. The source service is byte-for-byte
unchanged from the baseline; no baseline runtime reproduction is claimed.

Changing source activation selection would require a third application service
outside the approved B4 scope and review of historical versus current activation
semantics. Disallowing reactivation or accepting the missing journal would also
change established behaviour. Neither workaround is implemented. The financial
assertion is not weakened or relabelled as an expected pass.

## Actual PostgreSQL execution evidence

All runs used new, independent loopback PostgreSQL clusters with fresh credentials,
exact database inventory, expected non-elevated owner, no memberships and unchanged
existing safety checks. B4 suites reuse the exact allowlisted disposable name
`lcb_p02b3_finance_test` in a new cluster each time; they do not contact an existing
B3 database. Source file hashes/inventories remained unchanged during each run.

| Run | Executions | Assertions | Failures | Errors | Skips |
| --- | ---: | ---: | ---: | ---: | ---: |
| B4 eligibility/context/savepoint/atomicity/authority/one-attempt | 85 | 739 | 0 | 0 | 0 |
| B4 suspension/duplicates/configuration-versus-source races | 27 | 325 | 1 | 0 | 0 |
| Initial foreign-resource fixtures | 4 | 24 | 0 | 2 | 0 |
| Historical financial compatibility | 154 | 2,009 | 0 | 0 | 0 |
| Corrected foreign-resource fixtures | 4 | 28 | 0 | 0 | 0 |
| Focused reactivation/source repeat | 1 | 10 | 1 | 0 | 0 |
| **All actual executions** | **275** | **3,135** | **2** | **2** | **0** |

There are **270 unique cases**: latest outcomes **269 passed, one failed, zero
errors and zero skips**. Both failures are executions of the same unresolved
financial case. There are 271 passing executions; repeats are not extra coverage.

The initial foreign-resource fixture errors came from using the same globally
unique mapping/activation reference in two synthetic organisations. Only those
fixture references were made unique. All four foreign-resource assertions then
passed without changing a financial assertion. The focused repeat also uses a
consistent synthetic clock; its financial failure persists.

The passing B4 matrix covers nine operation/replay variants across active,
suspended, foreign, missing and invalid organisations; four nested configuration
APIs across valid, forged, committed, rolled-back, foreign and unsupported ambient
contexts; retained savepoint depth; failure after configuration/decision/audit
insertion; changed proposer authority; and eight actual PostgreSQL `40001` aborts
proving one root attempt and atomic rollback.

The 27 race cases include 18 suspension winner orders, duplicate proposal/approval/
activation requests, and six configuration-versus-source orders. Twenty-six pass;
reactivation-first/source-second fails. Historical governance and source financial
assertions remain unchanged, and the 154-case compatibility suite passes. This does
not substitute for the unexecuted complete acceptance matrix.

All six clusters shut down with exit zero, including failing runs. No protected
database, existing ownership or privileges, production infrastructure or records
were changed. Existing approved corruption fixtures execute only through their
unchanged bounded helper during historical regression; no trigger exception is
expanded. No deployment, provisioning or finance cutover occurred.

[Machine-readable evidence](validation/p0-2b4-postgres-evidence.json) retains each
original runner record, JUnit hash, per-case outcome, exact source head, native
deadlock count and structured lock/restoration/coverage proof. Failed and errored
attempts remain failures/errors, never passes or skips.

## Deferred gates and remaining risk

The complete B1, B2/F1, B3, security, SQLite and canonical-finance regression matrix
has **not** been rerun for B4. Work stopped on the mandatory dependency gate. The
previous B3 results are not counted as B4 validation. Additional direct implicit
current-slot blocking and nested-parent rollback cases remain outstanding; passing
root-lock races alone are not claimed as complete implicit-lock coverage.

The B6 membership/officer/role/permission writer gap remains unchanged. B5 ledger
and period administration, B7 read admission and B8 reconciliation are not migrated.
The proposal read APIs remain unchanged; mutation guards are not generic read
wrappers. No broad synchronisation or production-release claim is made.

CI is frontend/tooling verification, not PostgreSQL financial validation. Exact
final-head CI is reported in the PR; a green workflow cannot close this blocker.
Existing Vite/Node compatibility and deprecation warnings require no scope change.

## Exact changed-file inventory

```text
backend/app/Services/Finance/AccountingIntegrationConfigurationService.php
backend/app/Services/Finance/AccountingIntegrationProposalService.php
backend/tests/Feature/IntegrationConfigurationConcurrencyTest.php
backend/tests/Feature/IntegrationConfigurationEnforcementTest.php
backend/tests/Feature/IntegrationConfigurationResourceScopeTest.php
backend/tests/Support/IntegrationConfigurationFixture.php
backend/tests/Support/IntegrationConfigurationWorker.php
scripts/verify-cutover-bootstrap-postgres.ps1
docs/p0-2b4-integration-configuration.md
docs/validation/p0-2b4-postgres-evidence.json
```

## Separate approval required

Request approval for a narrowly scoped source-coverage investigation/design and,
after the semantics are accepted, remediation of activation selection in
`SourceAccountingIntegrationService.php` with charge/payment reactivation,
revocation, replay and both-winner-order PostgreSQL regressions. Existing persisted
coverage decisions, journals, source provenance and audit history must remain
immutable. No B1, schema or trigger expansion is assumed.

After resolving that dependency, resume the remaining B4 acceptance gates and full
coordinated regression selection before any completion or merge recommendation.

No rollback has been performed. If this unit is later approved and merged, code
rollback must revert the coordinated configuration/proposal interfaces together,
preserve B1-B3, and never rewrite financial history or configuration events. For
now the entire unit stays draft; no partial merge or production action is allowed.
