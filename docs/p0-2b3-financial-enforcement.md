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

## Application inventory and transaction ownership

All paths below are relative to `backend/app/Services/`.

| File | Root / nested contract | Original attempt budget |
| --- | --- | --- |
| `Finance/ClubJournalPostingService.php` | `post` owns root; `postWithin` requires issued context and keeps savepoint | 3 |
| `Finance/JournalReversalService.php` | `reverse` owns root; `reverseWithin` keeps savepoint; posting receives context | 3 |
| `Finance/ClubOpeningJournalActivationService.php` | Existing B2 root; only posting call becomes `postWithin` | 3, unchanged |
| `Finance/PaymentRecordingService.php` | Standalone root; fresh scoped member and existing actor checks inside root | 1 |
| `Finance/ReceiptGenerationService.php` | Standalone root; scoped payment lock before existing authorisation/receipt checks | 1 |
| `FinanceCollections/FinanceWriteService.php` | Seven roots: create/update/issue/close/cancel charge, record/void payment | 1 each |
| `Finance/SourceAccountingIntegrationService.php` | Context-required coverage, posting, reversal and command replay; old unproven entry points fail closed | No new root/retry owner |
| `Finance/ClubExpenseRecordingService.php` | Root; nested posting receives context | 3 |
| `Finance/ClubExpenseReversalService.php` | Root; nested reversal receives context | 3 |
| `Finance/ClubInternalTransferService.php` | Root; nested posting receives context | 3 |
| `Finance/ClubOrdinaryIncomeReceiptService.php` | Root plus explicit `recordWithin` preserving correction's existing savepoint | 3 |
| `Finance/ClubOrdinaryIncomeReceiptCorrectionService.php` | Root; reversal and replacement recording receive same issued context | 3 |

Posting callers are opening execution, reversal, source integration, expense
recording, internal transfer and ordinary-income receipt recording. Reversal
callers are source integration, expense reversal and ordinary-income correction.
Correction also calls ordinary-income receipt recording for replacement. Legacy
payment/receipt controllers call their standalone service boundaries. Collections
controllers call the seven standalone collections methods; collections alone owns
source-integration mutations. Reconciliation uses binding/read helpers, unchanged.

Lock contract: B1 authoritative club lock precedes all resource locks. Resource
queries include organisation scope before `FOR UPDATE`; core journal APIs retain
the missing-versus-foreign exception distinction through non-locking ID/owner
metadata, without locking foreign financial rows. Secondary lock order and journal
construction remain in their original statement order. Existing B2 F1 outcome
classification, separate audit transactions and durable audit cardinality are not
modified. Context does not grant actor permissions.

## Compatibility dependency requiring a decision

Five existing tests in
`backend/tests/Feature/Milestone3iizhChargePaymentAccountingIntegrationTest.php`
are SQLite-only. They deliberately drop/recreate SQLite immutable-data triggers or
depend on SQLite trigger syntax to construct corrupt records and exercise replay
and reconciliation defences. Their existing PostgreSQL skips remain unchanged.
The new B1-owned mutation roots intentionally reject SQLite; running the same
business assertions with genuine roots therefore needs a PostgreSQL corrupt-fixture
strategy. Disabling or replacing PostgreSQL triggers is not authorised by the
current no-schema/no-trigger-change restriction. No such change has been made.

Do not count these five cases as passed or silently replace their assertions.
Resolve this fixture-policy dependency before claiming complete regression
acceptance. A separately approved, disposable-only corruption-fixture design would
need exact target/owner checks, bounded corruption construction, guaranteed trigger
restoration, and unchanged production schema/guards. This report does not authorise
that design or its implementation.

## Rollback and acceptance

No schema migration or production-data operation is introduced. These interfaces
form one coordinated unit: reverting journal APIs alone would strand nested
callers. Any future rollback must revert the complete approved B3 unit under a
separately authorised operational procedure, preserving B1/B2/F1. Never rewrite
posted journals, source provenance, consumed authority or audit evidence.

Implementation completion and merge readiness remain NO-GO while fixture
compatibility, direct-call/concurrency coverage or final regression evidence is
incomplete. All changes stay draft; no partial increment merge is permitted.
