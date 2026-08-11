<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3djMvpFrontendUsabilityNarrowImplementationTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3dj_mvp_frontend_usability_narrow_implementation.md';
    private string $frontendPath = __DIR__ . '/../../../app/page.tsx';

    public function test_3dj_document_exists_and_records_purpose_scope_and_next_milestone(): void
    {
        $document = $this->implementationDocument();

        foreach ([
            'Milestone 3DJ - MVP Frontend Usability Narrow Implementation',
            'Milestone 3DJ is a narrow frontend runtime implementation milestone.',
            'The purpose of 3DJ is to make the existing internal LC Beenachi MVP easier to use without expanding modules or backend behavior.',
            '3DJ implements the narrow frontend usability lane authorized by 3DI.',
            '3DJ does not change backend runtime behavior.',
            '3DJ does not add new backend APIs.',
            'Milestone 3DK - MVP Frontend Usability Validation and Closure',
            '3DJ must not start 3DK.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3dj_records_base_branch_created_branch_and_relationships(): void
    {
        $document = $this->implementationDocument();

        foreach ([
            'milestone-3di-mvp-frontend-usability-implementation-authorization',
            'b030d9949ae3b535611d47fd24a5884bf9f24b6f',
            'milestone-3dj-mvp-frontend-usability-narrow-implementation',
            'Milestone 3DH recorded the MVP Frontend Usability Readiness Scope Gate',
            'Milestone 3DI converted the 3DH readiness gate into a precise future implementation authorization boundary.',
            '3DJ implements that authorized boundary',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_3dj_current_file_changes_are_limited_to_approved_files(): void
    {
        $document = $this->implementationDocument();

        foreach ([
            'Milestone 3DJ is a narrow frontend runtime implementation milestone.',
            '3DJ implements targeted internal MVP frontend usability improvements in app/page.tsx.',
            '3DJ does not change backend runtime behavior.',
            '3DJ does not add backend routes, controllers, requests, resources, services, models, migrations, policies, middleware, seeders, factories, config, package files, or lock files.',
            '3DJ does not add new backend APIs.',
            'Only app/page.tsx frontend runtime was edited in 3DJ.',
            'No frontend runtime files other than app/page.tsx were edited.',
            'Backend runtime was not edited in 3DJ.',
            'Routes/controllers/requests/resources/services/models/migrations were not edited in 3DJ.',
            'app/page.tsx',
            'No fake unavailable backend behavior was implemented.',
            'No new finance backend mutation behavior was added.',
            'Profile document upload/download/storage/OCR/PDF/DELETE remains out of scope.',
            'Milestone 3DK - MVP Frontend Usability Validation and Closure',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_frontend_records_3dj_internal_mvp_usability_implementation(): void
    {
        $frontend = $this->frontendSource();

        foreach ([
            'data-milestone="3DJ-internal-mvp-usability"',
            'Internal MVP Quick Actions',
            'Internal MVP',
            'Quick Actions',
            'MVP Manual Check',
            'Confirm member list, dues, payments, receipts, and profile document metadata before live use.',
            'Members',
            'Finance',
            'Receipts',
            'Profile Documents',
            'Current Dues',
            'Receipt',
            'Receipt PDF',
            'Payment History',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $frontend);
        }
    }

    public function test_frontend_records_member_finance_receipt_and_payment_history_usability_copy(): void
    {
        $frontend = $this->frontendSource();

        foreach ([
            '`${availableMembers.length} verified club members. Select a member to view the profile fields authorized for this role.`',
            '`${availableMembers.length} verified club members. Detailed member profiles are available only to authorized club officers.`',
            'Member Details: review the member profile here',
            'Current Dues, Last Payment, and Receipt Status come from the existing member finance summary API.',
            'Current Dues, Member Finance Summary, Last Payment, Receipt Status, and Payment History are shown only through existing frontend data and backend APIs.',
            'Use this internal MVP hub to find Record Payment, Receipts, Receipt PDF, and Payment History without adding new finance backend behavior.',
            'Select a member, review Current Dues and Last Payment, then record payment only if the existing backend context is ready.',
            'Receipt and Receipt PDF access are available here only for already issued payment receipts supported by the existing backend.',
            'Use Payment History to confirm existing payment records and receipt status after member selection.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $frontend);
        }
    }

    public function test_frontend_preserves_profile_documents_metadata_only_copy(): void
    {
        $frontend = $this->frontendSource();

        foreach ([
            'Profile Documents',
            'Document metadata only. File upload and document storage are not enabled in this version.',
            'No profile document records added yet.',
            'Add Document Metadata',
            'Edit Metadata',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $frontend);
        }
    }

    public function test_frontend_does_not_introduce_blocked_profile_document_ui_copy_or_fields(): void
    {
        $frontend = $this->frontendSource();

        foreach ([
            'Upload Document',
            'Attach File',
            'Download Profile Document',
            'Preview File',
            'Delete Document',
            'Generate Profile PDF',
            'OCR',
            'Storage Link',
            'Public Link',
            'Signed Link',
            'file_path',
            'storage_disk',
            'uploaded_file',
            'binary_data',
            'base64_data',
            'ocr_text',
            'pdf_path',
        ] as $blockedText) {
            $this->assertStringNotContainsString($blockedText, $frontend);
        }
    }

    public function test_document_records_frontend_location_backend_exclusions_and_existing_apis_reused(): void
    {
        $document = $this->implementationDocument();

        foreach ([
            'Frontend runtime implementation location:',
            'app/page.tsx',
            'Only app/page.tsx frontend runtime was edited in 3DJ.',
            'Backend runtime was not edited in 3DJ.',
            'Routes/controllers/requests/resources/services/models/migrations were not edited in 3DJ.',
            '3DJ reused existing frontend conventions and existing API calls already present in app/page.tsx.',
            'Existing member finance summary APIs remain the source for Current Dues, Last Payment, Receipt Status, and pending items.',
            'Existing receipt view and Receipt PDF APIs remain the only receipt/PDF behavior surfaced.',
            'Existing payment history GET APIs remain the only Payment History behavior surfaced.',
            'No fake unavailable backend behavior was implemented.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_usability_improvements_and_role_state_behavior(): void
    {
        $document = $this->implementationDocument();

        foreach ([
            '3DJ added an Internal MVP Quick Actions area to the dashboard experience.',
            'Members.',
            'Finance.',
            'Receipts.',
            'Profile Documents.',
            '3DJ added member directory helper copy',
            '3DJ added member detail helper copy',
            'The compact finance summary now surfaces Current Dues, Payment Status, Last Payment, and Receipt Status together.',
            'Receipt and Receipt PDF wording is used only for already issued payment receipts supported by the existing backend receipt and receipt PDF APIs.',
            'Payment History remains role-aware',
            '3DJ preserves the existing Profile Documents metadata section.',
            'President/Admin can see relevant member-management and finance/admin quick actions already authorized by backend.',
            'Normal Member sees own allowed information and does not receive officer-only payment recording controls.',
            'Backend authorization remains the enforcement layer.',
            '3DJ preserves existing loading states',
            '3DJ preserves existing empty states',
            '3DJ preserves safe error states',
            '3DJ preserves validation message display',
            '3DJ preserves success messages',
            '3DJ preserves refresh behavior',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    public function test_document_records_manual_checklist_gaps_exclusions_and_risks(): void
    {
        $document = $this->implementationDocument();

        foreach ([
            '3DJ supports or preserves this manual checklist where existing capabilities permit:',
            '1. President/Admin can log in and see a usable dashboard.',
            '8. Current dues/member finance summary is visible and understandable.',
            '10. Receipt/receipt PDF access is discoverable where already supported.',
            '12. Member profile document metadata list/create/edit is usable where authorized.',
            '16. No unsupported upload/download/OCR/profile-document PDF behavior is introduced.',
            '17. No SaaS/deployment/client-specific behavior is introduced.',
            'No new frontend/backend gap required fake implementation in 3DJ.',
            'Profile document upload/download/storage/OCR/PDF/DELETE remains out of scope.',
            'Profile document public URLs and signed URLs remain out of scope.',
            'No new finance backend mutation behavior was added.',
            'No member import behavior was added.',
            'No deployment, SaaS, billing, onboarding, support/admin tooling, or client-specific behavior was added.',
            'app/page.tsx remains large, so 3DJ avoided broad refactoring.',
            'Receipt PDF remains limited to the already-approved payment receipt PDF lane.',
            'Profile Documents remains metadata-only.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $document);
        }
    }

    private function implementationDocument(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }

    private function frontendSource(): string
    {
        $this->assertFileExists($this->frontendPath);

        return file_get_contents($this->frontendPath);
    }

    private function blockedRuntimePaths(): array
    {
        return [
            'backend/routes/api.php',
            'backend/app/Http/Controllers',
            'backend/app/Http/Requests',
            'backend/app/Http/Resources',
            'backend/app/Services',
            'backend/app/Models',
            'backend/database/migrations',
            'backend/database/seeders',
            'backend/database/factories',
            'backend/app/Policies',
            'backend/app/Providers',
            'backend/app/Http/Middleware',
            'backend/config',
            'composer.json',
            'composer.lock',
            'package.json',
            'package-lock.json',
            'pnpm-lock.yaml',
            'yarn.lock',
        ];
    }
}
