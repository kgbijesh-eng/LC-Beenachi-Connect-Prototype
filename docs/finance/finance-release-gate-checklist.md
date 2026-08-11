# Finance Release-Gate Checklist

This checklist is the canonical finance pre-merge gate. It authorizes no production accounting activation, cutover, deployment, or professional approval. Each item must be recorded as `PASS`, `FAIL`, `BLOCKED`, or `NOT APPLICABLE`; a blank item and a skipped test are not passes.

## Scope and repository identity

- [ ] Branch, full SHA, reviewer scope, and complete worktree status recorded.
- [ ] Diff contains no unapproved accounting workflow, posting rule, mapping, journal, migration, permission, or API semantic change.
- [ ] `git diff --check` passes and generated credentials, dumps, reports, and real finance data are absent from Git.

## Dependency security

- [ ] `composer audit --locked` executed against `backend/composer.lock`; advisories and abandoned packages classified.
- [ ] Root `npm audit` executed against `package-lock.json`; direct/transitive and runtime/dev exposure classified.
- [ ] Every additional npm manifest has a committed lockfile or is explicitly `BLOCKED` as non-reproducible.
- [ ] Critical/high runtime findings have an approved remediation or the release is `NO-GO`.
- [ ] Dev-only findings record reachability and compensating controls; audit exit codes are never suppressed.
- [ ] No `audit fix`, forced upgrade, lockfile rewrite, or advisory ignore occurs without separate review.

## Static and frontend verification

- [ ] Changed PowerShell parses and its non-destructive path is exercised.
- [ ] Repository-local report paths are rejected and success/failure environment-restoration probes pass with sentinel values.
- [ ] Composer and npm manifests parse; lockfiles remain unchanged unless dependency remediation is in scope.
- [ ] Frontend tests, TypeScript, lint, and production build results recorded separately.
- [ ] Existing warnings or unrelated failures are counted and classified, not described as a pass.

## Backend and database verification

- [ ] PHP runtime, Composer, PostgreSQL client/server, branch, and SHA recorded.
- [ ] Exact disposable database and protected databases verified before `migrate:fresh` or tests.
- [ ] PostgreSQL client/server versions and non-privileged application-role flags are recorded before and after tests.
- [ ] Forward migration and required-schema evidence pass on PostgreSQL.
- [ ] Each test group uses the isolation class in `finance-test-isolation-and-concurrency-contract.md`.
- [ ] Focused acceptance and affected regression suites pass with counts.
- [ ] Every applicable concurrency lane has its exact `RUN_*` opt-in and passes without skips.
- [ ] Failures are preserved as findings; incompatible suites are rerun separately rather than masked.
- [ ] Disposable target is retained for review or removed only by a separately reviewed exact-name cleanup.

## Decision

- [ ] Evidence report identifies every command, result, environment blocker, advisory disposition, and residual risk.
- [ ] All required gates are `PASS`; any `FAIL` or `BLOCKED` yields `NO-GO` unless an authorized reviewer explicitly accepts the documented exception.
- [ ] Reviewer confirms no accounting semantics changed.
- [ ] Commit, push, PR, merge, deployment, and production activation remain separate decisions.
