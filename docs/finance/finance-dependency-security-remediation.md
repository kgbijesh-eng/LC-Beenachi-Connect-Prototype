# Finance dependency security remediation

Baseline is main at `bc0150ebcb7fb3d954ae78e87aba247e049b8663`, the merge of PR #22. The scope is dependency locks and release verification. The manifests and the canonical verifier retain their existing contracts.

## Locked updates

| Composer runtime package | Before | After |
|---|---:|---:|
| `dompdf/dompdf` | 3.1.5 | 3.1.6 |
| `guzzlehttp/guzzle` | 7.11.0 | 7.16.1 |
| `guzzlehttp/promises` | 2.5.0 | 2.6.0 |
| `guzzlehttp/psr7` | 2.11.0 | 2.13.2 |
| `league/commonmark` | 2.8.2 | 2.10.7 |

Composer resolved the four documented packages in their existing major versions with security blocking enabled. Guzzle's selected release required the Promises update. The current audit reports zero advisories for those five packages.

The root lockfile was resolved with `npm audit fix --package-lock-only --ignore-scripts`, within the existing manifest ranges. The documented findings for `brace-expansion`, `js-yaml`, and `undici` cleared. Against the same feed, the original root lockfile had 18 affected packages, including a critical Next.js finding and high findings in Sharp and `source-map-js`. The compatible audit fix therefore also updated runtime packages, including Next.js 16.3.0 to 16.4.5 and Sharp 0.35.3 to 0.35.5.

The backend lockfile was generated with `npm install --package-lock-only --ignore-scripts`. Backend `npm ci` and `npm run build` passed. Vite emitted an optional Fontaine dependency warning. The backend npm audit completed with zero findings.

## Residual advisories before the Composer follow-up on 5 November 2026

Root `npm audit --omit=dev --json` reported zero findings. The full root audit reports eight moderate package findings stemming from one underlying advisory, [GHSA-g3hv-75wg-m249](https://github.com/advisories/GHSA-g3hv-75wg-m249). The locked `minimatch` 10.2.6 is vulnerable to denial of service through glob-part amplification. The other seven entries are propagated through `@typescript-eslint/typescript-estree`, `@typescript-eslint/parser`, `@typescript-eslint/eslint-plugin`, `@typescript-eslint/type-utils`, `@typescript-eslint/utils`, `typescript-eslint`, and `eslint-config-next`. The affected path runs in lint tooling and presents an availability risk when processing adversarial glob patterns. npm's forced fix would downgrade `eslint-config-next` to 15.5.29 across a major boundary. This milestone retains the compatible 16.x tooling and records the finding as an open gate failure.

The current Composer feed reports 12 additional findings in six runtime packages outside the four-package remediation. The locked versions are Laravel Framework 13.14.0, Flysystem 3.34.0, and Symfony HttpFoundation, HttpKernel, Mime, and Routing 8.1.0. The classifications describe the affected behavior and prerequisites. Finance-specific exploitability was not established. Composer reports no abandoned packages.

| Runtime package | Advisory | Feed severity | Exposure classification |
|---|---|---|---|
| `laravel/framework` | [CVE-2026-114509](https://github.com/advisories/GHSA-f9x3-7vq9-cph6) | Critical | Authentication bypass through non-string credentials in Laravel TokenGuard. |
| `laravel/framework` | [CVE-2026-114508](https://github.com/advisories/GHSA-5wcm-3v2w-8362) | Medium | Unauthorized data destruction through affected relationship inclusion behavior. |
| `laravel/framework` | [CVE-2026-102279](https://github.com/advisories/GHSA-jh5r-qr3c-85q8) | Low | Cross-site scripting when the affected debug-page information is exposed. |
| `league/flysystem` | [CVE-2026-102601](https://github.com/advisories/GHSA-cxf4-7mrp-vvpr) | Low | Malformed UTF-8 paths bypass the control-character check in the shared storage path normalizer. |
| `symfony/http-foundation` | [CVE-2026-118514](https://symfony.com/cve-2026-118514) | Unrated | HEAD requests can bypass affected Symfony method-based access rules. |
| `symfony/http-foundation` | [GHSA-3h27-hjv7-fqjx](https://github.com/symfony/symfony/security/advisories/GHSA-3h27-hjv7-fqjx) | Unrated | Appending a path to the PHP script name can bypass front-of-PHP path rules. |
| `symfony/http-foundation` | [GHSA-xg2q-4rrq-657q](https://github.com/symfony/symfony/security/advisories/GHSA-xg2q-4rrq-657q) | Unrated | `private_ranges` proxy trust can accept client-controlled address representations as proxy addresses. |
| `symfony/http-foundation` | [GHSA-5mx8-mwrp-5693](https://github.com/symfony/symfony/security/advisories/GHSA-5mx8-mwrp-5693) | Unrated | Client-controlled host can influence URL generation. |
| `symfony/http-foundation` | [CVE-2026-108082](https://symfony.com/cve-2026-108082) | High | Denial of service through regex backtracking in request `Cache-Control` parsing. |
| `symfony/http-kernel` | [GHSA-wg5q-m2ww-f3c9](https://github.com/symfony/symfony/security/advisories/GHSA-wg5q-m2ww-f3c9) | Unrated | Forwarded headers can influence URL generation when using inline fragments. |
| `symfony/mime` | [CVE-2026-45070](https://github.com/advisories/GHSA-vqc8-7275-q272) | Medium | Email header injection through affected MIME parameter names. |
| `symfony/routing` | [CVE-2026-118516](https://symfony.com/cve-2026-118516) | Unrated | Compiled host-placeholder routes can bypass path-based access control. |

## Initial verification and release decision

The canonical verifier ran on PostgreSQL 17.5 using `lcb_3ijae_finance_20261105_security` and `lcb_3iizt_statement_snapshot_concurrency_test`. Both disposable targets were retained. Before and after the run, the verifier confirmed both protected databases were present and the application role had none of the five elevated privilege flags.

| Check | Result |
|---|---|
| Repository diff, strict Composer manifest, locked PHP platform requirements, root npm manifest | Passed |
| Root and backend `npm ci`, backend Vite build | Passed |
| Frontend Vitest, TypeScript, Next.js production build | Passed, with 198 tests across 20 files |
| Frontend ESLint | Passed with 87 existing warnings and zero errors |
| Migrations on both disposable PostgreSQL targets | Passed |
| 3IJAE acceptance and PostgreSQL concurrency | 11 tests and 365 assertions passed |
| Transactional finance regression | 47 tests and 455 assertions passed |
| Receipt and statement regression | 35 tests and 334 assertions passed |
| 3IIZT PostgreSQL snapshot concurrency | 1 test and 11 assertions passed |
| Backend npm locked audit | Passed with zero findings |
| Composer and root npm locked audits | Failed on the residual advisories above |

All 94 canonical PHP tests passed with 1,165 assertions and zero skips. Composer installation emitted PSR-4 warnings for existing test classes outside the canonical groups. The verifier exited `1` because the Composer and root npm audits failed. The release decision remains **NO-GO**. The machine-readable run report is in the local temporary directory as `finance-dependency-security-20261105.json`.

## Approved Composer follow-up

The exact-version dry run and applied resolution each selected six version updates, with zero package additions or removals. Composer also refreshed the source and distribution URLs for `vlucas/phpdotenv` at the same version and commit reference. The original URLs were restored, and the final lockfile passed strict validation, audit, and the install dry run.

| Package | Previous lock | Updated lock |
|---|---:|---:|
| `laravel/framework` | 13.14.0 | 13.30.0 |
| `league/flysystem` | 3.34.0 | 3.35.3 |
| `symfony/http-foundation` | 8.1.0 | 8.1.11 |
| `symfony/http-kernel` | 8.1.0 | 8.1.11 |
| `symfony/mime` | 8.1.0 | 8.1.10 |
| `symfony/routing` | 8.1.0 | 8.1.11 |

Strict Composer validation and the locked audit passed. All 12 advisories listed above cleared. Locked installation succeeded with plugins and scripts disabled and emitted the existing PSR-4 warnings. Full and production-only locked platform checks passed on PHP 8.4.23. The selected Symfony releases require PHP 8.4.1 or newer, the same floor as the preceding Symfony 8.1 lock. The repository does not identify a PHP version for the intended deployment host. Confirm the host version and run `composer check-platform-reqs --lock --no-dev` in its deployment runtime before release.

The first canonical invocation reached a clean Composer audit and stopped because the verifier treated the empty JSON array as a package map. The verifier now recognizes the empty array and still uses the Composer exit code to determine audit status. The three npm files were verified byte-for-byte unchanged during the follow-up.

### Follow-up verification and release decision

| Check | Result |
|---|---|
| Focused SQLite authentication, Sanctum, club permissions, cross-club isolation, receipts and documents | 81 tests, 79 passed, 2 failed, 1,024 assertions, 0 skips |
| Pre-update replay of the two focused failures | 2 tests, 2 failed with the same assertions, 21 assertions, 0 skips |
| Frontend tests | 198 passed across 20 files, 0 skips |
| Frontend type check and production build | Passed |
| Frontend lint | 0 errors, 87 existing warnings |
| PostgreSQL 3IJAE acceptance | 10 passed, 288 assertions, 0 skips |
| PostgreSQL 3IJAE concurrency | 1 passed, 77 assertions, 0 skips |
| PostgreSQL transactional finance regression | 47 passed, 455 assertions, 0 skips |
| PostgreSQL receipt and statement regression | 35 passed, 334 assertions, 0 skips |
| PostgreSQL 3IIZT snapshot concurrency | 1 passed, 11 assertions, 0 skips |
| Locked Composer audit | 0 advisories and 0 abandoned packages |
| Backend npm locked audit and root production-only npm audit | 0 findings in each |
| Full root npm locked audit | 8 moderate entries from the single development-only `minimatch` advisory |

The two focused failures were reproduced in an isolated checkout with the pre-update dependency versions. `AuthMeRoleAwareResponseTest` expects an older permission list that omits expense, ordinary-income receipt, and transfer permissions. `Milestone3vPaymentReceiptGenerationValidationAndClosureTest` expects a route list that omits the ordinary-income receipt routes. The follow-up leaves those tests and the application contracts as they were.

The canonical run created `lcb_3ijae_finance_security_followup` and freshly migrated it and the designated disposable `lcb_3iizt_statement_snapshot_concurrency_test`. It retained both databases and passed all PostgreSQL safety checks. All 94 canonical backend tests passed with 1,165 assertions and zero skips. The canonical result is **NO-GO solely because of the full root npm audit**. The external report is `finance-security-followup-final.json` in the local temporary directory.

## Final canonical release verification — 5 November 2026

The approved follow-up locked Laravel 13.30.0, Flysystem 3.35.3, Symfony HttpFoundation 8.1.11, HttpKernel 8.1.11, Mime 8.1.10, and Routing 8.1.11. All 12 follow-up Composer advisories cleared.

The root npm override pins `minimatch` 11.0.1 only beneath `@typescript-eslint/typescript-estree` 8.72.1 and adds its helper `@isaacs/nonrecursive-glob-match` 1.0.3. ESLint remains at 9.39.5. The override crosses the parent's declared `^10.2.2` range. Continue testing ESLint and TypeScript project matching when updating either package.

| Check | Final result |
|---|---|
| Full root npm audit | Zero findings |
| Production-only root npm audit | Zero findings |
| Backend npm audit | Zero findings |
| Locked Composer audit | Zero advisories and zero abandoned packages |
| Frontend | 198 tests passed. Type checking and the production build passed. ESLint reported 87 existing warnings and zero errors. |
| Disposable PostgreSQL | 94 tests passed, 1,165 assertions, zero skips. Both 3IJAE and 3IIZT concurrency groups passed. |
| Canonical verifier | PASS, exit code `0` |

The final remediation run is recorded in `C:\Users\dell\AppData\Local\Temp\finance-security-final-minimatch.json`.

The two separate historical focused-test failures remain outside the canonical suite. `AuthMeRoleAwareResponseTest` expects an older permission list, and `Milestone3vPaymentReceiptGenerationValidationAndClosureTest` expects an older receipt route list. Both assertions failed identically on the pre-update baseline and after the dependency changes.

The production PHP runtime remains unverified. The lock requires PHP 8.4.1 or newer and passed the local production platform check on PHP 8.4.23.

The final patch review correction enforces strict JSON and validates the Composer abandoned-package result as an empty array or a package map with a null or valid replacement name. All 26 Windows PowerShell probes passed. A nonempty abandoned array containing `null` was rejected even when the simulated Composer command exited `0`. A valid nonempty advisory map still failed the gate.

The corrected verifier reran all canonical checks using the newly created `lcb_3ijae_finance_security_corrected` database and the freshly migrated disposable snapshot target. It exited `0` with the audit and test results above. Both disposable databases were retained. The corrected-run report is `C:\Users\dell\AppData\Local\Temp\finance-security-final-corrected.json`.
