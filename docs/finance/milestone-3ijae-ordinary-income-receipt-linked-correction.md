# Milestone 3IJAE — Ordinary Non-Member Income Receipt Linked Correction

## Purpose and scope

3IJAE implements the source-specific production correction workflow approved by 3IJAD for posted `ClubOrdinaryIncomeReceipt` records. It supports one immutable linked full accounting reversal and, in the same database transaction, at most one optional new replacement ordinary-income receipt. It implements no refund/payment-out, donation, member payment, allocation, expense, transfer, opening/manual journal, or other-source correction.

The bookkeeping reversal negates the original accounting recognition. It does not record or prove that cash was physically refunded.

## Architecture and persistence

`ClubOrdinaryIncomeReceiptCorrection` owns the public correction UUID, club, original receipt and journal, reversal journal, optional replacement receipt, reversal date, reason, actor, idempotency UUID, normalized request digest, and timestamps. A new migration adds this table and optional replacement lineage without editing the deployed 3IIZZ migration.

Composite club-aware foreign keys protect receipt/journal relationships, including a three-column replacement/original/club lineage key. Unique constraints protect public identity, one correction per original, original/reversal journal ownership, one replacement, and tenant-scoped idempotency. The service locks the club, original receipt, original journal and ordered lines, target period, and correction identities, validates complete persisted provenance, and invokes the canonical `JournalReversalService`. Receipt posting and correction share `ClubOrdinaryIncomeReceiptService::lockedPostingWindow`, so both use the same open-period and posted-cutover coverage interpretation.

An optional replacement runs through normal `ClubOrdinaryIncomeReceiptService` validation and posting inside the same transaction, receives new public/source/journal/replay identities, and records immutable `corrected_from_receipt_id` lineage. The correction is not externally visible before commit: its guarded construction-only binder may attach the replacement only to a just-created correction in the active transaction and only when club and original lineage agree. Normal later updates and all deletes remain rejected. Any failure rolls back the correction, reversal, replacement, journals, lines, audit, and lineage.

## Authority and accounting

The new `finance.club.ordinary_income_receipts.reverse` permission is assigned only to President/Admin and Treasurer. Existing view permission lets President/Admin, Secretary, and Treasurer inspect correction history. Secretary and Normal Member cannot reverse. Optional replacement additionally requires existing create authority.

The reversal preserves the original line order, accounts, INR paise amount, currency, and fund while inverting each direction. Caller-supplied lines, amounts, accounts, fund, currency, partial reversal, and refund fields are rejected. Its supplied date must fall in a same-club open accounting period; the original period is never reopened or altered. Original receipt, journal, and lines remain immutable and separately visible.

## Idempotency, provenance, and concurrency

The correction digest binds operation, original receipt, reversal date, normalized reason, replacement choice, and every normalized replacement fact. Exact replay returns the canonical complete result; a changed retry conflicts. Database uniqueness permits only one correction per original. Historical validation checks the original request digest, source and journal identity, club, currency, qualifying account classes, exact ordered lines, directions, amount, and fund without requiring accounts to remain active. Replay revalidates correction identity/date/reason/actor/timestamps/digest, reversal source/date/actor/description/metadata/period/currency, exact inverse lines and lineage, and every result-affecting replacement field plus its normal source/journal digest. Corruption fails closed, and 3IJAB source-type/event safeguards remain active.

Stable correction outcomes distinguish validation, invalid or closed period, missing authoritative coverage, already corrected, changed replay, concurrency conflict, invalid replacement, broken original provenance, broken committed correction/reversal/replacement provenance, posting failure, and unexpected failure. Route binding and permission middleware continue to provide non-disclosing not-found and forbidden outcomes.

## API, UI, history, and statement

`POST /api/v1/clubs/{club}/finance/ordinary-income-receipts/{receipt}/correction` accepts a UUID idempotency key, reversal accounting date, mandatory bounded reason, and optional normal ordinary-income replacement object. History/detail derive `posted`, `reversed`, and `replaced`, expose public lineage, and retain deterministic tenant-scoped pagination/filtering.

President/Admin and Treasurer can initiate correction in existing receipt detail. Secretary remains view-only. The form loads the normal eligible subtype, cash/bank, income-account, and fund options. Its prepare action creates a separate review screen summarizing the original, full-reversal consequence, date, reason, mode, complete replacement facts, and refund boundary; only final confirmation submits. It uses one client UUID for a stable payload, blocks double submission, synchronizes row/detail and an independently projected replacement row from the committed response, then clears the completed key and stale context. The journal-driven statement naturally shows original, inverse reversal, and independent replacement; no entry is hidden or rewritten and committed postings continue to affect the snapshot fingerprint.

Dedicated proof was added in `backend/tests/Feature/Milestone3ijaeOrdinaryIncomeReceiptCorrectionTest.php`, `backend/tests/Feature/Milestone3ijaePostgresOrdinaryIncomeReceiptCorrectionConcurrencyTest.php`, the reusable fixture trait, and the independent worker. The acceptance suite covers authority/isolation, exact reversal, immutable originals, window coverage, rollback, replacements, prohibited refund fields, sequential and changed replay, corruption, and stable outcomes. The opt-in PostgreSQL proof uses separate processes for exact-key, competing-key, and replacement races, checks canonical effect counts, and rejects cross-source idempotency impersonation.

## Verification record

Commands run on 11 August 2026:

- `npm run test:frontend -- --run app/__tests__/ClubOrdinaryIncomeReceiptHistoryBackend.test.tsx app/__tests__/ClubOrdinaryIncomeReceiptBackend.test.tsx` — 2 files and 7 tests passed; 0 failures.
- `npm run test:frontend -- --run` — 20 files and 198 tests passed; 0 failures.
- `npx tsc --noEmit` — passed.
- `npx eslint app/ClubOrdinaryIncomeReceiptHistoryBackend.tsx app/__tests__/ClubOrdinaryIncomeReceiptHistoryBackend.test.tsx --max-warnings=0` — passed with zero errors and zero warnings.
- `npm run lint -- --max-warnings=0` — zero errors, but failed the zero-warning threshold on 87 existing unrelated warnings; the changed correction frontend files produced no warning.

Windows host verification later resolved Laravel to a fresh disposable PostgreSQL database and completed `migrate:fresh`, but the dedicated acceptance command failed with 8 tests failed and 15 assertions before the concurrency and regression groups were reached. The failures exposed a missing closing quote in the replacement amount maximum validation rule and a PostgreSQL test-harness conflict: `RefreshDatabase` had already used an outer transaction before the statement service established its approved repeatable-read, read-only snapshot. The request syntax is corrected without changing validation, and the dedicated suite now uses Laravel `DatabaseTruncation` so statement projection owns its transaction while production snapshot semantics remain unchanged.

The first targeted rollback command also emitted `Migration not found` for earlier same-batch migrations despite returning zero because `--path` limited loaded migration files but did not limit the rollback batch records. The external verifier now lints every changed/untracked PHP file before database creation, rolls back the verified last migration with `--step=1`, treats any `Migration not found` as failure, and proves migration records plus target/earlier schema state before rollback, after rollback, and after reapplication. The disposable verification databases are preserved and the protected `lc_beenachi_connect_reconstructed_20260727` database was never a target.

The next host run passed PHP lint, authentication, migration proofs, all 8 dedicated tests with 212 assertions, the genuine PostgreSQL correction concurrency test with 38 assertions, and all previously failing receipt, permission, journal, reversal, statement, and metadata regressions. The regression run reached 82 passes and 792 assertions with one remaining schema-lifecycle failure: the final `DatabaseMigrations` teardown in the statement validation suite rolled back every migration, after which the traitless 3IIZT snapshot test attempted to create a club in a missing table. The affected receipt and statement tests now share `RefreshesDatabaseWithoutTransactions`, a focused test lifecycle that runs `migrate:fresh` before each test and again during teardown, clears the Artisan instance, and keeps Laravel's migrated-state flag aligned with the fully migrated schema. 3IIZT owns the same lifecycle, so it runs independently on a blank disposable database, after the affected suites, or before any later test without depending on order. Every test begins with clean migration-installed metadata, executes at transaction level zero, and leaves a clean fully migrated schema rather than committed fixtures or a rolled-back schema. Production services and the genuine repeatable-read/read-only snapshot implementation remain unchanged; controlled trigger/function cleanup remains guaranteed in `finally`. Complete post-remediation verification remains pending.

## Remaining release gate

Before release, rerun the repaired verifier against a new `lcb_3ijae_` database. Dedicated acceptance, independent-worker PostgreSQL exact-retry/competing-key/replacement/cross-source proof, and affected regressions remain pending; the failed host attempt is not backend verification. The verification procedure deliberately preserves every disposable database for inspection. No production, accounting-professional, legal, merge, or deployment approval is claimed.

No commit or push occurred.
