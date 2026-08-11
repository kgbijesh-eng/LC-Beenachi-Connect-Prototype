# Milestone 3IJAB — Cross-Source Finance Replay and Provenance Integrity Hardening

## Purpose

An exact request digest proves only that the caller repeated the same canonical request. It does not prove that the persisted source, journal, and journal lines still describe that economic event. This milestone therefore makes expense and internal-transfer replay fail closed unless both request identity and persisted accounting provenance remain canonical.

## Strengthened invariant

Every accepted exact replay must establish the tenant, source identity and event, source-to-journal binding, idempotency identity, journal lifecycle and INR currency, exact line count, source-specific account directions, amounts, and canonical fund.

Expense creation continues to post a debit to the bound eligible expense account and a credit to the bound cash/bank account. A legitimately reversed expense remains replayable only when its original posting and linked reversal provenance are both canonical.

Internal transfer continues to post a debit to the bound destination cash/bank account and a credit to the bound source cash/bank account.

Corrupt persisted provenance is never repaired during replay and returns the existing source-specific idempotency conflict.

## Existing sources reviewed

Ordinary-income replay already validates source/journal binding, tenant, source type/ID/event, idempotency key, lifecycle, INR, exact accounts, directions, amounts, and fund. Expense reversal validates both its original expense accounting and linked reversal replay. Charge and member-payment integration validates frozen source bindings, coverage, activation/mapping, integration checksum, journal identity, exact lines, allocation links, and terminal reversal provenance. Those contracts are unchanged.

## Scope

This is integrity hardening only. It adds no transaction type, correction workflow, public journal surface, policy decision, permission, accounting-period behavior, currency, fund, statement behavior, frontend behavior, or dependency change.

## Verification record

PostgreSQL 17.5 verification ran against the fresh-migrated disposable database `lcb_3ijab_replay_provenance_hardening_test`: 5 tests and 72 assertions passed with no failures or skips. The suite covered independent-worker exact and changed replay races, tenant UUID isolation, cross-club account rejection, atomic rollback, representative persisted-corruption replay for both sources, and canonical replay after a linked expense reversal. Accounting directions and the single-fund, equal-amount two-line effects remained unchanged.

The complete affected SQLite finance regression passed 221 tests and 1,672 assertions with no failures or skips. All changed PHP files passed PHP 8.4 syntax checks. The disposable PostgreSQL database was then removed; its remaining count was zero, while the protected `lc_beenachi_connect_reconstructed_20260727` database remained present. The application role retained `CREATEDB = false` and `SUPERUSER = false`.
