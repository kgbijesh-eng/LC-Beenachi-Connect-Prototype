# Milestone 3IIZS — DR-033 tenant-scoped opening equity policy resolution

**Decision:** APPROVED as SaaS policy on 10 August 2026. This defines how a tenant chooses an opening equity/net-asset account; it does not choose an account, designation, or amount for any tenant.

## Policy

- A package may name exactly one active equity account belonging to the same club and using the package currency.
- The account comes from that club's chart and is bound to the exact reviewed package and checksum. Clubs may use different codes and evidenced amounts.
- Assets and liabilities must reconcile with zero difference. The equity line is explicit package evidence, never a generated balancing plug, suspense line, or inferred residual.
- Cross-club, inactive, non-equity, currency-mismatched, stale, and changed-retry selections fail closed.
- DR-053 remains unchanged: the appointed independent CA records the accounting selection and the President supplies separate business approval. This milestone creates no authority or administration surface.

## Implementation boundary

The persisted `dr033_classification` and review `selected_classification` names remain for compatibility but hold the canonical tenant ledger-account code. Service and database guards accept a normalized account code instead of two global product codes. Lookup is scoped by `club_id`, account type, active state, and package currency. Composite foreign keys and activation locks are unchanged.

The ledger currently constrains ledger and journal currencies to INR and bootstrap creates INR packages. DR-033 validation now compares against package currency rather than hardcoding INR, but additional currencies require a broader ledger/schema decision outside this resolution. No unsupported multi-currency claim is made.

Earlier milestone documents that call DR-033 Pending are historical statements at their execution date. This decision supersedes that status without retroactively approving any package.

## Accounting and statement behavior

Activation still posts only explicit schedule rows after the overall journal and each fund balance to zero. Statement projection still classifies the opening journal as `opening_balance`, separately from period movement. Production accounting activation semantics are unchanged.

## Non-goals

No Beenachi-specific account, amount, officer designation, package approval, authority record, route, API, UI, console surface, or historical backfill is introduced. DR-054's fund structure, DR-053's independent review, and existing replay, locking, reversal, and one-time activation rules remain intact.
