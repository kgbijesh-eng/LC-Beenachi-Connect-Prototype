# Milestone 3CX - Member Profile Documents Read API Narrow Implementation

## Status

Milestone 3CX is a narrow backend read API implementation.

It implements only metadata-only GET routes for Member Profile Documents.

It does not change frontend runtime files.

It does not change the 3CT migration.

It does not change App\Models\MemberProfileDocument.

It does not add request classes, policy classes, middleware classes, upload behavior, storage behavior, OCR, PDF, finance behavior, member import behavior, deployment/SaaS behavior, or client-specific behavior.

## Purpose of 3CX

The purpose of 3CX is to implement the read API authorized by 3CW for metadata-only Member Profile Documents.

The API reads records from the existing member_profile_documents table through the existing App\Models\MemberProfileDocument model.

The implementation remains read-only, club-scoped, member-scoped, and metadata-only.

## Base

Base milestone:

- Milestone 3CW - Member Profile Documents Read API Implementation Authorization

Base branch:

- milestone-3cw-member-profile-documents-read-api-implementation-authorization

Implementation branch:

- milestone-3cx-member-profile-documents-read-api-narrow-implementation

Verified base hash:

- 49619776e9b17fa7a68c601d1ae37238ce24a2e9

## Allowed files

Only these files are allowed in 3CX:

- backend/routes/api.php
- backend/app/Http/Controllers/Api/V1/Members/MemberProfileDocumentController.php
- backend/app/Http/Resources/Members/MemberProfileDocumentResource.php
- backend/app/Services/Members/MemberProfileDocumentReadService.php
- backend/docs/milestone_3cx_member_profile_documents_read_api_narrow_implementation.md
- backend/tests/Feature/Milestone3cxMemberProfileDocumentsReadApiNarrowImplementationTest.php

## Relationship to 3CV read API scope gate

Milestone 3CV scoped the future Member Profile Documents read API as metadata-only.

3CX stays inside that scope gate.

3CX does not add upload, download, storage, OCR, PDF, mutation, frontend, finance, deployment/SaaS, or client-specific behavior.

## Relationship to 3CW implementation authorization

Milestone 3CW authorized a future narrow metadata-only read API implementation.

3CX implements only that authorized read API.

3CX does not add create, update, delete, upload, download, OCR, PDF, or frontend implementation.

## Existing table and model

Existing table:

- member_profile_documents

Existing model:

- App\Models\MemberProfileDocument

The migration and model are not changed in 3CX.

## Routes added

3CX adds only these GET routes:

- GET /api/v1/clubs/{club}/members/{member}/profile-documents
- GET /api/v1/clubs/{club}/members/{member}/profile-documents/{document}

Only GET routes were added.

No POST, PATCH, PUT, DELETE, upload, download, OCR, PDF, export, or file routes were added.

## Controller, resource, and service

3CX adds a read-only controller:

- App\Http\Controllers\Api\V1\Members\MemberProfileDocumentController

The controller has only index and show methods.

The controller does not have store, update, destroy, upload, download, OCR, or PDF methods.

3CX adds a metadata-only resource:

- App\Http\Resources\Members\MemberProfileDocumentResource

The resource exposes only approved metadata fields.

The resource does not generate URLs.

The resource does not expose file paths, storage details, OCR, PDF, or binary fields.

3CX adds a read-only service:

- App\Services\Members\MemberProfileDocumentReadService

The service only reads metadata records for a selected club/member pair.

The service does not mutate records, generate URLs, or touch storage.

## Access rules

President/Admin may read member profile document metadata within their club.

Secretary may read member profile document metadata within their club.

Treasurer is blocked in 3CX.

Normal Member may read only their own allowed profile document metadata.

Normal Member must not read another member's profile document metadata.

Unauthenticated access is blocked.

Cross-club access fails closed.

## Member and document ownership checks

The selected member must belong to the selected club.

The selected document must belong to the selected club.

The selected document must belong to the selected member.

Cross-club member mismatches fail closed.

Cross-club document mismatches fail closed.

Document/member mismatches fail closed.

## Metadata-only response fields

The response includes only:

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

The response does not include:

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

## Runtime boundaries

The migration was not changed.

App\Models\MemberProfileDocument was not changed.

Frontend runtime was not changed.

app/page.tsx was not changed.

No request classes were added.

No policy classes were added.

No middleware classes were added.

No seeders, factories, config, composer, or package files were changed.

## Blocked behavior

No file upload was added.

No file storage was added.

No S3 integration was added.

No local disk integration was added.

No public URL behavior was added.

No signed URL behavior was added.

No download URL behavior was added.

No file download endpoint behavior was added.

No OCR behavior was added.

No PDF generation was added.

No WhatsApp sending was added.

No SMS sending was added.

No email sending was added.

No reminder behavior was added.

No finance behavior was added.

No ledger behavior was added.

No payment behavior was added.

No receipt behavior was added.

No payment-history behavior was added.

No member import behavior was added.

No deployment implementation was added.

No SaaS implementation was added.

No client-specific behavior was added.

## Expected next milestone

The expected next milestone is:

Milestone 3CY - Member Profile Documents Read API Validation and Closure

3CY should be docs/tests-only validation and closure unless explicitly approved later.

3CX must not start 3CY.
