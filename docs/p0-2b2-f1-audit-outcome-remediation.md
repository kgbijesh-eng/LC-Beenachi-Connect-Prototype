# P0.2B2-F1: opening audit outcome classification

Baseline: clean main `c13a3aa2c221f6a6b3a69eb68e1db70c5f3dcc3f`.
Dedicated branch: `codex/p0-2b2-f1-audit-outcome`. Draft delivery only.

## Reproduction before application changes

Four post-commit cases inject direct/wrapped PDO exceptions carrying `40001` or
`40P01` from Laravel's committed event, after a real PostgreSQL financial commit.
Each asserts one journal, success, consumption, attempt and success audit before
asserting that no `not_posted` event may exist. Unchanged application code fails
all four assertions: it appended `not_posted` despite committed financial records.

Fresh-cluster evidence:
`%TEMP%/lc-p02b2-opening-8707d6eae48842c2b28b44db0068b5e5/`.
18 executed, 14 passed, 4 failures, 0 errors/skips, 222 assertions. Runner result
FAILED_OR_BLOCKED; shutdown 0. This failed evidence is retained, not relabelled.

## Narrow correction

Only `backend/app/Services/Finance/ClubOpeningJournalActivationService.php` changes
application behavior. B1, audit recorder, journal posting, financial body/replay,
authority/governance, schema, migrations, triggers, routes and configuration remain
unchanged. The B1 financial outer root still has exactly three attempts.

Before the first financial operation in each admitted callback, opening captures
the original PDO and `pg_current_xact_id()` top-level xid8. All attempts are retained
for this invocation; retries never reuse an eligibility context or discard earlier
transaction identities. On an exception:

| Evidence | Classification |
| --- | --- |
| No financial callback business work entered | Financial posting cannot have occurred in this invocation; normal rejection/terminal evidence, subject to safe standalone audit prerequisites |
| Every entered attempt positively reports `aborted` | Appropriate original terminal failure/conflict, once after retries finish |
| Any attempt reports `committed` | Never assert `not_posted`; surface reconciliation-required outcome and indeterminate audit |
| In progress, NULL, invalid/missing identity, disconnected/replaced PDO, unreadable status or inspection failure | Indeterminate; never infer rollback |

Inspection uses native statements on the original PDO, not a read replica or a new
connection. Idle PDO and zero Laravel depth are only prerequisites for inspection;
they are not the outcome proof. An empty attempt list is a code-path proof that
financial work was never entered, not an inference that an idle transaction rolled
back. SQLSTATE and exception cause chains are removed from outcome classification.

PostgreSQL documents `pg_xact_status(xid8)` for determining recent transaction
outcomes, including uncertain COMMIT acknowledgements. NULL/expired information
and prepared/in-progress states do not prove abort:
[PostgreSQL 17 transaction information functions](https://www.postgresql.org/docs/17/functions-info.html#FUNCTIONS-PG-SNAPSHOT).
The existing PostgreSQL 17 disposable pilot environment supports this function for
the expected non-elevated application role; no grants or server settings change.
An unavailable function degrades to explicit indeterminate outcome, not permissive
financial execution or a fabricated rollback claim.

Confirmed commits followed by exceptions remain explicit reconciliation-required
public failures; this increment does not introduce a new success response/recovery
API or automatic retry with a fresh key. Eligible exact replay recovers the original
result. Suspended replay remains denied. Terminal writer failures retain both the
business outcome exception and the audit exception.

## Tests and genuine PostgreSQL aborts

`backend/tests/Feature/CutoverOpeningEnforcementTest.php` adds four SQLSTATE/cause
cases with exact replay and cardinality checks, plus post-commit connection loss.
All existing lost-ack, initial/terminal/indeterminate audit failures, suspension,
replay and rollback assertions remain intact.

`backend/tests/Feature/CutoverOpeningTransactionOutcomeTest.php` and
`backend/tests/Support/OpeningOutcomeConcurrencyWorker.php` add genuine multi-session
tests. Serialization tests set SERIALIZABLE on test-owned roots, commit a second
session's update after the financial snapshot, then perform the conflicting update.
Deadlock tests stage a three-session cycle on three unrelated synthetic user
rows: financial A->worker B, B->worker C, C->financial A. Workers verify the exact
blocking PID edges before closing the cycle; small offsets separate detector
deadlines without changing privileges, settings or financial retries. No application wrapper, retry expansion or assertion
weakening is used. Both modes test recovery after one abort and exhaustion after
three aborts, with distinct root XIDs and PostgreSQL committed/aborted evidence.

The worker independently checks the exact allowlisted database, expected owner,
non-elevated role, no memberships and protected-name absence before any write.
No test schema or journal/trigger changes are introduced. F1 runs separately from
the original Integration suite: its four intentional `40P01` cycles must not be
confused with the original C+D+E gate, which must still observe zero deadlocks.
The runner adds only the F1 suite selection; all safety and teardown checks remain.

Initial corrected evidence:

- `%TEMP%/lc-p02b2-opening-fdb6e74bb5d14473b91f380562f49d53/`:
  18 passed /242 assertions, failures/errors/skips 0, shutdown 0. This preceded the
  final added connection-loss/cardinality assertions; final Integration supersedes it.
- `%TEMP%/lc-p02b2-f1-a32866067270415f86dc83dc8ea4f803/`:
  four genuine-abort cases passed /102 assertions, failures/errors/skips 0, shutdown 0.
- `%TEMP%/lc-p02b2f1-security.xml`: 87 passed /1,491 assertions, failures/errors/skips 0,
  in-memory SQLite only.

The first committed-tree Integration attempt executed only the D/E focused group:
48 executed, 47 passed, 0 failures, 1 error, 0 skips, 666 assertions. Evidence:
`%TEMP%/lc-p02b2-integration-911751c6d8ac43c2a3641c3fd0eab675/`, shutdown 0.
The new connection fixture detached PDO but did not restore audit availability;
the unchanged recorder correctly surfaced an indeterminate terminal-audit failure.
The fixture now explicitly reconnects before throwing, testing the intended case:
an available replacement PDO cannot establish the original root's rollback. No
application change or historical assertion was made to resolve this fixture error.
This is manager-connection replacement fault injection, not a wire-level partition.

A second run of the initial two-session deadlock fixture exposed victim-selection
instability: four executed, two passed, two failures, zero errors/skips, 71 assertions;
shutdown 0. Evidence: `%TEMP%/lc-p02b2-f1-763a75af38fd4389995d376b61e3cc77/`.
PostgreSQL selected the auxiliary worker, so the unchanged financial-root-victim
assertions correctly failed. That run is not accepted as financial rollback proof.
The fixture now uses a staged three-session cycle while retaining the requirement
that the financial root receive real 40P01 and the original three-attempt budget.
No role privileges, B1 behavior or historical race assertions change. The final
F1 suite requires two complete consecutive passes; any failed pass fails the runner.
It does not retry until green. Logs retain each root XID/outcome and real SQLSTATE.

## Final validation

| Group | Executed / passed | Assertions | Failures | Errors | Skips |
| --- | ---: | ---: | ---: | ---: | ---: |
| D/E/opening focused | 48 /48 | 672 | 0 | 0 | 0 |
| C organisation enforcement | 58 /58 | 621 | 0 | 0 | 0 |
| Bootstrap | 14 /14 | 169 | 0 | 0 | 0 |
| Historical cutover | 63 /63 | 703 | 0 | 0 | 0 |
| Original C+D+E PostgreSQL concurrency | 14 /14 | 389 | 0 | 0 | 0 |
| Genuine F1 outcomes, two full stability passes | 8 /8 | 228 | 0 | 0 | 0 |
| Unchanged B1 | 34 /34 | 313 | 0 | 0 | 0 |
| Canonical finance | 94 /94 | 1,165 | 0 | 0 | 0 |
| HTTP/security, SQLite memory | 87 /87 | 1,491 | 0 | 0 | 0 |
| **Final matrix** | **420 /420** | **5,751** | **0** | **0** | **0** |

JUnit reconciliation by class and dataset testcase name gives **408 unique cases**.
Twelve repeated executions are intentional: eight permission cases shared by SQLite
and PostgreSQL groups and four genuine-outcome cases repeated for stability. The
historical ten identical and ten changed opening races remain iterations inside
their original testcase, not extra case counts. No final group is blocked.

All backend invocations in this task, including the retained reproduction and
resolved fixture failures: 512 executions, 505 passes, six failures, one error,
zero skips, 7,054 assertions. The six failures are four before-fix reproduction
cases plus two initial deadlock-fixture victim-selection failures; the one error
is the initial unavailable-audit connection fixture. These are not silently omitted
from the history or counted as passing final evidence.

Final evidence under `%TEMP%`:

- `lc-p02b2-integration-ed9d9cde06b142dc86db45300ffb81e8/`:
  committed head `c8f60632dbff1f02313df1bd7dd46e0130c95bbf`,
  197 passed /2,554 assertions; zero deadlocks, timeout/cancellation records,
  failures/errors/skips; shutdown 0.
- `lc-p02b2-f1-59552ba5190f4fd9a410d26f275418de/`:
  committed head `2d1b92655af6e34c00c31755e719c476fa11d5d4`,
  two complete four-case passes /114 assertions each; shutdown 0. Eight intended
  deadlocks and eight genuine concurrent-update serialization aborts are recorded;
  zero timeout/cancellation records. Every financial root victim and worker outcome
  is asserted. These intended cycles are separate from the unchanged original gate.
- `lc-p02b2-guard-b574fcb149434c9dbeda88dd3af98b3f/`:
  34 /313, shutdown 0.
- `lc-p02b2-finance-0716b4acf3034673b63e05c6601c73d0/`:
  94 /1,165, shutdown 0. Breakdown: acceptance 10/288; receipt-correction
  concurrency 1/77; transactional 47/455; receipts/statements 35/334;
  read-only snapshot concurrency 1/11.
- `lc-p02b2f1-security.xml`: 87 /1,491, in-memory SQLite only.

All final PostgreSQL runners report PASS, before/after identity, inventory,
ownership/non-elevated-role/protected-name checks, unchanged application/test hashes
and successful shutdown. No existing PostgreSQL server or protected database was
accessed. Original failed artifacts remain intact. This is the requested selected
matrix, not a claim of the complete historical backend test collection.

Example first-pass PostgreSQL evidence retained in `F1_OUTCOME_EVIDENCE` log lines:

| Scenario | Root transaction IDs and outcomes | SQLSTATEs | Journal/success/consumption |
| --- | --- | --- | --- |
| Serialization recovery | 1154 aborted; 1157 committed | 40001 | 1 /1 /1 |
| Serialization exhaustion | 1493,1496,1499 aborted | 40001 x3 | 0 /0 /0 |
| Deadlock recovery | 1837 aborted; 1841 committed | 40P01 | 1 /1 /1 |
| Deadlock exhaustion | 2177,2181,2185 aborted | 40P01 x3 | 0 /0 /0 |

The server's three-session graph identifies backend 20996 waiting for transaction
1839 held by 19676, 19676 waiting for 1840 held by 11812, and 11812 waiting for
1838 held by 20996. Financial root 20996 is aborted; after retry only one financial
result commits. No synthetic RAISE substitutes for these genuine engine failures.

## Commits, scope and CI

- `704f3c9121dc2c998f7cdb7699aaee959d970c5f`: application correction and tests.
- `c8f60632dbff1f02313df1bd7dd46e0130c95bbf`: connection fixture correction only.
- `2d1b92655af6e34c00c31755e719c476fa11d5d4`: three-session fixture and two-pass gate.

Application contents have not changed since 704f3c9. B1 and finance ran at that
commit; later integration and F1 runs above use the same application contents.
The unchanged historical concurrency file retains every original assertion and
iteration. A source comparison verifies `executeFinancial()` and all following
financial/replay/provenance methods are identical to baseline. Protected B1,
journal, C/D/bootstrap/audit-recorder, schema and configuration paths are unchanged.
PowerShell parser and Git whitespace checks passed.

Non-destructive GitHub source CI passed at
[704f3c9 run 42450710282](https://github.com/kgbijesh-eng/LC-Beenachi-Connect-Prototype/actions/runs/42450710282) and
[c8f6063 run 42450943190](https://github.com/kgbijesh-eng/LC-Beenachi-Connect-Prototype/actions/runs/42450943190), and
[2d1b926 run 42451671070](https://github.com/kgbijesh-eng/LC-Beenachi-Connect-Prototype/actions/runs/42451671070).
Latest fixture/report-head CI is recorded on draft
[PR #33](https://github.com/kgbijesh-eng/LC-Beenachi-Connect-Prototype/pull/33).
CI is separate from the local PostgreSQL evidence and does not execute these backend
suites. Existing Node/Vite/action-runtime notices are not addressed by this scope.

Reproduction commands for the final cohorts:

```powershell
./scripts/verify-cutover-bootstrap-postgres.ps1 -Suite Integration
./scripts/verify-cutover-bootstrap-postgres.ps1 -Suite F1 -Port 55495
./scripts/verify-cutover-bootstrap-postgres.ps1 -Suite Guard -Port 55495
./scripts/verify-cutover-bootstrap-postgres.ps1 -Suite Finance -Port 55496
```

Run commands sharing a port sequentially; each owns a fresh independently verified
cluster and shuts it down. The F1 runner fails if either stability pass fails.

## Limitations, rollback and recommendations

Outcome inspection is synchronous and invocation-local. It does not persist XIDs
or add a reconciliation command. NULL/expired status, unavailable status functions,
connection loss/replacement, inability to confirm an abort or failed audit storage
remain explicit reconciliation-required outcomes. The tests inject committed-event
exceptions and connection replacement; they are not exhaustive wire-level network,
failover, crash or storage fault tests. Process death can prevent terminal recording;
retain the original request reference/key and reconcile immutable financial links.
No new permission, schema, server setting or role elevation is required.

Rollback must use a separately reviewed revert of F1 and restrict opening execution
until the known prior audit-classification defect is handled. Preserve journals,
successes, consumption, approvals and all audit evidence. No data restoration or
schema rollback is appropriate. C+D+E club-first ordering remains intact on F1 revert.

Implementation recommendation: **GO for the approved F1 scope**. All mandatory
final tests pass; no remaining F1 blocker was observed. Technical evidence supports
review of the fix, not automatic release or blanket acceptance of later milestones.
Merge execution: **NO-GO pending separate business-owner review and approval**.
Keep PR #33 draft, do not mark Ready or merge, and verify the exact integration tree
and CI after any authorised merge. Formal B2 closure must await acceptance of that
merged remediation. No deployment, finance cutover or later implementation occurred.
