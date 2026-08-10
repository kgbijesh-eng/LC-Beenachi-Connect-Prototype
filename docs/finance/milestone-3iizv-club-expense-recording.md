# Milestone 3IIZV — Club Expense Recording

## Purpose

This workflow records a tenant-supplied expense that is incurred and paid immediately. It creates one immutable source record and one balanced canonical journal in the same database transaction.

## API

`GET /api/v1/clubs/{club}/finance/expenses/options` returns the club's active INR expense accounts, cash/bank accounts, and authoritative funds.

`POST /api/v1/clubs/{club}/finance/expenses` accepts `accounting_date`, expense and payment account public IDs, `fund`, integer `amount_paise`, optional `payee` and `external_reference`, required `description`, and a UUID `idempotency_key`. Raw journal lines are prohibited.

The response exposes only public expense, account, and journal identities plus the recorded tenant facts and posting state.

## Accounting and validation

The server posts:

```text
Debit  tenant-selected active expense account
Credit tenant-selected active cash/bank asset account
```

Both lines carry the same valid authoritative fund and amount. The existing journal service enforces same-club ownership, active accounts, INR, per-fund balance, an open same-club accounting period, immutable posting, and canonical journal references. Expense-specific validation additionally requires `EXPENSES` and `CASH_AND_BANK` statement classifications and a posted cutover package covering the accounting date. Negative cash balances are not prohibited by this workflow.

## Idempotency and immutability

Idempotency is unique within a club. An exact canonical replay returns the existing expense and journal; a changed replay returns a conflict. Account IDs, date, fund, amount, payee, description, and external reference all participate in the digest. Club-row locking plus the database uniqueness constraint prevents duplicate concurrent posting.

After journal binding, the expense source cannot be updated or deleted. Corrections must use the established linked journal-reversal architecture in a later explicitly authorized workflow.

## Permission and SaaS boundary

`finance.club.expenses.create` is assigned to President/Admin, Secretary, and Treasurer. Normal Members receive no access. Middleware and service validation independently enforce active same-club membership and tenant-owned accounts.

No vendor master, accounts payable, approval chain, tax, attachment, transfer, reimbursement, recurring expense, or manual journal-entry workflow is introduced. Tenant facts remain tenant data; ledger integrity remains a product invariant.

## PostgreSQL concurrency verification

The production-shaped PostgreSQL verification ran against fresh disposable database `lcb_3iizv_expense_concurrency_test`: **2 passed / 38 assertions / 0 failed / 0 skipped**. Two independent PHP worker processes proved exact replay serialization and changed-payload conflict behavior. The suite also proved tenant-scoped UUID reuse, cross-club account rejection, one expense/one journal/two lines per winning event, and full rollback when journal fund validation fails. PostgreSQL exposed and enforced the club/journal foreign keys, tenant idempotency unique constraint, positive-paise check, and INR check. The disposable database was removed after verification; protected database `lc_beenachi_connect_reconstructed_20260727` remained present and was not used as a test target.

## Traceability and statement integration

The expense public ID is the journal source ID. The expense stores the resulting journal relationship, and the journal metadata retains non-accounting audit context. Because the journal uses canonical accounts, classifications, and funds, the existing Club Account Statement includes it without expense-specific projection logic.
