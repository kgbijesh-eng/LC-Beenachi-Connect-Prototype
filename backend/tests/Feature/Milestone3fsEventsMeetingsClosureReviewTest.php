<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3fsEventsMeetingsClosureReviewTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3fs_events_meetings_closure_review.md';

    public function test_3fs_document_records_identity_chain_and_authorized_scope(): void
    {
        $document = $this->document();

        foreach ([
            'Milestone 3FS - Events & Meetings Closure Review',
            'milestone-3fs-events-meetings-closure-review',
            '3FS is intentionally documentation and governance-test only.',
            'd3fb29775c83f8c16b99fd53808a5d0e27278eb4',
            '02206652d88317b94c025b29d5ed0a205f107ff8',
            '360c6f6a60f91bf406ce71101d17e23228ad3176',
            '9e27a7ab75d10cf2a9affab3b4d85fd576c43617',
            '3d8c9adb164815382171d5628548ea64023463ba',
            '717781ef8a0e009f62ba451ae591dcd8db76471a',
            '9bd6ede2db5f72cf8d1ff17cc4beb5dcc4feab18',
            '914913bb3c11daf1f3df8c219d015ed79b95cf45',
            'backend/docs/milestone_3fs_events_meetings_closure_review.md',
            'backend/tests/Feature/Milestone3fsEventsMeetingsClosureReviewTest.php',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fs_document_records_architecture_api_and_database_model(): void
    {
        $document = $this->document();

        foreach ([
            'Laravel API routes under `/api/v1/clubs/{club}/events`',
            'Persistent `club_events` storage',
            'Authorization that separates `events.view` from `events.manage`',
            'Frontend Events & Meetings surfaces that load from the backend',
            'GET /api/v1/clubs/{club}/events',
            'POST /api/v1/clubs/{club}/events',
            'PATCH /api/v1/clubs/{club}/events/{event}` updates allowed fields',
            'POST /api/v1/clubs/{club}/events/{event}/publish',
            'POST /api/v1/clubs/{club}/events/{event}/cancel',
            'POST /api/v1/clubs/{club}/events/{event}/complete',
            '`DELETE` is intentionally absent and returns 405.',
            'Stable primary key `id` is the frontend identity anchor',
            '`lock_version` supports optimistic concurrency',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fs_document_records_permission_role_read_and_mutation_models(): void
    {
        $document = $this->document();

        foreach ([
            'President/Admin and Secretary have `events.view` plus `events.manage`',
            'Treasurer has `events.view` only',
            'Normal Member has `events.view` only',
            'Inactive same-club memberships cannot list or mutate Events & Meetings records.',
            'Unauthenticated requests return 401',
            'Cross-club access is rejected',
            'Cancelled Draft records are hidden from Treasurer and Normal Member.',
            'Private cancellation reasons and audit internals are not exposed',
            'Creation persists a real Draft.',
            'Arbitrary status mutation is rejected.',
            'Read-only roles, inactive members, unauthenticated callers, and cross-club callers cannot mutate records.',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fs_document_records_lifecycle_all_day_audit_concurrency_and_cross_club_closure(): void
    {
        $document = $this->document();

        foreach ([
            'Draft can be created by management roles.',
            'Future timed completion is denied.',
            'Future all-day completion is denied.',
            'Delete is out of scope and remains absent.',
            'The backend rejects inconsistent all-day/timed payloads',
            'Member-visible responses do not leak private cancellation reasons',
            'A stale update returned 409.',
            'Publishing the selected Draft advanced the backend lock version to 3.',
            'Cross-club list access returned 403.',
            'wrong nested route ownership attempts returned 404',
            'client-provided `club_id` overrides',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fs_document_records_frontend_data_truth_browser_navigation_and_responsive_closure(): void
    {
        $document = $this->document();

        foreach ([
            'List cards and detail views are tied to backend records',
            'Detail view displays `Stable backend ID`',
            'Duplicate `3FR Duplicate Title Identity` records remained distinguishable',
            'No local-only save, fake persistence, false success, dummy Event record, fake attendance, or backend error masking was accepted.',
            'President/Admin used Operations -> Events & Meetings',
            'Treasurer used Club Visibility -> Events & Meetings',
            'Normal Member used member Events & Meetings navigation',
            'Role switching removed stale Draft records',
            'Responsive checks covered 1440px, 900px, and 390px viewports',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fs_document_records_regression_genuine_data_deferred_features_and_rollback(): void
    {
        $document = $this->document();

        foreach ([
            '3FR final validation before 3FS branch creation passed:',
            'Milestone3frEventsMeetingsBrowserRoleVerificationTest`: passed, 6 tests, 75 assertions.',
            'Milestone3fqa3fpaGovernanceTestLifecycleRepairTest`: passed, 9 tests, 86 assertions.',
            'Milestone3fpaEventsMeetingsIsolatedBrowserPermissionAlignmentTest`: passed, 5 tests, 131 assertions.',
            'Milestone3fpEventsMeetingsLifecycleBackendWorkflowTest`: passed, 10 tests, 81 assertions.',
            'Milestone3foEventsMeetingsDatabaseReadApiFoundationTest`: passed, 13 tests, 129 assertions.',
            'Milestone3fnEventsMeetingsBackendAuthorizationContractApprovalTest`: passed, 11 tests, 157 assertions.',
            'Milestone3fmEventsMeetingsBackendActivationScopeGateTest`: passed, 9 tests, 111 assertions.',
            '`npm run build`: passed.',
            'No genuine PostgreSQL database, imported member roster, payments, documents, profiles, Fellowship data, Event data, or Meeting data was intentionally read or mutated',
            'Attendance.',
            'WhatsApp.',
            'Google Calendar integration.',
            'Committee-specific visibility.',
            'Reverting 3FS itself removes only this closure review document and its governance test',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fs_document_records_closure_critical_findings_and_final_decision(): void
    {
        $document = $this->document();

        foreach ([
            'Closure-critical findings:',
            'The Events & Meetings stack is backed by real database records and real API calls.',
            'Role authorization is enforced at backend and reflected in frontend navigation and controls.',
            'Stable backend IDs protect duplicate-title records from ambiguous selection or mutation.',
            'Browser verification found one in-scope cancelled-Draft visibility defect, and 3FR fixed and retested it.',
            'No unresolved in-scope defect remains from 3FR.',
            'Milestone 3FS decision: Events & Meetings backend activation, browser role verification, and governance-test lifecycle are approved for closure within the stated scope',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    private function document(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }
}