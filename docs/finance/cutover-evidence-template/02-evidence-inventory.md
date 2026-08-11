# 02 — Evidence Inventory

**Template state:** Blank, non-authoritative, non-sensitive, and dry-run-only. Do not enter protected filenames, storage paths, personal identities, bank details, or operational identifiers in Git.

| Evidence reference | Evidence type | Related account/control | Source-owner role | Effective date | Classification | Storage classification | Authorized roles | Verification status | Retention category | Legal hold | Exception reference |
|---|---|---|---|---|---|---|---|---|---|---|---|
| `[EVIDENCE-REFERENCE]` | `[TYPE]` | `[ACCOUNT-CODE/CONTROL]` | `[ROLE]` | `[DATE]` | `[NON-SENSITIVE/RESTRICTED]` | `[APPROVED-RESTRICTED-STORAGE]` | `[ROLE-LIST]` | `[PENDING]` | `[PERMANENT-PROVENANCE/PROVISIONAL-EIGHT-LIONS-YEARS/EXTENDED]` | `[NONE/ACTIVE]` | `[EXCEPTION-ID]` |

## Controls

- [ ] Every included balance has an independent evidence reference.
- [ ] Unavailable or unresolved evidence blocks activation and is never treated as zero.
- [ ] The non-sensitive manifest identifies evidence without exposing protected details.
- [ ] Restricted evidence is least-privilege, club-scoped, and tenant-isolated.
- [ ] Retention or deletion never removes posted journals, approvals, checksums, references, or permanent audit provenance.
- [ ] Expired evidence is not automatically deleted; evaluation, authorization, scope, and logging are required.
