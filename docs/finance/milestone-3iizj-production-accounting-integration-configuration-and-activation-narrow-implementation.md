# Milestone 3IIZJ — Production Accounting Integration Configuration and Activation Narrow Implementation

## Identity and authority

Milestone 3IIZJ started on branch `milestone-3iizj-production-accounting-integration-delivery` at merged base commit `d1b62242af15ecaf2306461ff01c75e54f316101`. It implements the backend-only delivery authorized by the binding Milestone 3IIZI production accounting integration authority and delivery contract.

The exact permission is `finance.accounting.integration.configure`. It is provisioned in the permission catalogue but assigned to no default role. Authentication, the exact permission, an active same-club membership, and a current `president-admin` or `treasurer` appointment are all required. Secretary, Member, inactive, cross-club, administrator-only, and `finance.manage`-only access fail closed. Proposer and decision-maker must be different authenticated users.

## API

The authenticated club-scoped route family is `/api/v1/clubs/{club}/finance/accounting/integration`:

- `GET /readiness`
- `GET /configuration`
- `POST /mapping-proposals`
- `POST /activation-proposals`
- `POST /revocation-proposals`
- `POST /proposals/{proposal}/approve`
- `POST /proposals/{proposal}/reject`
- `POST /proposals/{proposal}/withdraw`

Reads return safe configuration metadata only. They do not create proposals, audit events, mappings, activations, journals, or repairs. Readiness publishes the stable vocabulary `Ready`, `Not ready`, `Already active`, `Revoked`, `Missing mappings`, `Invalid mappings`, `Unintegrated source inside coverage`, `Reconciliation defect`, `Closed or missing accounting period`, and `Opening package not posted`. Private member, payment, receipt, balance, evidence, and authentication data are excluded.

## Proposal and decision contract

The new club-scoped proposal schema preserves the canonical UUID, controlled proposal/source types, safe source key, ledger account, mapping version, effective/expiry/coverage dates, activation/mapping/terminal-event snapshot, proposer and appointment context, client request UUID, canonical checksum, status, lock version, reference, reason, and terminal timestamp. A separate append-only decision row records exactly one `approved`, `rejected`, or `withdrawn` terminal decision with before/after state and resulting record identity.

Migration rollback restores the original activation uniqueness only when no historical reactivation generation exists. If reactivation data exists, rollback fails before dropping any 3IIZJ table so it cannot silently discard proposals or strand an activation history that the earlier schema cannot represent.

The exact lifecycle is `pending → approved`, `pending → rejected`, or `pending → withdrawn`. Only the proposer may withdraw. The proposer cannot approve or reject. Terminal proposals cannot transition again and decision rows are immutable. Club-scoped request uniqueness provides exact replay; changed-payload replay conflicts. Canonical checksums use normalized material values rather than request JSON ordering.

## Mapping, activation, and revocation

Mapping approval delegates to the existing `AccountingIntegrationConfigurationService`. Charge categories accept only active same-club INR income accounts. Payment modes accept only active same-club INR cash/bank asset accounts. Mapping replacement appends a revocation before creating the approved replacement in the same outer transaction.

Activation approval reuses the existing configuration owner and reconciliation service. It requires one posted cutover package and valid opening journal, one open covering period, one active `MEMBER-RECEIVABLES` account, exactly one valid mapping for every supported charge category and payment mode under the explicit mapping version, same-club INR accounts, no blocking reconciliation defect, an explicit coverage date, and no current conflicting activation. The historical activation uniqueness constraint is replaced by a lookup index so a terminal activation can be followed by a new approved activation while the existing current-activation resolver continues to reject ambiguity.

Mapping and activation revocations use the existing append-only terminal-event methods. They do not delete historical configuration, detach source/journal links, reinterpret historical coverage, or reverse journals. Replacement and reactivation require a new proposal.

## Staleness, concurrency, audit, and atomicity

Approval locks the club and proposal, checks optimistic lock version and pending state, recomputes the checksum, revalidates proposer authority context, and compares the current terminal-event identity with the proposal snapshot. Proposal request identity and terminal decision uniqueness remain database safeguards. Existing club, mapping, activation, source, and journal locks are not bypassed.

Proposal creation and every terminal decision write an audit record inside the same transaction. Audit contains safe proposal configuration, actors, decision/reason, before/after state, checksum, client request identity, and resulting record identity. It excludes private payments, member balances, receipts, bank secrets, evidence contents, and tokens. Any configuration, decision, or audit failure rolls back the complete transaction.

## Files changed

- `backend/database/migrations/2026_08_04_000002_add_accounting_integration_proposal_workflow.php`
- `backend/app/Models/ClubAccountingIntegrationProposal.php`
- `backend/app/Models/ClubAccountingIntegrationProposalDecision.php`
- `backend/app/Services/Finance/AccountingIntegrationProposalService.php`
- `backend/app/Services/Finance/AccountingIntegrationConfigurationService.php`
- `backend/app/Http/Controllers/Api/V1/Finance/AccountingIntegrationController.php`
- `backend/app/Http/Requests/Finance/StoreAccountingMappingProposalRequest.php`
- `backend/app/Http/Requests/Finance/StoreAccountingActivationProposalRequest.php`
- `backend/app/Http/Requests/Finance/StoreAccountingRevocationProposalRequest.php`
- `backend/app/Http/Requests/Finance/DecideAccountingIntegrationProposalRequest.php`
- `backend/app/Http/Resources/Finance/AccountingIntegrationProposalResource.php`
- `backend/database/seeders/DefaultRolesAndPermissionsSeeder.php`
- `backend/routes/api.php`
- `backend/tests/Feature/DefaultRolesAndPermissionsSeederTest.php` (exact permission count and no-default-grant assertion)
- `backend/tests/Feature/Milestone3iiuAnnouncementDecisionPermissionFoundationTest.php` (forward-compatible exact permission catalogue assertion)
- `backend/tests/Feature/Milestone3nPaymentRecordingServiceValidationAndClosureTest.php` (exact authorized finance mutation route allowlist)
- `backend/tests/Feature/Milestone3iiziProductionAccountingIntegrationAuthorityAndDeliveryContractTest.php` (historical 3IIZI commit check and exact authorized 3IIZJ route family)
- `backend/tests/Feature/Milestone3iizjProductionAccountingIntegrationConfigurationAndActivationNarrowImplementationTest.php`
- this document.

## Blocked scope and verification

No frontend, Club Account Statement, statement API/projection/export, direct journal route, automatic activation, single-user path, broad role grant, command, job, scheduler, backfill, expense, donation, fellowship, fundraising, Kuri/Chit integration, deployment change, or generated file is included.

Validation used PHP 8.4.23. The focused 3IIZJ test passed 9 tests and 70 assertions; 3IIZG passed 25 tests and 120 assertions; 3IIZH passed 33 tests and 173 assertions. The 3IIZI route assertions pass and its intentionally preserved dirty-working-tree guard fails while the authorized 3IIZJ files remain uncommitted. The full suite passed 1,989 of 2,016 tests with 24,578 assertions; all 21 failures are historical dirty-working-tree guards, and 6 isolated PostgreSQL tests were skipped because that environment was not enabled. PostgreSQL-only competing-decision verification remains gated by the repository's isolated PostgreSQL test environment.

The recommended next milestone is a focused 3IIZJ validation and closure review. Club Account Statement delivery remains blocked until production activation is operationally verified.
