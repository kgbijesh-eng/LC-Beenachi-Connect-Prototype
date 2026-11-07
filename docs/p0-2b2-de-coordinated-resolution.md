# Coordinated P0.2B2-D/E integration

Integration baseline: reviewed C `5a039f94eef0db0ada67690d43b2fd46fd8f2020`.
PR #29 remains draft on its original branch; no approved C application behavior is
rewritten. D, E1 and E2 will be distinct reviewable commits on a separate branch.
No partial increment is safe to merge/deploy independently.

Application file inventory relative to the reviewed C baseline:

| Increment | Exact file | Change |
| --- | --- | --- |
| D | `backend/app/Services/Finance/ClubCutoverLifecycleService.php` | One-attempt guarded lifecycle roots; scoped package-before-decision revocation |
| D | `backend/app/Services/Finance/ClubCutoverEvidenceVerificationService.php` | One-attempt guarded evidence decision and scoped replay lookup |
| E1 | `backend/app/Services/Finance/CutoverExecutionAuditRecorder.php` | Dedicated audit-only roots |
| E1 | `backend/app/Services/Finance/Exceptions/CutoverExecutionAuditException.php` | Explicit audit phase plus both failures |
| E1 | `backend/app/Services/Finance/Exceptions/CutoverExecutionOutcomeUnknownException.php` | Indeterminate financial outcome marker |
| E2 | `backend/app/Services/Finance/ClubOpeningJournalActivationService.php` | Guarded three-attempt financial root and separate durable audit orchestration |

Tests are `CutoverLifecycleEligibilityTest.php`, `CutoverExecutionAuditRecorderTest.php`
and `CutoverOpeningEnforcementTest.php` under `backend/tests/Feature/`;
`Milestone3iizgPostgresConcurrencyTest.php` retains the historical race assertions.
`backend/tests/Support/BuildsOpeningExecutionFixture.php` copies the established
public-service-authorised fixture without altering the historical fixture.
`Milestone3iizgPostgresConcurrencyWorker.php` and
`Milestone3iizsPostgresRevocationRaceWorker.php` in that support directory adapt
only worker identity, real-lock barriers and additional tested race modes.
`scripts/verify-cutover-bootstrap-postgres.ps1` adds isolated suite selections and
teardown allowance. No application files outside this six-file inventory change.

## D: lifecycle and evidence roots

Lifecycle prepare/verify/review/approve/decide/revoke use B1 at their outer boundary,
one attempt each. Revocation changes decision-first to club/package/scoped decision.
Evidence verification uses a one-attempt club/scoped-package root. Its exact replay
comparison remains before expected-lock validation and retains supplied version
semantics. Scoped reference collisions remain conflicts. DR-033 selection/checksum
sequence, current authority, dual-control and immutable provenance remain unchanged.

D validation: 23 focused cases / 332 assertions and 63 historical cases / 703
assertions passed, zero failures/errors/skips. An initial invocation executed zero
tests because a test helper collided with Laravel's public `call()` method; it was
renamed before validation. Its failed runner evidence is retained and is not a pass.
Local evidence: `%TEMP%/lc-p02b2-d-973871df4ca441618a5765240b769e0a/`.

## E1: audit-only recorder

The recorder appends only attempt, terminal failure/conflict or indeterminate audit
evidence. Each write owns a short one-attempt root: authoritative club FOR UPDATE,
scoped package FOR KEY SHARE, actor binding, append, commit. It cannot accept a
business callback, create success/consumption, grant authority or issue a B1 context.
Suspended evidence requires valid authoritative identity and ownership; foreign or
misconfigured targets cannot write foreign evidence. Ambient roots fail closed.
The recorder is initially unwired from opening so E1 can be reviewed separately.

E1 validation: six focused PostgreSQL cases / 74 assertions passed with zero
failures/errors/skips and confirmed shutdown. Active and inactive audit-only paths,
foreign/configuration/ownership failures, native/Laravel ambient rejection and
single-attempt persistence failure were exercised. Evidence:
`%TEMP%/lc-p02b2-audit-a5019be6dd04484ab0d41a3147b89dd9/`.

## E2: financial root and outcome certainty

Opening invocation now commits its narrowly scoped attempt first, then enters B1
with the original three-attempt budget. Each callback receives a new transaction
context and reloads actor, scoped package, replay bindings, authority, decisions,
validation, preview and period. The existing journal posting call and its nested
transaction remain unchanged. Success, consumption, package transition and success
or replay event remain atomic in the financial root. Exact replay requires active
eligibility while holding club before any replay response.

Financial ordering is club -> scoped package -> execution/boundary -> authority
and decision prerequisites -> period -> unchanged nested journal posting. The
journal's repeated club lock is acquired by the same transaction, which already
owns it. D and C obtain club before their scoped resource locks as well. Trigger
package locks and foreign-key key-share locks therefore no longer complete the
recorded package-to-club versus club-to-package cycle for these public roots.

Attempt storage failure prevents financial entry. Confirmed rollback permits one
terminal failure/conflict write, outside the financial root and without an active
state waiver usable by business services. Both original and audit failures are
retained in a typed exception if terminal storage fails. A disconnected/unprovable
transaction or an exception after financial callback completion without a known
server serialization/deadlock abort is conservatively indeterminate. It never
asserts `not_posted`; it emits `outcome_unknown` if audit persistence is available,
otherwise explicitly surfaces both failures. Do not blindly retry with a new key:
reconcile original reference/key, immutable journal and consumption first. A later
eligible exact replay can recover a committed result without duplicating posting.

The commit-ambiguity test injects a lost acknowledgement at Laravel's committed
event after PostgreSQL has committed. This tests classification and exact replay;
it is not a claim of exhaustive network/driver fault injection. B1's context and
retry semantics, DR-033, journal services, schemas and triggers remain unchanged.

## Concurrency regression protocol

The original failing C safety gate and every winner/provenance assertion remain.
Its opening worker pauses after the financial package lock (not the new short
audit root). Before E2 this gives package-first opening versus club-first C,
reproducing the recorded `40P01`. With E2 opening owns club and package; the other
public root is observed waiting through `pg_stat_activity` and blocking PIDs before
opening is released. No ambient test wrapper, altered retry policy or timeout-as-
success is used. The original ten identical and ten changed opening iterations
retain their financial, overlap, terminal-event and winner assertions. Their
barrier waits for the second worker to enter and really block, rather than requiring
its audit to commit while another worker already owns club. Both durable attempts
are still required after completion.

Appointment and permission revocation cannot revoke consumed authority after an
activation winner. Existing lifecycle governance does permit append-only decision
revocation after posting; the added decision race verifies its event plus unchanged
consumed decision/journal provenance. It does not introduce a new post-posting
governance rule or undo accounting. Suspension-first rejects financial mutation;
financial-first commits atomically before suspension obtains club.

## Review, rollout and rollback constraints

D `a041d8cfe12525528341dd32c3b8e3ba13c8cf1e` and E1
`ac167f4899447c20376d227231d7c95b8d3ee738` are independently reviewable increments,
not independent release units. Keep PR #29 unchanged and all increments draft.
Only the verified C+D+E tree may be considered for separate business-owner merge
approval. No partial merge or mixed-version writer deployment is safe.
For eventual integration, obtain separate approval to retarget the final integration
PR to `main` as the complete C+D+E unit, then verify its exact resulting tree and CI.
Do not merge the stacked review PRs one at a time or change PR #29 under this task.

Rollback requires stopping affected writers, reverting the coordinated application
increments together and keeping affected mutation entry points unavailable until
review. Preserve all attempt/terminal/success audit evidence, grants, package
versions, decisions, authority consumption and journals. No database migration or
data rollback is required or authorised. Reverting the enforcement loses direct-
call protection and reintroduces the C/opening lock inversion if C remains active.

Remaining scope: unrelated operational services, B2-F and B3-B8, credential
revocation and operator suspension/resumption remain unimplemented. HTTP guards
alone are not transaction-time protection for these remaining operations. Club-
wide serialization can increase contention; bounded operations and unchanged retry
budgets are retained. Durable audit is not guaranteed when its storage is unavailable:
failure is explicit and requires reconciliation. This increment does not provide an
operator reconciliation command or change read-admission policy.

## Retained intermediate runs and resolved verification issues

These runs are not substituted for the final integrated gate:

| Run | Executions | Passed | Failures | Errors | Skips | Assertions | Runner result |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | --- |
| D focused + historical | 86 | 86 | 0 | 0 | 0 | 1,035 | PASS, shutdown 0 |
| E1 audit | 6 | 6 | 0 | 0 | 0 | 74 | PASS, shutdown 0 |
| First E2 focused | 10 | 10 | 0 | 0 | 0 | 121 | PASS, shutdown 0 |
| First C+D+E integration | 188 | 187 | 1 | 0 | 0 | 2,218 | FAILED_OR_BLOCKED, shutdown 1 |
| First finance verification | 94 | 94 | 0 | 0 | 0 | 1,165 | FAILED_OR_BLOCKED, shutdown 1 |

The first E2 focused evidence is
`%TEMP%/lc-p02b2-opening-8f3bf7e160a340a79b1750bf7f90d54b/`.
The first integration evidence is
`%TEMP%/lc-p02b2-integration-721cc46675e24f42b4bae4356124086d/`.
Its single failure was the repeated-opening concurrency case: the worker inherited
the runner's deliberately unassigned organisation, so eligibility rejected before
attempt one. No ten-plus-ten success is claimed for that run. The original forced
deadlock gate and all other thirteen concurrency cases passed. Both workers now
receive the fixture's exact organisation through process environment, matching the
already established bootstrap/C worker contract; assertions remain intact.

The first finance evidence is
`%TEMP%/lc-p02b2-finance-a7172e2bab73463bac86284f73916067/`.
Its five PHP test groups passed, but the old sixty-second `pg_ctl` wait expired.
The shutdown checkpoint took 69.407 seconds. Both timed-out disposable clusters
were subsequently confirmed stopped using their exact data directories: no server,
no postmaster PID and no listener on their dedicated port; server logs confirmed
shutdown. Original failed runner evidence remains unchanged. Infrastructure teardown
patience is now 120 seconds, with unchanged worker timeouts, financial attempts and
all database safety checks. This is not an accepted business-operation timeout.

The earlier D helper-name fatal invocation executed zero tests and produced no
usable JUnit counts; its exit 255 is retained at
`%TEMP%/lc-p02b2-d-53049b92332c413898a728ec221e996d/`. It is not counted as a
passing suite or silently converted into a skip.

## Final C+D+E matrix

| Group | Executed / passed | Assertions | Failures | Errors | Skips |
| --- | ---: | ---: | ---: | ---: | ---: |
| D/E focused PostgreSQL (23 D + 6 audit + 14 opening) | 43 / 43 | 580 | 0 | 0 | 0 |
| Unchanged C PostgreSQL | 58 / 58 | 621 | 0 | 0 | 0 |
| Unchanged bootstrap PostgreSQL | 14 / 14 | 169 | 0 | 0 | 0 |
| Historical cutover PostgreSQL | 63 / 63 | 703 | 0 | 0 | 0 |
| PostgreSQL concurrency, including original deadlock gate | 14 / 14 | 389 | 0 | 0 | 0 |
| Unchanged B1 PostgreSQL | 34 / 34 | 313 | 0 | 0 | 0 |
| Canonical finance PostgreSQL | 94 / 94 | 1,165 | 0 | 0 | 0 |
| HTTP/security and B1 boundary, SQLite memory | 87 / 87 | 1,491 | 0 | 0 | 0 |
| **Final selected matrix total** | **407 / 407** | **5,431** | **0** | **0** | **0** |

JUnit reconciliation by `(class, testcase name)` gives **399 unique cases** and
407 executions: eight permission-service/middleware cases intentionally execute
in both SQLite security and PostgreSQL finance. Dataset cases count separately;
the ten identical and ten changed races remain iterations inside one historical
testcase (197 assertions), not twenty extra testcases. Intermediate/repeated runs
above are excluded from this final-selected total. No requested final group is
blocked or silently skipped.

Final integration: 192 executions /2,462 assertions in
`%TEMP%/lc-p02b2-integration-4c0b16d568274558b03d14a6014b4a02/`.
The runner started with E1 HEAD and the complete pending E2 worktree, then E2 was
committed unchanged as `4b26d472afc571a5f464ae7632eb72d35c343699` during execution.
The source before/after hash gate checks application and test contents; later
changes are documentation only. The finance rerun and GitHub E2 CI use that exact
E2 commit. This distinction is explicit rather than presenting E1 as the tested
application tree.

All final concurrency winner/provenance assertions passed. Server-log inspection
found zero `deadlock detected` records and zero lock/statement timeout or statement
cancellation records. The original C artifact still contains its real `40P01`
failure at `%TEMP%/lc-p02b2-c-ff9e9d24c0ed450e8a02e174a734e05c/`; it is preserved
as before-fix evidence, not relabelled as a passing run. After-fix traces show opening
locks club before package, later reacquires its own club lock in unchanged journal
posting, and revocation then rejects consumed appointment/permission authority.
No timeout stands in for that governance rejection. Both decision and suspension
orders preserve the documented original governance and atomic accounting outcomes.

Final integration runner result: **PASS**, shutdown exit **0**, source hashes
unchanged, before/after cluster/owner/role/inventory checks passed. Its server log
contains no deadlock or accepted timeout. All final disposable clusters stopped
successfully; earlier failed teardown evidence remains retained as described above.

Supporting final evidence:

- B1: `%TEMP%/lc-p02b2-guard-fb61a69bf5df4ab3a73331bddb0debe6/`,
  34 passed /313 assertions, zero failures/errors/skips, shutdown 0.
- Finance rerun at E2 commit `4b26d472afc571a5f464ae7632eb72d35c343699`:
  `%TEMP%/lc-p02b2-finance-4c8be3ab04a34a6e95df5c4e7fa5eada/`,
  94 passed /1,165 assertions, zero failures/errors/skips, shutdown 0. Its shutdown
  checkpoint took 66.235 seconds, within the infrastructure-only 120-second limit.
  Breakdown: acceptance 10/288; correction concurrency 1/77; transactional 47/455;
  receipts/statements 35/334; read-only snapshot concurrency 1/11.
- Unchanged HTTP/security and B1 boundary: `%TEMP%/lc-p02b2de-security.xml`,
  in-memory SQLite only, 87 passed /1,491 assertions, zero failures/errors/skips.

All PostgreSQL runs use the exact database names in the runner, a newly generated
cluster, independent temporary credentials, loopback port, expected non-elevated
owner and zero role memberships. Before/after identity, inventory, ownership and
protected-name checks and unchanged-source checks remain enabled. No existing
server or protected database is contacted. Failed-run evidence is retained rather
than relabelled. No full historical backend matrix is claimed beyond the explicitly
listed requested cutover/security/canonical finance groups.

## Draft increments and GitHub validation

| Increment | Exact commit | Draft review |
| --- | --- | --- |
| C, unchanged | `5a039f94eef0db0ada67690d43b2fd46fd8f2020` | [#29](https://github.com/kgbijesh-eng/LC-Beenachi-Connect-Prototype/pull/29) |
| D | `a041d8cfe12525528341dd32c3b8e3ba13c8cf1e` | [#30](https://github.com/kgbijesh-eng/LC-Beenachi-Connect-Prototype/pull/30), base C |
| E1 | `ac167f4899447c20376d227231d7c95b8d3ee738` | [#31](https://github.com/kgbijesh-eng/LC-Beenachi-Connect-Prototype/pull/31), base D |
| E2 | `4b26d472afc571a5f464ae7632eb72d35c343699` | [#32](https://github.com/kgbijesh-eng/LC-Beenachi-Connect-Prototype/pull/32), base E1 |

Non-destructive GitHub CI passed on exact source commits:
[D run 42420238241](https://github.com/kgbijesh-eng/LC-Beenachi-Connect-Prototype/actions/runs/42420238241),
[E1 run 42420259114](https://github.com/kgbijesh-eng/LC-Beenachi-Connect-Prototype/actions/runs/42420259114),
[E2 run 42420783947](https://github.com/kgbijesh-eng/LC-Beenachi-Connect-Prototype/actions/runs/42420783947).
E2 ran 210 frontend tests across 21 files, typecheck, build, manifests, existing
PowerShell syntax and verifier preflight. It does not execute PostgreSQL/backend
tests. The changed PostgreSQL runner's PowerShell syntax and Git whitespace checks
were checked locally as well. Subsequent report-only commit/check state is on #32.

Non-blocking CI notices: Node `punycode` and `url.parse()` deprecations, Vite's future
native-loader configuration warning, and pinned Node-20 actions forced to Node 24.
No dependency or workflow changes are included. Intentional PostgreSQL constraint
and serialization errors occur in negative tests; finance also retains existing
`SET CONSTRAINTS`-outside-transaction warnings. These are distinct from JUnit
failures/errors, which are zero in the final selected matrix.

## Decisions

**Implementation completion: GO. Coordinated C+D+E technical integration: GO**
for the tested source tree. The reproduced C/opening cycle is resolved in the
covered public roots without changing C, B1, bootstrap, journal posting, schema,
triggers, retries or financial governance. Scope comparison against C confirms
those protected files and configuration/routes/migrations are unchanged.

**Merge now or merge partial increments: NO-GO.** All four PRs remain draft and
unmerged; PR #29 stays at its original reviewed commit. The positive technical
recommendation is for the complete coordinated unit only. Separate business-owner
review/approval and verification of the eventual exact `main` integration tree and
CI are required before any merge. No deployment or finance cutover is authorised.

No requested final test is failed, errored, skipped or blocked. Residual limitations
are the explicitly deferred services and bounded concurrency/fault coverage above,
plus operational reconciliation when storage or commit outcome is indeterminate.
They must not be represented as complete organisation suspension enforcement for
the whole application. Final delivery records the report-only head, cleanliness and
remote ahead/behind counts separately; no protected data or infrastructure changed.
