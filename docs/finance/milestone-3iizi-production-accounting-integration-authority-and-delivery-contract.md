# Milestone 3IIZI — Production Accounting Integration Authority and Delivery Contract

## Decision and status

Milestone 3IIZI is a docs-and-tests-only decision, scope, and implementation-authorization gate based on verified commit `bc5ba7967591ffef46736691bd6b8beef0e42fba`. Milestone 3IIZH — Charge and Payment Accounting Integration is the completed dependency. Its accounting integration remains disabled by default, its internal configuration service remains limited to controlled setup and verification, and production configuration authority is not currently implemented.

This contract approves dual control and a protected club-scoped backend API as the first production delivery surface. It authorizes Milestone 3IIZJ to implement only the narrow workflow described here. This milestone introduces no runtime behavior.

## Approved production authority

Only a President or Treasurer with an active membership in the affected club may participate. The Secretary receives no accounting-configuration authority by default. Every production configuration action requires two distinct authenticated users in the same club: one proposer and one independent approver. The same user must never perform both roles, even if that user holds multiple appointments.

Dual control applies to:

- creating or replacing a source mapping;
- activating charge accounting integration;
- activating payment accounting integration;
- revoking a mapping or activation;
- replacing a revoked mapping or reactivating a revoked source;
- changing a coverage start date; and
- changing a mapping version.

The approved future permission is `finance.accounting.integration.configure`. Possession of this permission alone is insufficient: the user must also have an eligible active officer appointment in the same club and satisfy separation of duties. This milestone does not seed, grant, or assign the permission to any default role. There is no administrator bypass and no single-user approval.

## Authorized delivery boundary

Milestone 3IIZJ may deliver a narrow authenticated, club-scoped backend API under the candidate route family `/api/v1/clubs/{club}/finance/accounting/integration`. No frontend is authorized for that first delivery.

Candidate operations are contractual guidance for 3IIZJ, not registered routes in 3IIZI:

```text
GET    /readiness
GET    /configuration
POST   /mapping-proposals
POST   /activation-proposals
POST   /revocation-proposals
POST   /proposals/{proposal}/approve
POST   /proposals/{proposal}/reject
```

Readiness is read-only and must clearly distinguish `Ready`, `Not ready`, `Already active`, `Revoked`, `Missing mappings`, `Invalid mappings`, `Unintegrated source inside coverage`, `Reconciliation defect`, `Closed or missing accounting period`, and `Opening package not posted`.

## Approval prerequisites

Before approving an activation proposal, the future workflow must atomically confirm that:

- the club accounting cutover package is posted;
- the opening journal exists and remains valid;
- the accounting period covering the activation date is open;
- `MEMBER-RECEIVABLES` exists and is active;
- every required charge category and supported payment mode has exactly one valid mapping;
- all mapped accounts belong to the same club and use INR;
- charge mappings use approved income accounts and payment mappings use approved cash or bank accounts;
- no unintegrated source exists inside the proposed coverage boundary;
- the existing accounting reconciliation service reports no blocking defect;
- mapping version and coverage start date are explicit; and
- proposer and approver are distinct authorized officers.

Any failure stops approval without partial persistence. Activation approval must call the existing reconciliation service; a blocking reconciliation result prevents activation.

## Proposal lifecycle and stale decisions

The approved lifecycle is:

```text
pending → approved
pending → rejected
pending → withdrawn
```

Approved, rejected, and withdrawn proposals are terminal and immutable. A stale proposal cannot be approved if any material value changed after creation: club, source type, source key, ledger account, mapping version, effective date, expiry date, coverage start date, activation identity, existing terminal event, proposer, or proposal checksum.

## Idempotency and concurrency

The implementation must use a canonical proposal UUID and client request UUID, define exact replay behavior and changed-payload conflict behavior, apply optimistic locking and club-level serialization, and retain database uniqueness as the final safeguard. It must create no duplicate proposal approval, activation, mapping, or revocation event and must never persist partial configuration state.

## Audit contract

Every proposal and decision must produce an append-only audit record containing the club, source type, exact proposed configuration, proposer, approver or rejector, decision and reason, before and after state, canonical checksum, request identity, timestamp, and resulting activation, mapping, or terminal-event identity. Audit records must not contain private member payment data.

## Scope guardrails

Milestone 3IIZI adds only this document and its focused feature test. It adds no migration, model, controller, request, resource, service, route, permission seed, command, job, scheduler, frontend, deployment change, or other runtime file. It does not implement backfill, automatic activation, direct journal mutation, expenses, donations, fellowship, fundraising, or Kuri/Chit accounting.

Club Account Statement projection, statement APIs, and statement frontend work remain blocked until production accounting activation is safely operational and verified.

## Next milestone

Successful closure authorizes **Milestone 3IIZJ — Production Accounting Integration Configuration and Activation Narrow Implementation**. Milestone 3IIZJ must implement only the approved backend authority and configuration workflow.
