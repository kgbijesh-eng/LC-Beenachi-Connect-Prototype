# Milestone 3DF - Member Profile Documents Frontend Narrow Implementation

## Status

Milestone 3DF implements the narrow frontend runtime lane authorized by 3DE.

3DF adds a Profile Documents section inside the existing member/member-management UI in app/page.tsx.

3DF is metadata-only.

3DF does not change backend runtime behavior.

3DF does not add routes, controllers, requests, resources, services, models, migrations, policies, middleware, seeders, factories, config, package files, or lock files.

3DF does not add file upload, file download, file preview, delete behavior, OCR, PDF generation, storage URL behavior, finance behavior, member import behavior, deployment/SaaS behavior, or client-specific behavior.

## Purpose

The purpose of 3DF is to connect the existing frontend prototype to the already closed Member Profile Documents backend metadata API.

The frontend implementation is intentionally narrow: it lists metadata records, creates metadata records, updates metadata records, shows safe loading/empty/error/validation feedback, and refreshes after create/update.

3DF does not reopen the backend lane.

## Base and Created Branch

Base branch:

- milestone-3de-member-profile-documents-frontend-implementation-authorization

Verified base hash:

- b502123f27d33afc113b0702100ae62a0473f78e

Created branch:

- milestone-3df-member-profile-documents-frontend-narrow-implementation

## Allowed Files

Only these files are allowed in 3DF:

- app/page.tsx
- backend/docs/milestone_3df_member_profile_documents_frontend_narrow_implementation.md
- backend/tests/Feature/Milestone3dfMemberProfileDocumentsFrontendNarrowImplementationTest.php

## Relationship to 3CT through 3DE

Milestone 3CT created the Member Profile Documents database foundation.

Milestone 3CU validated and closed the database foundation.

Milestone 3CV scoped the read API lane.

Milestone 3CW authorized the read API implementation.

Milestone 3CX implemented the narrow read API.

Milestone 3CY validated and closed the read API lane.

Milestone 3CZ scoped the create/update API boundary.

Milestone 3DA authorized the narrow metadata-only create/update implementation lane.

Milestone 3DB implemented the narrow metadata-only create/update API.

Milestone 3DC validated and closed the create/update API lane.

Milestone 3DD defined the frontend scope gate.

Milestone 3DE authorized this narrow frontend implementation.

## Backend Routes Used

3DF may use only these existing backend routes:

- GET /api/v1/clubs/{club}/members/{member}/profile-documents
- GET /api/v1/clubs/{club}/members/{member}/profile-documents/{document}
- POST /api/v1/clubs/{club}/members/{member}/profile-documents
- PATCH /api/v1/clubs/{club}/members/{member}/profile-documents/{document}

The implemented frontend uses the collection GET route, the POST create route, and the PATCH update route.

The single-document GET route remains optional and is not required by this implementation.

The backend create/update lane is closed.

Backend authorization remains authoritative.

UI hiding is only convenience.

## Implementation Location

The implementation lives in app/page.tsx inside the existing MemberProfile component.

The section title is Profile Documents.

The section uses the existing prototype role-login, member lookup, card, notice, field, and form styling patterns.

## Metadata-Only Fields

3DF forms expose and submit only these writable fields:

- document_type
- status
- external_reference
- remarks

3DF may display returned document type, status, external reference, remarks, created date, and updated date.

3DF does not expose or submit ownership/audit fields:

- club_id
- member_id
- created_by_user_id
- updated_by_user_id

3DF does not expose or submit file/path/storage/url/OCR/PDF/binary fields:

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

Club/member/user ownership remains backend-derived.

## Role Controls

President/Admin may see create/edit metadata controls for members inside their own club.

Secretary may see create/edit metadata controls for members inside their own club.

Treasurer must not see create/edit metadata controls.

Normal Member must not see create/edit metadata controls.

Normal Member must not see controls to create/update another member's document metadata.

Backend authorization remains the enforcement layer for all roles and club boundaries.

## States and Refresh

3DF includes:

- Loading state.
- Empty state.
- Safe error state.
- Validation error display from backend responses.
- Cancel/save behavior.
- Refresh after create/update.

The frontend refreshes the profile document metadata list after successful create/update.

## UX Copy Used

3DF uses this required UI copy:

- Profile Documents
- Document metadata only. File upload and document storage are not enabled in this version.
- No profile document records added yet.
- Add Document Metadata
- Edit Metadata

The implemented 3DF section avoids labels that imply file operations.

## Exclusions

3DF excludes:

- Backend runtime changes.
- Route/controller/request/resource/service changes.
- Model/migration changes.
- Policy/middleware changes.
- Package or lock file changes.
- DELETE behavior.
- File upload.
- File preview.
- File download.
- Public URLs.
- Signed URLs.
- OCR.
- PDF generation.
- Storage URL behavior.
- Finance behavior.
- Member import behavior.
- Deployment/SaaS behavior.
- Client-specific behavior.

## Expected Next Milestone

Expected next milestone:

- Milestone 3DG - Member Profile Documents Frontend Validation and Closure

3DF must not start 3DG.
