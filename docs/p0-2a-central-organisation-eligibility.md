# P0.2A — Central Organisation Eligibility

Base: clean `main` at `eee782adde5c37895cb016b300069ac262a78f31`.
Branch: `codex/p0-2a-central-organisation-eligibility`.
Status: draft implementation for review; no deployment or pilot approval.

## Scope and compatibility confirmation

The existing API-group `EnsureInstanceOrganisation` middleware remains before
model substitution. It now delegates to `OrganisationAccess`, which resolves the
authoritative instance organisation from the database on every call through the
unchanged P0.1 `InstanceOrganisation` resolver. It does not retain an activity
decision between calls or trust a supplied model's `is_active` attribute.

The check can be introduced without changing financial behavior: it rejects an
ineligible request before controller/domain execution, while eligible requests
continue through the same authentication, permissions, resource ownership,
validation and finance services. No finance service, lock ordering, journal
posting, transaction boundary, idempotency mechanism, schema, environment file or
credential-revocation behavior changes in this milestone.

The pilot remains one codebase with separate application instances, databases,
credentials, configuration, storage and application keys per organisation. This
change does not introduce tenant selection or make shared infrastructure safe.

## HTTP contract

All routes in the API middleware group require organisation eligibility by
default, including future routes without a `{club}` parameter.

Order inside the guard:

1. Resolve the exact configured organisation through P0.1. Missing, empty,
   malformed, unknown or non-singleton identity fails closed with the existing
   `503 Organisation configuration is unavailable.` behavior.
2. If the route has a club parameter, compare it with the authoritative club ID.
   A foreign/nonmatching target returns generic 404 before suspension disclosure
   and before model binding or domain execution.
3. Reject an inactive authoritative organisation with HTTP 403 and exactly:

   ```json
   {"message":"Organisation access is suspended.","code":"ORGANISATION_SUSPENDED"}
   ```

4. Store only the eligible organisation in the request context and continue.

Authentication ordering remains unchanged. An unauthenticated protected request
can fail with 401 before eligibility enforcement. The guard grants no authority,
and it does not bypass existing membership or resource-ownership checks.

The check applies to login, public activation completion, authenticated activation
issuance, `/auth/me`, every club API read/mutation and PDF endpoint. A bearer token
issued while active does not authorize protected HTTP access after the database
state becomes inactive, even when an old Club model or request context says active.

## Narrow exceptions

| Route | Exception | Remaining protection |
| --- | --- | --- |
| `GET`/`HEAD /api/v1/health` | No organisation eligibility required | Fixed health response, no organisation/member data |
| `POST /api/v1/auth/logout` | No organisation eligibility required | Existing `auth:sanctum` remains mandatory; deletes the current token |
| `GET`/`HEAD /up` | Existing framework health route outside API group | No business data |

Exceptions match exact route URI and HTTP method. A future POST health route or
GET logout route does not inherit an exception. Query/header/body values cannot
select another organisation. Invalid configuration does not disable health or
authenticated logout. Invalid/revoked credentials still receive normal 401.

## Test coverage

`backend/tests/Feature/OrganisationAccessTest.php` covers:

- Active login, assigned session context and protected reads.
- A real login-issued bearer token, followed by a database-only suspension and
  denial on every declared club route/method, including HEAD and mutations.
- Exact suspension JSON without protected data, with no business/finance writes.
- Reused access-service object and stale active Club model.
- Denied login and activation completion/issuance without password, activation,
  audit or token creation side effects.
- Foreign-club 404 precedence while the assigned organisation is suspended.
- Missing, blank, unknown, whitespace, array and numeric configuration; a removed
  configured organisation; retained 503 behavior.
- Health and authenticated logout while suspended and misconfigured; anonymous
  logout denial; method-specific exceptions.
- Default protection of new non-club API routes and actual route middleware
  ordering across all 115 declared API routes.
- Explicit P0.2A limitation: toggling active again does not revoke old tokens;
  existing membership/permission checks still apply and inactive memberships stay
  denied. No operator resumption functionality is introduced.

The existing P0.1 security, authentication, activation, permission and ownership
tests remain unchanged. No historical assertion is weakened to obtain a pass.

## Deferred P0.2B–D work and risks

**HTTP admission is not full suspension safety.** A request can pass this check
and then race with a state change. It may finish its existing transaction after
suspension. No guarantee is made about cancelling already delivered data.

P0.2B requires transaction-level identity/activity checks and reviewed club-first
lock ordering at operational entry points. This includes both payment systems,
charge issue/cancel/close, receipt generation, expense/reversal, transfer,
ordinary receipt/correction, journal posting/reversal, period/account operations,
cutover/bootstrap/evidence/authority operations, integration proposals/configuration
and source accounting. Reads with snapshot semantics need their own compatible
design. Member/import/document/profile and announcement/event/project/reminder
mutations also require race protection. Direct service calls and CLI entry points
are not made eligible by HTTP middleware.

Later P0.2 milestones must deliver audited operator suspension/resumption,
credential/session/activation revocation, login/activation issuance race handling,
and explicit non-restoration of revoked grants. This milestone changes no token
records on denial: an old token can become usable again when the flag is set
active, subject to current permissions. Do not claim irreversible credential
revocation or secure operator lifecycle completion.

Generated signed local-storage GET/PUT routes, public storage links, Sanctum's
CSRF route and static web routes are outside the API group. The application has
no implemented document-file delivery workflow, but unused signed storage routes
remain an identified bypass surface for any files placed there. Their removal or
guarded replacement needs the later approved storage scope. Protected receipt
PDFs delivered by the API are covered here.

No business queued jobs or scheduled delivery are introduced. Future jobs,
schedulers, retries and commands must enforce eligibility at execution and
transaction boundaries; stopping a worker alone is not authorization. Existing
local bootstrap seeders can restore activity/access on rerun and require later
hardening. Infrastructure credentials, cache/storage/temp isolation, browser
state clearing on suspension and restored-backup credential resurrection remain
outside P0.2A.

## Rollback and review gate

This milestone has no migration. In disposable development it can be reverted as
a code change. In any future exposed instance, stop protected traffic/workers
before rolling back to a release without eligibility enforcement. Preserve P0.1,
configuration, keys, audit and financial data; never restore a database or reverse
accounting merely to undo this HTTP change.

## Validation evidence — 6 November 2026

All test execution used process-scoped testing configuration and disposable
databases. No production database was connected to by a test or migration. Safety
checks queried the PostgreSQL maintenance database's catalog for protected database
presence and application-role privileges. No database was dropped.

| Lane | Tests | Passed | Failed | Errors | Skipped | Assertions |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| Final focused/supporting security, in-memory SQLite | 80 | 80 | 0 | 0 | 0 | 1,471 |
| Canonical 3IJAE acceptance, PostgreSQL | 10 | 10 | 0 | 0 | 0 | 288 |
| Canonical 3IJAE genuine concurrency | 1 | 1 | 0 | 0 | 0 | 77 |
| Canonical transactional finance regression | 47 | 47 | 0 | 0 | 0 | 455 |
| Canonical receipt/statement regression | 35 | 35 | 0 | 0 | 0 | 334 |
| Canonical genuine statement snapshot concurrency | 1 | 1 | 0 | 0 | 0 | 11 |
| Additional opening/authority concurrency | 7 | 7 | 0 | 0 | 0 | 219 |
| Additional charge/payment integration concurrency | 4 | 4 | 0 | 0 | 0 | 61 |
| Additional expense/replay concurrency | 3 | 3 | 0 | 0 | 0 | 46 |
| Additional transfer/replay concurrency | 2 | 2 | 0 | 0 | 0 | 26 |
| Additional ordinary receipt concurrency | 1 | 1 | 0 | 0 | 0 | 28 |
| Final expense-reversal concurrency, fresh isolated cluster | 1 | 1 | 0 | 0 | 0 | 27 |

The canonical verifier exited 0: 94 PHP tests, 1,165 assertions, zero
failures/errors/skips. Additional completed PostgreSQL lanes total 17 passed with
zero failures/errors/skips. These groups overlap historical matrix coverage and
must not be added together as disjoint tests.

Frontend: 21 files, 210 tests passed; zero failures/skips. TypeScript and production
build passed. ESLint: zero errors, 87 existing warnings. Locked Composer and both
npm audits passed with zero findings. PHP syntax checks and `git diff --check`
passed. Generated Next type references and build-info were restored/removed;
neither is part of the implementation.

### Complete historical backend matrix

All 261 test files (260 Feature, one Unit) were invoked in separate PHP processes
with SQLite `:memory:` to avoid cross-file database-lifecycle coupling. This is a
complete invocation inventory, not a claim that SQLite validates PostgreSQL races.

Raw result: **2,144 tests; 2,096 passed, 27 failures, nine errors, 12 skips**.

The raw issues were:

- 19 historical worktree scope assertions failed because this milestone changes
  files outside their old milestone-specific allowlists. Affected milestones:
  3DP, 3DR, 3DT, 3DU, 3DV, 3DW, 3EA, 3EB, 3EC, 3ED, 3EE, 3EF, 3EG, 3EH,
  3EI, 3EJ, 3EK, 3EL and 3IIZI. Their test files/assertions remain unchanged.
- Eight PostgreSQL opt-in/engine assertions failed in five files: expense (three),
  expense reversal (one), transfer (two), ordinary receipt (one), correction (one).
  Running these on SQLite cannot constitute concurrency proof.
- Nine correction acceptance cases errored with SQLite `no such table: clubs`
  after that suite's `DatabaseTruncation` lifecycle. All ten cases passed in their
  canonical PostgreSQL lane, without modifying tests.
- 12 explicit PostgreSQL cases skipped: opening/authority (seven), charge/payment
  integration (four) and statement snapshot (one). All 12 passed in PostgreSQL.

Control run: the 25 failing files were rerun with the exact middleware source from
base commit `eee782adde5c37895cb016b300069ac262a78f31` loaded by a temporary bootstrap.
Result: 103 tests, 67 passed, the same 27 failures and nine errors, zero skips.
Comparison by file, test name and issue type found no new failure/error identities.
This is a middleware substitution control in the current worktree, not a claim
that a second complete pristine-main matrix was run.

Clean-commit rerun of the 19 historical worktree-scope files: **85 tests passed,
zero failures/errors/skips**, on implementation commit
`7a78c0d6f85796cfecef160ddfa60f4347286b8a`. This confirms that their initial
failures were caused by the uncommitted worktree diff, without changing an assertion.

The [complete per-file matrix](p0-2a-backend-test-matrix.md) retains every raw
outcome and selects the correct PostgreSQL or clean-commit lane for each file.
Final reconciled coverage is **2,144 unique passing cases, 27,863 assertions,
zero failures/errors/skips and zero blocked cases**. This comprises 2,129
pre-existing historical cases and 15 new P0.2A cases. It is a reconciliation of
selected executions, not a single all-green invocation or a sum of overlapping
groups. The final isolated-cluster verification below resolves the initial block.

### Initial blocked historical lane

`Milestone3iizwPostgresExpenseReversalConcurrencyTest` could not run in its required
PostgreSQL lane: `lcb_3iizw_expense_reversal_concurrency_test` already exists with an
owner different from the non-elevated application role. The runner rejected it
before migration/testing. No ownership or role privileges were changed, and no
test was bypassed. It was correctly recorded as **BLOCKED** at commit
`9228c21ebe8987acee1d08b8e08db77803b260aa`. The raw SQLite failure is retained above;
the isolated-cluster execution below supplies the subsequent passing evidence.

Completed additional lanes used these exact disposable targets:

- `lcb_3ijae_finance_p02a_opening_20261106`
- `lcb_3ijae_finance_p02a_integration_20261106`
- `lcb_3ijab_replay_provenance_hardening_test` (expense and transfer; freshly
  migrated separately for each suite)
- `lcb_3iizz_ordinary_income_receipt_concurrency_test`

Catalog ownership/role checks passed for those targets before execution. Protected
database presence and all five elevated application-role flags were checked before
and after. The canonical verifier separately tested
`lcb_3ijae_finance_p02a_20261106` and
`lcb_3iizt_statement_snapshot_concurrency_test` and retained both.

### Reproduction and retained evidence

Security invocation from `backend`, with process-only `APP_ENV=testing`,
`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` and empty `DB_URL`:

```text
<PHP-8.4.23> vendor/phpunit/phpunit/phpunit
  tests/Feature/OrganisationAccessTest.php
  tests/Feature/InstanceOrganisationTest.php
  tests/Feature/AuthApiTest.php
  tests/Feature/AuthMeRoleAwareResponseTest.php
  tests/Feature/AccountActivationTest.php
  tests/Feature/ClubPermissionServiceTest.php
  tests/Feature/ClubPermissionMiddlewareTest.php
  tests/Feature/MemberSelfProfileAuthorizationTest.php
  tests/Feature/LocalRoleApiPermissionSmokeTest.php
  tests/Feature/Milestone3cxMemberProfileDocumentsReadApiNarrowImplementationTest.php
  tests/Feature/HealthEndpointTest.php
```

Canonical verifier invocation from the repository root:

```powershell
./scripts/verify-finance-release-gate.ps1 `
  -CreateDisposableDatabase -AllowFreshExistingDisposableDatabase `
  -DatabaseAdministratorUsername postgres `
  -DatabaseName lcb_3ijae_finance_p02a_20261106 `
  -PhpPath <installed-PHP-8.4.23> `
  -ReportPath <outside-repository>/lc-p02a-release-gate.json
```

Local raw evidence, intentionally outside Git:

- `%TEMP%/lc-p02a-security-final.xml`
- `%TEMP%/lc-p02a-release-gate.json` and `.log`
- `%TEMP%/lc-p02a-history/`: per-file JUnit/logs, `current-summary.json`,
  `baseline-summary.json`, `postgres-summary.json`, and clean-scope rerun evidence.
- `%TEMP%/lc-p02a-history.ps1` and `lc-p02a-postgres-history.ps1`: temporary
  per-file/isolation runners. They are not application commands or provisioning.

Initial sandboxed PHP execution could not read `vendor/autoload.php`. The same
focused suites were rerun outside that filesystem restriction with explicit
in-memory settings. No assertions were changed to work around the restriction.

## Final PostgreSQL verification of PR #26

Reviewed implementation: `9228c21ebe8987acee1d08b8e08db77803b260aa`, on 6 November
2026. The only follow-up changes are these two validation documentation files.
The PR still contains only the middleware, new access service, new security test
and two supporting documents. Application, finance, test/worker, schema,
configuration, route and bootstrap sources are unchanged from that reviewed head.

Read-only catalog inspection on the original server (`127.0.0.1:5432`, PostgreSQL
17.5) established the precise mismatch: the named disposable database belongs to
`postgres`, while the expected application role is `lc_beenachi_app`. Its
CREATEDB, SUPERUSER, CREATEROLE, REPLICATION and BYPASSRLS flags are all false.
No existing ownership, privileges or contents were changed. Catalog queries used
the maintenance database with read-only session settings; no protected database
was connected to. Original before/after owner, ACL and role snapshots matched.

A separate PostgreSQL 17.5 cluster was initialized in a unique temporary directory,
using generated credentials unrelated to the original server. Its server identity
was verified as:

- System identifier: `7705005952497150312`.
- Loopback listener: `127.0.0.1:55492`.
- Data directory: `%TEMP%/lc-p02a-finalpg-5c13f1e24b9a446bbc2c8232485c3c40/data`.
- Exact test database: `lcb_3iizw_expense_reversal_concurrency_test`.
- Owner and test/worker login: `lc_beenachi_app`, with all five elevated flags false,
  `NOINHERIT` and no role memberships.
- Fresh database inventory: only that test database, `postgres`, `template0` and
  `template1`. Both protected database names were absent from this cluster.

These identity, ownership, role and protected-name checks passed before migration,
immediately before test execution and after the test. New-schema migrations and
the unchanged test ran only as the non-elevated application role. The original
test exercised its three concurrent reversal scenarios and all assertions:
**one test passed, 27 assertions, zero failures/errors/skips**, exit 0.

The original test and concurrency worker SHA-256 hashes were identical before and
after execution:

```text
test:   81A118ABA5B0298643D5DF25AC8FE53D2D0CD6A26D191389914CFF1A24DD10B0
worker: 16458732778C785DBF8356EF41A6A3D5079A669C9AD0F548346DDF0CA0DC8BAF
```

The fresh cluster was stopped successfully, exit 0, and retained for inspection;
generated plaintext credential files were removed. Two earlier temporary harness
attempts stopped before any test: Windows startup-handle behavior and an IP text
format comparison (`127.0.0.1/32` versus `host(inet_server_addr())`). Both clusters
were stopped. No safety guard or test assertion was removed to obtain the pass.

An evidence audit detected a prior temporary JUnit filename collision between
Feature/ExampleTest and Unit/ExampleTest. The Feature example was replayed on
SQLite `:memory:` (one test, one assertion, passed) to retain its distinct case
identity. The per-file matrix now identifies files and namespaces independently.
Selected case identities were checked for duplicates: exactly 2,144 unique cases.

| Retained execution phase | Executions | Passed | Failed | Errors | Skipped | Assertions |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| Primary SQLite per-file matrix | 2,144 | 2,096 | 27 | 9 | 12 | 26,848 |
| Baseline middleware control | 103 | 67 | 27 | 9 | 0 | 1,383 |
| Clean-commit scope rerun | 85 | 85 | 0 | 0 | 0 | 1,615 |
| Canonical finance verifier | 94 | 94 | 0 | 0 | 0 | 1,165 |
| Additional completed PostgreSQL lanes | 17 | 17 | 0 | 0 | 0 | 380 |
| Initial focused security | 28 | 28 | 0 | 0 | 0 | 1,022 |
| Initial supporting security | 79 | 79 | 0 | 0 | 0 | 1,461 |
| Final supporting security | 80 | 80 | 0 | 0 | 0 | 1,471 |
| Feature example audit replay | 1 | 1 | 0 | 0 | 0 | 1 |
| Final isolated expense-reversal concurrency | 1 | 1 | 0 | 0 | 0 | 27 |
| **All retained backend executions, including controls/retries** | **2,632** | **2,548** | **54** | **18** | **12** | **35,373** |
| **Selected unique-case reconciliation** | **2,144** | **2,144** | **0** | **0** | **0** | **27,863** |

The aggregate execution row deliberately preserves failed/errored controls and
SQLite-only skips. It is not a release-pass count. The selected row chooses one
correct-environment execution per case; these rows must not be added together.
Startup/safety checks that never entered PHPUnit contribute zero test executions.

Final local evidence: `%TEMP%/lc-p02a-finalpg-5c13f1e24b9a446bbc2c8232485c3c40/`
contains `evidence.json`, `reversal.xml`, test/migration/start/stop logs;
`%TEMP%/lc-p02a-final-reconciliation.json` contains every selected file, case counts,
assertions and source lane. The temporary verification and reconciliation runners
are `%TEMP%/lc-p02a-final-postgres.ps1` and `lc-p02a-reconcile.py`. No credentials
are included in documentation or retained JSON evidence.

## GO / NO-GO

**Pre-merge GO for the narrow P0.2A HTTP implementation:** focused security,
canonical finance, all historical concurrency lanes and the selected complete
backend reconciliation pass. No implementation expansion is needed. Keep the PR
draft pending user review and separate explicit merge approval.

**NO-GO for full suspension safety, pilot deployment or finance cutover.** All
deferred P0.2B–D risks above remain. No operator lifecycle, credential revocation
or transaction-level suspension guarantee is supplied by this PR.
