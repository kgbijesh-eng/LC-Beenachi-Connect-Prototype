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
`backend/tests/Support/OpeningOutcomeConcurrencyWorker.php` add genuine two-session
tests. Serialization tests set SERIALIZABLE on test-owned roots, commit a second
session's update after the financial snapshot, then perform the conflicting update.
Deadlock tests create opposite lock acquisition on two unrelated synthetic user
rows; the worker waits until PostgreSQL reports the financial root actually blocked
before closing the cycle. No application wrapper, retry expansion or assertion
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

Final integration, B1, canonical finance, CI, source comparisons and recommendations
will be recorded after completion. No merge or Ready-for-Review action is authorised.
