# P0.2B2-C: package-owned mutations and authority administration

Baseline: clean `main` at `1d76eadd9de0aeb546871c916d247b7bd72ffe5e`.
Branch: `codex/p0-2b2-c-package-authority-enforcement`.
Delivery is draft only. Merge is NO-GO until mixed opening/revocation safety is
resolved under separately approved D/E work. C does not establish complete cutover
suspension safety.

## Application scope and security contracts

Only two application files change:

- `backend/app/Services/Finance/ClubAccountingCutoverPackageService.php`:
  `replaceSchedules`, `replaceEvidenceReferences` (through private `mutate`) and
  `refreshValidation` now use the existing B1 guard as their outer boundary with
  `attempts: 1`. Account-dependent schedule checks execute inside the boundary,
  before child replacement. Package predicates include verified club before locking.
- `backend/app/Services/Finance/ClubCutoverAuthorityAdministrationService.php`:
  all five request methods, approval and two self-revocation methods enter guarded
  roots with `attempts: 1`. Requests that previously autocommitted now have one
  atomic root. A private package helper starts no nested guard. Private persistence
  and application helpers require a verified transaction context. Package, operation
  and authority target predicates use verified organisation before resource locks.

Original sequence: package mutations locked package first; administration approval
locked operation then package; self-revocation locked package then target; requests
had no explicit transaction. New sequence: verified club FOR UPDATE first, then
the original scoped resource sequence. Applied approval replay remains before
pending-operation package/version/actor checks, but after club eligibility and
scoped operation locking. There is no implicit adoption of an ambient transaction.

Request digests retain supplied package version/lock version. Canonical package
reloads govern ownership and authority checks, not silent semantic rebinding.
Exact stale replay remains exact; replay with a refreshed version conflicts. A new
request supplied with stale versions retains those versions and cannot pass pending
approval's existing current-version check. Reference lookup is organisation-scoped;
a foreign globally-used reference is rejected as a semantic conflict without
returning the foreign operation.

Package version increments, checksum content/order, validation, draft-only editing,
invalidation events and in-transaction audit remain unchanged. Administration
role/subject separation, distinct approving roles/actors, eligibility metadata,
currentness, consumption and immutable provenance remain in place. C adds no new
requester governance rule and does not change retained approval policy. Suspended
self-revocation is rejected too; there is no audit or fail-safe bypass.

`recordAccountingReview` and `recordClubApproval` remain deferred lifecycle facades.
Read/calculation APIs remain usable by existing lifecycle/opening callers without
recursively entering a guard. Disabled legacy APIs remain disabled. B1, bootstrap,
authority resolver, lifecycle, evidence verification, opening execution, journal
posting, models, schemas, migrations and database triggers are not modified.

## Test environment and concurrency gate

Use the existing fresh-cluster runner:

```powershell
./scripts/verify-cutover-bootstrap-postgres.ps1 -Suite C
./scripts/verify-cutover-bootstrap-postgres.ps1 -Suite C -COnly
./scripts/verify-cutover-bootstrap-postgres.ps1 -Suite C -CRegressionOnly
./scripts/verify-cutover-bootstrap-postgres.ps1 -Suite Guard
./scripts/verify-cutover-bootstrap-postgres.ps1 -Suite Finance
```

Run sequentially. The C suite uses exactly `lcb_p02b2_cutover_test` and the expected
non-elevated `lc_beenachi_app` owner, no role memberships, explicit instance identity
and PostgreSQL root transactions. The existing fresh-directory/server/system-id,
loopback, inventory, protected-name, role/ownership and shutdown checks remain.
No production credentials or existing server are accessed. No security check is
disabled. Test-only suspension writers are restricted to the disposable database.

`-COnly` is focused evidence only; `-CRegressionOnly` executes historical/mixed
groups without recounting separately executed focused/bootstrap cases. Full C runs
all groups, including the seven original concurrency cases plus the new deterministic
mixed-order safety gate. Mixed-concurrency failure recording retains original JUnit failures and a
non-zero overall result; it never turns a failed assertion into a pass. Omitted,
skipped or blocked cases are not counted as passed.

The historical revocation worker formerly opened an ambient package-first
transaction. Its C paths must now enter services at depth zero. Barriers observe
the first lock actually acquired by each public operation; they do not add an
outer transaction or change retries. Synthetic instance codes are passed explicitly
to workers. Original winner/loser and provenance assertions remain unchanged.

Opening retains package-first locking, later requesting the club through journal
posting. C revocation owns club and then requests package. This permits a real
cycle: opening owns package/waits club; revocation owns club/waits package. There
is no approved fix in C. In particular, do not give opening a test-only club-first
wrapper, suppress its failure, broaden C retries or weaken a historical assertion.

## Validation evidence

Executed C results:

| Group | Executed | Passed | Failures | Errors | Skips | Assertions |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| Focused C PostgreSQL | 58 | 58 | 0 | 0 | 0 | 621 |
| Unchanged bootstrap PostgreSQL | 14 | 14 | 0 | 0 | 0 | 169 |
| Historical cutover PostgreSQL | 63 | 63 | 0 | 0 | 0 | 703 |
| Original PostgreSQL concurrency plus deterministic gate | 8 | 7 | 1 | 0 | 0 | 270 |
| Supporting HTTP/B1 boundary, SQLite memory | 87 | 87 | 0 | 0 | 0 | 1,491 |
| Unchanged B1 PostgreSQL guard | 34 | 34 | 0 | 0 | 0 | 313 |
| Canonical finance PostgreSQL backend groups | 94 | 94 | 0 | 0 | 0 | 1,165 |

Rows are suite executions and can overlap; no aggregate unique-test total is claimed.

The focused suite was executed twice with the same 58/621 passing result. It covers
all eleven supported public mutation paths with active/suspended/foreign/missing
configuration, scoped lock traces at depth one, supplied-version replay, applied
replay after suspension, reactivation without restoring grants, ambient rejection,
forged scope, audit/target rollback, one-attempt real SQLSTATE 40001, both suspension
orders, both authoring/revocation orders, duplicate requests and distinct approvals.
Concurrent C workers prove waits through PostgreSQL statistics/blocking PIDs rather
than accepting a sleep as evidence.

An initial historical run executed 63 cases: 62 passed, zero failures, one error,
zero skips, 700 assertions. Its foreign-club case expected the old generic exception.
Only that expectation was strengthened to exact B1 `ORGANISATION_FOREIGN` / 404;
the stale-lock and no-mutation checks remain. The subsequent 63/703 run passed.
The initial run did not reach mixed concurrency; it is not counted as mixed proof.

All seven original concurrency cases passed with their existing winner/loser and
provenance assertions, including ten identical and ten changed opening races.
The eighth, added safety gate failed and remains failed. The complete mixed group
and runner exit non-zero, with `FAILED_OR_BLOCKED` and
`mixed_order_deadlock_observed: true`. Blocked gate: coordinated opening/revocation
lock ordering. No executed test is relabelled as skipped or passed.

### Actual PostgreSQL deadlock evidence

The deterministic test pauses opening only after its public transaction acquires
package; pauses revocation only after its public guard acquires club; releases
revocation and observes it waiting for package while retaining club; then releases
opening. No test-owned wrapper transaction, retry adjustment or guard bypass exists.

PostgreSQL reported SQLSTATE `40P01`, with backend 25848 waiting for transaction
22243 held by backend 3288, and 3288 waiting for transaction 22244 held by 25848.
The server identified the competing statements:

```sql
-- C revocation owns club, requests package:
select * from "club_accounting_cutover_packages"
where "club_accounting_cutover_packages"."id" = $1 and "club_id" = $2
limit 1 for update;
-- Unchanged opening/journal path owns package, requests club:
select * from "clubs" where "clubs"."id" = $1 limit 1 for update;
```

Revocation was the deadlock victim and rolled back; opening committed. Reconciliation:
one posted opening journal, one execution success, one authority consumption,
zero revocation events, package `posted`. This particular run preserved financial
atomicity but did not prove safe ordering or guarantee future victim selection.
The no-deadlock assertion fails even though final winner/provenance happens to match
the old activation-first outcome. The failure must not be removed or suppressed.

Local C evidence under `%TEMP%`:

- `lc-p02b2-c-8143427fe4e247a2a17e4e16b1e632c4/`: first focused pass.
- `lc-p02b2-c-b8c067730f03407a9b8bd9b5640dd6e8/`: second focused/bootstrap passes
  and initial historical exception-classification error.
- `lc-p02b2-c-ff9e9d24c0ed450e8a02e174a734e05c/`: corrected historical pass,
  mixed JUnit failure, `server.log` deadlock graph, SQL traces, reconciliation and
  fresh-cluster identity/role/ownership evidence before/after. Shutdown exit zero.
- `lc-p02b2c-security.xml`: supporting security result.

The C suites used exact allowlisted disposable databases, non-elevated owner and
zero role memberships. No source changed during execution. No original PostgreSQL
server was accessed, and completed C clusters stopped successfully.

B1 and canonical finance reruns passed with shutdown exit zero and unchanged-source
checks. Finance breakdown: acceptance 10/288 assertions, receipt-correction
concurrency 1/77, transactional 47/455, receipts/statements 35/334, read-only snapshot
concurrency 1/11. Additional local evidence under `%TEMP%`:

- `lc-p02b2-guard-86fc05bfcb6d46a9bafa973324a8e2e2/`.
- `lc-p02b2-finance-0d115f509a5449b28204846950169991/`.

All requested groups have executed. The unresolved blocker is the mixed-order merge
gate, not an unexecuted/skipped case. PowerShell parser and Git whitespace checks
passed. The initial historical exception-classification error is superseded by the
passing exact-404 run; the new mixed-order failure is unresolved and retained.

Local PostgreSQL evidence is distinct from GitHub CI; the existing GitHub workflow
does not execute these database suites. No complete historical backend matrix or
full frontend/dependency-audit finance verifier is claimed.

## Rollback and decisions

Stop affected/mixed-version writers before reverting C code and its test-harness
changes together. Preserve B1 and A/B. Retain packages, authority grants, approvals,
events, audits, consumed authority and journals; no database rollback or migration
is required. Rollback removes C eligibility controls, so keep affected mutations
unavailable pending review rather than exposing the previous direct-call behavior.

Implementation GO: the approved C boundaries are implemented and the local C-only
security/rollback/ordering contracts pass. This means retain the implementation for
draft review, not that the combined application is safe to release. Merge NO-GO:
the real `40P01` cycle requires separately approved D/E coordination. No attempted
fix or operational workaround is included. Passing C-only security/finance tests
cannot override the failed mixed-order gate. Do not mark Ready
for Review, merge, deploy or implement D/E/F or B3–B8 under this approval.
