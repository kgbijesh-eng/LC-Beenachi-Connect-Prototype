# P0.2B3 coordinated financial enforcement

Baseline main: `b44e6de7c4de258c918bd655ee8853b2ba85a1b4`.
Development branch: `codex/p0-2b3-financial-enforcement`.
Draft increments only; no partial merge/deployment.

## Caller and transaction contract

Standalone posting and reversal own B1 roots with the original three attempts.
Their explicit `postWithin`/`reverseWithin` interfaces require an issued B1 context
and preserve the original nested transaction/savepoint. Unknown ambient calls fail
closed. Standalone roots do not gain an additional savepoint or local retry owner.
Contexts are never recovered from transaction depth or a global active flag.

B2 opening passes its existing context to posting; its outer root, F1 XID evidence,
attempt and terminal audit roots and financial/governance behavior remain unchanged.
Reversal passes context to nested posting. Collections owns seven one-attempt B1
roots, propagating context to coverage/post/reversal/replay source integration.
Source mutations remain nested-only. Expense/reversal/transfer/ordinary-income and
correction roots retain three attempts; correction passes its context to reversal
and to replacement receipt recording. Legacy Payment/Receipt are separate models
and standalone one-attempt APIs, retaining existing actor and monetary semantics.

Actor authorisation is not conferred by context. Existing operation policies and
the trusted-internal actor contract of journal APIs remain; no new role grants or
membership requirement for B2 external technical appointees is introduced.
Member/membership/officer/role/permission writers remain unsynchronised unless they
already follow club-first ordering. This increment does not migrate B6 writers.

## Test foundation

FinancialPostgresTestCase owns genuine root fixtures, verifies the exact dedicated
`lcb_p02b3_finance_test` database (or the existing canonical finance runner database),
expected non-elevated owner, no memberships and an exact inventory before migration.
No RefreshDatabase transaction or fake B1 context is permitted. Existing historical
concurrency guards retain their independent exact allowlists.

The runner always creates a fresh loopback cluster with independent credentials,
checks cluster identity before/after, retains logs/JUnit and confirms shutdown.
Explicit synthetic instance configuration belongs to fixture setup, never request
fallback in application code. Foreign-operation assertions must keep a fixed
authoritative assignment during the operation under test.

Validation and exact increment inventories will be recorded as work completes.
