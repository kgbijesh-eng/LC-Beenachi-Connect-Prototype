# Milestone 3CZ - Member Profile Documents Create/Update Scope Gate

## Status

Milestone 3CZ is a docs/tests-only create/update scope gate milestone.

It defines the future Member Profile Documents Create/Update API boundary only.

It does not implement create/update APIs.

It does not change production code.

It does not edit app/page.tsx.

It does not change frontend runtime files.

It does not change backend routes, controllers, requests, resources, services, policies, middleware, or config.

It does not change the 3CT migration.

It does not change App\Models\MemberProfileDocument.

It does not change older milestone tests.

## Purpose of 3CZ

The purpose of 3CZ is to define the future Member Profile Documents Create/Update API boundary only. 3CZ must not implement create/update APIs. 3CZ must not add routes, controllers, requests, resources, services, policies, middleware, frontend screens, migrations, model changes, upload flows, storage flows, OCR, PDF, reminders, finance behavior, or SaaS/client-specific behavior. This is only a create/update scope gate for a future milestone.

## Base and Created Branch

Base branch:

- milestone-3cy-member-profile-documents-read-api-validation-and-closure

Verified base hash:

- 0b57a138e7f8799a273e77270e00d9c7a6487da9

Created branch:

- milestone-3cz-member-profile-documents-create-update-scope-gate

## Allowed files

Only these files are allowed in 3CZ:

- backend/docs/milestone_3cz_member_profile_documents_create_update_scope_gate.md
- backend/tests/Feature/Milestone3czMemberProfileDocumentsCreateUpdateScopeGateTest.php

Read-only inspection of existing frontend, backend, docs, tests, and config is allowed.

## Relationship to 3CT and 3CU Database Foundation

Milestone 3CT created the database foundation and model for Member Profile Documents. Milestone 3CU validated and closed that foundation. 3CZ confirms that the database and model foundation remains closed and unchanged. 3CZ does not authorize database migration or model changes.

## Relationship to 3CV, 3CW, 3CX, and 3CY Read API Lane

Milestone 3CV scoped the read API, 3CW authorized the read API implementation, 3CX implemented the narrow read API, and 3CY validated and closed the read API lane. 3CZ confirms that the read API lane remains closed. Existing read routes and implementation remain completely unchanged.

Existing table:
- member_profile_documents

Existing model:
- App\Models\MemberProfileDocument

Existing read routes:
- GET /api/v1/clubs/{club}/members/{member}/profile-documents
- GET /api/v1/clubs/{club}/members/{member}/profile-documents/{document}

## Future Create/Update API Concept

Future create/update API may later allow officers to create or update metadata-only member profile document records. Potential future routes may be scoped as:

- POST /api/v1/clubs/{club}/members/{member}/profile-documents
- PATCH /api/v1/clubs/{club}/members/{member}/profile-documents/{document}

These routes are only future candidates. 3CZ does not add them. DELETE remains out of scope unless explicitly authorized in a later separate milestone.

## Future Access Boundary

Future create/update API should be:
- Club-scoped.
- Member-scoped.
- Metadata-only.
- Officer-managed.
- President/Admin may create/update member profile document metadata within their club.
- Secretary may create/update member profile document metadata within their club.
- Treasurer must remain blocked from create/update unless later explicitly authorized.
- Normal Member must remain blocked from create/update unless later explicitly authorized.
- Normal Member must not create/update another member's document metadata.
- Cross-club access must fail closed.
- Member/document ownership mismatch must fail closed.
- Sensitive/private metadata must be protected.
- Create/update API must never accept file paths, storage disks, URLs, OCR text, binary data, or physical file data.

## Future Writable Metadata Boundary

Future create/update request may allow writing metadata fields only:
- document_type
- status
- external_reference
- remarks

The following fields must be server-derived and not user-controlled:
- club_id
- member_id
- created_by_user_id
- updated_by_user_id

Future create/update request must not accept or allow these blocked request fields:
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

## Explicit Non-Authorization

3CZ does not authorize:
- Runtime implementation.
- Backend route implementation.
- Controller/request/resource/service implementation.
- Policy/middleware implementation.
- Frontend implementation.
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

- Milestone 3DA — Member Profile Documents Create/Update Implementation Authorization

3DA should remain docs/tests-only unless explicitly approved later. 3CZ must not start 3DA.
