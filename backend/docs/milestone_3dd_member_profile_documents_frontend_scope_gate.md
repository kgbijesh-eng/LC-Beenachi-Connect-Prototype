# Milestone 3DD - Member Profile Documents Frontend Scope Gate

## Status

Milestone 3DD is a docs/tests-only frontend scope gate milestone.

3DD defines the future frontend boundary for Member Profile Documents.

3DD does not implement frontend runtime.

3DD does not edit app/page.tsx.

3DD does not change backend runtime behavior.

3DD does not add routes, controllers, requests, resources, services, models, migrations, policies, middleware, seeders, factories, config, package files, or lock files.

3DD does not add upload, storage, file download, public URLs, signed URLs, OCR, PDF generation, DELETE behavior, finance behavior, member import behavior, deployment/SaaS behavior, or client-specific behavior.

## Purpose of 3DD

The purpose of 3DD is to define the future frontend boundary for member profile documents after the backend create/update lane was validated and closed by 3DC.

3DD may only authorize a future narrow frontend implementation lane if later explicitly approved.

3DD must not start frontend implementation.

## Base and Created Branch

Base branch:

- milestone-3dc-member-profile-documents-create-update-validation-closure

Verified base hash:

- 2c5b8a05677647b14f56b969018966605cb31de3

Created branch:

- milestone-3dd-member-profile-documents-frontend-scope-gate

## Allowed files

Only these files are allowed in 3DD:

- backend/docs/milestone_3dd_member_profile_documents_frontend_scope_gate.md
- backend/tests/Feature/Milestone3ddMemberProfileDocumentsFrontendScopeGateTest.php

Read-only inspection of existing frontend, backend, docs, and tests is allowed.

## Relationship to Closed Lanes

Milestone 3CT created the Member Profile Documents database foundation.

Milestone 3CU validated and closed the database foundation.

3DD confirms that the 3CT/3CU database foundation remains closed and unchanged.

Milestone 3CV scoped the read API lane.

Milestone 3CW authorized the read API implementation.

Milestone 3CX implemented the narrow read API.

Milestone 3CY validated and closed the read API lane.

3DD confirms that the 3CV/3CW/3CX/3CY read API lane remains closed and unchanged.

Milestone 3CZ scoped the create/update API boundary.

Milestone 3DA authorized the narrow metadata-only create/update implementation lane.

Milestone 3DB implemented the narrow metadata-only create/update API.

Milestone 3DC validated and closed the create/update API lane.

3DD confirms that the backend create/update lane is closed before any future frontend scope is considered.

## Existing Foundation and Backend Routes

Existing table:

- member_profile_documents

Existing model:

- App\Models\MemberProfileDocument

Existing backend GET collection route:

- GET /api/v1/clubs/{club}/members/{member}/profile-documents

Existing backend GET single-document route:

- GET /api/v1/clubs/{club}/members/{member}/profile-documents/{document}

Existing backend POST create route:

- POST /api/v1/clubs/{club}/members/{member}/profile-documents

Existing backend PATCH update route:

- PATCH /api/v1/clubs/{club}/members/{member}/profile-documents/{document}

DELETE remains out of scope.

File upload/download/storage/OCR/PDF remains out of scope.

## Future Frontend Section Boundary

A future frontend implementation may include only a Member Profile Documents section inside the existing member/member-management UI.

The future section may include:

- Profile Documents section title.
- Document list/table/card display.
- Single document detail display if useful.
- Metadata-only create form.
- Metadata-only edit form.
- Loading state.
- Empty state.
- Safe error state.
- Validation message display.
- Role-aware UI controls.

3DD does not implement this frontend section.

## Future Frontend API Call Boundary

The future frontend may call only these existing backend routes:

- GET /api/v1/clubs/{club}/members/{member}/profile-documents
- GET /api/v1/clubs/{club}/members/{member}/profile-documents/{document}
- POST /api/v1/clubs/{club}/members/{member}/profile-documents
- PATCH /api/v1/clubs/{club}/members/{member}/profile-documents/{document}

The future frontend must not call or assume:

- DELETE /api/v1/clubs/{club}/members/{member}/profile-documents/{document}
- upload endpoint
- download endpoint
- public URL endpoint
- signed URL endpoint
- OCR endpoint
- PDF endpoint
- storage endpoint

## Future Frontend Writable Metadata Boundary

Future frontend forms may expose only these writable fields:

- document_type
- status
- external_reference
- remarks

Future frontend must not expose or submit ownership/audit fields:

- club_id
- member_id
- created_by_user_id
- updated_by_user_id

Future frontend must not expose or submit file/path/storage/url/OCR/PDF/binary fields:

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

Club/member/user ownership must remain backend-derived.

Frontend must not pretend to enforce security.

Backend authorization remains authoritative.

## Future Frontend Role Boundary

President/Admin may see create/edit metadata controls for members inside their own club.

Secretary may see create/edit metadata controls for members inside their own club.

Treasurer must not see create/edit metadata controls unless later explicitly authorized.

Normal Member must not see create/edit metadata controls unless later explicitly authorized.

Normal Member must not see controls to create/update another member's document metadata.

Cross-club behavior must fail closed through backend authorization.

UI hiding is convenience only; backend remains the enforcement layer.

## Future UI Behavior Boundary

Future UI should support:

- Document type.
- Status.
- External reference.
- Remarks.
- Created/updated metadata if already returned by the API.
- Add metadata button for authorized roles only.
- Edit metadata button for authorized roles only.
- Cancel/save behavior.
- Loading state.
- Empty state.
- Safe error state.
- Validation message display.

Future UI must not support:

- file upload
- drag-and-drop upload
- attachment picker
- file preview
- file download
- public link
- signed link
- delete button
- OCR extraction
- PDF generation
- WhatsApp/SMS/email/reminder action
- finance mutation
- member import
- deployment/SaaS/client-specific behavior

## Explicit Non-Authorization

3DD does not authorize:

- Frontend runtime implementation.
- app/page.tsx changes.
- Backend runtime changes.
- Route/controller/request/resource/service changes.
- Model/migration changes.
- Policy/middleware changes.
- Package or lock file changes.
- DELETE behavior.
- File upload.
- File storage.
- File download.
- Public URLs.
- Signed URLs.
- OCR.
- PDF generation.
- Finance/import/deployment/SaaS/client-specific behavior.

## Expected Next Milestone

Expected next milestone:

- Milestone 3DE - Member Profile Documents Frontend Implementation Authorization

3DE may only authorize a future narrow frontend implementation if explicitly approved later.

3DD must not start 3DE.
