# P0.2B3 coordinated financial enforcement

Baseline main: `b44e6de7c4de258c918bd655ee8853b2ba85a1b4`.
Development branch: `codex/p0-2b3-financial-enforcement`.
Draft increments only; no partial merge/deployment.

## Caller and transaction contract

Standalone posting and reversal own B1 roots with the original three attempts.
Their explicit `postWithin`/`reverseWithin` interfaces require an issued B1 context
and preserve the original nested transaction/savepoint. Unknown ambient calls fail
closed. Standalone roots do not gain an additional savepoint or local retry owner.
Contexts are never recovered from transaction depth or a global active flag.

B2 opening passes its existing context to posting; its outer root, F1 XID evidence,
attempt and terminal audit roots and financial/governance behavior remain unchanged.
Reversal passes context to nested posting. Collections owns seven one-attempt B1
roots, propagating context to coverage/post/reversal/replay source integration.
Source mutations remain nested-only. Expense/reversal/transfer/ordinary-income and
correction roots retain three attempts; correction passes its context to reversal
and to replacement receipt recording. Legacy Payment/Receipt are separate models
and standalone one-attempt APIs, retaining existing actor and monetary semantics.

Actor authorisation is not conferred by context. Existing operation policies and
the trusted-internal actor contract of journal APIs remain; no new role grants or
membership requirement for B2 external technical appointees is introduced.
Member/membership/officer/role/permission writers remain unsynchronised unless they
already follow club-first ordering. This increment does not migrate B6 writers.

## Test foundation

FinancialPostgresTestCase owns genuine root fixtures, verifies the exact dedicated
`lcb_p02b3_finance_test` database (or the existing canonical finance runner database),
expected non-elevated owner, no memberships and an exact inventory before migration.
No RefreshDatabase transaction or fake B1 context is permitted. Existing historical
concurrency guards retain their independent exact allowlists.

The runner always creates a fresh loopback cluster with independent credentials,
checks cluster identity before/after, retains logs/JUnit and confirms shutdown.
Explicit synthetic instance configuration belongs to fixture setup, never request
fallback in application code. Foreign-operation assertions must keep a fixed
authoritative assignment during the operation under test.

Validation and exact increment inventories will be recorded as work completes.

## Application inventory and transaction ownership

All paths below are relative to `backend/app/Services/`.

| File | Root / nested contract | Original attempt budget |
| --- | --- | --- |
| `Finance/ClubJournalPostingService.php` | `post` owns root; `postWithin` requires issued context and keeps savepoint | 3 |
| `Finance/JournalReversalService.php` | `reverse` owns root; `reverseWithin` keeps savepoint; posting receives context | 3 |
| `Finance/ClubOpeningJournalActivationService.php` | Existing B2 root; only posting call becomes `postWithin` | 3, unchanged |
| `Finance/PaymentRecordingService.php` | Standalone root; fresh scoped member and existing actor checks inside root | 1 |
| `Finance/ReceiptGenerationService.php` | Standalone root; scoped payment lock before existing authorisation/receipt checks | 1 |
| `FinanceCollections/FinanceWriteService.php` | Seven roots: create/update/issue/close/cancel charge, record/void payment | 1 each |
| `Finance/SourceAccountingIntegrationService.php` | Context-required coverage, posting, reversal and command replay; old unproven entry points fail closed | No new root/retry owner |
| `Finance/ClubExpenseRecordingService.php` | Root; nested posting receives context | 3 |
| `Finance/ClubExpenseReversalService.php` | Root; nested reversal receives context | 3 |
| `Finance/ClubInternalTransferService.php` | Root; nested posting receives context | 3 |
| `Finance/ClubOrdinaryIncomeReceiptService.php` | Root plus explicit `recordWithin` preserving correction's existing savepoint | 3 |
| `Finance/ClubOrdinaryIncomeReceiptCorrectionService.php` | Root; reversal and replacement recording receive same issued context | 3 |

Posting callers are opening execution, reversal, source integration, expense
recording, internal transfer and ordinary-income receipt recording. Reversal
callers are source integration, expense reversal and ordinary-income correction.
Correction also calls ordinary-income receipt recording for replacement. Legacy
payment/receipt controllers call their standalone service boundaries. Collections
controllers call the seven standalone collections methods; collections alone owns
source-integration mutations. Reconciliation uses binding/read helpers, unchanged.

Lock contract: B1 authoritative club lock precedes all resource locks. Resource
queries include organisation scope before `FOR UPDATE`; core journal APIs retain
the missing-versus-foreign exception distinction through non-locking ID/owner
metadata, without locking foreign financial rows. Secondary lock order and journal
construction remain in their original statement order. Existing B2 F1 outcome
classification, separate audit transactions and durable audit cardinality are not
modified. Context does not grant actor permissions.

## Compatibility dependency requiring a decision

Five existing tests in
`backend/tests/Feature/Milestone3iizhChargePaymentAccountingIntegrationTest.php`
are SQLite-only. They deliberately drop/recreate SQLite immutable-data triggers or
depend on SQLite trigger syntax to construct corrupt records and exercise replay
and reconciliation defences. Their existing PostgreSQL skips remain unchanged.
The new B1-owned mutation roots intentionally reject SQLite; running the same
business assertions with genuine roots therefore needs a PostgreSQL corrupt-fixture
strategy. Disabling or replacing PostgreSQL triggers is not authorised by the
current no-schema/no-trigger-change restriction. No such change has been made.

Do not count these five cases as passed or silently replace their assertions.
Resolve this fixture-policy dependency before claiming complete regression
acceptance. A separately approved, disposable-only corruption-fixture design would
need exact target/owner checks, bounded corruption construction, guaranteed trigger
restoration, and unchanged production schema/guards. This report does not authorise
that design or its implementation.

## Rollback and acceptance

No schema migration or production-data operation is introduced. These interfaces
form one coordinated unit: reverting journal APIs alone would strand nested
callers. Any future rollback must revert the complete approved B3 unit under a
separately authorised operational procedure, preserving B1/B2/F1. Never rewrite
posted journals, source provenance, consumed authority or audit evidence.

Implementation completion and merge readiness remain NO-GO while fixture
compatibility, direct-call/concurrency coverage or final regression evidence is
incomplete. All changes stay draft; no partial increment merge is permitted.


## Validation reconciliation (blocked draft)

Application source is frozen at `2a059304615839b50ac39af09c84038994375d10`;
its `backend/app` tree is `2dca6844310fdf65678bd91a9acca1d6f110f7ca`.
Later commits change tests, runner diagnostics and documentation only. The complete
C+D+E regression ran at `e0ea42ed593986de020ea9724b817616145445bc`;
B2 opening and shared journal/reversal files are unchanged since that run. This is
not a claim that the complete final B3 acceptance matrix has run on one final head.

The table selects the latest recorded result for each group; repaired groups
replace earlier failing attempts in this selection only. The evidence archive
retains every earlier attempt and its exact JUnit totals and case identities.

| Selected group | Executions | Assertions | Failures | Errors | Skips |
| --- | ---: | ---: | ---: | ---: | ---: |
| B3 core/security | 43 | 498 | 0 | 0 | 0 |
| C+D+E/bootstrap/historical cutover | 197 | 2554 | 0 | 0 | 0 |
| B1 | 34 | 313 | 0 | 0 | 0 |
| F1 (two four-case runs) | 8 | 228 | 0 | 0 | 0 |
| Canonical finance (reconciled groups) | 94 | 1345 | 0 | 0 | 0 |
| Historical financial compatibility (reconciled groups) | 155 | 1996 | 0 | 0 | 5 |
| P0.1/P0.2A HTTP (SQLite :memory:) | 29 | 1032 | 0 | 0 | 0 |

Selected evidence: **560 executions, 7,966 assertions, zero failures/errors,
five skips**. This selection contains duplicate executions across groups and the
F1 stability pass. Across all attempts there are **514 unique cases**, whose latest
recorded outcomes are **509 passed and five skipped**. These figures cover executed
cases only; they do not include unimplemented acceptance cases listed below.

All actual attempts, including diagnostic failures: **959 backend executions,
12,372 JUnit assertions, 37 failures, 16 errors and 15 skips** (891 passing
executions). Repeats are intentional and are not additional unique coverage.
The JSON test reporter counted 29 failed `setUp` engine checks separately where
JUnit recorded zero assertions; JUnit assertion totals are used consistently here.
The wrongly targeted PostgreSQL HTTP invocation is retained as 29 failures, not
silently relabelled. Its unchanged tests subsequently passed on required SQLite
`:memory:`: 29 cases / 1,032 assertions.

[Machine-readable execution evidence](validation/p0-2b3-postgres-evidence.json)
contains each run's original safety/status evidence, JUnit SHA-256, per-case
outcome/assertions, original Git head and local evidence directory.

### Every recorded attempt

| Attempt | Executions | Assertions | Failures | Errors | Skips | Runner status |
| --- | ---: | ---: | ---: | ---: | ---: | --- |
| foundation | 1 | 12 | 0 | 0 | 0 | PASS |
| core-diagnostic-1 | 37 | 427 | 1 | 0 | 0 | FAILED_OR_BLOCKED |
| core-diagnostic-2 | 43 | 488 | 2 | 0 | 0 | FAILED_OR_BLOCKED |
| historical-diagnostic-1 | 154 | 1864 | 3 | 12 | 5 | FAILED_OR_BLOCKED |
| core-pass-1 | 43 | 498 | 0 | 0 | 0 | PASS |
| cutover-integration | 197 | 2554 | 0 | 0 | 0 | PASS |
| b1 | 34 | 313 | 0 | 0 | 0 | PASS |
| f1 | 8 | 228 | 0 | 0 | 0 | PASS |
| finance-diagnostic-1 | 11 | 295 | 1 | 0 | 0 | FAILED_OR_BLOCKED |
| historical-diagnostic-2 | 154 | 1975 | 1 | 3 | 5 | FAILED_OR_BLOCKED |
| finance-diagnostic-2 | 94 | 1341 | 0 | 1 | 0 | FAILED_OR_BLOCKED |
| core-pass-2 | 43 | 498 | 0 | 0 | 0 | PASS |
| repair | 111 | 847 | 29 | 0 | 5 | FAILED_OR_BLOCKED |
| http-sqlite | 29 | 1032 | 0 | 0 | 0 | SQLite exit 0 |

Initial failures were preserved and corrected: synthetic instance assignment,
unhydrated database-default versions, explicit-context fixture adapters, two
obsolete lexical `DB::transaction` checks, a dirty-tree historical assertion,
worker instance configuration, and wrong-engine HTTP invocation. Financial
winner/loser, provenance, balance, immutability and count assertions were retained.
Two lexical transaction-owner checks now assert the B1 guard and `run` boundary.
The B1 nested journal compatibility test calls `postWithin` with its existing
issued context; B1 implementation/security policy is unchanged.

Transfer rollback uses a test-only post-insert query listener instead of the old
SQLite-only INSERT-rejection trigger. It verifies the source already existed and
the financial INSERT was reached, then proves both source and journal rollback.
No PostgreSQL trigger was created, disabled, dropped or modified by that adapter.

The canonical finance result above reconciles its original 94 cases, including
correction/replacement/independent-instance races and non-blocking snapshot
concurrency. The original 35-case statement group error is retained in the raw
attempt and superseded by its corrected 35-case / 334-assertion rerun.

C+D+E retained **197 / 2,554**, including all 14 concurrency tests, original deadlock
regression, and the existing ten identical plus ten changed opening-race iterations.
Its PostgreSQL log contains **zero deadlock errors**. F1 deliberately produced
**eight genuine PostgreSQL deadlock errors** across its two runs; those are expected
rollback/retry tests, not evidence of a mixed-order regression. They are kept in a
separate cluster from the zero-deadlock cutover gate.

All 13 fresh PostgreSQL clusters were observed shut down. The first historical
runner exceeded its 120-second shutdown wait and recorded exit 1; it later completed
shutdown, confirmed in the retained server log. That attempt remains FAILED_OR_BLOCKED.
Every other PostgreSQL shutdown returned zero. No existing database owner,
privileges, production configuration or protected database was changed.

GitHub CI is separate from this local PostgreSQL evidence. The repository workflow
runs frontend tests/build/type checks, manifest checks and non-destructive verifier
preflight; it does not execute the backend PostgreSQL matrix. The draft PR records
the exact final-head CI URL/result after publishing. No CI success closes the backend
gaps below.

## Exact test and supporting file inventory

Test changes are real-root fixture conversion, exact synthetic instance setup,
explicit-context call adaptation, security tests and rollback fault injection.
No test supplies a fake valid B1 context or weakens database allowlists/role checks.

```text
backend/tests/Feature/FinancialOrganisationEnforcementTest.php
backend/tests/Feature/FinancialTransactionFoundationTest.php
backend/tests/Feature/Milestone3adPaymentReceiptPdfNarrowImplementationTest.php
backend/tests/Feature/Milestone3dsSeededPaymentPermissionDefectImplementationTest.php
backend/tests/Feature/Milestone3duPaymentRefreshRetestExecutionTest.php
backend/tests/Feature/Milestone3iicCoreJournalPostingServiceTest.php
backend/tests/Feature/Milestone3iieInternalJournalReversalServiceTest.php
backend/tests/Feature/Milestone3iifInternalAccountingPeriodCloseServiceTest.php
backend/tests/Feature/Milestone3iizhChargePaymentAccountingIntegrationTest.php
backend/tests/Feature/Milestone3iizjProductionAccountingIntegrationConfigurationAndActivationNarrowImplementationTest.php
backend/tests/Feature/Milestone3iizkACanonicalAccountingMetadataFundAttributionTest.php
backend/tests/Feature/Milestone3iizkClubAccountStatementProjectionTest.php
backend/tests/Feature/Milestone3iizlClubAccountStatementProjectionValidationAndClosureTest.php
backend/tests/Feature/Milestone3iiztPostgresStatementSnapshotConcurrencyTest.php
backend/tests/Feature/Milestone3iizvClubExpenseRecordingTest.php
backend/tests/Feature/Milestone3iizwExpenseHistoryCorrectionTest.php
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
backend/tests/Support/BuildsMilestone3ijaeFixture.php
backend/tests/Support/FinancialPostgresTestCase.php
```

Supporting files: `scripts/verify-cutover-bootstrap-postgres.ps1`, this report,
and `docs/validation/p0-2b3-postgres-evidence.json`. No production configuration,
schema, migration, trigger, B1 service, route, model or frontend file is modified.
Application changes are limited to the twelve files enumerated above.

## Concrete fixture exception proposed for separate approval

The user restriction is: **"No changes to B1 security policy or schemas/triggers
without separate approval."** The remaining SQLite-only tests cannot be claimed
as PostgreSQL passes, and the mutation guard will not be relaxed to support SQLite.

Proposed exception, NOT IMPLEMENTED:

1. Keep the SQLite-specific public-identity trigger test on disposable `:memory:`
   fixtures, separating its setup from guarded financial services and retaining all
   original identity/immutability assertions.
2. Port the other four corruption/reconciliation tests to real-root PostgreSQL.
   Permit temporary disable/re-enable of ONLY these existing immutable triggers,
   ONLY on their corresponding tables in exact `lcb_p02b3_finance_test`:
   `club_accounting_source_integrations_immutable`,
   `club_accounting_source_integration_events_immutable`, and
   `club_accounting_source_coverage_decisions_immutable`.
3. A test-only helper must verify the fresh cluster identity/inventory, non-elevated
   `lc_beenachi_app` owner with no memberships, no other test clients, authoritative
   organisation, depth-zero entry, expected trigger definitions and enabled state.
   It must own a supported B1 root, lock the authoritative club first and perform
   bounded raw fixture corruption only. Never invoke a financial service while a
   trigger is disabled. Re-enable and verify identical definitions/enabled state
   before commit and before any service assertion. Rollback restores DDL on failure;
   any uncertain restoration/outcome fails the test and discards the fresh cluster.
4. Do not disable FK/CHECK constraints, use `session_replication_role`, elevated
   roles, `DISABLE TRIGGER ALL/USER`, production hooks, or alter migration/schema
   files. Use format-valid but semantically wrong checksum fixture values where
   PostgreSQL constraints are stricter; preserve every original defect assertion.
5. Adapt corruption replay calls to issued contexts; retain all old winner/loser,
   balance, provenance, rejection and reconciliation assertions. Add restoration
   and fail-closed helper tests. This exception grants no financial/audit authority
   and is never available to application code.

These named triggers and their PostgreSQL naming are defined in
`backend/database/migrations/2026_08_04_000001_add_charge_payment_accounting_integration.php`
(`addGuards`, immutable trigger loop). No exception is assumed or executed here.

## Acceptance still outstanding

Implementation completion: **NO-GO**. Merge readiness: **NO-GO**.
Application integration is a first pass; B3 is not declared complete.

- Resolve the five-case fixture/coverage dependency above without weakening B1 or
  historical assertions.
- Add the remaining service-specific direct-call and two-order suspension races
  across collections, legacy boundaries and dependent services; test all source
  mutation/replay context boundaries and standalone/nested retry exhaustion.
- Adapt/run the exact-allowlisted historical collections, expense and expense-
  reversal PostgreSQL worker suites. Their historical assertions are not edited or
  counted as passed here. Add the chosen cross-service journal/opening/period-close
  and membership-change race coverage required by the approved B3 contract.
- Characterise the FK/member-lock inversion risk from unsynchronised member,
  membership, officer, role and permission writers. Do not migrate or claim these
  writers synchronised: B6 remains deferred. Preserve fail-closed rollback and
  existing retry budgets in the meantime. Configuration/period-close writers and
  read-admission migrations remain outside B3; no B4-B8 work is included.
- Run the complete coordinated B3 acceptance selection on the final approved
  source, retaining original C+D+E/F1/B1/bootstrap/security/finance gates, with exact
  unique-case/execution reconciliation and clean-tree evidence. Current selected
  historical evidence is not a substitute for the missing new concurrency cases.
- Obtain separate business-owner review/merge approval. Keep this entire unit
  draft; do not merge any partial increment or deploy it.
