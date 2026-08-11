# Milestone 3IJAF — Finance Release-Gate Consolidation and Dependency Security Assessment

## Scope and conclusion

3IJAF consolidates verification controls only. It adds no accounting workflow and changes no accounting semantics, persistence, route, permission, posting, reversal, mapping, or cutover behavior.

The milestone reconciles the already-completed 3IJAE PostgreSQL proof, establishes a repository-owned disposable-PostgreSQL verifier, normalizes test isolation and concurrency opt-ins, assesses locked dependencies, and creates an explicit finance release checklist.

## 3IJAE status reconciliation

The 3IJAE milestone record documents successful dedicated acceptance and genuine PostgreSQL concurrency proof on 11 August 2026. The source-mapping and decision-traceability registers retained earlier pre-verification wording. 3IJAF corrects those register descriptions to `implemented; backend and PostgreSQL proof complete` without changing the 3IJAE implementation or expanding its source boundary. Refund/payment-out remains unsupported.

## Canonical verifier

`scripts/verify-finance-release-gate.ps1` is the repository-owned entry point, exposed as `npm run verify:finance-release-gate`. It records repository identity, audits locked dependencies, runs frontend gates, and—unless explicitly skipped—requires an exact `lcb_3ijae_finance_...` PostgreSQL target before migrations or backend tests.

Database creation is separately opted in. Protected database names are rejected and positively reverified, credentials are never printed, every changed environment variable is restored, concurrency flags are process-scoped, and the verifier never drops its targets. JSON reports default outside the repository, and repository-local report paths are rejected.

## Dependency-security assessment — 12 August 2026

### Root npm lockfile

The earlier 12 August scan reported 22 affected dependency nodes (20 high and 2 moderate) because npm expanded advisory effects through the tooling graph. The final corrected canonical `npm audit --json` run against the unchanged `package-lock.json` returned exit code 1 with three high-severity vulnerable package findings and no critical, moderate, low, or information findings. The current machine-readable findings are:

- `brace-expansion` denial of service, transitively reachable through ESLint tooling;
- `js-yaml` quadratic CPU consumption, transitively reachable through ESLint configuration;
- `undici` response/cache/header parsing issues, transitively reachable through `jsdom` and Vitest.

All three current findings are transitive (`isDirect=false`) and resolve only within development-tool paths in the committed lockfile. The lockfile reports 18 production dependencies and 468 development dependencies, and no vulnerable production package node was identified. The application does not expose ESLint, Vitest, or jsdom as a production request handler, so this is classified as development-tool availability/confidentiality risk rather than demonstrated finance-runtime reachability. Gate disposition: **FAIL / remediation required before an unqualified release**, with production runtime exposure currently assessed as not demonstrated. No automatic fix or lockfile change was made.

### Backend npm manifest

`npm audit --json` returned `ENOLOCK`: `backend/package.json` has no committed `package-lock.json`. Its Vite/Tailwind development toolchain therefore has no reproducible locked audit surface. Gate disposition: **BLOCKED**. Creating or updating that lockfile is dependency-management work outside this milestone.

### Composer lockfile

`composer audit --locked --format=json` completed with PHP 8.4.23 and Composer 2.10.1 and returned exit code 1: 23 advisories affecting four production/runtime packages (5 high, 16 medium, 2 low; 0 critical; no abandoned packages).

| Locked package | Locked version | Advisory classification | Minimum version outside every reported affected range | Finance/runtime assessment |
|---|---:|---|---:|---|
| `dompdf/dompdf` | 3.1.5 | 4 medium file/resource issues; 2 low file-oracle/chroot issues | 3.1.6 | Runtime PDF dependency used by receipt generation; crafted SVG/image/font content is not an accepted finance API input, but runtime presence and file-read/resource risk require remediation. |
| `guzzlehttp/guzzle` | 7.11.0 | 1 high host-validation issue; 8 medium cookie, redirect, proxy, and resource issues | 7.15.2 | Runtime transitive HTTP client. No direct finance-service Guzzle call was found, but host/cookie/proxy behavior is runtime-capable and cannot receive a dev-only exception. |
| `guzzlehttp/psr7` | 2.11.0 | 2 medium host-validation/start-line issues | 2.12.3 | Runtime transitive HTTP-message implementation; no direct finance call was found, but affected parsing/serialization remains installed. |
| `league/commonmark` | 2.8.2 | 4 high denial-of-service issues; 2 medium unsafe-link/XML issues | 2.9.0 | Runtime Laravel dependency. No finance path that accepts Markdown was found; reachability is not demonstrated, but high-severity runtime advisories remain release-blocking. |

The “minimum version” column restates the audit’s affected ranges; compatibility and lock resolution still require a separate dependency-remediation milestone. Gate disposition: **FAIL / runtime dependency remediation required**. No Composer update or lockfile change was made. A launcher failure caused by PHP being unavailable on the invoking process `PATH` was resolved by using the installed PHP path and was not classified as an advisory result.

## Release decision

The release decision remains **NO-GO** while any required security or verification gate is `FAIL` or `BLOCKED`. This status does not invalidate the historical 3IJAE PostgreSQL proof and does not claim a defect in accounting semantics.

## Verification record

Canonical run on 12 August 2026:

```text
powershell -NoProfile -ExecutionPolicy Bypass \
  -File scripts/verify-finance-release-gate.ps1 \
  -CreateDisposableDatabase \
  -AllowFreshExistingDisposableDatabase \
  -DatabaseAdministratorUsername postgres \
  -DatabaseName lcb_3ijae_finance_20260812_0315 \
  -PhpPath <installed PHP 8.4.23 path> \
  -ReportPath <local temporary report path>
```

The local PostgreSQL administrator created the disposable targets with the configured non-privileged application role as owner. The corrected rerun explicitly refreshed the already authorized primary target and separately used exact `lcb_3iizt_statement_snapshot_concurrency_test`. The verifier did not drop either database; both are preserved for review. PostgreSQL client and server were 17.5. Before and after testing, both protected databases existed and the application role remained `CREATEDB=false`, `SUPERUSER=false`, `CREATEROLE=false`, `REPLICATION=false`, and `BYPASSRLS=false`.

| Gate | Result | Evidence |
|---|---|---|
| Diff and repository identity | PASS | `git diff --check`; requested branch at base `d9f3fd8d96f4c71c241077bc9bb15c91e6b82ea7` |
| Composer/npm manifest validation | PASS | Composer strict validation and root npm manifest parse |
| Composer locked audit | FAIL | 23 advisories across 4 runtime packages |
| Root npm locked audit | FAIL | final structured run: 3 high transitive dev-tool packages; earlier expanded scan: 22 affected nodes |
| Backend npm locked audit | BLOCKED | `backend/package-lock.json` absent |
| Frontend test | PASS | 20 files, 198 tests |
| TypeScript | PASS | `tsc --noEmit` |
| Frontend lint | PASS WITH EXISTING WARNINGS | 0 errors, 87 pre-existing warnings; no changed frontend source |
| Production build | PASS | Next.js 16.3.0 optimized build and static generation |
| PostgreSQL migration | PASS | all migrations through 3IJAE applied on the exact disposable target |
| 3IJAE acceptance | PASS | 10 tests, 288 assertions |
| 3IJAE PostgreSQL concurrency | PASS | 1 test, 77 assertions; explicit `RUN_3IJAE_POSTGRES_CONCURRENCY=1` |
| Transactional finance regression | PASS | 47 tests, 455 assertions; 0 failed/errors/skipped |
| Non-transactional receipt/statement regression | PASS | 35 tests, 334 assertions; 0 failed/errors/skipped |
| 3IIZT PostgreSQL snapshot concurrency | PASS | 1 test, 11 assertions, 0 skipped; explicit `RUN_3IIZT_POSTGRES_CONCURRENCY=1` on its exact database |
| PostgreSQL safety before/after | PASS | both protected databases intact; both disposable targets application-role-owned; all five privilege flags false |
| Release decision | NO-GO | two failed security gates and one blocked reproducibility gate |

An additional default-SQLite invocation of the 3IJAE acceptance file produced one pass followed by nine `no such table: clubs` errors. It is not release evidence and confirms why the suite is classified as PostgreSQL/non-transactional in the isolation contract; the canonical PostgreSQL run supersedes it with 10/10 passing.

PowerShell parsing and `git diff --check` passed. A repository-local report path was rejected with no file created. Sentinel probes proved exact restoration of all modified environment variables on both normal return and forced failure. Lint was classified `PASS WITH EXISTING WARNINGS` with 0 errors and 87 warnings.

The original machine-readable report remains `C:\Users\dell\AppData\Local\Temp\finance-release-gate-report.json`. The corrected structured report is `C:\Users\dell\AppData\Local\Temp\finance-release-gate-corrected-report.json`; it includes audit packages/severities, backend `ENOLOCK`, PHPUnit totals/assertions/skips, lint totals, database identities, versions, protected-database proofs, and role flags, and contains no credentials. No commit, push, pull request, merge, deployment, accounting activation, or professional approval is authorized by this document.
