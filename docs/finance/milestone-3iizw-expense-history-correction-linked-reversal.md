# Milestone 3IIZW — Expense History, Correction and Linked Reversal

## Product boundary

Authorized officers can list and inspect same-club immediate expenses created by the 3IIZV source workflow. The read API supports bounded pagination plus date, account, fund, exact external-reference, search-text and status filters. Responses use public expense, account, journal and line identities; internal database identifiers are not part of the contract.

This milestone exposes no generic journal-reversal route. Payment void, charge cancellation, opening journals, cutover journals and other integration journals retain their existing source-specific lifecycles.

## Immutable correction rule

A posted expense is never edited or deleted. Correction posts one linked full reversal through the existing `JournalReversalService`:

```text
Original: debit expense / credit cash-bank
Reversal: debit cash-bank / credit expense
```

The reversal preserves the original amount, account identities and fund attribution. The expense source, original journal, reversal source record and reversal journal remain permanently linked and visible. Partial reversal and user-supplied journal lines are prohibited.

An optional corrected replacement is a new `ClubExpense` with a new public ID, idempotency key and journal. It passes through `ClubExpenseRecordingService`; `corrected_from_expense_id` provides navigation provenance without rewriting the original.

## Period rule

The caller supplies an effective reversal date. That date must belong to an available open club accounting period. A closed original period is not reopened: correction may be posted only into an explicitly selected later open period, preserving the original link under DR-013 and DR-021.

## Authorization

- `finance.club.expenses.view`: President, Secretary and Treasurer.
- `finance.club.expenses.reverse`: President and Treasurer.
- Normal Members receive neither club-wide permission.

Backend membership and permission middleware is authoritative. Frontend role visibility is only a usability control.

## Integrity, idempotency and concurrency

The source wrapper locks the club, expense, original journal, lines and target period; revalidates club/source/journal linkage, original posted state, exact accounts, amount and fund; invokes the canonical reversal service; binds the immutable `ClubExpenseReversal`; and commits atomically. Corrupt or incomplete provenance fails closed.

Within a club, one reversal record is allowed per expense and one reversal identity is allowed per idempotency key. Exact canonical replay returns the existing reversal. Changed replay conflicts. Different keys racing for the same expense cannot bypass the one-reversal rule. PostgreSQL verification uses two independent PHP workers and deterministic barriers against only the authorized disposable database `lcb_3iizw_expense_reversal_concurrency_test`.

Final PostgreSQL verification: fresh migration including the 3IIZW migration passed; the three race scenarios passed as **1 test / 27 assertions / 0 failed / 0 skipped**. The disposable database was removed afterward. The protected database `lc_beenachi_connect_reconstructed_20260727` remained present and was not used as the migration or test target.

## Statement behavior

The existing Club Account Statement consumes canonical journal records without expense-specific aggregation. It retains original and reversal provenance under DR-023. The original plus reversal net to zero; a corrected replacement supplies the new economic effect. Each legitimate posting changes the statement snapshot fingerprint.
