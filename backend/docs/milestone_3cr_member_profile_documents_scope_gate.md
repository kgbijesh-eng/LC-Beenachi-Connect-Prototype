# Milestone 3CR - Member Profile Documents Scope Gate

## Status

Milestone 3CR is a docs/tests-only scope gate milestone.

It defines the future Member Profile Documents module boundary only.

It does not implement the module.

It does not change production code.

It does not edit app/page.tsx.

It does not change frontend runtime files.

It does not change backend runtime files or API behavior.

It does not create migrations, models, routes, controllers, services, requests, resources, policies, middleware, seeders, config, or package changes.

It does not add file upload, file storage, S3/local disk integration, OCR, PDF generation, finance behavior, deployment/SaaS behavior, or client-specific behavior.

It does not change older milestone tests.

## Purpose of 3CR

The purpose of 3CR is to scope a future Member Profile Documents module without implementation.

3CR records the metadata-only boundary for future member document records.

3CR prepares a controlled future path for member profile document metadata while keeping file handling, storage, OCR, PDF, messaging, finance, deployment, SaaS, and client-specific behavior out of scope.

## Base

Base milestone:

- Milestone 3CQ - Post Member Data Import Module Next Scope Decision

Base branch:

- milestone-3cq-post-member-data-import-module-next-scope-decision

Scope gate branch:

- milestone-3cr-member-profile-documents-scope-gate

Verified base hash:

- 58594578f39fa17bfc6b696e55a1eaad58f28343

## Allowed files

Only these files are allowed in 3CR:

- backend/docs/milestone_3cr_member_profile_documents_scope_gate.md
- backend/tests/Feature/Milestone3crMemberProfileDocumentsScopeGateTest.php

Read-only inspection of existing frontend, backend, docs, and tests is allowed.

## Scope gate focus

3CR defines a future module boundary only.

3CR does not authorize runtime implementation.

3CR does not authorize frontend implementation.

3CR does not authorize backend API implementation.

3CR does not authorize database table creation.

3CR does not authorize model creation.

3CR does not authorize controller, request, resource, or service creation.

3CR does not authorize a storage or upload flow.

3CR does not authorize OCR or PDF behavior.

## 3CQ selection confirmation

Milestone 3CQ selected Member Profile Documents as the next safe scope after Member Data Import module closure.

3CR follows that decision as a scope gate only.

3CR does not reopen the Member Data Import module.

## Member Data Import remains closed

The Member Data Import module remains closed.

Member import preview remains closed.

Member import commit remains closed.

3CR does not change member import behavior.

3CR does not change member import preview APIs.

3CR does not change member import commit APIs.

3CR does not add Excel import, CSV import, upload, parser, or storage behavior.

## Future Member Profile Documents concept

The future Member Profile Documents module may later support structured document metadata linked to members.

At this scope gate stage, Member Profile Documents is metadata-only future scope.

Future document metadata examples may include:

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

Metadata-only means the future scope may describe document records, labels, references, statuses, and audit fields without storing or uploading physical files.

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

President/Admin and Secretary may be considered for managing document metadata in a later implementation authorization.

Treasurer access must be decided carefully and may be read-only unless later authorized.

Normal Member visibility must be limited to their own allowed document metadata if later authorized.

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

Future scope should consider status changes as auditable metadata.

Future scope should preserve accountability without adding file storage or file processing.

## Sensitive and private metadata protection

Sensitive/private document metadata must be protected.

Future scope must consider which document type labels, status labels, references, and remarks are safe to expose.

Future scope must avoid public exposure of sensitive identity, address, consent, or membership metadata.

Future scope must treat optional remarks carefully because remarks may contain private information.

## Runtime implementation blocked

Runtime implementation is blocked in 3CR.

Frontend implementation is blocked in 3CR.

Backend API implementation is blocked in 3CR.

Migration/table creation is blocked in 3CR.

Model creation is blocked in 3CR.

Controller/request/resource/service creation is blocked in 3CR.

Policy, middleware, provider, seeder, config, and package changes are blocked in 3CR.

Any production behavior change is blocked in 3CR.

## File upload and storage blocked

File upload is blocked.

File storage is blocked.

S3 integration is blocked.

Local disk storage integration is blocked.

Public file URLs are blocked.

Signed file URLs are blocked.

File download endpoints are blocked.

File persistence is blocked.

No physical file should be stored, moved, parsed, transformed, or served by 3CR.

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

3CR does not authorize:

- Runtime implementation
- Frontend implementation
- Backend API implementation
- Migration/table creation
- Model creation
- Controller creation
- Request creation
- Resource creation
- Service creation
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

Milestone 3CS - Member Profile Documents Implementation Authorization

3CS should remain docs/tests-only unless explicitly approved later.

3CR must not start 3CS.

## Final scope gate statement

Milestone 3CR scopes Member Profile Documents as a future metadata-only, club-scoped, member-scoped, officer-managed module.

The future module may evaluate document type labels, status labels, optional external/manual references, optional remarks, audit metadata, President/Admin and Secretary management, careful Treasurer access, Normal Member own-document visibility, cross-club fail-closed behavior, auditability, and sensitive/private metadata protection.

3CR does not implement runtime behavior and does not authorize file upload, file storage, S3/local disk integration, public or signed URLs, file download endpoints, OCR, PDF generation, messaging, reminders, finance mutation, member import changes, deployment/SaaS behavior, or client-specific behavior.
