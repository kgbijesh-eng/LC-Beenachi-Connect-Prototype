# Milestone 3CS - Member Profile Documents Implementation Authorization

## Status

Milestone 3CS is a docs/tests-only implementation authorization milestone.

It converts the 3CR Member Profile Documents scope gate into a precise future implementation authorization boundary.

It does not implement the module.

It does not change production code.

It does not edit app/page.tsx.

It does not change frontend runtime files.

It does not change backend runtime files or API behavior.

It does not create migrations, models, routes, controllers, services, requests, resources, policies, middleware, seeders, config, or package changes in 3CS.

It does not add file upload, file storage, S3/local disk integration, OCR, PDF generation, finance behavior, deployment/SaaS behavior, or client-specific behavior.

It does not change older milestone tests.

## Purpose of 3CS

The purpose of 3CS is to authorize only a future narrow implementation lane for Member Profile Documents if later explicitly approved.

3CS records the boundary for a future metadata-only backend foundation.

3CS must not create migrations, models, routes, controllers, services, requests, resources, frontend screens, upload flows, storage flows, OCR, or PDF behavior.

## Base

Base milestone:

- Milestone 3CR - Member Profile Documents Scope Gate

Base branch:

- milestone-3cr-member-profile-documents-scope-gate

Authorization branch:

- milestone-3cs-member-profile-documents-implementation-authorization

Verified base hash:

- 36ebe66de471d74f2722a4d2ec7bad2a5a9c59f3

## Allowed files

Only these files are allowed in 3CS:

- backend/docs/milestone_3cs_member_profile_documents_implementation_authorization.md
- backend/tests/Feature/Milestone3csMemberProfileDocumentsImplementationAuthorizationTest.php

Read-only inspection of existing frontend, backend, docs, and tests is allowed.

## Authorization focus

3CS authorizes only a future narrow backend implementation lane if later approved.

3CS does not authorize runtime implementation in 3CS.

3CS does not authorize frontend implementation in 3CS.

3CS does not authorize backend API implementation in 3CS.

3CS does not authorize migration/table creation in 3CS.

3CS does not authorize model creation in 3CS.

3CS does not authorize controller, request, resource, or service creation in 3CS.

3CS does not authorize storage, upload, OCR, PDF, messaging, finance, deployment, SaaS, or client-specific behavior.

## Relationship to 3CR scope gate

Milestone 3CR scoped Member Profile Documents as a future metadata-only, club-scoped, member-scoped, officer-managed module.

3CS preserves the 3CR scope gate boundary.

3CS converts the 3CR scope gate into implementation authorization for a future narrow database foundation only.

3CS does not broaden 3CR into upload, storage, OCR, PDF, frontend, messaging, finance, deployment, SaaS, or client-specific behavior.

## Member Data Import remains closed

The Member Data Import module remains closed.

Member import preview remains closed.

Member import commit remains closed.

3CS does not change member import behavior.

3CS does not change member import preview APIs.

3CS does not change member import commit APIs.

3CS does not add Excel import, CSV import, upload, parser, or storage behavior.

## Future Member Profile Documents concept

The future Member Profile Documents module may later support structured metadata records linked to members.

This authorization remains metadata-only.

This authorization must not authorize storing files.

Future document metadata may include:

- Member profile photo reference
- Membership form reference
- ID proof reference
- Address proof reference
- Consent form reference
- General member document reference
- Document type label
- Document status label
- Optional external/manual reference number
- Optional remarks
- Audit metadata

Metadata-only means the future implementation may store references, labels, statuses, remarks, and audit fields without storing, uploading, downloading, parsing, transforming, or serving physical files.

## Future database foundation authorization boundary

3CS may authorize a future narrow backend implementation that creates document metadata records only.

Future implementation may include, only if later approved:

- Database table for member document metadata
- Model for member document metadata
- Club-scoped ownership
- Member-scoped ownership
- Officer-managed metadata records
- Document type field
- Document status field
- External/manual reference field
- Remarks field
- Audit-friendly timestamps
- Creator/updater references if consistent with project patterns
- Read APIs for authorized roles
- Create/update APIs for authorized officers

No delete behavior is authorized unless explicitly scoped later.

## Future model and table authorization boundary

A future table may represent member document metadata only.

A future model may represent member document metadata only.

The future table/model must not represent uploaded files.

The future table/model must not represent stored blobs, storage paths, public URLs, signed URLs, or download endpoints.

The future table/model must not mutate finance, member import, committee, fellowship, activity, or event behavior.

## Future club-scoped requirement

Future Member Profile Documents behavior must be club-scoped.

Document metadata records must belong to a club context.

Users from one club must not access another club's member document metadata.

Cross-club access must fail closed.

## Future member-scoped requirement

Future Member Profile Documents behavior must be member-scoped.

Document metadata records must be linked to a specific member.

Document metadata must not become a generic club-wide file repository.

Document metadata must not be detached from member ownership unless a later scope gate explicitly authorizes a separate module.

## Future officer-managed requirement

Future Member Profile Documents behavior should be officer-managed.

President/Admin and Secretary may manage document metadata in a later implementation authorization.

Treasurer access remains restricted and should be read-only unless later explicitly authorized.

Normal Member may view only their own allowed document metadata if later authorized.

Normal Member must not view other members' document metadata.

Normal Member must not manage document metadata unless a later scope gate explicitly authorizes it.

## Cross-club fail-closed requirement

Future implementation must fail closed across clubs.

Unauthorized club access must not reveal document metadata.

Unauthorized club access must not create, update, delete, upload, download, or expose document metadata or files.

Frontend visibility must not be treated as security.

Backend authorization must remain the source of truth.

## Auditability requirement

Future document metadata records must be auditable.

Future scope should define created-by, updated-by, and timestamp expectations before implementation.

Creator/updater references may be used only if consistent with project patterns.

Future scope should consider status changes as auditable metadata.

Future scope should preserve accountability without adding file storage or file processing.

## Sensitive and private metadata protection

Sensitive/private metadata must be protected.

Future scope must consider which document type labels, status labels, references, and remarks are safe to expose.

Future scope must avoid public exposure of sensitive identity, address, consent, or membership metadata.

Future scope must treat optional remarks carefully because remarks may contain private information.

## Runtime implementation blocked in 3CS

Runtime implementation in 3CS is blocked.

Frontend implementation in 3CS is blocked.

Backend API implementation in 3CS is blocked.

Migration/table creation in 3CS is blocked.

Model creation in 3CS is blocked.

Controller/request/resource/service creation in 3CS is blocked.

Policy, middleware, provider, seeder, config, and package changes in 3CS are blocked.

Any production behavior change in 3CS is blocked.

## File upload and storage blocked

File upload is blocked.

File storage is blocked.

S3 integration is blocked.

Local disk storage integration is blocked.

Public file URLs are blocked.

Signed file URLs are blocked.

File download endpoint behavior is blocked.

File persistence is blocked.

No physical file should be stored, moved, parsed, transformed, or served by 3CS.

## OCR, PDF, messaging, and reminder behavior blocked

OCR is blocked.

PDF generation is blocked.

WhatsApp sending is blocked.

SMS sending is blocked.

Email sending is blocked.

Reminder behavior is blocked.

Notification behavior is blocked.

## Finance and adjacent module behavior blocked

Finance mutation is blocked.

Ledger mutation is blocked.

Payment mutation is blocked.

Receipt mutation is blocked.

Payment-history changes are blocked.

Member import changes are blocked.

Committee changes are blocked.

Fellowship changes are blocked.

Activity changes are blocked.

Event changes are blocked.

## Deployment, SaaS, and client-specific behavior blocked

Deployment implementation is blocked.

SaaS implementation is blocked.

Client-specific behavior is blocked.

Hardcoded Lions Club Beenachi rules are blocked.

Hardcoded Rotary Central Bathery rules are blocked.

Hardcoded future pilot club rules are blocked.

## Explicit non-authorization

3CS does not authorize:

- Runtime implementation in 3CS
- Frontend implementation in 3CS
- Backend API implementation in 3CS
- Migration/table creation in 3CS
- Model creation in 3CS
- Controller creation in 3CS
- Request creation in 3CS
- Resource creation in 3CS
- Service creation in 3CS
- File upload
- File storage
- S3/local disk integration
- Public file URLs
- Signed file URLs
- File download endpoints
- OCR
- PDF generation
- WhatsApp/SMS/email sending
- Reminder behavior
- Member import changes
- Finance/ledger/payment/receipt/payment-history changes
- Committee/fellowship/activity/event changes
- Deployment/SaaS/client-specific behavior
- Any production behavior change

## Expected next milestone

The expected next milestone is:

Milestone 3CT - Member Profile Documents Database Foundation

3CT may implement only a narrow database foundation if explicitly approved later.

3CS must not start 3CT.

## Final authorization statement

Milestone 3CS authorizes only a future narrow Member Profile Documents database foundation lane, if later approved, for metadata records linked to clubs and members.

The future lane may consider a member document metadata table/model, document type and status fields, external/manual reference, remarks, audit-friendly timestamps, creator/updater references, authorized read APIs, and authorized officer create/update APIs.

The authorization remains metadata-only and does not authorize storing files, upload, storage integration, public or signed URLs, file downloads, OCR, PDF generation, messaging, reminders, finance mutation, member import changes, deployment/SaaS behavior, client-specific behavior, or any runtime implementation in 3CS.
