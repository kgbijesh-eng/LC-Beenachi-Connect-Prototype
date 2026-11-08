# P0.2B3 coordinated financial enforcement

Baseline main: `b44e6de7c4de258c918bd655ee8853b2ba85a1b4`.
Draft PR: https://github.com/kgbijesh-eng/LC-Beenachi-Connect-Prototype/pull/34.
Validated application/test source: `c978366bab1437b506e934f49f784d2482f31693`.
Application source remains `2a059304615839b50ac39af09c84038994375d10`,
`backend/app` tree `2dca6844310fdf65678bd91a9acca1d6f110f7ca`.
The approved exception adds only test infrastructure/fixtures, validation and reports.
No B1 policy, production migration/trigger definition, schema, route, model,
configuration, workflow, frontend or financial-governance change is included.

## Result and boundaries

**Implementation completion: GO for the approved B3 scope. Merge readiness: NO-GO pending separate business-owner approval.** The complete selected local acceptance unit passed. Deferred writer synchronisation is not represented as complete.

The prior five-case fixture dependency is resolved under the business owner's
explicit three-trigger exception. No further trigger or governance exception was
needed. The final selection below reruns every coordinated gate on the same
committed PHP source; source file inventory and hashes remained unchanged for each
PostgreSQL run. Documentation may follow without changing that source.

No protected database, existing instance, production record or existing ownership
was accessed or changed. No deployment, provisioning or finance cutover occurred.
Keep the PR draft and unmerged. Do not merge any partial increment.

## Caller, context and lock contract

All paths in this table are under `backend/app/Services/`.

| File | Standalone / nested API and ownership | Original attempts |
| --- | --- | ---: |
| `Finance/ClubJournalPostingService.php` | `post` owns B1 root; `postWithin` requires issued context and retains nested savepoint | 3 |
| `Finance/JournalReversalService.php` | `reverse` owns B1 root; `reverseWithin` retains savepoint and propagates context to posting | 3 |
| `Finance/ClubOpeningJournalActivationService.php` | Existing B2 root; only posting adapter becomes `postWithin` | 3, unchanged |
| `Finance/PaymentRecordingService.php` | Standalone root, fresh scoped member and existing actor checks | 1 |
| `Finance/ReceiptGenerationService.php` | Standalone root, scoped payment lock before existing authorisation/receipt checks | 1 |
| `FinanceCollections/FinanceWriteService.php` | Seven roots: create/update/issue/close/cancel charge and record/void payment | 1 each |
| `Finance/SourceAccountingIntegrationService.php` | Seven context-required mutation/replay interfaces; legacy unproven entry points reject | No new root |
| `Finance/ClubExpenseRecordingService.php` | Root; posting receives context | 3 |
| `Finance/ClubExpenseReversalService.php` | Root; reversal receives context | 3 |
| `Finance/ClubInternalTransferService.php` | Root; posting receives context | 3 |
| `Finance/ClubOrdinaryIncomeReceiptService.php` | Root and `recordWithin` preserving correction's savepoint | 3 |
| `Finance/ClubOrdinaryIncomeReceiptCorrectionService.php` | Root; reversal and replacement receive issued context | 3 |

Opening, reversal, source integration, expenses, transfers and ordinary income call
posting. Source integration, expense reversal and income correction call reversal.
Correction calls nested replacement recording. Legacy controllers call standalone
payment/receipt roots. Collections controllers call the seven standalone roots;
collections owns the source-integration mutation chain. Read-only binding and
reconciliation helpers remain read-only and unchanged.

The authoritative club lock and fresh eligibility check precede resource locks,
business mutation and replay return. Resource locking queries include organisation
scope before `FOR UPDATE`. Journal missing/foreign distinctions retain non-locking
ID/owner metadata checks without acquiring foreign financial locks. Secondary lock
statement order remains unchanged. Unknown ambient calls fail closed; transaction
depth and stale loaded activity are not admission proof. Nested operations require
an issued, current, same-organisation B1 context, never a global/request flag.

Context grants no actor authority. Existing authorisation, trusted-internal journal
actor semantics, approval, balance, replay/version binding, immutable provenance,
source correction and accounting rules remain. B2 external technical appointees do
not gain a new membership requirement. The narrow opening adapter preserves F1
outcome classification, separate durable audit roots, one-time execution and
consumption cardinality. No source, journal, success or consumption duplicates are
permitted by the tests.

## Approved disposable corruption exception

`backend/tests/Support/DisposableCorruptionFixture.php` accepts a data-only plan of
at most 16 one-row, organisation-scoped mutations and bound scalar values, never a
callback. It runs only on exact `lcb_p02b3_finance_test` with explicit opt-in.

Before DDL it verifies loopback endpoint/port, database OID and postmaster start
attested by the runner to its admin-verified PostgreSQL system identifier/data
path; exact database inventory; owner `lc_beenachi_app`; no elevated flags,
inheritance or role memberships; no other client backends; depth-zero entry;
authoritative organisation via the unchanged B1 root. Targets are scoped and locked
after the club. The application role never receives elevated privileges.

The helper snapshots all triggers and constraints on its nine allowlisted mutation
tables. The three immutable triggers additionally require exact trigger definition,
function body/owner, enabled state, type, argument count and security/configuration.
Only these names may be disabled, only on their own tables:

- `club_accounting_source_integrations_immutable`
- `club_accounting_source_integration_events_immutable`
- `club_accounting_source_coverage_decisions_immutable`

A fixed raw-DML loop runs while selected triggers are disabled. No financial
service runs then. No FK/CHECK constraint or other trigger is disabled. Existing
journal/source rows used by historical corruption cases receive only bounded
fixture DML where existing guards permit it; no additional journal trigger exception
is introduced. Format-valid wrong checksum data preserves semantic-corruption
assertions while leaving PostgreSQL CHECK constraints intact.

All selected triggers are re-enabled and the entire catalogue snapshot must match
before commit. PostgreSQL XID status and the unchanged native connection prove
commit/rollback; idle PDO state, depth and arbitrary exception SQLSTATE are not
outcome proof. Verification repeats after the root and immediately before business
assertions. Query-log evidence records identical before/after catalogue SHA-256,
organisation, XID, outcome and selected triggers.

Failure with confirmed rollback retains the original error and proves catalogue
restoration. Unprovable outcome/restoration writes a discard marker and prohibits
further database/business assertions; the runner stops and retires that fresh
cluster without after-queries. Failure to persist the marker terminates the process
with exit 97. The two explicit uncertainty tests require `EXPECTED_DISCARD`, not an
ordinary reusable-cluster PASS, and shutdown exit zero.

### Resolution of every historical skipped case

| Original case | Disposition |
| --- | --- |
| `test_sqlite_public_source_identity_guards_are_canonical_null_aware_and_immutable` | Moved to `SourcePublicIdentitySqliteTest` on disposable `:memory:`; original invalid/null/noncanonical UUID, immutable identity and uppercase checksum assertions retained. Raw FK-valid setup invokes no guarded financial service. |
| `test_corrupt_reversal_event_cannot_short_circuit_authoritative_replay` | Genuine-root PostgreSQL; bounded corruption of reversal event; restored event trigger before replay assertion. |
| `test_reconciliation_detects_child_binding_key_checksum_and_binding_corruption` | Genuine-root PostgreSQL; bounded child/source/journal corruption; only named integration trigger temporarily disabled. All original reconciliation findings retained. |
| `test_exact_replay_rejects_every_material_source_and_journal_corruption` | Genuine-root PostgreSQL; all eight material corruptions retained; no trigger disable needed for these bounded fields; explicit-context replay. |
| `test_reconciliation_independently_detects_coverage_decision_corruption` | Genuine-root PostgreSQL; only named coverage trigger temporarily disabled; original independent coverage findings retained. |

The four PostgreSQL cases remain in
`backend/tests/Feature/Milestone3iizhChargePaymentAccountingIntegrationTest.php`.
That class now has 33 passing cases; the extracted SQLite class has one. The
machine-readable reconciliation maps that exact relocation for logical unique
counts; it does not inflate coverage or count an old skip as a pass.

Helper tests cover success/restored enforcement, CHECK-error atomic rollback,
re-enable acknowledgement failure with verified abort, rejected cluster identity,
unapproved/foreign/missing/ambient targets, concurrent client rejection, and
unexpected disabled state. Separate fresh-cluster tests cover unavailable catalogue
verification and post-commit acknowledgement loss. They deliberately perform no
subsequent business operation after discard.

## Final coordinated local validation

| Final gate | Executions | Assertions | Failures | Errors | Skips | Status |
| --- | ---: | ---: | ---: | ---: | ---: | --- |
| Integration | 197 | 2,554 | 0 | 0 | 0 | PASS |
| Finance | 94 | 1,345 | 0 | 0 | 0 | PASS |
| B3Historical | 154 | 2,009 | 0 | 0 | 0 | PASS |
| B3Fixtures | 40 | 386 | 0 | 0 | 0 | PASS |
| B3Poison | 1 | 9 | 0 | 0 | 0 | EXPECTED_DISCARD |
| B3PoisonCommit | 1 | 8 | 0 | 0 | 0 | EXPECTED_DISCARD |
| B3 | 43 | 498 | 0 | 0 | 0 | PASS |
| Guard | 34 | 313 | 0 | 0 | 0 | PASS |
| F1 | 8 | 228 | 0 | 0 | 0 | PASS |
| B3Direct | 152 | 1,378 | 0 | 0 | 0 | PASS |
| B3Contexts | 42 | 343 | 0 | 0 | 0 | PASS |
| B3Resources | 18 | 144 | 0 | 0 | 0 | PASS |
| B3Workers | 8 | 134 | 0 | 0 | 0 | PASS |
| B3Races | 54 | 766 | 0 | 0 | 0 | PASS |
| B3Cross | 12 | 162 | 0 | 0 | 0 | PASS |
| B3OpeningPeer | 2 | 38 | 0 | 0 | 0 | PASS |
| B3Retries | 36 | 792 | 0 | 0 | 0 | PASS |
| SQLite | 30 | 1,051 | 0 | 0 | 0 | PASS |


Final selection: **926 executions, 847 unique cases, 12,158 assertions, zero failures/errors/skips**. PostgreSQL: **896 / 11,107**; SQLite: **30 / 1,051**. SQLite separates identity **1 / 19** from unchanged HTTP security **29 / 1,032**. Two PostgreSQL executions / 17 assertions are expected-discard safety tests; ordinary regression gates account for the other 894 PostgreSQL executions. Repeated groups/F1 stability are executions, not extra unique coverage. All final fresh clusters shut down with exit zero; identity, role and source-integrity gates passed. Local runtime: PostgreSQL 17.5, PHP 8.4.23, PowerShell 7.

[Machine-readable evidence](validation/p0-2b3-postgres-evidence.json) contains every
run's identity/safety/shutdown evidence, original head, per-case results, JUnit
SHA-256, PostgreSQL deadlock counts and structured lock/restoration/abort proof.
Its `prior_phase` preserves the complete previously published diagnostic history.
Skipped results are never counted as passed. These are the explicitly inventoried
coordinated historical/security/finance gates, not a claim to run the unfiltered
backend default suite or every remaining milestone.

### Security, finance and concurrency evidence

- Direct matrix: 27 active/replay operations, 108 suspended/foreign/missing/invalid
  cases and 17 post-insert atomic rollback cases. Stale supplied models cannot grant
  admission. Full financial/audit snapshots remain unchanged on rejection.
- Source matrix: all seven mutation/replay interfaces with active, forged,
  committed, rolled-back, foreign and unsupported ambient contexts. Existing B1
  tests also retain retry/context lifecycle and framework compatibility coverage.
- Suspension: all 27 operation/replay variants in both winner orders. Tests
  observe actual `pg_blocking_pids` club-lock edges. Suspension-first rejects
  before mutation/replay; mutation-first finishes atomically then suspension wins.
- Foreign resource scope: 18 held-foreign-row cases covering journal periods,
  accounts, reversal, receipt/payment, collection, source and dependent service
  resources. A lock timeout is a failure, never an accepted domain rejection.
- Native abort matrix: actual PostgreSQL `40001` and `40P01`, standalone and nested
  post/reverse recovery/exhaustion; collections one-attempt issue/payment/cancel/void;
  six canonical three-attempt paths with serialization recovery/exhaustion. XIDs
  prove abort/commit, contexts expire/rebuild, exhaustion restores full snapshots.
  Test hooks inject failure after financial insertion, including replacement after
  correction reversal. Original retry budgets and savepoints remain.
- Historical collections, expense and expense-reversal workers independently check
  exact DB/role/owner/cluster identity and explicit synthetic instance configuration.
  Original winner/loser, journal, balance, provenance and count assertions remain.
- Eight period-close races cover journal/reversal/expense/replacement versus the
  existing unmodified close service in both orders, with observed club-lock waits.
  Closing-first rejects and rolls back; mutation-first completes then close succeeds.
- Two fully authorised opening-versus-standalone-post races exercise both winner
  orders: two balanced journals, sequences 1 and 2, exactly one opening success,
  attempt, terminal success audit and authority consumption.
- Full C+D+E retains the original forced mixed-order deadlock regression, original
  winner/provenance assertions and ten identical plus ten changed opening-race
  iterations. Normal race gates require zero PostgreSQL deadlock errors. Genuine
  abort/F1 suites intentionally generate native deadlocks and report them separately.

### Retained diagnostics

All phases including diagnostics: **2,435 actual backend executions / 32,690 JUnit assertions / 37 failures / 29 errors / 15 skipped executions** (2,354 passing executions). Previously published phase: 959 / 12,372 / 37 F / 16 E / 15 S. Historical failures/skips remain historical, not final-gate outcomes. All-attempt logical latest coverage is **847 passed / 0 failed / 0 errored / 0 skipped**; raw historical identities contain one retired skipped source-class identity replaced by the extracted SQLite case.

| Post-exception diagnostic | Tests | Assertions | Failures | Errors | Skips |
| --- | ---: | ---: | ---: | ---: | ---: |
| SQLite (`lc-b3-sqlite-identity-ac00572309074b72b82abd054ae96bae`) | 30 | 1,050 | 0 | 1 | 0 |
| B3Fixtures (`lc-p02b2-b3fixtures-57d6ff87cc164e5d91e476d5b1a8e629`) | 40 | 377 | 0 | 3 | 0 |
| SQLite (`lc-b3-sqlite-identity-36c4b340fb8b460d94115ae0e395d2e6`) | 30 | 1,050 | 0 | 1 | 0 |
| B3Active (`lc-p02b2-b3active-c99b7661fd1d42fd92494907a1d7a7f3`) | 27 | 223 | 0 | 7 | 0 |
| B3Active (`lc-p02b2-b3active-c83c88a240174d05b4c5cdd361b86f2a`) | 27 | 249 | 0 | 1 | 0 |


Earlier application/adapter/fixture failures remain in `prior_phase`, including
wrong-engine HTTP safety rejection and the first historical shutdown timeout. That
old timeout later completed shutdown but remains failed in the archive. New fixture
work initially exposed retained test connections, missing SQLite fixture metadata,
and incomplete synthetic receivable/close prerequisites. Corrections are fixture
only: explicit session disconnect, required raw fixture columns, valid receivable
mapping and paid obligations before closing. No failed financial assertion was
removed, relaxed or relabelled.

Three final-gate launch attempts used Windows PowerShell 5 and stopped at random
credential generation before cluster/database creation. They executed zero tests;
PowerShell 7 reruns are the final selection. A sandboxed SQLite launch could not
read installed vendor bootstrap and executed zero tests; the same :memory: command
then passed with installed dependencies. These infrastructure attempts are not
passes and do not contribute test/assertion totals.

## Residual risks and deferred work

B6 member, membership, officer, role and permission writers are not migrated or
represented as synchronised. Four two-process tests characterise actual member
HTTP update and membership model changes: they can finish while finance holds the
club lock. Changes visible before financial admission are rejected; changes after
admission can coexist with one coherent legacy payment and ledger entry under the
existing actor-check policy. This is a demonstrated remaining synchronisation gap,
not proof of transactional permission revocation. Officer/role/permission writer
permutations are deferred with B6; no blanket safety claim is made for them.

Configuration/activation/period-close enforcement and other B4-B8/read-admission
migrations remain outside scope. Tested existing close ordering is not a claim that
all such writers now use B1. Broader operational-writer FK/row-lock inversions can
still cause native aborts; bounded existing retries and atomic rollback remain the
failure policy, not a universal absence-of-deadlocks guarantee.

The explicit APIs are a compatibility boundary: unported external/informal direct
callers using ambient transactions or legacy source mutation aliases must migrate
to issued contexts. This repository's approved caller inventory is migrated;
unknown external callers are not authorised by transaction depth. PostgreSQL tests
cover the pinned local PG17 environment; CI below is a separate frontend/tooling
gate and does not rerun the backend PostgreSQL matrix.

## Reviewable increments and exact inventory

```text
05d657c23939f42de57578a5b6a565ef309bfe99 test: establish genuine-root B3 PostgreSQL foundation
61714a87b16ee2808cfd689807570ca9cabe671d feat: require explicit organisation ownership for journal transactions
e0ea42ed593986de020ea9724b817616145445bc feat: coordinate financial roots and source accounting contexts
2a059304615839b50ac39af09c84038994375d10 test: align explicit financial fixtures and record blocked corruption coverage
dc15ff46f164e06bdddeffad61693cb7cfe89c05 test: reconcile all finance groups without hiding failed gates
5e0e5c40a17819f4eb04bc0dbd6b6107c49f7a9e test: preserve financial rollback and replay assertions on genuine roots
6b3ac97e9776397ff70ef0a752b7f33e4d16ad20 docs: reconcile B3 evidence and request bounded fixture-policy approval
cacc8de43007959db1a5c51338e77c065b398231 test: port bounded corruption fixtures with verified trigger restoration
c978366bab1437b506e934f49f784d2482f31693 test: verify B3 boundaries contexts suspension races and financial retries
```

The final report-only commit follows these source/test increments; its exact SHA and CI are recorded in the draft PR. No partial merge is authorised.

Full baseline-to-head inventory: **68 files**: 12 application, 53 tests/support, one runner, two report/evidence files.

```text
backend/app/Services/Finance/ClubExpenseRecordingService.php
backend/app/Services/Finance/ClubExpenseReversalService.php
backend/app/Services/Finance/ClubInternalTransferService.php
backend/app/Services/Finance/ClubJournalPostingService.php
backend/app/Services/Finance/ClubOpeningJournalActivationService.php
backend/app/Services/Finance/ClubOrdinaryIncomeReceiptCorrectionService.php
backend/app/Services/Finance/ClubOrdinaryIncomeReceiptService.php
backend/app/Services/Finance/JournalReversalService.php
backend/app/Services/Finance/PaymentRecordingService.php
backend/app/Services/Finance/ReceiptGenerationService.php
backend/app/Services/Finance/SourceAccountingIntegrationService.php
backend/app/Services/FinanceCollections/FinanceWriteService.php
backend/tests/Feature/DisposableCorruptionFixtureTest.php
backend/tests/Feature/DisposableCorruptionFixtureUncertainTest.php
backend/tests/Feature/FinancialBoundaryMatrixTest.php
backend/tests/Feature/FinancialCrossServiceConcurrencyTest.php
backend/tests/Feature/FinancialOpeningPeerConcurrencyTest.php
backend/tests/Feature/FinancialOrganisationEnforcementTest.php
backend/tests/Feature/FinancialResourceLockScopeTest.php
backend/tests/Feature/FinancialSuspensionConcurrencyTest.php
backend/tests/Feature/FinancialTransactionFoundationTest.php
backend/tests/Feature/FinancialTransactionRetryTest.php
backend/tests/Feature/Milestone3adPaymentReceiptPdfNarrowImplementationTest.php
backend/tests/Feature/Milestone3dsSeededPaymentPermissionDefectImplementationTest.php
backend/tests/Feature/Milestone3duPaymentRefreshRetestExecutionTest.php
backend/tests/Feature/Milestone3iicCoreJournalPostingServiceTest.php
backend/tests/Feature/Milestone3iieInternalJournalReversalServiceTest.php
backend/tests/Feature/Milestone3iifInternalAccountingPeriodCloseServiceTest.php
backend/tests/Feature/Milestone3iizhChargePaymentAccountingIntegrationTest.php
backend/tests/Feature/Milestone3iizhPostgresConcurrencyTest.php
backend/tests/Feature/Milestone3iizjProductionAccountingIntegrationConfigurationAndActivationNarrowImplementationTest.php
backend/tests/Feature/Milestone3iizkACanonicalAccountingMetadataFundAttributionTest.php
backend/tests/Feature/Milestone3iizkClubAccountStatementProjectionTest.php
backend/tests/Feature/Milestone3iizlClubAccountStatementProjectionValidationAndClosureTest.php
backend/tests/Feature/Milestone3iiztPostgresStatementSnapshotConcurrencyTest.php
backend/tests/Feature/Milestone3iizvClubExpenseRecordingTest.php
backend/tests/Feature/Milestone3iizvPostgresExpenseConcurrencyTest.php
backend/tests/Feature/Milestone3iizwExpenseHistoryCorrectionTest.php
backend/tests/Feature/Milestone3iizwPostgresExpenseReversalConcurrencyTest.php
backend/tests/Feature/Milestone3iizxInternalCashBankTransferTest.php
backend/tests/Feature/Milestone3iizzOrdinaryIncomeReceiptTest.php
backend/tests/Feature/Milestone3ijaePostgresOrdinaryIncomeReceiptCorrectionConcurrencyTest.php
backend/tests/Feature/Milestone3mPaymentRecordingServiceNarrowImplementationTest.php
backend/tests/Feature/Milestone3nPaymentRecordingServiceValidationAndClosureTest.php
backend/tests/Feature/Milestone3qPaymentRecordingApiNarrowImplementationTest.php
backend/tests/Feature/Milestone3rPaymentRecordingApiValidationAndClosureTest.php
backend/tests/Feature/Milestone3uPaymentReceiptGenerationNarrowImplementationTest.php
backend/tests/Feature/Milestone3vPaymentReceiptGenerationValidationAndClosureTest.php
backend/tests/Feature/Milestone3yPaymentReceiptApiNarrowImplementationTest.php
backend/tests/Feature/Milestone3zPaymentReceiptApiValidationAndClosureTest.php
backend/tests/Feature/OrganisationTransactionGuardTest.php
backend/tests/Feature/SourcePublicIdentitySqliteTest.php
backend/tests/Feature/SourceTransactionContextMatrixTest.php
backend/tests/Support/BuildsMilestone3ijaeFixture.php
backend/tests/Support/DisposableCorruptionFixture.php
backend/tests/Support/FinancialAbortWorker.php
backend/tests/Support/FinancialOpeningPeerWorker.php
backend/tests/Support/FinancialOperationFixture.php
backend/tests/Support/FinancialPostgresTestCase.php
backend/tests/Support/FinancialSuspensionWorker.php
backend/tests/Support/FinancialWorkerSafety.php
backend/tests/Support/Milestone3iizhPostgresConcurrencyWorker.php
backend/tests/Support/Milestone3iizvExpenseWorker.php
backend/tests/Support/Milestone3iizwExpenseReversalWorker.php
backend/tests/Support/SourceContextFixture.php
docs/p0-2b3-financial-enforcement.md
docs/validation/p0-2b3-postgres-evidence.json
scripts/verify-cutover-bootstrap-postgres.ps1
```

## Rollback and merge gate

No migration or data repair accompanies this unit. Rollback must revert the entire
coordinated B3 interface/caller unit; reverting journal APIs alone strands explicit
nested callers. Preserve baseline B1/B2/F1, posted immutable journals, source
provenance, consumed authority and audit records. Never roll back accounting data or
rewrite posted financial records. Use a separately approved code rollback and
repeat B1/B2/F1, historical finance and context gates before any later release.

Implementation acceptance requires all final local gates, restoration proof,
expected-discard shutdowns, historical assertions and unchanged source inventory.
Merge additionally requires business-owner review/approval of the complete B3 unit,
exact-head CI success and explicit acceptance of the documented deferred writer
risks. Keep draft; no partial merge, deployment or later-milestone implementation.

## GitHub CI and repository state

[Source-head CI 42466619672](https://github.com/kgbijesh-eng/LC-Beenachi-Connect-Prototype/actions/runs/42466619672) succeeded on exact `c978366bab1437b506e934f49f784d2482f31693`: 210 frontend tests, TypeScript, production build, manifests, PowerShell syntax and non-destructive preflight passed. This workflow does not execute the backend PostgreSQL matrix. The report-only final head is checked separately after publishing; its result and exact SHA are recorded in the PR body.

Existing warnings: Vite native-loader ESM/CommonJS configuration compatibility; Node `punycode` and `url.parse()` deprecations; pinned Actions targeting Node 20 forced onto Node 24. No workflow/dependency changes are made.

Local `main` and `origin/main` remain the exact baseline. Final clean-tree and feature ahead/behind verification is recorded after the report commit/push; no uncommitted application work is discarded.
