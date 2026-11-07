# Coordinated P0.2B2-D/E integration

Integration baseline: reviewed C `5a039f94eef0db0ada67690d43b2fd46fd8f2020`.
PR #29 remains draft on its original branch; no approved C application behavior is
rewritten. D, E1 and E2 will be distinct reviewable commits on a separate branch.
No partial increment is safe to merge/deploy independently.

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

Final combined validation and merge recommendation follow after execution.
