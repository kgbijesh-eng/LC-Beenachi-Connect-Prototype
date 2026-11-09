# P0.2B5 ledger and period administration

Draft PR #36. Implementation GO following the complete selection below. Merge execution remains NO-GO pending separate business-owner approval.

| Reference | SHA |
|---|---|
| Approved baseline | `5ac0e1939456ebe0661bae797497bebf798b645c` |
| Validated source | `bc794b03754262e218ef983fd35e104ebc82c284` |
| Validated source tree | `da5aa412b977791ccecdc1c987b2a031cdbaebc2` |
| Application subtree | `529a603f4c049657898c15c3cfd992e2a82460bb` |

The retained B1-B4 selection and SQLite ran on `4f9d8af162d734f2cd56ff17ea593c9604413c5b`. The validated source adds only B5 test evidence refinements. B5 and the F1 serial rerun used that source. The application tree and runner are identical across both heads. The report commit changes documentation only. The draft PR records its final commit, full tree and exact-head CI.

## Transaction and recovery contract

The three approved finance services use B1 to own standalone transactions with three attempts. `provisionWithin()` and `closeWithin()` validate an issued context and use nested savepoints. The outer owner handles genuine concurrency retries. Normalization remains pure, and every attempt rereads mutable actor and resource state after authoritative club admission.

Both provisioners establish a private, randomly named PostgreSQL savepoint below the B1 club lock. Their recovery catch covers the business callback. A query failure recognised by the framework concurrency detector propagates to the transaction owner. For other query failures, the provisioner executes `ROLLBACK TO SAVEPOINT` and `RELEASE SAVEPOINT` on the owned marker. The recovery query follows successful database acknowledgement of both operations and fresh context and actor checks. The candidate is locked within the verified organisation and compared against all original semantic fields. Missing candidates, rollback/release uncertainty and completion failures propagate. Proven actor and semantic conflicts retain their domain rejections. The outer transaction has no replay lookup or recovery retry.

The focused tests inject real PostgreSQL statement failures and record the acknowledged rollback, subsequent actor query and scoped resource lock. They cover exact, conflicting and absent resources, invalid actors, changed configuration, suspended state, rollback uncertainty and release errors. Completion tests separately cover post-savepoint, post-commit `40001`, post-commit `40P01` and lost acknowledgements. Fresh explicit replay after a committed acknowledgement loss checks row and audit cardinality.

Period close locks the verified club before the scoped period, journal entries and lines. Non-locking ownership probes retain detection of malformed foreign-linked records. Entry and line locks have deterministic order. Close retains the same active-membership check, status and journal integrity checks, actor-bound replay and single atomic close audit. Same-actor replay may carry a different reason while preserving the original reason and timestamp. B1, B2/F1, B3, B4, schema, production triggers and actor governance remain at baseline.

## Validation

Final selection **1,267 executions and 16,067 assertions across 1,187 unique cases, with 0 failures, 0 errors and 0 skips**. PostgreSQL contributed 1,237 executions and 15,016 assertions. In-memory SQLite contributed 30 executions and 1,051 assertions. All 1,019 B4 case identities are present, plus 168 additional unique cases. All 17 historical provisioning methods passed after conversion to genuine-root PostgreSQL. Actor and financial assertions were preserved. The old unknown-club error expectation now asserts the typed B1 foreign rejection.

| Suite | Executions | Assertions | Failures | Errors | Skips | Status |
|---|---:|---:|---:|---:|---:|---|
| Integration | 197 | 2554 | 0 | 0 | 0 | PASS |
| Finance | 94 | 1345 | 0 | 0 | 0 | PASS |
| B3Historical | 154 | 2010 | 0 | 0 | 0 | PASS |
| B3 | 43 | 498 | 0 | 0 | 0 | PASS |
| B4 | 85 | 739 | 0 | 0 | 0 | PASS |
| Guard | 34 | 313 | 0 | 0 | 0 | PASS |
| F1 | 8 | 228 | 0 | 0 | 0 | PASS |
| B4Reactivation | 1 | 13 | 0 | 0 | 0 | PASS |
| B3Fixtures | 40 | 386 | 0 | 0 | 0 | PASS |
| B3Poison | 1 | 9 | 0 | 0 | 0 | EXPECTED_DISCARD |
| B3PoisonCommit | 1 | 8 | 0 | 0 | 0 | EXPECTED_DISCARD |
| B3Direct | 152 | 1378 | 0 | 0 | 0 | PASS |
| B3Contexts | 42 | 343 | 0 | 0 | 0 | PASS |
| B3Resources | 18 | 144 | 0 | 0 | 0 | PASS |
| B3Workers | 8 | 134 | 0 | 0 | 0 | PASS |
| B3Races | 54 | 766 | 0 | 0 | 0 | PASS |
| B3Cross | 12 | 162 | 0 | 0 | 0 | PASS |
| B3OpeningPeer | 2 | 38 | 0 | 0 | 0 | PASS |
| B3Retries | 36 | 792 | 0 | 0 | 0 | PASS |
| Coverage | 42 | 451 | 0 | 0 | 0 | PASS |
| CoverageRaces | 14 | 202 | 0 | 0 | 0 | PASS |
| B4Races | 27 | 328 | 0 | 0 | 0 | PASS |
| B4Resources | 4 | 28 | 0 | 0 | 0 | PASS |
| SQLite | 30 | 1051 | 0 | 0 | 0 | PASS |
| B5 | 68 | 611 | 0 | 0 | 0 | PASS |
| B5Outcomes | 33 | 336 | 0 | 0 | 0 | PASS |
| B5Races | 41 | 528 | 0 | 0 | 0 | PASS |
| B5Retries | 24 | 622 | 0 | 0 | 0 | PASS |
| B5Opening | 2 | 50 | 0 | 0 | 0 | PASS |

The existing allowlists, fresh cluster identity and inventory checks, non-elevated database owner, worker attestations and shutdown gates passed. B3's expected-discard tests preserve their separate retirement status. Restoration hashes match for the existing bounded corruption fixtures. The evidence records 16 savepoint outcomes, 40 B5 blocking edges, 2 opening/close edges and 24 genuine-abort cases. Ordinary suites recorded zero deadlocks. B5Retries, B3Retries and F1 retain deliberately induced native deadlocks as separate evidence.

The concurrency suite checks suspension-first and operation-first for mutations and replays, duplicate account/period requests, actor-bound close conflicts, B3 financial peers and B4 configuration peers. The foreign-period case holds a real foreign lock and requires the scoped rejection before a lock timeout. Opening-first produces the one-time journal and authority consumption before close. Close-first blocks opening and retains confirmed `not_posted` audit evidence. The existing B2 mixed-order regression and historical winner/provenance assertions remain part of the selection.

## Retained diagnostics

All B5 attempts total 1,581 executions and 19,660 assertions, with 4 failures, 1 errors and 0 skips. Earlier B4 evidence remains in its original file and is reconciled by case ID and evidence digest.

- `B5Races` on `4ba7a543d8a0d2f61994602ac20750acc5364736` recorded 41 executions, 500 assertions, 3 failures, 0 errors and 0 skips. Runner status was `FAILED_OR_BLOCKED` with shutdown exit `0`. The machine-readable record retains the original outcomes and failure details.
- `B5Opening` on `4f9d8af162d734f2cd56ff17ea593c9604413c5b` recorded 2 executions, 34 assertions, 1 failures, 0 errors and 0 skips. Runner status was `FAILED_OR_BLOCKED` with shutdown exit `0`. The machine-readable record retains the original outcomes and failure details.
- `F1` on `4f9d8af162d734f2cd56ff17ea593c9604413c5b` recorded 4 executions, 97 assertions, 0 failures, 1 errors and 0 skips. Runner status was `FAILED_OR_BLOCKED` with shutdown exit `0`. The machine-readable record retains the original outcomes and failure details.

The initial B5 race worker treated two source-integration errors and one ordinary-income error as unexpected worker failures. All three were the expected closed-period domain rejections. The follow-up changes only the test worker classification and asserts the expected rejection message. The original failed JUnit cases remain recorded. A second diagnostic exposed incorrect expected wording for B2 close-first rejection. Its follow-up asserts the exact existing exception and message, checks confirmed failure audit evidence and verifies exact opening replay after close. The B5 snapshots now include account and period rows and assert durable retry results. The retained F1 worker timeout was rerun serially with its original assertions and timeout. Preparation/formatting commands also encountered a wrong working directory and a Windows sandbox writable-file check. Those commands executed zero tests. Targeted formatting completed after correcting the directory and using the authorised PHP execution context.

## Scope and rollback

The complete diff contains 16 files. Three application files implement the approved boundaries. The remaining files are tests, test workers, the disposable runner and this evidence.

- `backend/app/Services/Finance/ClubAccountingPeriodCloseService.php`
- `backend/app/Services/Finance/ClubAccountingPeriodProvisioningService.php`
- `backend/app/Services/Finance/LedgerAccountProvisioningService.php`
- `backend/tests/Feature/AccountingAdministrationConcurrencyTest.php`
- `backend/tests/Feature/AccountingAdministrationEnforcementTest.php`
- `backend/tests/Feature/AccountingAdministrationOpeningConcurrencyTest.php`
- `backend/tests/Feature/AccountingAdministrationOutcomeTest.php`
- `backend/tests/Feature/AccountingAdministrationRetryTest.php`
- `backend/tests/Feature/Milestone3iidLedgerAccountPeriodProvisioningTest.php`
- `backend/tests/Feature/Milestone3iifInternalAccountingPeriodCloseServiceTest.php`
- `backend/tests/Support/AccountingAdministrationFixture.php`
- `backend/tests/Support/AccountingAdministrationOpeningWorker.php`
- `backend/tests/Support/AccountingAdministrationWorker.php`
- `scripts/verify-cutover-bootstrap-postgres.ps1`
- `docs/p0-2b5-ledger-period-enforcement.md`
- `docs/validation/p0-2b5-postgres-evidence.json`

B6 operational writers remain unsynchronised. Period close retains its existing cost of scanning and locking a period's journal rows while holding the club lock. B7 read admission and B8 reconciliation remain pending. A rollback must revert the complete B5 code/test unit, then rerun the relevant security and finance gates. Preserve provisioned records, closed periods, journals and audits. The baseline has the original B5 admission gaps, so rollback requires an explicit operational decision.

[Machine-readable execution, case and lock evidence](validation/p0-2b5-postgres-evidence.json)
