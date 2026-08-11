# Milestone 3DC - Member Profile Documents Create/Update Validation and Closure

## Purpose of 3DC

Milestone 3DC is a docs/tests-only validation and closure milestone.

3DC validates and closes the 3DB metadata-only Member Profile Documents create/update API lane.

3DC does not add new runtime behavior.

3DC does not change frontend runtime files.

3DC does not change backend routes, controllers, requests, resources, services, policies, middleware, models, migrations, config, or API behavior.

3DC does not add file upload, file storage, file download, public URLs, signed URLs, OCR, PDF generation, DELETE behavior, finance behavior, member import behavior, deployment/SaaS behavior, or client-specific behavior.

## Base and Created Branch

Base branch:

- milestone-3db-member-profile-documents-create-update-narrow-implementation

Verified base hash:

- fd176a0cd5edd7af424b21a0113f9b8978d26917

Created branch:

- milestone-3dc-member-profile-documents-create-update-validation-closure

## Allowed files

Only these files are allowed in 3DC:

- backend/docs/milestone_3dc_member_profile_documents_create_update_validation_and_closure.md
- backend/tests/Feature/Milestone3dcMemberProfileDocumentsCreateUpdateValidationAndClosureTest.php

Read-only inspection of existing backend, frontend, docs, tests, routes, controller, requests, resource, service, model, and migration files is allowed.

## Relationship to Closed Lanes

Milestone 3CT created the Member Profile Documents database foundation.

Milestone 3CU validated and closed the database foundation.

3DC confirms that the 3CT/3CU database foundation remains closed and unchanged.

Milestone 3CV scoped the read API lane.

Milestone 3CW authorized the read API implementation.

Milestone 3CX implemented the narrow read API.

Milestone 3CY validated and closed the read API lane.

3DC confirms that the 3CV/3CW/3CX/3CY read API lane remains closed and unchanged.

Milestone 3CZ scoped the future create/update boundary.

Milestone 3DA authorized the future narrow metadata-only create/update implementation lane.

Milestone 3DB implemented the narrow metadata-only create/update API.

3DC validates and closes the 3DB create/update lane.

## Existing Foundation and Routes

Existing table:

- member_profile_documents

Existing model:

- App\Models\MemberProfileDocument

Existing read routes:

- GET /api/v1/clubs/{club}/members/{member}/profile-documents
- GET /api/v1/clubs/{club}/members/{member}/profile-documents/{document}

Existing POST create route:

- POST /api/v1/clubs/{club}/members/{member}/profile-documents

Existing PATCH update route:

- PATCH /api/v1/clubs/{club}/members/{member}/profile-documents/{document}

The read API remains unchanged.

The create/update API is metadata-only.

## Metadata-Only Boundary

Writable fields remain limited to:

- document_type
- status
- external_reference
- remarks

Ownership/audit fields remain server-derived or blocked:

- club_id
- member_id
- created_by_user_id
- updated_by_user_id

File/path/storage/url/OCR/PDF/binary fields remain blocked:

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

The request cannot control ownership, audit, file, path, storage, URL, OCR, PDF, upload, attachment, binary, or base64 fields.

## Authorization Closure

President/Admin can create/update member profile document metadata inside their own club.

Secretary can create/update member profile document metadata inside their own club.

Treasurer remains blocked from create/update.

Normal Member remains blocked from create/update.

Normal Member cannot create/update another member's document metadata.

Cross-club create/update access fails closed.

Member/document mismatch update fails closed.

The selected member must belong to the selected club.

The selected document must belong to the selected club and selected member.

## Explicit Exclusions

DELETE remains out of scope.

File upload remains out of scope.

File storage remains out of scope.

Public/signed/download URLs remain out of scope.

OCR/PDF remains out of scope.

Frontend remains out of scope.

Model/migration changes remain out of scope.

Finance/import/deployment/SaaS/client-specific behavior remains out of scope.

No finance behavior was added.

No member import behavior was added.

No deployment/SaaS behavior was added.

No client-specific behavior was added.

## Older Guard-Test Forward-Compatibility Repair

3DB required a narrow forward-compatibility repair to older 3CT-3DA member-profile-document guard tests.

Those older guards were updated only so they no longer permanently block 3DB-approved POST/PATCH/store/update behavior.

The repaired older guards still block DELETE, PUT, upload/download routes, storage routes, OCR/PDF routes, model/migration mutation, frontend behavior, and unrelated behavior.

The repair did not broaden file upload, storage, OCR, PDF, finance, import, deployment/SaaS, client-specific, model, migration, or frontend scope.

## Validation Commands and Results

Validation commands for 3DC:

- npm run build
- php artisan test --filter=Milestone3dcMemberProfileDocumentsCreateUpdateValidationAndClosureTest
- php artisan test
- php -d memory_limit=512M vendor/bin/phpunit
- git status --short --branch
- git diff --stat
- git diff --check
- git diff --cached --stat
- git diff --cached --check

The final command results are recorded in the milestone final report after execution.

## Closure Decision

Milestone 3DC closes the Member Profile Documents create/update backend lane delivered by 3DB.

The closed lane consists only of metadata-only POST/PATCH create/update behavior for member_profile_documents records through the existing App\Models\MemberProfileDocument model.

The closed create/update lane is authenticated, club-scoped, member-scoped, officer-managed, metadata-only, and fail-closed for unauthorized, cross-club, and mismatched member/document access.

The closed create/update lane does not add DELETE, file upload, file storage, download endpoints, public URLs, signed URLs, OCR, PDF generation, frontend runtime, model/migration changes, finance behavior, member import behavior, deployment/SaaS behavior, or client-specific behavior.

## Expected Next Milestone

Expected next milestone:

- Milestone 3DD - Member Profile Documents Frontend Scope Gate

3DC must not start 3DD.
