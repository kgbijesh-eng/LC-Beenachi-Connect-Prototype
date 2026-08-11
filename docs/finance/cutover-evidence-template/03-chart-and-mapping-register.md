# 03 — Chart and Mapping Register

**Template state:** Blank, non-authoritative, and dry-run-only. Catalog semantics do not provision or activate accounts.

## Package-level versions

| Field | Blank value |
|---|---|
| Approved catalog version | `[CHART-VERSION]` |
| Approved source/category mapping version | `[MAPPING-VERSION]` |
| Verified launch subset version | `[SUBSET-VERSION]` |

## Selected account and mapping rows

| Account code | Name | Type | Normal balance | Activation justification | Opening-position status | Source workflow | Debit/credit mapping | Mapping version | Reconciliation-owner role | Privacy | Evidence reference |
|---|---|---|---|---|---|---|---|---|---|---|---|
| `[ACCOUNT-CODE]` | `[ACCOUNT-NAME]` | `[ASSET/LIABILITY/EQUITY/INCOME/EXPENSE]` | `[DEBIT/CREDIT]` | `[APPROVED-JUSTIFICATION]` | `[SUPPORTED-ZERO/SUPPORTED-NONZERO/UNAVAILABLE]` | `[APPROVED-WORKFLOW]` | `[APPROVED-MAPPING]` | `[MAPPING-VERSION]` | `[ROLE]` | `[CLASSIFICATION]` | `[EVIDENCE-REFERENCE]` |

## Mandatory controls

- [ ] Only the smallest verified subset required by approved launch workflows is selected.
- [ ] `CASH-ON-HAND` is selected only if verified physical cash exists.
- [ ] Each verified real bank account has one separate non-sensitive alias.
- [ ] No generic combined-bank account exists.
- [ ] One `MEMBER-RECEIVABLES` control account is used with exact private reconciliation.
- [ ] Only approved and mapped charge categories activate credit accounts.
- [ ] Unsupported and future-workflow accounts remain inactive.
- [ ] A supported zero opening position has no zero-value journal line.
- [ ] No suspense account exists.
- [ ] No generic Other account exists.
- [ ] Direct manual posting is prohibited.
- [ ] Exactly one economically correct opening-equity account may be selected only after DR-033 is separately resolved; this blank template makes no selection.
