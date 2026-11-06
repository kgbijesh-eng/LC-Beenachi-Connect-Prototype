# P0.2B1 — Security contracts and transaction guard

Base: clean `main` at `55af1561816622c113833b97a013fe5e96058d57`.
Branch: `codex/p0-2b1-organisation-transaction-guard`.
Status: draft implementation for review; no B2–B8 service adoption or deployment.

## Scope

B1 adds three Access classes and two test classes. Existing P0.1 identity resolution,
P0.2A HTTP middleware/access checks, finance/cutover services, transaction APIs,
models, migrations, configuration, routes and bootstrap remain unchanged. No
permission, journal, money, idempotency, credential-revocation or operator-command
behavior is changed. There is no new application service-provider binding or
transaction-event listener.

The separate-instance architecture remains mandatory: separate databases,
credentials, configuration, keys and storage for each organisation. A transaction
context cannot select another organisation or another connection.

## Approved contracts

- Reads use fresh eligibility at admission. An admitted bounded read may finish
  after suspension; subsequent reads must be admitted again. The existing statement
  `REPEATABLE READ READ ONLY` and concurrent-posting behavior remain unchanged.
- A mutation must acquire the authoritative club lock in its outer transaction,
  check identity/activity, and hold it through its business effects and replay.
- Nested operations must verify a context belonging to that exact transaction.
  A request attribute, process flag or model's loaded `is_active` is not authority.
- Unsupported ambient transactions fail closed. Testing is not an exception.
- Durable rejection/failure audit evidence must be narrowly scoped. It must never
  authorize business work or turn an eligibility failure into financial success.

## API and proof

`OrganisationTransactionGuard::run($organisation, $operation, $attempts = 1)`:

1. Requires no Laravel transaction and no native PDO transaction already open.
   Rejects unsupported engines; this implementation requires PostgreSQL's actual
   transaction identity. SQLite is used for HTTP/boundary tests, never lock proof.
2. Starts a new outer transaction through the existing Laravel connection API.
   Attempts default to one and must be explicitly selected by the adopting caller.
3. Rejects a read-only mutation transaction. Resolves organisation identity using
   the unchanged `InstanceOrganisation` rules inside the primary transaction.
4. Rejects a foreign target before activity disclosure. Acquires the assigned
   club's `FOR UPDATE` lock as the first domain row lock, then reloads/checks its
   identity and activity. A supplied Club model contributes its identifier, never
   its cached activity. A model from another connection is rejected.
5. Issues an immutable context registered against its exact object identity in
   the issuing guard. The proof records the Laravel connection, native PDO object,
   PostgreSQL backend PID and full transaction ID (`pg_current_xact_id()`), and the
   authoritative organisation code. The registry is not an activity cache.
6. Executes the callback. Verifies the proof and fresh eligibility again before
   accepting its result, requires balanced root depth, and lets Laravel commit.
7. Invalidates the context at callback exit on every success/failure/retry, even
   before after-commit callbacks can run. Cleans up unbalanced owned transactions;
   if a connection was replaced, rolls back the original still-open PDO as well.

`OrganisationTransactionContext::assertUsable($organisation = null)` verifies:

- The issuer recognises this exact context object. Public construction or cloning
  grants no authority; serialization/unserialization is prohibited.
- The default Laravel connection and physical PDO still match; a transaction is
  actually open and its backend/transaction ID matches the original proof.
- Current configured organisation identity and fresh activity still match.
- Any explicit nested target is the same organisation and connection.

`OrganisationTransactionGuard::within($context, $organisation, $operation)` checks
that context before and after nested work. It starts no transaction/savepoint and
changes no retry policy. A different guard instance can join through the context's
issuer, without a global current-organisation variable.

Proofs expire after root commit, root rollback or retry. Reusing the same PDO and
Laravel nesting depth in another root transaction does not revive a proof. A
normal child savepoint commit/rollback does not expire the parent context: the
club lock was acquired before the child savepoint and remains held by the root.

## Typed rejection and HTTP compatibility

`OrganisationAccessRejected` exposes a typed exception with stable `reason` and
`status` fields for direct service callers. Its API-only renderer returns generic
responses:

| Reason | HTTP | Body |
| --- | ---: | --- |
| `ORGANISATION_CONFIGURATION_UNAVAILABLE` | 503 | Existing generic configuration message |
| `ORGANISATION_FOREIGN` | 404 | `Not found.` only |
| `ORGANISATION_SUSPENDED` | 403 | Exact P0.2A suspension message/code |
| `ORGANISATION_TRANSACTION_UNSUPPORTED` | 409 | Supported outer transaction required |
| `ORGANISATION_TRANSACTION_CONTEXT_INVALID` | 409 | Context no longer valid |

It does not replace the existing HTTP exceptions or change existing route handling.
P0.2A health/authenticated-logout exceptions, authentication ordering and permissions
remain intact. Database concurrency exceptions propagate to Laravel's existing
retry detector rather than being relabelled as eligibility failures. At this guard's
owned outer boundary only, a nested Laravel `DeadlockException` wrapping a PDO
SQLSTATE `40001` is unwrapped: Laravel otherwise casts the string SQLSTATE to zero
and loses retry classification. No framework or finance retry policy is changed. An aborted
transaction cannot validate an old context and continue as if it were healthy.

## Adoption contract for B2–B7

This is a foundation API, not installed operational enforcement. Existing business
services still have the P0.2B gaps documented by the investigation. Do not claim
that this PR closes their suspension races.

At a later approved outer mutation boundary:

```php
return $guard->run($clubId, function (OrganisationTransactionContext $context) use ($guard, $nested, $clubId) {
    return $guard->within($context, $clubId, function ($context) use ($nested) {
        return $nested->perform($context); // future explicit context-aware API
    });
}, attempts: 3); // only if the existing outer operation's approved budget is three
```

The adopting code must preserve the existing outer operation boundary and attempt
budget. This example is not permission to wrap every HTTP request or an arbitrary
existing transaction. Existing inner finance calls may retain their mapped Laravel
savepoints, but they must use the verified context before business work/replay.

- B2 must resolve package/operation/decision-before-club inversions before cutover
  adoption. Do not put `run()` inside an already-running package transaction.
- B3–B5 must retain balance, period, source-binding, immutable-journal, allocation,
  reference, digest, provenance and idempotency checks. Guard eligibility grants
  no actor permission. Do not add retries to previously one-attempt outer operations.
- B6 must guard whole member/import/profile/announcement/event/project/reminder
  mutations. Recompute mutable prerequisites per retry and avoid captured mutable
  counters/models carrying results from a rolled-back attempt.
- B7 read paths must use read admission and retain their existing snapshot setup.
  Do not pass `READ ONLY` snapshots to this mutation guard or hold the club mutex
  over statement generation: that would block the existing concurrent posting test.
- Future CLI/jobs must obtain a new context at execution; never transport one.
  Existing bootstrap/maintenance and authentication paths require explicit review.
- Durable attempt/failure auditing needs its own narrow reviewed policy in the
  adopting cutover increment. B1 adds no audit bypass, suspended-work option,
  alternate organisation mode or privileged actor path.

Callbacks must not commit/roll back/restart the root, replace connections, execute
transaction-control SQL, or perform business work after leaving their valid context.
The guard detects changed identity and rejects further context use; it cannot undo
an explicit commit made by privileged raw PDO/SQL code. It is not a sandbox for
untrusted PHP or arbitrary database administrators. Nested framework savepoints
are the supported transaction API, and their root context is checked at exit.

Framework-managed test transactions are unknown ambient transactions too. Positive
guard tests must own a real root transaction on an allowlisted disposable PostgreSQL
database. Do not disable checks for `APP_ENV=testing`, silently adopt a fixture's
transaction or count SQLite as PostgreSQL lock/concurrency verification.

## Verification

Final validation results (each row is a suite execution, not an additive unique-test
count; the seven boundary cases also occur in the supporting suite):

| Suite | Tests / passed | Assertions | Failures | Errors | Skips |
| --- | ---: | ---: | ---: | ---: | ---: |
| New guard, fresh isolated PostgreSQL 17 | 34 / 34 | 313 | 0 | 0 | 0 |
| Supporting HTTP/security, in-memory SQLite | 87 / 87 | 1,491 | 0 | 0 | 0 |
| Boundary suite rerun after final retry fix, in-memory SQLite | 7 / 7 | 20 | 0 | 0 | 0 |
| Canonical finance gate, disposable PostgreSQL databases | 94 / 94 | 1,165 | 0 | 0 | 0 |

Finance breakdown: 3IJAE acceptance 10/288 assertions; PostgreSQL concurrency
1/77; transactional regression 47/455; receipts/statements 35/334; PostgreSQL
read-only snapshot concurrency 1/11. This is the relevant canonical finance gate,
not a claim that the entire historical backend matrix was rerun. Frontend: 210
tests across 21 files passed; typecheck/build passed; lint had zero errors and 87
existing warnings. Composer and both npm locked-dependency audits passed.

An earlier guard execution passed 30 cases/278 assertions. The expanded execution
then had 34 cases, 33 passed, 310 assertions, zero failures, one error and zero
skips: the real nested SQLSTATE 40001 retry exposed Laravel's wrapper losing the
SQLSTATE. The narrow guard-only unwrap above fixed it without changing the test's
assertions or concurrency behavior; the final 34-case execution passed. A sandboxed
boundary invocation could not read the installed bootstrap dependency; its rerun
with dependency access passed all seven cases. No existing assertion or safety
guard was weakened.

Local evidence: `%TEMP%/lc-p02b1-guardpg-b60fb5169c924eb89dff3561d69e9d9e/`
contains JUnit, log and safety evidence; `%TEMP%/lc-p02b1-security-final.xml`
records the supporting suite; `%TEMP%/lc-p02b1-release-gate.json` and `.log`
record the canonical gate. These are local artifacts, not portable CI URLs.
The fresh guard cluster used only `lcb_p02b1_transaction_guard_test` on loopback
port 55493, with the expected non-elevated owner, zero role memberships and no
protected database names. Original catalog snapshots matched before/after; the
fresh server stopped successfully. No existing database ownership, privileges or
contents were changed. The evidence runner's legacy `worker_sha256` field hashes
the context class, not a separate concurrency worker.

The PostgreSQL suite checks its opt-in, exact database name, engine and non-elevated
database owner before any fixture migration. Its external runner independently
checks loopback/server/data-directory identity, exact database inventory, role
memberships, protected-name absence and unchanged original maintenance-catalog
snapshots. Fresh test credentials are generated independently; original credentials
are used only for read-only catalog checks, never copied to the fresh cluster.

The suite covers assigned/suspended/foreign/misconfigured/deleted organisations,
stale models, suspension between initial resolution and locking, competing update
lock timeout, fresh eligibility on retry, same-PDO root replacement, connection
changes, rollback/retry expiry, forged/cloned/serialized contexts, child savepoints,
unbalanced transaction cleanup and the existing journal API/replay nested unchanged.
The boundary suite covers the framework fixture transaction and generic API
rejections, plus SQLite's inability to issue PostgreSQL proofs.

## Risks, rollback and GO / NO-GO

- No business service uses the guard yet. HTTP middleware remains the only common
  admission enforcement for existing endpoints until separately approved adoption.
- PostgreSQL transaction metadata access and a primary connection are required.
  Other engines are intentionally unsupported. Test-mode acceptance is not added.
- Additional identity/activity queries cost round trips; optimise only after
  measured integration work, without caching an active decision.
- Club-lock contention and cross-service ordering remain B2–B7 responsibilities.
- Read admission does not cancel previously admitted reads or retract delivered data.
- No operator suspension/resumption, token/session revocation, audit policy
  implementation, storage bypass repair or automatic job replay is included.

Rollback is a code rollback removing these new, currently unwired classes/tests
and documentation. No migration, accounting reversal or database restore is needed.
Keep P0.1/P0.2A in place. A future adopted guard needs a coordinated service rollback;
this B1-only rollback statement must not be reused for a partially integrated release.

GO for B1 draft review based on the passing focused and canonical verification.
NO-GO for claiming operational suspension safety or starting B2–B8: lock-order
adoption, per-service transaction/replay placement, audit policy and read admission
remain separately approved work. Keep the PR draft; no merge, deployment,
organisation provisioning or finance cutover is authorised by B1.
