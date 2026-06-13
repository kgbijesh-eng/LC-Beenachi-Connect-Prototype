# Milestone 3CV - Member Profile Documents Read API Scope Gate

## Status

Milestone 3CV is a docs/tests-only read API scope gate milestone.

It defines the future Member Profile Documents Read API boundary only.

It does not implement read APIs.

It does not change production code.

It does not edit app/page.tsx.

It does not change frontend runtime files.

It does not change backend runtime files or API behavior.

It does not change the 3CT migration.

It does not change MemberProfileDocument.php.

It does not change older milestone tests.

## Purpose of 3CV

The purpose of 3CV is to scope a future metadata-only read API for Member Profile Documents.

3CV must not add routes, controllers, requests, resources, services, policies, middleware, migrations, models, frontend screens, upload flows, storage flows, OCR, PDF, reminders, finance behavior, or SaaS/client-specific behavior.

This is only a read API scope gate for a future milestone.

## Base

Base milestone:

- Milestone 3CU - Member Profile Documents Database Foundation Validation and Closure

Base branch:

- milestone-3cu-member-profile-documents-database-foundation-validation-and-closure

Scope gate branch:

- milestone-3cv-member-profile-documents-read-api-scope-gate

Verified base hash:

- 709188f2a9fe0d342e9bb34afccefa297324b826

## Allowed files

Only these files are allowed in 3CV:

- backend/docs/milestone_3cv_member_profile_documents_read_api_scope_gate.md
- backend/tests/Feature/Milestone3cvMemberProfileDocumentsReadApiScopeGateTest.php

Read-only inspection of existing frontend, backend, docs, schema, model, and tests is allowed.

## Scope gate focus

3CV defines a future read API boundary only.

3CV does not authorize runtime implementation.

3CV does not authorize backend route implementation.

3CV does not authorize controller, request, resource, or service implementation.

3CV does not authorize policy or middleware implementation.

3CV does not authorize frontend implementation.

3CV does not authorize migration/table changes.

3CV does not authorize model changes.

3CV does not authorize upload, storage, OCR, PDF, messaging, finance, member import, deployment, SaaS, or client-specific behavior.

## Relationship to 3CR scope gate

Milestone 3CR scoped Member Profile Documents as a future metadata-only, club-scoped, member-scoped, officer-managed module.

3CV stays inside that metadata-only scope gate.

3CV does not broaden the module into file upload, file storage, OCR, PDF, messaging, finance, deployment, SaaS, or client-specific behavior.

## Relationship to 3CS implementation authorization

Milestone 3CS authorized only a future narrow database foundation lane for member document metadata.

3CV builds on that authorization by scoping future reads only.

3CV does not authorize create, update, delete, upload, download, OCR, PDF, or frontend implementation.

## Relationship to 3CT database foundation

Milestone 3CT created the metadata-only database/model foundation.

Existing table:

- member_profile_documents

Existing model:

- App\Models\MemberProfileDocument

3CV keeps that foundation unchanged.

## Relationship to 3CU database foundation closure

Milestone 3CU validated and closed the 3CT database/model foundation.

3CV records that the database foundation is closed before read API authorization begins.

3CV does not reopen the database foundation.

## Member Data Import remains closed

The Member Data Import module remains closed.

Member import preview remains closed and unchanged.

Member import commit remains closed and unchanged.

3CV does not change member import behavior.

3CV does not add Excel import, CSV import, upload, parser, storage, queue, or import mutation behavior.

## Future read API concept

Future read API may later expose metadata-only document records linked to members.

Future read API must be metadata-only.

Future read API must not expose physical files.

Future candidate read routes may be:

- GET /api/v1/clubs/{club}/members/{member}/profile-documents
- GET /api/v1/clubs/{club}/members/{member}/profile-documents/{document}

These routes are only future candidates.

3CV does not add these routes.

## Future access boundary

Future read API should be club-scoped.

Future read API should be member-scoped.

Future read API should be metadata-only.

President/Admin and Secretary may read member profile document metadata in a later implementation authorization.

Treasurer access must remain restricted and should be read-only only if explicitly authorized later.

Normal Member may read only their own allowed document metadata if later authorized.

Normal Member must not read other members' document metadata.

Cross-club access must fail closed.

Sensitive/private metadata must be protected.

Read API must never expose file paths, storage disks, URLs, OCR text, binary data, or physical file data.

## Future metadata-only response fields

Future read response may include metadata only:

- id
- club_id
- member_id
- document_type
- status
- external_reference
- remarks
- created_by_user_id
- updated_by_user_id
- created_at
- updated_at

## Blocked file, path, storage, URL, OCR, PDF, and binary response fields

Future read response must not include:

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

## Runtime and API implementation blocked

Runtime implementation is blocked in 3CV.

Backend route implementation is blocked in 3CV.

Controller implementation is blocked in 3CV.

Request implementation is blocked in 3CV.

Resource implementation is blocked in 3CV.

Service implementation is blocked in 3CV.

Policy implementation is blocked in 3CV.

Middleware implementation is blocked in 3CV.

Frontend implementation is blocked in 3CV.

Migration/table changes are blocked in 3CV.

Model changes are blocked in 3CV.

Any production behavior change is blocked in 3CV.

## File and storage behavior blocked

File upload is blocked.

File storage is blocked.

S3 integration is blocked.

Local disk integration is blocked.

Public file URL behavior is blocked.

Signed file URL behavior is blocked.

Download URL behavior is blocked.

File download endpoint behavior is blocked.

## OCR, PDF, messaging, and reminder behavior blocked

OCR is blocked.

PDF generation is blocked.

WhatsApp sending is blocked.

SMS sending is blocked.

Email sending is blocked.

Reminder behavior is blocked.

Notification behavior is blocked.

## Finance and member import behavior blocked

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

Hardcoded Lions Club Beenachi behavior is blocked.

Hardcoded Rotary Central Bathery behavior is blocked.

Hardcoded future pilot club behavior is blocked.

## Explicit non-authorization

3CV does not authorize:

- Runtime implementation
- Backend route implementation
- Controller implementation
- Request implementation
- Resource implementation
- Service implementation
- Policy implementation
- Middleware implementation
- Frontend implementation
- Migration/table changes
- Model changes
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

Milestone 3CW - Member Profile Documents Read API Implementation Authorization

3CW should remain docs/tests-only unless explicitly approved otherwise.

3CV must not start 3CW.

## Final scope gate statement

Milestone 3CV scopes a future Member Profile Documents read API only.

The future API may read metadata-only member document records from the existing member_profile_documents table and App\Models\MemberProfileDocument model.

The future API must be club-scoped, member-scoped, metadata-only, privacy-protective, and fail closed across clubs.

3CV does not add routes, controllers, requests, resources, services, policies, middleware, frontend implementation, migration/model changes, upload/storage behavior, public/signed/download URLs, OCR, PDF, finance behavior, member import behavior, deployment/SaaS behavior, or client-specific behavior.
