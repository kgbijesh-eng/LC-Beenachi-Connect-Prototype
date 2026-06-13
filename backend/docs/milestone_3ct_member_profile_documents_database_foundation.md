# Milestone 3CT - Member Profile Documents Database Foundation

## Status

Milestone 3CT is a narrow backend database foundation implementation.

It creates only a metadata-only table and model for future Member Profile Documents behavior.

It does not add frontend runtime behavior.

It does not add backend API routes, controllers, requests, resources, or services.

It does not add file upload, file storage, S3/local disk integration, OCR, PDF generation, finance behavior, deployment/SaaS behavior, or client-specific behavior.

## Purpose of 3CT

The purpose of 3CT is to implement the database/model foundation authorized by 3CS for future Member Profile Documents metadata.

3CT creates the member_profile_documents table and the MemberProfileDocument model.

3CT remains metadata-only and does not store, upload, download, parse, transform, or serve physical files.

## Base

Base milestone:

- Milestone 3CS - Member Profile Documents Implementation Authorization

Base branch:

- milestone-3cs-member-profile-documents-implementation-authorization

Foundation branch:

- milestone-3ct-member-profile-documents-database-foundation

Verified base hash:

- 065dd8dfe7f70aa165d6e9d4cf9667baa725eb00

## Allowed files

Only these files are allowed in 3CT:

- backend/database/migrations/2026_06_13_000001_create_member_profile_documents_table.php
- backend/app/Models/MemberProfileDocument.php
- backend/docs/milestone_3ct_member_profile_documents_database_foundation.md
- backend/tests/Feature/Milestone3ctMemberProfileDocumentsDatabaseFoundationTest.php

Only one new migration file is allowed.

## Relationship to 3CR scope gate

Milestone 3CR scoped Member Profile Documents as a future metadata-only, club-scoped, member-scoped, officer-managed module.

3CT stays inside that metadata-only scope gate.

3CT does not broaden the module into upload, storage, OCR, PDF, messaging, finance, deployment, SaaS, or client-specific behavior.

## Relationship to 3CS implementation authorization

Milestone 3CS authorized a future narrow database foundation for member document metadata.

3CT implements only that narrow database foundation.

3CT does not implement APIs, frontend screens, upload flows, storage flows, OCR, PDF, reminders, finance mutation, or SaaS/client-specific behavior.

## Member Data Import remains closed

The Member Data Import module remains closed.

3CT does not change member import preview behavior.

3CT does not change member import commit behavior.

3CT does not add Excel import, CSV import, upload, parser, storage, queue, or import mutation behavior.

## New table

The new table is:

- member_profile_documents

The table is metadata-only.

The table is club-scoped.

The table is member-scoped.

## Required columns

The member_profile_documents table includes:

- id
- club_id
- member_id
- document_type
- status
- external_reference nullable
- remarks nullable
- created_by_user_id nullable
- updated_by_user_id nullable
- created_at
- updated_at

## Metadata-only table boundary

document_type is a short string suitable for values such as profile_photo, membership_form, id_proof, address_proof, consent_form, or general.

status is a short string suitable for values such as pending, verified, rejected, or archived.

external_reference is only a manual/external reference string.

remarks is only text notes.

created_by_user_id and updated_by_user_id are audit-friendly nullable references.

No enum constraint is added for document_type or status in 3CT.

## Blocked upload, storage, path, URL, OCR, and PDF columns

The table does not include:

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

No physical file storage is allowed in 3CT.

## Model

The model is:

- App\Models\MemberProfileDocument

The model follows existing Laravel model conventions in the project.

## Fillable metadata boundary

The MemberProfileDocument model fillable fields are metadata-only:

- club_id
- member_id
- document_type
- status
- external_reference
- remarks
- created_by_user_id
- updated_by_user_id

The model does not include file upload, file storage, URL generation, OCR, PDF, or download behavior.

## Relationship boundary

The MemberProfileDocument model may define relationships to:

- Club
- Member
- creator User
- updater User

No file, storage, upload, OCR, PDF, finance, member import, committee, fellowship, activity, or event relationship is added.

## API and frontend implementation blocked

No API route is added in 3CT.

No controller is added in 3CT.

No request is added in 3CT.

No resource is added in 3CT.

No service is added in 3CT.

No frontend runtime implementation is added in 3CT.

app/page.tsx is not edited in 3CT.

## File and storage behavior blocked

No file upload is added.

No file storage is added.

No S3 integration is added.

No local disk integration is added.

No public URL behavior is added.

No signed URL behavior is added.

No download URL behavior is added.

No file download endpoint behavior is added.

## OCR, PDF, messaging, and reminder behavior blocked

No OCR is added.

No PDF generation is added.

No WhatsApp sending is added.

No SMS sending is added.

No email sending is added.

No reminder behavior is added.

No notification behavior is added.

## Finance, import, and adjacent module behavior blocked

No finance behavior is added.

No ledger behavior is added.

No payment behavior is added.

No receipt behavior is added.

No payment-history behavior is added.

No member import behavior is added.

No committee behavior is added.

No fellowship behavior is added.

No activity behavior is added.

No event behavior is added.

## Deployment, SaaS, and client-specific behavior blocked

No deployment implementation is added.

No SaaS implementation is added.

No client-specific behavior is added.

No hardcoded Lions Club Beenachi behavior is added.

No hardcoded Rotary Central Bathery behavior is added.

No hardcoded future pilot club behavior is added.

## Final foundation statement

Milestone 3CT creates only the Member Profile Documents database/model foundation.

The foundation consists of one metadata-only member_profile_documents table and one MemberProfileDocument model.

The foundation is club-scoped, member-scoped, audit-friendly, and limited to document metadata fields.

The foundation does not add APIs, frontend screens, upload, storage, OCR, PDF, messaging, reminders, finance behavior, member import behavior, deployment/SaaS behavior, or client-specific behavior.

## Expected next milestone

The expected next milestone is:

Milestone 3CU - Member Profile Documents Database Foundation Validation and Closure

3CU should be docs/tests-only validation and closure unless explicitly approved later.

3CT must not start 3CU.
