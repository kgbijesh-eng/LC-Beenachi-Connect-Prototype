# Milestone 3DA - Member Profile Documents Create/Update Implementation Authorization

## Status

Milestone 3DA is a docs/tests-only implementation authorization milestone.

It converts the 3CZ Member Profile Documents Create/Update scope gate into a precise future implementation authorization boundary.

It does not implement create/update APIs.

It does not change production code.

It does not edit app/page.tsx.

It does not change frontend runtime files.

It does not change backend routes, controllers, requests, resources, services, policies, middleware, models, migrations, config, or API behavior.

It does not change the 3CT migration.

It does not change App\Models\MemberProfileDocument.

It does not change older milestone tests.

## Purpose of 3DA

The purpose of 3DA is to authorize only a future narrow metadata-only create/update API implementation lane if later explicitly approved.

3DA must not add routes, controllers, requests, resources, services, policies, middleware, frontend screens, migrations, model changes, upload flows, storage flows, OCR, PDF, reminders, finance behavior, member import behavior, deployment/SaaS behavior, or client-specific behavior.

This milestone authorizes only a future narrow metadata-only create/update API implementation lane.

## Base and Created Branch

Base branch:

- milestone-3cz-member-profile-documents-create-update-scope-gate

Verified base hash:

- 82138475e3cb84b81f7f38dee963335700a948c9

Created branch:

- milestone-3da-member-profile-documents-create-update-implementation-authorization

## Allowed files

Only these files are allowed in 3DA:

- backend/docs/milestone_3da_member_profile_documents_create_update_implementation_authorization.md
- backend/tests/Feature/Milestone3daMemberProfileDocumentsCreateUpdateImplementationAuthorizationTest.php

Read-only inspection of existing frontend, backend, docs, tests, schema, model, routes, and config is allowed.

## Authorization focus

3DA authorizes a future metadata-only create/update API boundary only.

3DA does not authorize runtime implementation in 3DA.

3DA does not authorize backend route implementation in 3DA.

3DA does not authorize controller, request, resource, or service implementation in 3DA.

3DA does not authorize policy or middleware implementation in 3DA.

3DA does not authorize frontend implementation in 3DA.

3DA does not authorize migration/table changes.

3DA does not authorize model changes.

3DA does not authorize DELETE behavior.

3DA does not authorize upload, storage, OCR, PDF, messaging, reminder, finance, member import, deployment, SaaS, or client-specific behavior.

## Relationship to 3CT and 3CU Database Foundation

Milestone 3CT created the database foundation and model for Member Profile Documents.

Milestone 3CU validated and closed that foundation.

3DA records that the 3CT and 3CU database foundation lane is already closed.

3DA keeps the database and model foundation unchanged.

3DA does not authorize database migration or model changes.

Existing table:

- member_profile_documents

Existing model:

- App\Models\MemberProfileDocument

## Relationship to 3CV, 3CW, 3CX, and 3CY Read API Lane

Milestone 3CV scoped the read API.

Milestone 3CW authorized the read API implementation.

Milestone 3CX implemented the narrow read API.

Milestone 3CY validated and closed the read API lane.

3DA records that the read API lane remains closed.

Existing read routes and implementation remain completely unchanged.

Existing read routes:

- GET /api/v1/clubs/{club}/members/{member}/profile-documents
- GET /api/v1/clubs/{club}/members/{member}/profile-documents/{document}

## Relationship to 3CZ Create/Update Scope Gate

Milestone 3CZ scoped the future Member Profile Documents Create/Update API boundary.

3DA converts the 3CZ create/update scope gate into a future implementation authorization boundary.

3DA preserves the 3CZ metadata-only, officer-managed, club-scoped, member-scoped, non-file, non-storage, non-OCR, non-PDF, non-finance, non-SaaS, and non-client-specific boundaries.

3DA does not implement the 3CZ candidate routes.

## Future Metadata-Only Create/Update API Authorization

3DA may authorize a future narrow metadata-only create/update API implementation.

Future create/update API may include, only if later approved:

- POST /api/v1/clubs/{club}/members/{member}/profile-documents
- PATCH /api/v1/clubs/{club}/members/{member}/profile-documents/{document}

These routes are future-authorized candidates only.

3DA must not add these routes.

DELETE remains out of scope unless explicitly authorized in a later separate milestone.

## Future Read/Write Separation Boundary

The closed read API lane remains read-only and unchanged.

The future create/update API lane must be separate from the read API lane.

Future write behavior must not broaden read responses into file paths, storage disks, URLs, OCR text, binary data, physical file data, public URLs, signed URLs, download URLs, or generated PDFs.

Future read/write separation must preserve metadata-only response and request boundaries.

## Future Create/Update Implementation Boundary

Only in a later approved implementation milestone, the future create/update API may add:

- POST route for creating metadata-only member profile document records.
- PATCH route for updating metadata-only member profile document records.
- Controller create/update behavior.
- Request validation for metadata-only fields.
- Resource reuse or extension if metadata-only.
- Service layer if needed.
- Feature tests for authorized create/update access.
- Club-scoped access checks.
- Member-scoped access checks.
- Document ownership checks.
- Cross-club fail-closed checks.
- Server-derived ownership and audit fields.

The future create/update API must remain metadata-only.

The future create/update API must not upload, store, download, generate, OCR, send, import, export, or mutate finance behavior.

## Future Access Boundary

Future create/update API should be club-scoped.

Future create/update API should be member-scoped.

Future create/update API should be metadata-only.

Future create/update API should be officer-managed.

President/Admin may create/update member profile document metadata within their club.

Secretary may create/update member profile document metadata within their club.

Treasurer must remain blocked from create/update unless later explicitly authorized.

Normal Member must remain blocked from create/update unless later explicitly authorized.

Normal Member must not create/update another member's document metadata.

Cross-club access must fail closed.

Member/document ownership mismatch must fail closed.

Sensitive/private metadata must be protected.

Create/update API must never accept file paths, storage disks, URLs, OCR text, binary data, or physical file data.

## Future Writable Metadata Boundary

Future create/update request may allow writing metadata fields only:

- document_type
- status
- external_reference
- remarks

The following ownership and audit fields must be server-derived and not user-controlled:

- club_id
- member_id
- created_by_user_id
- updated_by_user_id

Future create/update request must not accept user-controlled ownership or audit fields:

- club_id
- member_id
- created_by_user_id
- updated_by_user_id

Future create/update request must not accept or allow these blocked file, path, storage, URL, OCR, PDF, and binary request fields:

- file_path
- path
- disk
- storage_disk
- file_name
- original_file_name
- mime_type
- file_size
- url
- public_url
- signed_url
- download_url
- ocr_text
- extracted_text
- pdf_path
- thumbnail_path
- uploaded_file
- attachment
- binary_data
- base64_data

## Runtime and API Implementation Blocked in 3DA

Runtime implementation in 3DA is blocked.

Backend route implementation in 3DA is blocked.

Controller implementation in 3DA is blocked.

Request implementation in 3DA is blocked.

Resource implementation in 3DA is blocked.

Service implementation in 3DA is blocked.

Policy implementation in 3DA is blocked.

Middleware implementation in 3DA is blocked.

Frontend implementation in 3DA is blocked.

Migration/table changes are blocked.

Model changes are blocked.

Any production behavior change is blocked in 3DA.

## File and Storage Behavior Blocked

File upload is blocked.

File storage is blocked.

S3 integration is blocked.

Local disk integration is blocked.

S3/local disk integration is blocked.

Public file URL behavior is blocked.

Signed file URL behavior is blocked.

Download URL behavior is blocked.

Public/signed/download URL behavior is blocked.

File download endpoint behavior is blocked.

## OCR, PDF, Messaging, and Reminder Behavior Blocked

OCR is blocked.

PDF generation is blocked.

WhatsApp sending is blocked.

SMS sending is blocked.

Email sending is blocked.

WhatsApp/SMS/email sending is blocked.

Reminder behavior is blocked.

Notification behavior is blocked.

WhatsApp/SMS/email/reminder behavior is blocked.

## Finance, Member Import, and Adjacent Modules Blocked

Finance mutation is blocked.

Ledger mutation is blocked.

Payment mutation is blocked.

Receipt mutation is blocked.

Payment-history changes are blocked.

Finance/ledger/payment/receipt/payment-history changes are blocked.

Member import changes are blocked.

Member import behavior is blocked.

Committee changes are blocked.

Fellowship changes are blocked.

Activity changes are blocked.

Event changes are blocked.

Committee/fellowship/activity/event changes are blocked.

## Deployment, SaaS, and Client-Specific Behavior Blocked

Deployment implementation is blocked.

SaaS implementation is blocked.

Deployment/SaaS behavior is blocked.

Client-specific behavior is blocked.

Deployment/SaaS/client-specific behavior is blocked.

Hardcoded Lions Club Beenachi behavior is blocked.

Hardcoded Rotary Central Bathery behavior is blocked.

Hardcoded future pilot club behavior is blocked.

## Explicit Non-Authorization

3DA does not authorize:

- Runtime implementation in 3DA.
- Backend route implementation in 3DA.
- Controller/request/resource/service implementation in 3DA.
- Policy/middleware implementation in 3DA.
- Frontend implementation in 3DA.
- Migration/table changes.
- Model changes.
- DELETE behavior.
- File upload.
- File storage.
- S3/local disk integration.
- Public file URLs.
- Signed file URLs.
- File download endpoints.
- OCR.
- PDF generation.
- WhatsApp/SMS/email sending.
- Reminder behavior.
- Member import changes.
- Finance/ledger/payment/receipt/payment-history changes.
- Committee/fellowship/activity/event changes.
- Deployment/SaaS/client-specific behavior.
- Any production behavior change.

## Expected Next Milestone

Expected next milestone:

- Milestone 3DB - Member Profile Documents Create/Update Narrow Implementation

3DB may implement only the narrow metadata-only create/update API if explicitly approved later.

3DA must not start 3DB.

## Final Authorization Statement

Milestone 3DA authorizes only a future narrow metadata-only Member Profile Documents create/update API lane, if later approved.

The future lane may implement metadata-only POST/PATCH access to member_profile_documents through App\Models\MemberProfileDocument using candidate create and update routes, controller/request/resource/service patterns if needed, metadata-only writable fields, club-scoped checks, member-scoped checks, document ownership checks, cross-club fail-closed checks, and server-derived ownership/audit fields.

3DA does not implement routes, controllers, requests, resources, services, policies, middleware, frontend runtime, migration/model changes, upload/storage behavior, public/signed/download URLs, OCR, PDF, messaging, reminders, finance mutation, member import changes, deployment/SaaS behavior, client-specific behavior, DELETE behavior, or any production behavior change.
