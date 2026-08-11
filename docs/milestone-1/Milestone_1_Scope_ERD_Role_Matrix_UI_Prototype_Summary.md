# Milestone 1 Scope, ERD, Role Matrix & UI Prototype Summary

## Status

```text
Milestone 1 — COMPLETED
```

Milestone 1 established the product direction, V1 scope, role model, UI-first process, and prototype approval path for LC Beenachi Connect.

## Purpose

Milestone 1 created the project foundation before backend implementation.

The main purpose was to avoid rebuilding the old AppSheet confusion in a new web app by first clarifying:

- V1 scope
- Deferred scope
- Role-wise access
- Workflow logic
- Finance and ledger direction
- Fellowship sponsorship flow
- Member privacy boundaries
- Mobile-first UI expectations
- Prototype-before-development approval gate

## UI-First Decision

The approved process is:

```text
1. UX flow
2. Wireframes / prototype
3. Visual UI direction
4. Clickable prototype
5. Development
6. Final polish
```

Backend and database development must not start only from assumptions. The approved UI/UX flow should guide:

- Routes
- Screens
- Forms
- Permissions
- Workflow logic
- Reports
- Member-facing wording

## Version 1 Scope

V1 includes:

- Member management
- PST role handling
- Member profile
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
- Club account statement
- Fund classification
- Reports and tools
- Blood group capture
- Blood donor consent
- Basic authorized donor search

## Deferred Scope

Later phases should include:

- Full Kuri/chit module
- WhatsApp automation
- Payment gateway
- QR public profile card
- NFC/tap-to-share business card
- Advanced accounting
- SaaS billing
- Multi-club tenant management

## Blood Donor Scope Freeze

V1 includes:

- Blood group capture
- Donor consent capture
- Basic authorized donor search for consented donors only

V2 should include:

- Blood request workflow
- Compatible blood group suggestions
- Emergency request tracking

## Digital Card Scope Freeze

V1 includes:

- Basic in-app digital membership card

Later phases:

- V2: QR public profile card
- V3: NFC/tap-to-share business card

## Kuri / Chit Scope Freeze

V1:

- Keep the database design future-ready

V2:

- Build the full Kuri/chit module only after separate approval

## Core Roles

Approved V1 roles:

- President
- Secretary
- Treasurer
- Board Member
- Committee Chair
- Member
- Auditor / Read-only

Future platform role:

- Platform Super Admin, future SaaS/internal platform owner only

## PST Rule

President, Secretary, and Treasurer are not separate person records.

They are members with additional role assignments.

Expected model:

```text
Member Profile
+ Role
+ Term / Lionistic Year
+ Designation
+ Committee or board responsibility
+ Permissions
```

PST users must retain their personal member access:

- My Profile
- My Digital Card
- My Dues
- My Fellowship
- My Documents
- My Celebrations
- Blood Donor Consent

## Member Privacy Rule

Members should see only their own private data.

Members should not see:

- Admin workflow controls
- Other members' dues
- Board-only documents
- Treasurer/internal finance records
- Donor contact details without consent
- Internal permission/debug labels

## Fellowship Workflow

Approved workflow:

```text
Select Month
→ Show Eligible Queue Members
→ Select Sponsors
→ Confirm Group
→ Generate Sponsor Rows
→ Prepare Ledger Impact
→ Track Payments
→ Close Month
```

Member-facing fellowship wording:

- Monthly Family Fellowship
- My Fellowship
- Family Fellowship Contribution

Internal/admin wording:

- Fellowship Sponsorship
- Guided Sponsor Workflow
- Sponsor queue
- Sponsor payment tracking

## Finance Direction

Approved finance concepts:

- One central club account ledger
- Fund sections are classifications, not separate disconnected ledgers
- Member ledger is separate from club account statement
- Special collection creates member dues first
- Special collection becomes income only when payment is received
- Collection box / donation is club income only and does not create member dues

Fund sections:

- General Club Fund
- Service Activity Fund
- Fellowship & Member Activity Fund

## Document Access Direction

Member-visible:

- General body minutes
- Annual plans approved for members
- Member guidelines
- Public/member circulars
- Forms intended for members

Restricted:

- Board meeting minutes
- Board-only documents
- Treasurer/internal finance documents
- Committee-private working documents

## Milestone Roadmap

```text
Milestone 1A
Clickable UI prototype
Status: Completed

Milestone 1B
Technical specification + backend readiness
Status: Next

Milestone 2
Laravel + PostgreSQL backend foundation

Milestone 3
Real authentication + role permissions

Milestone 4
Core modules implementation

Milestone 5
Finance / ledger / receipts

Milestone 6
Notifications / reminders

Milestone 7
Reports / exports / admin controls

Milestone 8
Testing + LC Beenachi live pilot
```

## Freeze Decision

Milestone 1 is considered complete.

No further scope changes should be added unless they are:

- Needed to correct a serious contradiction
- Required for backend readiness
- Raised by a valid developer clarification
- Approved before Milestone 2 starts

