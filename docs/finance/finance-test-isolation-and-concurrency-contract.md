# Finance Test Isolation and Concurrency Contract

## Purpose

This is the canonical execution contract for finance verification. It changes no accounting behavior. Its purpose is to prevent a green result produced by the wrong database engine, an incompatible outer transaction, shared state, accidental suite order, or an unenabled concurrency test.

## Isolation classes

| Class | Required database | Laravel lifecycle | Permitted use | Prohibited claim |
|---|---|---|---|---|
| Fast deterministic | PHPUnit-configured in-memory SQLite | `RefreshDatabase` unless the test documents another lifecycle | validation, authorization, pure service behavior, ordinary request/response regression | PostgreSQL locking, isolation-level, constraint, or race proof |
| Transaction-owning | Explicitly disposable PostgreSQL | `RefreshesDatabaseWithoutTransactions` or `DatabaseTruncation`, as selected by the suite | statement snapshots and workflows whose application code opens or configures its own transaction | execution inside an outer `RefreshDatabase` transaction |
| Concurrent process | Explicitly disposable PostgreSQL | clean migrated schema; independent worker processes | lock ordering, exact replay, competing request, cross-tenant independence | pass when skipped, serialized, or run on SQLite |

Every test must be order-independent. A suite may not depend on fixtures, migration state, environment flags, open transactions, or committed rows left by an earlier suite. Non-transactional suites must establish and restore their documented clean schema lifecycle.

## Canonical affected-suite grouping

- `Milestone3ijaeOrdinaryIncomeReceiptCorrectionTest` owns database cleanup through Laravel `DatabaseTruncation`.
- `Milestone3iizzOrdinaryIncomeReceiptTest`, statement projection suites, and the 3IIZT snapshot suite use `RefreshesDatabaseWithoutTransactions` where production code must own a genuine transaction.
- A verifier must not wrap those suites in an additional transaction or silently substitute SQLite.
- Incompatible lifecycle groups run as separate test commands. A combined command is evidence only when every included suite has compatible isolation.

## Concurrency opt-ins

The environment flag is part of the test contract, not an optional optimization.

| Lane | Required flag | Database guard |
|---|---|---|
| Opening activation | `RUN_3IIZG_POSTGRES_CONCURRENCY=1` | suite-defined disposable target |
| Charge/payment integration | `RUN_3IIZH_POSTGRES_CONCURRENCY=1` | suite-defined disposable target |
| Statement snapshot | `RUN_3IIZT_POSTGRES_CONCURRENCY=1` | suite-defined `lcb_3iizt_...` target |
| Expense recording / replay hardening | `RUN_3IIZV_POSTGRES_CONCURRENCY=1` or `RUN_3IJAB_POSTGRES_CONCURRENCY=1`, exactly as the suite specifies | suite-defined exact target |
| Expense reversal | `RUN_3IIZW_POSTGRES_CONCURRENCY=1` | suite-defined exact target |
| Internal transfer / replay hardening | `RUN_3IIZX_POSTGRES_CONCURRENCY=1` or `RUN_3IJAB_POSTGRES_CONCURRENCY=1`, exactly as the suite specifies | suite-defined exact target |
| Ordinary income receipt | `RUN_3IIZZ_POSTGRES_CONCURRENCY=1` | suite-defined exact target |
| Ordinary income correction | `RUN_3IJAE_POSTGRES_CONCURRENCY=1` | canonical `lcb_3ijae_finance_...` disposable target |

The verifier records passed, failed, error, skipped, assertion, duration, and exit-code values for every PHPUnit group. A skip is `BLOCKED`, never `PASS`. Flags are process-scoped and every changed environment variable is restored to its exact prior existence/value in `finally`.

## Disposable PostgreSQL safety

The canonical entry point is `npm run verify:finance-release-gate -- <arguments>`, backed by `scripts/verify-finance-release-gate.ps1`.

- The database name must begin `lcb_3ijae_finance_`, contain only lowercase letters, digits, and underscores, and be at most 63 characters. Later finance lanes may adopt their own equally narrow canonical prefix only after their in-test guard and this contract are updated together.
- 3IIZT runs separately on exactly `lcb_3iizt_statement_snapshot_concurrency_test`; it is never folded into the 3IJAE database or test command.
- `lc_beenachi_connect` and `lc_beenachi_connect_reconstructed_20260727` are protected and rejected.
- Database creation requires `-CreateDisposableDatabase` and an explicit `-DatabaseAdministratorUsername`; otherwise the exact target must already exist. The administrator creates the database with the application role as owner. The application role remains `CREATEDB=false` and performs migrations/tests.
- The application role and credentials come from existing backend configuration and are never printed or stored in the report.
- The verifier never drops a database. It preserves the exact target for review and requires a separate, exact-name cleanup decision.
- `-SkipPostgres` produces a blocked PostgreSQL gate. It supports non-destructive preflight; it cannot produce release approval.
- The report defaults to the operating-system temporary directory. Repository-local report paths are rejected; no ignore-policy exception is authorized.

## Concurrency authorization rule

Adding a new finance `RUN_*` flag requires a named PostgreSQL test, an exact disposable-target assertion inside that test, a documented isolation class, and an entry in this contract and the canonical verifier before the lane can satisfy a release gate.
