# Milestone 3CW - Member Profile Documents Read API Implementation Authorization

## Status

Milestone 3CW is a docs/tests-only implementation authorization milestone.

It converts the 3CV Member Profile Documents Read API scope gate into a precise future implementation authorization boundary.

It does not implement the read API.

It does not change production code.

It does not edit app/page.tsx.

It does not change frontend runtime files.

It does not change backend runtime files or API behavior.

It does not change the 3CT migration.

It does not change MemberProfileDocument.php.

It does not change older milestone tests.

## Purpose of 3CW

The purpose of 3CW is to authorize only a future narrow metadata-only read API implementation lane if later explicitly approved.

3CW must not add routes, controllers, requests, resources, services, policies, middleware, frontend screens, migrations, model changes, upload flows, storage flows, OCR, PDF, reminders, finance behavior, or SaaS/client-specific behavior.

This milestone authorizes only a future narrow read API implementation lane.

## Base

Base milestone:

- Milestone 3CV - Member Profile Documents Read API Scope Gate

Base branch:

- milestone-3cv-member-profile-documents-read-api-scope-gate

Authorization branch:

- milestone-3cw-member-profile-documents-read-api-implementation-authorization

Verified base hash:

- 570dfb832674fa155954ca7f9cfea401d670c505

## Allowed files

Only these files are allowed in 3CW:

- backend/docs/milestone_3cw_member_profile_documents_read_api_implementation_authorization.md
- backend/tests/Feature/Milestone3cwMemberProfileDocumentsReadApiImplementationAuthorizationTest.php

Read-only inspection of existing frontend, backend, docs, schema, model, and tests is allowed.

## Authorization focus

3CW authorizes a future metadata-only read API boundary only.

3CW does not authorize runtime implementation in 3CW.

3CW does not authorize backend route implementation in 3CW.

3CW does not authorize controller, request, resource, or service implementation in 3CW.

3CW does not authorize policy or middleware implementation in 3CW.

3CW does not authorize frontend implementation in 3CW.

3CW does not authorize migration/table changes.

3CW does not authorize model changes.

3CW does not authorize upload, storage, OCR, PDF, messaging, finance, member import, deployment, SaaS, or client-specific behavior.

## Relationship to 3CR scope gate

Milestone 3CR scoped Member Profile Documents as a future metadata-only, club-scoped, member-scoped, officer-managed module.

3CW stays inside that metadata-only scope gate.

3CW does not broaden the module into file upload, file storage, OCR, PDF, messaging, finance, deployment, SaaS, or client-specific behavior.

## Relationship to 3CS implementation authorization

Milestone 3CS authorized only a future narrow database foundation lane for member document metadata.

3CW builds on that authorization by authorizing future metadata-only reads only.

3CW does not authorize create, update, delete, upload, download, OCR, PDF, or frontend implementation.

## Relationship to 3CT database foundation

Milestone 3CT created the metadata-only database/model foundation.

Existing table:

- member_profile_documents

Existing model:

- App\Models\MemberProfileDocument

3CW keeps that foundation unchanged.

## Relationship to 3CU database foundation closure

Milestone 3CU validated and closed the 3CT database/model foundation.

3CW records that the database foundation is closed before read API implementation authorization.

3CW does not reopen the database foundation.

## Relationship to 3CV read API scope gate

Milestone 3CV scoped the future Member Profile Documents Read API.

3CW converts that scope gate into a future implementation authorization boundary.

3CW preserves the 3CV metadata-only response, access, and non-file boundaries.

3CW does not implement the candidate routes.

## Member Data Import remains closed

The Member Data Import module remains closed.

Member import preview remains closed and unchanged.

Member import commit remains closed and unchanged.

3CW does not change member import behavior.

3CW does not add Excel import, CSV import, upload, parser, storage, queue, or import mutation behavior.

## Future metadata-only read API authorization

3CW may authorize a future narrow metadata-only read API implementation.

Future read API may include, only if later approved:

- GET /api/v1/clubs/{club}/members/{member}/profile-documents
- GET /api/v1/clubs/{club}/members/{member}/profile-documents/{document}

These routes are future-authorized candidates only.

3CW must not add these routes.

## Future read-only implementation boundary

Only in a later approved implementation milestone, the future read API may add:

- Read-only route definitions
- Read-only controller
- Read-only resource
- Read-only query/service if needed
- Feature tests for authorized read access
- Metadata-only response
- Club-scoped access checks
- Member-scoped access checks
- Cross-club fail-closed checks

The future read API must remain read-only.

The future read API must not create, update, delete, upload, download, generate, send, or mutate anything.

## Future access boundary

Future read API should be club-scoped.

Future read API should be member-scoped.

Future read API should be metadata-only.

Future read API should be read-only.

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

## Runtime and API implementation blocked in 3CW

Runtime implementation in 3CW is blocked.

Backend route implementation in 3CW is blocked.

Controller implementation in 3CW is blocked.

Request implementation in 3CW is blocked.

Resource implementation in 3CW is blocked.

Service implementation in 3CW is blocked.

Policy implementation in 3CW is blocked.

Middleware implementation in 3CW is blocked.

Frontend implementation in 3CW is blocked.

Migration/table changes are blocked.

Model changes are blocked.

Any production behavior change is blocked in 3CW.

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

3CW does not authorize:

- Runtime implementation in 3CW
- Backend route implementation in 3CW
- Controller implementation in 3CW
- Request implementation in 3CW
- Resource implementation in 3CW
- Service implementation in 3CW
- Policy implementation in 3CW
- Middleware implementation in 3CW
- Frontend implementation in 3CW
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

Milestone 3CX - Member Profile Documents Read API Narrow Implementation

3CX may implement only the narrow metadata-only read API if explicitly approved later.

3CW must not start 3CX.

## Final authorization statement

Milestone 3CW authorizes only a future narrow metadata-only Member Profile Documents read API lane, if later approved.

The future lane may implement read-only access to member_profile_documents through App\Models\MemberProfileDocument using the candidate list and detail routes, read-only controller/resource/query patterns if needed, metadata-only responses, club-scoped checks, member-scoped checks, and cross-club fail-closed checks.

3CW does not implement routes, controllers, requests, resources, services, policies, middleware, frontend runtime, migration/model changes, upload/storage behavior, public/signed/download URLs, OCR, PDF, messaging, finance mutation, member import changes, deployment/SaaS behavior, client-specific behavior, or any production behavior change.
