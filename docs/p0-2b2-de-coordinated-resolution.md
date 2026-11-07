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

## E2 and validation

Financial root, audit-failure/indeterminate handling, test evidence and decisions
will be recorded as the approved increments complete. Existing journal services,
schemas, triggers, C/A-B guards and financial governance remain untouched.
