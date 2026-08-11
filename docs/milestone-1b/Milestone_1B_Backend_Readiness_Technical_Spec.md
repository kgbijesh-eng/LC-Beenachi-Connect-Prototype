# Milestone 1B Backend Readiness & Technical Specification

## Status

Milestone 1A is closed, reviewed, pushed, and tagged:

- Tag: `milestone-1a-ui-prototype-reviewed`
- Prototype status: approved for backend-readiness planning
- Build status: passed
- Audit gate: no high-severity vulnerability
- Known audit note: two moderate PostCSS advisories through the Next dependency chain. Do not run `npm audit fix --force` because the suggested fix may downgrade Next and break the prototype.

Milestone 1B is a documentation and technical specification milestone only.

## Scope Rule

Milestone 1B must stay docs-only until approved.

Do not add:

- Laravel application code
- PostgreSQL migrations
- API controllers
- Authentication implementation
- Production data models
- Frontend production wiring
- Real payment, reminder, upload, or notification logic

## Objective

Prepare the backend implementation foundation for LC Beenachi Connect V1 based on the approved Milestone 1A clickable prototype.

The goal is to define:

- Final V1 backend scope
- Entity relationship model
- Role and permission matrix
- API surface
- Data migration approach
- Ledger/accounting model
- Security and privacy requirements
- Development milestones for Milestone 2 onward

## Approved Stack Direction

- Frontend: Next.js, React, TypeScript, mobile-first PWA
- Backend: Laravel API
- Database: PostgreSQL
- Design/prototype source: coded Next.js prototype from Milestone 1A
- Deployment target: production-ready hosted web app/PWA

## V1 Scope Freeze

V1 should include:

- Member management
- PST and role handling
- Member profile and personal member area
- Basic in-app digital member card
- Events and meetings
- Announcements
- Documents
- Projects and activities
- Celebrations
- Fellowship sponsorship workflow
- Fellowship queue
- Member ledger and dues
- Payments and receipts
- Special collection
- Club account statement with fund classification
- Reports and tools
- Blood group and donor consent capture
- Basic authorized donor search

Later phases should include:

- Full Kuri/chit module
- WhatsApp automation
- Payment gateway
- QR public profile card
- NFC/tap-to-share business card
- Advanced accounting
- SaaS billing and tenant management

## Core Backend Principle

The backend must follow the approved UI/UX flow.

Routes, forms, permissions, ledger behavior, report outputs, and role visibility should be derived from the prototype screens and approved workflows, not from database assumptions alone.

## Primary Roles

V1 roles:

- President
- Secretary
- Treasurer
- Board Member
- Committee Chair
- Member
- Auditor / Read-only

Future platform role:

- Platform Super Admin, SaaS owner only, not a normal LC Beenachi club role

## PST Rule

President, Secretary, and Treasurer are not separate person records.

They are normal member records with role assignments.

Backend model should support:

- Member
- Role assignment
- Term / lionistic year
- Designation
- Committee or board responsibility
- Permission set

PST users must retain access to their own member area:

- My Profile
- My Digital Card
- My Dues
- My Fellowship
- My Documents
- My Celebrations
- Blood Donor Consent

## Data Model Draft

Recommended core tables:

- members
- roles
- member_role_assignments
- committees
- committee_members
- member_profiles
- member_digital_cards
- blood_donor_consents
- events
- meetings
- announcements
- documents
- document_permissions
- projects
- project_reports
- celebrations
- fellowship_months
- fellowship_queue_entries
- fellowship_sponsors
- member_ledger_entries
- payment_receipts
- special_collections
- club_account_entries
- fund_sections
- report_exports
- audit_logs

Future-ready tables or reserved design:

- kuri_groups
- kuri_members
- kuri_transactions
- whatsapp_message_logs
- payment_gateway_transactions
- tenant_accounts
- subscription_plans

## Member Ledger Rule

Member ledger handles member-facing dues and payments.

Examples:

- Monthly dues
- Fellowship contribution due
- Special collection due
- Member payment received

Member-facing wording should avoid accounting language where possible.

Admin/Treasurer view can show:

- Debit
- Credit
- Balance impact
- Receipt number
- Linked module

Member view should show:

- Current Dues
- Last Payment Made
- Pending Items
- Recent Payments
- Advance Balance, where applicable

## Club Account Ledger Rule

The app should use one central club account ledger.

Fund sections are classifications for reporting, not separate disconnected ledgers.

Initial fund sections:

- General Club Fund
- Service Activity Fund
- Fellowship & Member Activity Fund

Accounting rule:

Opening + Income - Expense = Closing

Special collection rule:

- Creating a special collection adds dues to selected member accounts.
- It is not club income until payments are received.

Voluntary collection box rule:

- Do not create member dues.
- Do not create receivables.
- Record as club income only when cash is counted and verified.
- Typical fund: Fellowship & Member Activity Fund.

## Fellowship Sponsorship Backend Rule

The approved workflow:

1. Select month
2. Show suggested queue members
3. Select sponsors
4. Confirm group
5. Generate sponsor rows
6. Prepare ledger impact
7. Track payments
8. Close month

Backend must support:

- Queue position
- Eligibility
- Skipped/deferred reason
- Selected sponsor group
- Sponsor contribution amount
- Payment status
- Month closure
- Queue movement history

Secretary may coordinate and view workflow status.

Treasurer handles payment posting.

Member view must show only:

- Whether the member is a sponsor
- Own contribution
- Own payment status
- Meeting date
- Co-sponsor names
- No admin workflow controls

## Document Access Rule

Normal member access:

- General body minutes
- Annual plan approved for members
- Member guidelines
- Public/member circulars
- Forms intended for members

Restricted access:

- Board meeting minutes
- Board-only documents
- Internal committee working documents
- Treasurer/internal finance documents
- Admin templates unless published for members

Backend must enforce document visibility and role permissions.

## Blood Donor Consent Rule

V1 includes:

- Blood group capture
- Donor consent capture
- Basic authorized donor search for consented donors only

V2 should handle:

- Full blood request workflow
- Compatible group suggestions
- Emergency request tracking

Privacy:

- Hidden contact must not be exposed.
- Consent status must be respected in all reports and searches.

## Reports Rule

Reports should be generated from approved backend data models, not hardcoded UI assumptions.

Report categories:

- Member Reports
- Finance / Ledger Reports
- Fellowship Reports
- Events & Meetings Reports
- Club Activity / Service Reports
- Celebration Reports
- Blood Donor Consent Report
- Document Reports

Reports must respect role permissions.

## API Planning

Recommended API groups:

- `/api/auth`
- `/api/me`
- `/api/members`
- `/api/roles`
- `/api/committees`
- `/api/events`
- `/api/meetings`
- `/api/announcements`
- `/api/documents`
- `/api/projects`
- `/api/celebrations`
- `/api/fellowship`
- `/api/member-ledger`
- `/api/payments`
- `/api/club-account`
- `/api/reports`
- `/api/blood-donors`
- `/api/settings`
- `/api/audit-logs`

Milestone 1B should define request/response shapes before implementation starts.

## Security Requirements

Minimum requirements:

- Role-based access control
- Server-side permission checks
- Audit logs for sensitive changes
- Secure file upload plan
- PII protection
- Donor consent enforcement
- Backup and restore plan
- Environment variable strategy
- Source code handover clarity

## Migration Planning

Migration planning should cover current AppSheet data sources:

- Member records
- Dues and payments
- Events and meetings
- Announcements
- Documents
- Fellowship sponsorship history
- Donor consent fields
- Activity/project records

Milestone 1B should define:

- Source data inventory
- Field mapping
- Data cleanup needs
- Import order
- Validation checks
- Rollback approach

## Milestone 1B Deliverables

Milestone 1B should produce:

- Final backend-ready V1 scope
- ERD/database schema draft
- Role and permission matrix
- API endpoint specification
- Ledger/accounting rules document
- Migration mapping checklist
- Security and privacy checklist
- Milestone 2 implementation plan

## Milestone 2 Entry Criteria

Do not start Milestone 2 until Milestone 1B is reviewed and approved.

Milestone 2 may start only after:

- ERD is approved
- Role matrix is approved
- API structure is approved
- Ledger rules are approved
- Migration plan is approved
- Security baseline is approved

## Milestone 1B Status

Status: APPROVED

Current status:

```text
Milestone 1B — APPROVED / DOCS ONLY / BACKEND READINESS
```

Next action:

```text
Proceed to Milestone 1C — Laravel Backend Foundation Setup.
```

Backend code has not started in Milestone 1B.
