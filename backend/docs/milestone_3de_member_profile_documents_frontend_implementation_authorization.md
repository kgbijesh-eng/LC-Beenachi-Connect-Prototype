# Milestone 3DE - Member Profile Documents Frontend Implementation Authorization

## Status

Milestone 3DE is a docs/tests-only frontend implementation authorization milestone.

3DE converts the 3DD frontend scope gate into a precise future frontend implementation authorization boundary.

3DE authorizes only a future narrow frontend implementation lane, if later explicitly approved.

3DE does not implement frontend runtime behavior.

3DE does not edit app/page.tsx.

3DE does not change backend runtime behavior.

3DE does not add routes, controllers, requests, resources, services, models, migrations, policies, middleware, seeders, factories, config, package files, or lock files.

3DE does not add upload, storage, file download, public URLs, signed URLs, OCR, PDF generation, DELETE behavior, finance behavior, member import behavior, deployment/SaaS behavior, or client-specific behavior.

## Purpose of 3DE

The purpose of 3DE is to authorize a future narrow frontend implementation lane for member profile document metadata UI behavior only.

3DE must not implement the frontend.

3DE must not start 3DF.

## Base and Created Branch

Base branch:

- milestone-3dd-member-profile-documents-frontend-scope-gate

Verified base hash:

- ee4b6421d5cafe567b137638507718d5e9bee4cb

Created branch:

- milestone-3de-member-profile-documents-frontend-implementation-authorization

## Allowed files

Only these files are allowed in 3DE:

- backend/docs/milestone_3de_member_profile_documents_frontend_implementation_authorization.md
- backend/tests/Feature/Milestone3deMemberProfileDocumentsFrontendImplementationAuthorizationTest.php

Read-only inspection of existing frontend, backend, docs, and tests is allowed.

## Relationship to Closed Lanes

Milestone 3CT created the Member Profile Documents database foundation.

Milestone 3CU validated and closed the database foundation.

3DE confirms that the 3CT/3CU database foundation remains closed and unchanged.

Milestone 3CV scoped the read API lane.

Milestone 3CW authorized the read API implementation.

Milestone 3CX implemented the narrow read API.

Milestone 3CY validated and closed the read API lane.

3DE confirms that the 3CV/3CW/3CX/3CY read API lane remains closed and unchanged.

Milestone 3CZ scoped the create/update API boundary.

Milestone 3DA authorized the narrow metadata-only create/update implementation lane.

Milestone 3DB implemented the narrow metadata-only create/update API.

Milestone 3DC validated and closed the create/update API lane.

Milestone 3DD defined the frontend scope gate.

3DE converts the 3DD frontend scope gate into a future implementation authorization boundary.

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

The backend create/update lane is closed.

DELETE remains out of scope.

File upload/download/storage/OCR/PDF remains out of scope.

## Future Frontend Implementation Authorization Boundary

3DE may authorize a future narrow frontend implementation for:

- Member Profile Documents section inside the existing member/member-management UI.
- Read-only profile document list display.
- Optional single-document detail display if useful.
- Metadata-only create form.
- Metadata-only edit form.
- Loading state.
- Empty state.
- Safe error state.
- Validation error display.
- Role-aware create/edit controls.
- Basic refresh after create/update.

The future implementation must not add file upload, file download, file preview, delete, OCR, PDF, public URL, signed URL, or storage URL behavior.

## Future Frontend File Boundary

A future 3DF implementation may edit frontend runtime files only if explicitly approved in 3DF.

Expected narrow frontend implementation target:

- app/page.tsx

3DE must not edit app/page.tsx.

3DE must not create or edit frontend runtime files.

If 3DF later needs additional frontend files, that must be explicitly justified and kept narrow.

## Future Frontend API Call Boundary

Future frontend implementation may call only these existing backend routes:

- GET /api/v1/clubs/{club}/members/{member}/profile-documents
- GET /api/v1/clubs/{club}/members/{member}/profile-documents/{document}
- POST /api/v1/clubs/{club}/members/{member}/profile-documents
- PATCH /api/v1/clubs/{club}/members/{member}/profile-documents/{document}

Future frontend implementation must not call or assume:

- DELETE /api/v1/clubs/{club}/members/{member}/profile-documents/{document}
- upload endpoint
- download endpoint
- public URL endpoint
- signed URL endpoint
- OCR endpoint
- PDF endpoint
- storage endpoint

## Future Writable Metadata Boundary

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

## Future Role-Aware UI Boundary

President/Admin may see create/edit metadata controls for members inside their own club.

Secretary may see create/edit metadata controls for members inside their own club.

Treasurer must not see create/edit controls unless later explicitly authorized.

Normal Member must not see create/edit controls unless later explicitly authorized.

Normal Member must not see controls to create/update another member's document metadata.

UI hiding is only convenience.

Backend authorization remains the enforcement layer.

Cross-club behavior must fail closed through backend authorization.

## Future UI Display Boundary

Future frontend may display:

- Profile Documents section title.
- Document type.
- Status.
- External reference.
- Remarks.
- Created date if returned by API.
- Updated date if returned by API.
- Created by / updated by display only if already returned safely by API.
- Add Metadata button for authorized roles only.
- Edit Metadata button for authorized roles only.
- Save/cancel form actions.
- Loading state.
- Empty state.
- Safe error state.
- Validation messages.

Future frontend must not display or introduce:

- file upload control
- drag-and-drop upload
- attachment picker
- file preview
- file download button
- public link
- signed link
- delete button
- OCR extraction action
- PDF generation action
- WhatsApp/SMS/email/reminder action
- finance mutation action
- member import action
- deployment/SaaS/client-specific behavior

## Future UX Copy Boundary

Future UI copy should make the limitation clear.

Suggested section title:

- Profile Documents

Suggested scope copy:

- Document metadata only. File upload and document storage are not enabled in this version.

Suggested empty state:

- No profile document records added yet.

Suggested create button:

- Add Document Metadata

Suggested edit button:

- Edit Metadata

Do not use labels that imply file upload or file storage.

Avoid:

- Upload Document
- Attach File
- Download
- Preview File
- Open File
- Generate PDF

## Explicit Non-Authorization

3DE does not authorize:

- Frontend runtime implementation in 3DE.
- app/page.tsx changes in 3DE.
- Frontend runtime file changes in 3DE.
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

- Milestone 3DF - Member Profile Documents Frontend Narrow Implementation

3DF may implement a narrow frontend metadata UI only if explicitly approved later.

3DE must not start 3DF.
