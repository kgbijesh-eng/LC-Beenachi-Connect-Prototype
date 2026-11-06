# P0.2B2-A/B: PostgreSQL foundation and bootstrap enforcement

Baseline: `aad27f47e9f6d83ee14eab4951fabe30edb1be16`.

## Before application changes: transaction/lock record

Recorded from the baseline before editing application code:

- Bootstrap `request`: no explicit transaction; authority/preparer reads, digest
  reference lookup/replay, pending-operation insert (implicit FK/unique locks).
- Bootstrap `approve`: one-attempt Laravel transaction; operation FOR UPDATE,
  applied replay before eligibility, club FOR UPDATE, approval insert, optional
  package/initial permission creation, operation update and audit insert.
- Initial permission insertion's PostgreSQL trigger locks the new package.
- Package authoring/validation, evidence and lifecycle lock package first.
- Administration approval locks operation then package; holder revocation locks
  package then holder record; decision revocation locks decision first.
- Opening execution writes attempt evidence outside its three-attempt transaction;
  locks package, success reference/boundary, period, then calls journal posting.
  Journal posting locks club then period/accounts/replay rows. Failure evidence is
  written after the opening transaction unwinds.

Only bootstrap enforcement is authorised here. Other cutover services, B1,
journal services, triggers, migrations and accounting semantics remain unchanged.
This increment cannot establish complete cutover suspension or lock-order safety.

## Test foundation contract

Positive bootstrap calls require PostgreSQL root transactions, never a fixture
transaction or an APP_ENV exception. Historical cutover assertions are retained;
affected fixture suites run on an exact allowlisted disposable database. Synthetic
test processes receive explicit authoritative instance configuration. Safety checks
must precede migrations and fixture writes, including owner, non-elevated role,
no inherited memberships and protected-name exclusion. No original database access
is needed for the fresh-cluster runner.

## After: bootstrap boundary

Only `backend/app/Services/Finance/ClubCutoverPackageBootstrapService.php` changes
application behavior. `request` now owns one guarded transaction instead of making
autocommit writes. `approve` replaces its old outer transaction with the guard,
explicitly retaining `attempts: 1`. The guard runs at depth zero, acquires the
authoritative club FOR UPDATE, checks fresh activity, and then invokes business
work. Approval reloads its operation with both id and context organisation before
locking it. An input model's changed `club_id` cannot select a foreign persisted
operation. Applied replay is inside the same guarded boundary.

Authority/preparer checks, exact digest normalization, two distinct officer-role
approvals, current-officer revalidation in `apply`, bootstrap uniqueness, initial
permission provenance and audit atomicity are unchanged. Applied replay retains
its historical actor/approval semantics after the new eligibility prerequisite;
this increment does not redefine replay authorization or restore revoked grants.
No B1 code, transaction context semantics, journal code, triggers or schema changes.

Unknown Laravel/PDO ambient transactions remain unsupported and are not adopted or
rolled back by bootstrap. There is no test-mode bypass. Callers needing composed
transactions require separately reviewed explicit context APIs; none is added here.

## Running verification

On Windows with PowerShell 7, PostgreSQL 17 binaries and the installed PHP runtime:

```powershell
./scripts/verify-cutover-bootstrap-postgres.ps1 -Suite Cutover
./scripts/verify-cutover-bootstrap-postgres.ps1 -Suite Guard
./scripts/verify-cutover-bootstrap-postgres.ps1 -Suite Finance
```

`-PostgresBin`, `-PhpPath` and `-Port` may locate installed tools/a free loopback port.
`-Suite Cutover -BootstrapOnly` runs only the new focused cases; the default Cutover
mode also requires every historical group. Omitted groups are not counted as passed.
Database names and role cannot be overridden. Run sequentially to avoid competing
filesystem checkpoints. The runner creates a GUID-named temporary data directory,
uses independent SCRAM credentials, verifies data-directory/system identifier,
loopback port, full database inventory, ownership, all elevated role flags and zero
memberships before migrations and each test group, and repeats checks afterwards.
It retains JUnit/log/evidence outside the repository and stops its own cluster.
It never reads `.env` credentials, contacts the normal server or drops a database.
Failure or unconfirmed shutdown returns a non-zero exit.

Exact disposable targets:

| Suite | Database(s) |
| --- | --- |
| Cutover | `lcb_p02b2_cutover_test` |
| Guard | B1's existing `lcb_p02b1_transaction_guard_test` |
| Finance | `lcb_3ijae_finance_p02b2ab`, `lcb_3iizt_statement_snapshot_concurrency_test` |

All run as `lc_beenachi_app`, non-elevated, NOINHERIT, no memberships, owning only
the fresh disposable targets in that cluster. Administrative credentials only
initialize the fresh cluster/roles/databases and perform identity checks.

The historical package, opening and administration fixture suites replace
RefreshDatabase transactions with `Tests/Support/CutoverPostgresTestCase.php`.
Their test bodies/assertions remain intact apart from explicit synthetic instance
configuration in the two direct bootstrap cases. The shared synthetic bootstrap
helper explicitly assigns the fixture organisation. The historical bootstrap race
passes that code as deployment configuration to each process, independently of
request payload. The existing opening/revocation race worker is unchanged: its
ambient package transaction still calls only services not guarded by this increment.

The new base skips when not explicitly opted into the isolated runner. This is not
a passing substitute for PostgreSQL evidence: the opt-in run requires zero skips.
Positive cutover service tests now require this runner rather than SQLite. B1's
SQLite boundary rejection and HTTP security suites continue to run unchanged.

## Validation

Latest complete suite results (suite rows may overlap; no combined unique-test
count is claimed):

| Group | Passed / executed | Assertions | Failures | Errors | Skips |
| --- | ---: | ---: | ---: | ---: | ---: |
| Focused bootstrap eligibility/root/lock/race | 14 / 14 | 169 | 0 | 0 | 0 |
| Historical cutover package/lifecycle/opening/administration | 63 / 63 | 702 | 0 | 0 | 0 |
| Historical PostgreSQL cutover concurrency | 7 / 7 | 261 | 0 | 0 | 0 |
| Unchanged B1 PostgreSQL guard | 34 / 34 | 313 | 0 | 0 | 0 |
| P0.1/P0.2A/B1 boundary and supporting security, SQLite memory | 87 / 87 | 1,491 | 0 | 0 | 0 |
| Canonical finance backend groups, fresh PostgreSQL | 94 / 94 | 1,165 | 0 | 0 | 0 |

Historical breakdown: package 16/189 assertions, lifecycle contract 4/47, opening
25/273, administration 18/193. Original business assertions are unchanged; the
PostgreSQL base adds six safety/setup assertions per affected case. Concurrency
retains all seven cases, including ten identical and ten changed opening races.
Finance breakdown: acceptance 10/288; receipt-correction concurrency 1/77;
transactional 47/455; receipts/statements 35/334; read-only snapshot concurrency 1/11.

Observed SQL at depth one, including applied replay:

```sql
select * from "clubs" where "clubs"."id" = ? limit 1 for update;
select * from "club_accounting_cutover_bootstrap_operations"
where "club_accounting_cutover_bootstrap_operations"."id" = ?
and "club_id" = ? limit 1 for update;
```

The suspension-first test held an uncommitted inactive club update, observed the
independent approver waiting through `pg_stat_activity`/`pg_blocking_pids`, then
committed suspension. The approver rejected with `ORGANISATION_SUSPENDED`; only
the club lock query completed, with no second approval or package creation.
The bootstrap-first test paused immediately after its club lock, observed the
separate suspension writer waiting, released bootstrap, and verified atomic apply
followed by committed suspension and denied applied replay. Trigger package locking
on initial permission insertion remains unchanged and follows the owned club lock.
The observer refreshes PostgreSQL's statistics snapshot per poll; sleeps alone are
not accepted as proof of a lock wait.

Initial executions also passed all test assertions, but their three concurrent
cluster shutdown checkpoints exceeded the original 30-second wait. Those runs
retained `FAILED_OR_BLOCKED` evidence despite zero test failures/errors/skips.
Each server subsequently stopped and was independently checked with `pg_ctl status`.
The runner now waits up to 60 seconds and propagates unconfirmed shutdown as a
non-zero exit. Sequential complete reruns passed with confirmed shutdown. No
application defect, weakened assertion or retry expansion was needed.

Local artifacts under `%TEMP%` (JUnit, logs and JSON identity/safety evidence):

- Full cutover: `lc-p02b2-cutover-d7f7562566b64abb9d99cf1df7700b0c/`.
- B1: `lc-p02b2-guard-d73709d11501425f88c7e753a251f931/`.
- Finance: `lc-p02b2-finance-55321e7de1684bdcbb3e3e549fcfe072/`.
- Supporting security: `lc-p02b2ab-security.xml`.
- Final focused observer rerun: `lc-p02b2-cutover-b536e3fa3c52476d80c733b0bca4959e/`.

These are local artifacts, not hosted CI evidence. No original server or production
database was accessed. PowerShell parser and Git whitespace checks passed.
The Finance mode invokes the canonical verifier's same five backend test groups
against fresh clusters; it does not run the full frontend/dependency-audit verifier
or claim the complete historical backend matrix.

## Remaining dependencies, rollback and recommendation

Later B2 stages must migrate package/authority/lifecycle/evidence/opening boundaries
and approve a narrow durable opening-audit contract. Those services retain their
existing lock orders and eligibility gaps. B2-A/B is not complete cutover suspension
safety. No suspension commands, credential revocation or B3–B8 work is included.

Rollback must coordinate all bootstrap callers: stop old/new writers before
reverting, rather than mixing operation-first and club-first processes. Preserve
B1/P0.1/P0.2A. A code rollback removes bootstrap enforcement and restores the old
request transaction behavior; it must not delete packages, approvals, assignments,
audit history, consumed authority or journals. No database rollback/migration is
required. Affected bootstrap operations should remain unavailable if enforcement
is rolled back pending review.

GO for A/B draft review on the passing scoped evidence; NO-GO for claiming complete
cutover suspension safety or starting later stages. Keep the PR draft and unmerged. No later
stage, deployment, organisation provisioning or finance cutover is authorised.
