# P0.2B4 — coordinated integration configuration and source coverage

Baseline main: `6691e5d4b7df46bb636df39e68067afe10f2f437`.
Reviewed blocked head: `55d0431c68093376d6cfd2bcc6c5e0004f194b15`.
Draft PR: https://github.com/kgbijesh-eng/LC-Beenachi-Connect-Prototype/pull/35.
Validated source: `1f17fbf1994b9453cba4dce9bfa3736173180f2b`.
Application tree: `0634b677d3a24a99f5de51a1d09334689e4d2c02`.
A report-only final commit follows; its exact SHA/tree and CI are recorded in the PR.

## Acceptance decision

Implementation **GO**, supported by the final complete selection below. Merge execution **NO-GO pending separate business-owner review and approval**. PR #35 remains draft and unmerged. No partial-unit merge is recommended.

The separately approved forward-only remediation resolves the retained failing
reactivation/source race without changing its two-journal assertion. The complete
coordinated selection below ran on the same committed application/test/runner
source. No partial configuration/proposal merge is authorised. Keep PR #35 draft
and unmerged until separate business-owner approval.

No protected database, existing database ownership/privileges, production records,
infrastructure or deployment configuration was changed. No deployment, real
organisation provisioning or financial cutover occurred. B5-B8 remain unimplemented.

## Approved application contract and exact boundaries

Only these three application files differ from baseline:

| File under `backend/app/Services/Finance/` | Implemented boundary |
| --- | --- |
| `AccountingIntegrationConfigurationService.php` | Four standalone one-attempt B1 roots and four issued-context nested APIs retaining one-attempt savepoints; private context-required locking; resource ownership scoped before locking |
| `AccountingIntegrationProposalService.php` | One-attempt proposal and terminal-decision roots; context propagation through `apply()`; existing governance, request/checksum/version/target-snapshot rules and atomic audit |
| `SourceAccountingIntegrationService.php` | Shared private context-validated current-slot resolver only for new coverage decisions and new posting |

The configuration/proposal implementation is unchanged from reviewed blocked head
55d0431. The source correction is isolated in commit 9a7f0b4d4c9a377cbb35e31cb8fafa3e2bb32ea2; tests follow in
1f17fbf. B1, B2/F1, journal posting/reversal, other B3 application services, actor
policies, routes, models, schema, migrations, production trigger definitions,
workflow and dependency files are unchanged. No new production surface, permission
grant, subscription or operator suspension/revocation implementation is added.

Standalone configuration mutations keep one attempt. `*Within` operations require
an issued same-organisation context and preserve nested savepoints. Proposal
terminal decisions pass that context to configuration mutations, including each
revoke/create step of replacement. Unknown ambient standalone calls fail closed.
Independent decision-maker and proposer-only withdrawal controls remain; authority
is not granted by context. Governance/checksum/version/overlap/reference/audit rules
are retained. The original B4 and historical test assertions remain intact.

The new resolver operates only inside the existing club-first B1 transaction:
context validation; organisation/source-scoped activation locks in ID order; scoped
current-slot lock; binding and liveness verification. It creates no transaction,
savepoint or retry owner. Configuration writers already serialise on the same club
lock. Trigger-driven slot deletion and referenced-row effects remain inside their
original transactions. This does not establish a global lock protocol for unmigrated
operational writers.

## Approved forward-only accounting rules

| State at the new source transaction | Result |
| --- | --- |
| Valid sole current slot and source date before coverage | Persist `before_coverage`, false, referencing the current activation |
| Valid sole current slot and date on/after coverage | Persist `integration_required`, true; posting must use that exact activation/version |
| No slot and no history | Retain `not_activated` |
| No slot and one unambiguous terminal generation | Retain established date-before-boundary / after-terminal reason precedence |
| No slot and multiple terminal generations | Fail closed; do not choose earliest/latest history or fabricate an uncovered decision |
| Missing slot with non-terminal history, inconsistent binding, or ambiguous live generations | Fail closed before source commit |
| Existing coverage decision or committed integration | Retain historical decision/references and existing replay/reversal validation |

No new source chooses a revoked activation because its accounting date fits an old
coverage range. Payment accounting dates can be backdated; commit order and current
configuration govern the new decision. Mapping versions, immutable activation IDs,
source bindings, deterministic journal keys, balanced posting and period/account
controls remain unchanged. Missing/invalid mappings still roll back source creation.

The existing `activation()` read helper and `enabled()` are not changed into B7
read-admission wrappers. Historical replay, `coverageRequired()` legacy fallback,
reversal and reconciliation are not redirected through the new resolver. Existing
coverage-decision returns remain before new selection. No retrospective journal or
repair, decision rewrite, version rebinding or financial-history migration occurs.

## Original blocker and corrected evidence

The unchanged baseline coverage lookup selected an unfiltered, unordered first
historical activation. Reactivation preserves old activation rows, so after current
activation 3 committed, the next source incorrectly referenced revoked activation 1
and recorded `after_terminal_event`, false. Both workers returned success, while the
journal count remained one. The failing assertion was reproduced twice before
approval, including a controlled-clock repeat; both failures remain in prior-phase
machine-readable evidence.

The same original test and assertion now pass:

| Retained diagnostic | Before correction | After correction |
| --- | --- | --- |
| Current-slot activation | 3 | 3 |
| New coverage activation | 1, revoked | 3, current |
| Coverage reason / requirement | `after_terminal_event` / false | `integration_required` / true |
| Journal count, including opening | 1; expected 2 | 2 |
| Worker outcomes / root depths | success, success / 0, 0 | success, success / 0, 0 |
| Actual club-lock edge | Observed | Observed |

These are independent fresh disposable runs. The correction creates a correct new
decision; it does not overwrite the old failed run's immutable decision. The focused
post-fix check passed 1 test / 13 assertions, and the unchanged race also passes in
the full B4 race suite. The earlier source service blob and complete failed records
remain archived, rather than relabelled as passed.

## Final coordinated validation

| Selection | Executions | Assertions | Failures | Errors | Skips | Result |
|---|---:|---:|---:|---:|---:|---|
| Integration | 197 | 2554 | 0 | 0 | 0 | PASS |
| Finance | 94 | 1345 | 0 | 0 | 0 | PASS |
| B3Historical | 154 | 2009 | 0 | 0 | 0 | PASS |
| B3 | 43 | 498 | 0 | 0 | 0 | PASS |
| B4 | 85 | 739 | 0 | 0 | 0 | PASS |
| Guard | 34 | 313 | 0 | 0 | 0 | PASS |
| F1 | 8 | 228 | 0 | 0 | 0 | PASS |
| B4Reactivation | 1 | 13 | 0 | 0 | 0 | PASS |
| B3Fixtures | 40 | 386 | 0 | 0 | 0 | PASS |
| B3Poison | 1 | 9 | 0 | 0 | 0 | EXPECTED_DISCARD |
| B3PoisonCommit | 1 | 8 | 0 | 0 | 0 | EXPECTED_DISCARD |
| B3Direct | 152 | 1378 | 0 | 0 | 0 | PASS |
| B3Contexts | 42 | 343 | 0 | 0 | 0 | PASS |
| B3Resources | 18 | 144 | 0 | 0 | 0 | PASS |
| B3Workers | 8 | 133 | 0 | 0 | 0 | PASS |
| B3Races | 54 | 766 | 0 | 0 | 0 | PASS |
| B3Cross | 12 | 162 | 0 | 0 | 0 | PASS |
| B3OpeningPeer | 2 | 38 | 0 | 0 | 0 | PASS |
| B3Retries | 36 | 792 | 0 | 0 | 0 | PASS |
| Coverage | 42 | 451 | 0 | 0 | 0 | PASS |
| CoverageRaces | 14 | 202 | 0 | 0 | 0 | PASS |
| B4Races | 27 | 328 | 0 | 0 | 0 | PASS |
| B4Resources | 4 | 28 | 0 | 0 | 0 | PASS |
| SQLite | 30 | 1051 | 0 | 0 | 0 | PASS |

Final acceptance: **1,099 executions, 1,019 unique cases, 13,918 assertions; 0 failures, 0 errors, 0 skips**. PostgreSQL: 1,069 tests, 12,867 assertions, 0 failures, 0 errors, 0 skipped. SQLite: 30 tests, 1,051 assertions, 0 failures, 0 errors, 0 skipped. The two expected-discard fixture tests (17 assertions) are passing negative tests with intentionally retired clusters, not normal-cluster reuse. Every selected PostgreSQL run passed identity/ownership/non-elevated-role checks, source-hash verification and shutdown (exit 0). All 847 unique cases from the reviewed B3 final selection are retained, with zero missing case identities and 172 additional unique B4 cases (116 configuration/proposal and 56 source-coverage). The baseline evidence digest and set reconciliation are recorded in JSON.

Selected PostgreSQL runs verified fresh cluster path/system identity, loopback
endpoint, exact database inventory and non-elevated owner `lc_beenachi_app`, without
role memberships or inherited/elevated flags. B4/coverage suites use the existing
exact allowlisted name `lcb_p02b3_finance_test` in independent newly created clusters;
no existing B3 database is reused. Canonical/concurrency suites retain their own
existing exact allowlists. All final clusters shut down with exit zero. Application
and test file inventories/hashes stayed unchanged during each run.

The selected SQLite `:memory:` run separately executes the extracted public-identity
case (1 / 19) and unchanged P0.1/P0.2A HTTP/security tests (29 / 1,032). No SQLite
transaction is used as a financial row-lock substitute. Skips are not passes.

[Machine-readable evidence](validation/p0-2b4-postgres-evidence.json) retains each
runner's exact head, identity/owner/isolation and shutdown evidence, JUnit hashes,
per-case results, lock/restoration/coverage proofs and native deadlock counts. The
`prior_blocked_phase` preserves every previously published B4 failure/error.

### Remediation-specific security and financial verification

- Forty-two new selection tests cover charge/payment current generation, before/on/
  after boundaries, backdated payment dates, differing mapping versions/accounts,
  third current generation over two terminal generations, no history, single-terminal
  history and no-current/multiple-terminal rejection. Missing/orphan and ambiguous
  activation states roll back. Existing PostgreSQL guards reject terminal, wrong-
  source and foreign slot bindings without disabling constraints or triggers.
- Historical exact/changed replay, covered/uncovered cancellation and void, and
  payment replay after void retain original decisions and accounting. They run with
  a later current generation and again after two generations are terminal. Later
  activation and mapping revocations do not reclassify old sources.
- Failures after coverage insertion, journal creation, linkage insertion and audit
  creation prove complete source/receipt/allocation/journal/coverage rollback for
  both source types. Parent rollback after nested revocation restores the trigger-
  deleted current slot and terminal event, then ordinary source posting still works.
- Fourteen new concurrency cases exercise activation, activation revocation and
  mapping revocation against charges/payments in both winner orders. They observe
  actual `pg_blocking_pids` edges, assert exact loser outcomes and journal/linkage/
  coverage counts, and check fund balance. Source-first uncovered commits still
  prevent retroactive activation over those unjournalled covered dates.
- Two of those cases explicitly hold the current-slot row, observe the terminal
  trigger's deletion waiting on that peer, and observe a source waiting on the
  revoker's club lock. No terminal event is visible before commit. Releasing the
  peer allows atomic revocation/slot deletion, then uncovered source completion.
- Original B4 eligibility/context/savepoint/governance/atomicity/one-attempt, duplicate
  request/decision, two-order suspension and foreign-resource assertions remain.
- Full B3 direct-call, context, suspension, foreign-resource, historical worker,
  period-close/opening peer, native retry/exhaustion and atomic rollback gates remain.
  C+D+E keeps the original forced mixed-order regression, winner/provenance assertions
  and ten identical plus ten changed opening-race iterations. F1 outcome classification
  and B1 policies are unchanged.

Normal race suites require zero PostgreSQL deadlocks. Genuine abort/F1 suites
intentionally generate native deadlocks; their counts are reported separately and
are not accepted as timeouts or domain failures. Final selected intentional native deadlock counts: F1: 8; B3Retries: 20. All other selected PostgreSQL suites recorded zero deadlock errors.

## All attempts and retained diagnostics

All actual B4 phases, including the blocked baseline, corrected-source diagnostic and repeated gates: **2,111 tests, 25,849 assertions, 3 failures, 2 errors, 0 skipped**. These are execution totals, not a claim that historical failures passed. The original blocked phase remains nested verbatim in machine-readable evidence; it recorded 275 executions / 3,135 assertions / 2 failures / 2 errors / 0 skips. Every current-source attempt is retained with case outcome and JUnit digest.

- `B3Races` (`lc-p02b2-b3races-b61a3f80b116459d81ef9f7a50554b33`): 54 tests, 758 assertions, 1 failures, 0 errors, 0 skipped; runner `FAILED_OR_BLOCKED`, shutdown `0`. Original 20-second blocking-edge barrier was not observed for transfer-replay/mutation-first under concurrent full-cluster migration load; unchanged suite was rerun sequentially. No assertion or timeout was changed.

- `B4` (`lc-p02b2-b4-6a854309c93e49b19ee68a6ec0607002`): 85 tests, 739 assertions, 0 failures, 0 errors, 0 skipped; runner `FAILED_OR_BLOCKED`, shutdown `1`. Test assertions passed, but the 120-second pg_ctl shutdown limit expired during heavy checkpoint/fsync contention. These attempts remain failed infrastructure gates; logs subsequently confirm those dedicated clusters shut down. The complete suite was repeated on a fresh cluster sequentially.

- `Finance` (`lc-p02b2-finance-32c935e87b884682823f16da2d7f942b`): 94 tests, 1,345 assertions, 0 failures, 0 errors, 0 skipped; runner `FAILED_OR_BLOCKED`, shutdown `1`. Test assertions passed, but the 120-second pg_ctl shutdown limit expired during heavy checkpoint/fsync contention. These attempts remain failed infrastructure gates; logs subsequently confirm those dedicated clusters shut down. The complete suite was repeated on a fresh cluster sequentially.

- `B3Direct` (`lc-p02b2-b3direct-d223e16fb1ba47be9b1bf9332304f504`): 152 tests, 1,378 assertions, 0 failures, 0 errors, 0 skipped; runner `FAILED_OR_BLOCKED`, shutdown `1`. Test assertions passed, but the 120-second pg_ctl shutdown limit expired during heavy checkpoint/fsync contention. These attempts remain failed infrastructure gates; logs subsequently confirm those dedicated clusters shut down. The complete suite was repeated on a fresh cluster sequentially.

- `B3Historical` (`lc-p02b2-b3historical-6d4e76b5138a4ad6aa3801ad206307e3`): 154 tests, 2,009 assertions, 0 failures, 0 errors, 0 skipped; runner `FAILED_OR_BLOCKED`, shutdown `1`. Test assertions passed, but the 120-second pg_ctl shutdown limit expired during heavy checkpoint/fsync contention. These attempts remain failed infrastructure gates; logs subsequently confirm those dedicated clusters shut down. The complete suite was repeated on a fresh cluster sequentially.

The two original resource setup errors reused globally unique configuration
references in two synthetic organisations. Test-only unique references corrected
those fixtures; all original resource assertions were retained. The controlled
clock repeat still reproduced the original financial failure before remediation.

Six remediation-phase batch launches failed shell parsing before reaching the
runner; they created no cluster and executed zero tests. Correct literal quoting
then started every full regression batch. A formatter invocation from the wrong
working directory also executed no tests; the targeted backend invocation passed.
These command failures are recorded separately from backend test/error totals.

The existing bounded corruption helper and its three approved immutable triggers
are unchanged. Restoration hashes and expected-discard negative tests remain part
of the B3 regression selection. No FK/CHECK or unrelated trigger is disabled and
no trigger exception is expanded. An uncertain fixture cluster is retired before
any subsequent business assertion, not reused as a normal successful cluster.

## Remaining limitations and rollback

This correction is forward-only. A structurally valid historical decision previously
misclassified as uncovered is not automatically detected, reinterpreted or repaired.
No production data assessment is claimed. Any retrospective investigation or recovery
requires separate authorised scope; current-slot comparison alone cannot distinguish
all legitimate inactive-period history from an earlier defect, especially with equal
clocks. No data repair or retrospective journal creation accompanies this PR.

B6 member/membership/officer/role/permission writers remain unsynchronised. Existing
B3 characterisation retains their admission semantics; this is not transactional
credential/permission revocation or a universal deadlock-free guarantee. B5 ledger
and period administration, B7 read admission, B8 reconciliation and later milestones
remain pending. The pilot architecture of separate application instances, databases,
credentials, storage, configuration and keys is unchanged.

Rollback must revert the complete coordinated configuration/proposal/source API unit
through a separately approved code rollback, preserve baseline B1-B3, and rerun the
relevant guarded financial/security gates. Never rewrite coverage decisions,
configuration events, posted journals, source provenance, consumed authority or audits.
No schema rollback is introduced. No partial merge or production release is authorised.

## Reviewable commits and exact inventory

```text
55d40444f12c96aeff2b7da45945d00b5ebfc12c feat: enforce B4 organisation ownership through explicit configuration contexts
741abd58f7a7ee3460cda96e3df1c1a98669b483 test: isolate reactivation coverage blocker with consistent synthetic evidence
55d0431c68093376d6cfd2bcc6c5e0004f194b15 docs: record B4 coverage blocker and preserve PostgreSQL evidence
9a7f0b4d4c9a377cbb35e31cb8fafa3e2bb32ea2 fix: bind new source coverage and posting to the current activation slot
1f17fbf1994b9453cba4dce9bfa3736173180f2b test: cover forward-only source activation selection history and PostgreSQL races
```

Full coordinated PR inventory: 14 files: 3 application, 8 test/support, 1 disposable runner and 2 documentation/evidence files.

- `backend/app/Services/Finance/AccountingIntegrationConfigurationService.php`
- `backend/app/Services/Finance/AccountingIntegrationProposalService.php`
- `backend/app/Services/Finance/SourceAccountingIntegrationService.php`
- `backend/tests/Feature/IntegrationConfigurationConcurrencyTest.php`
- `backend/tests/Feature/IntegrationConfigurationEnforcementTest.php`
- `backend/tests/Feature/IntegrationConfigurationResourceScopeTest.php`
- `backend/tests/Feature/SourceCoverageActivationConcurrencyTest.php`
- `backend/tests/Feature/SourceCoverageActivationSelectionTest.php`
- `backend/tests/Support/IntegrationConfigurationFixture.php`
- `backend/tests/Support/IntegrationConfigurationWorker.php`
- `backend/tests/Support/SourceCoverageFixture.php`
- `docs/p0-2b4-integration-configuration.md`
- `docs/validation/p0-2b4-postgres-evidence.json`
- `scripts/verify-cutover-bootstrap-postgres.ps1`

## GitHub CI and repository state

Source CI on exact `1f17fbf1994b9453cba4dce9bfa3736173180f2b`: [Finance release gate #42518255809](https://github.com/kgbijesh-eng/LC-Beenachi-Connect-Prototype/actions/runs/42518255809), success; all steps passed, 210 frontend tests. This workflow does not execute the local PostgreSQL backend matrix. A report-only commit follows, with exact final-head CI and tree recorded in the draft PR. Existing Vite native-config-loader and Node punycode/url.parse deprecations, plus pinned Node-20 actions forced to Node24, remain warnings; no dependency/workflow change is included. Main remains at the approved baseline. No merge/deployment is authorised.
