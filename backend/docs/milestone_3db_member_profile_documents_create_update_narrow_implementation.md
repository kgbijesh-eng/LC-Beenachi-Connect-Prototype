# Milestone 3DB - Member Profile Documents Create/Update Narrow Implementation

## Purpose of 3DB

Milestone 3DB implements the narrow backend runtime lane for metadata-only Member Profile Documents create/update API behavior.

3DB adds only metadata-only POST and PATCH behavior for member_profile_documents records.

3DB does not implement DELETE, file upload, file storage, file download, public URLs, signed URLs, OCR, PDF generation, frontend screens, finance behavior, member import behavior, deployment/SaaS behavior, or client-specific behavior.

## Base and Created Branch

Base branch:

- milestone-3da-member-profile-documents-create-update-implementation-authorization

Verified base hash:

- 141be8b68633d0e6bc5e602cb5477d8905450f94

Created branch:

- milestone-3db-member-profile-documents-create-update-narrow-implementation

## Relationship to Closed Lanes

Milestone 3CT created the Member Profile Documents database foundation.

Milestone 3CU validated and closed the database foundation.

3DB keeps the 3CT/3CU foundation unchanged.

Milestone 3CV scoped the read API lane.

Milestone 3CW authorized the read API implementation.

Milestone 3CX implemented the narrow read API.

Milestone 3CY validated and closed the read API lane.

3DB keeps the existing read API behavior working and unchanged.

Milestone 3CZ scoped the future create/update boundary.

Milestone 3DA authorized the future narrow metadata-only create/update implementation lane.

3DB implements only the 3DA-authorized metadata-only create/update lane.

## Existing Foundation and Read API

Existing table:

- member_profile_documents

Existing model:

- App\Models\MemberProfileDocument

Existing read routes:

- GET /api/v1/clubs/{club}/members/{member}/profile-documents
- GET /api/v1/clubs/{club}/members/{member}/profile-documents/{document}

The read API remains unchanged.

## New Metadata-Only Routes

New POST route:

- POST /api/v1/clubs/{club}/members/{member}/profile-documents

New PATCH route:

- PATCH /api/v1/clubs/{club}/members/{member}/profile-documents/{document}

DELETE remains out of scope.

No upload route is introduced.

No download route is introduced.

## Metadata-Only Create/Update Boundary

Create and update accept only metadata fields.

Writable fields:

- document_type
- status
- external_reference
- remarks

Blocked ownership/audit fields:

- club_id
- member_id
- created_by_user_id
- updated_by_user_id

Blocked file/path/storage/url/OCR/PDF/binary fields:

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

Validation explicitly rejects blocked ownership, audit, file, path, storage, URL, OCR, PDF, upload, attachment, binary, and base64 fields.

## Server-Derived Ownership and Audit Behavior

club_id is derived from the route club.

member_id is derived from the route member.

created_by_user_id is derived from the authenticated user on create.

updated_by_user_id is derived from the authenticated user on create and update.

The request cannot control club_id, member_id, created_by_user_id, or updated_by_user_id.

## Authorization Behavior

The API requires authentication.

President/Admin may create/update member profile document metadata within their own club.

Secretary may create/update member profile document metadata within their own club.

Treasurer remains blocked from create/update.

Normal Member remains blocked from create/update.

Normal Member cannot create/update another member's document metadata.

Cross-club create fails closed.

Cross-club update fails closed.

Member/document ownership mismatch update fails closed.

The selected member must belong to the selected club.

The selected document must belong to the selected club.

The selected document must belong to the selected member.

## Explicit Exclusions

File upload remains out of scope.

File storage remains out of scope.

Public/signed/download URLs remain out of scope.

OCR/PDF remains out of scope.

No frontend changes were made.

No model changes were made.

No migration changes were made.

No finance behavior was added.

No member import behavior was added.

No deployment/SaaS behavior was added.

No client-specific behavior was added.

## Expected Next Milestone

Expected next milestone:

- Milestone 3DC - Member Profile Documents Create/Update Validation and Closure

3DB must not start 3DC.
