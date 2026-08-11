# 15 — Reversal Readiness Checklist

**Template state:** Blank, non-authoritative, and dry-run-only. No reversal, replacement, database restoration, posting, or operational action is authorized or executed.

| Field | Blank value |
|---|---|
| Original package identity | `[PACKAGE-ID]` |
| Original journal reference | `[JOURNAL-REFERENCE]` |
| Linked-reversal method | `[METHOD]` |
| Separately approved replacement-package method | `[METHOD]` |
| Integration-stop procedure | `[PROCEDURE-REFERENCE]` |
| Authoritative-statement stop procedure | `[PROCEDURE-REFERENCE]` |
| Incident-owner role | `[ROLE]` |
| Business approval role | `[PRESIDENT/BUSINESS-APPROVER]` |
| Accounting approval role | `[INDEPENDENT-ACCOUNTING-REVIEWER]` |
| Technical execution role, if authorized | `[TECHNICAL-OPERATOR-IF-AUTHORIZED]` |
| Evidence-preservation method | `[METHOD]` |
| Rehearsal status | `[NOT-RUN/PASS/FAIL]` |
| Unresolved blockers | `[NONE/EXCEPTION-REFERENCES]` |

## Mandatory controls

- [ ] Before posting, rollback means preventing activation.
- [ ] After posting, rollback means one linked full reversal and a separately approved replacement.
- [ ] No posted journal header or line is edited or deleted.
- [ ] Original, reversal, and replacement histories remain visible and linked.
- [ ] Downstream integrations stop until resolution and revalidation.
- [ ] Authoritative statements stop until resolution and revalidation.
- [ ] Evidence, approvals, versions, and checksums are preserved.
- [ ] Database restoration is prohibited for ordinary correction and reserved for separately authorized disaster recovery.
- [ ] Reversal readiness has no unresolved amount, identity, mapping, evidence, duplication, privacy, or authorization blocker.

No reversal may be executed during this documentation milestone.
