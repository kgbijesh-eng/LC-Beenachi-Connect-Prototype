<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3frEventsMeetingsBrowserRoleVerificationTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3fr_events_meetings_browser_role_verification.md';

    public function test_3fr_document_records_milestone_identity_base_and_isolated_environment(): void
    {
        $document = $this->document();

        foreach ([
            'Milestone 3FR - Events & Meetings Browser and Role Verification',
            'milestone-3fqa-3fpa-governance-test-lifecycle-repair',
            '9bd6ede2db5f72cf8d1ff17cc4beb5dcc4feab18',
            'milestone-3fr-events-meetings-browser-role-verification',
            '%TEMP%\\\\lc_beenachi_3fr_isolated.sqlite',
            'http://localhost:3000/',
            'http://127.0.0.1:8000/api/v1',
            '3FR Synthetic Isolated Club',
            'lc_beenachi_connect',
            'No genuine imported member, payment, document, profile, Fellowship, Event, or Meeting data was intentionally read or mutated.',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fr_document_records_required_roles_and_records(): void
    {
        $document = $this->document();

        foreach ([
            'President/Admin: `president@lcbeenachi.test`',
            'Secretary: `secretary@lcbeenachi.test`',
            'Treasurer: `treasurer@lcbeenachi.test`',
            'Normal Member: `local.member@lcbeenachi.test`',
            'Inactive member: `inactive.3fr@lcbeenachi.test`',
            'Another-club member: `otherclub.3fr@lcbeenachi.test`',
            'Draft meeting.',
            'Draft event.',
            'Published `members` meeting.',
            'Published `management` event.',
            'Completed `members` record.',
            'Cancelled previously Published `members` record.',
            'All-day record.',
            'Stale-version record.',
            '3FR Duplicate Title Identity',
            '3FR ISOLATED SENTINEL Published Members Meeting',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fr_document_records_browser_role_results(): void
    {
        $document = $this->document();

        foreach ([
            'President/Admin browser workflows verified',
            'Secretary loaded Events & Meetings from the verified backend',
            'Treasurer used the read-only Club Visibility path',
            'Normal Member saw only member-visible allowed records',
            'Unauthenticated Events API access',
            'Inactive same-club membership could not list Events',
            'Cross-club list access returned 403',
            'Direct cross-club object attempts and wrong nested route ownership attempts returned 404',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fr_document_records_lifecycle_identity_data_truth_and_responsive_results(): void
    {
        $document = $this->document();

        foreach ([
            'Timed Draft creation through the UI persisted backend record ID 19.',
            'Edit form explicitly reported selected backend ID 19 and lock version 1.',
            'Publishing the selected Draft changed backend status to `published` and lock version to 3.',
            'Eligible Published record completed successfully.',
            'Future timed completion was denied',
            'Future all-day completion was denied',
            'Stale update returned 409.',
            'DELETE returned 405.',
            'Detail view displayed `Stable backend ID`.',
            'Two `3FR Duplicate Title Identity` records remained distinguishable',
            'No local-only save, fake persistence, false success, dummy Event record, fake attendance, or backend error masking was observed.',
            '1440px: President Meeting Notices',
            '900px: Treasurer read-only Upcoming Events',
            '390px: Normal Member Events & Meetings',
            'no document-level horizontal overflow',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fr_document_records_fixed_defect_retests_and_no_unresolved_scope_expansion(): void
    {
        $document = $this->document();

        foreach ([
            'IN-SCOPE DEFECT - FIXED',
            'Cancelled Draft member-visible records were visible to Normal Member/Treasurer',
            'Root cause: `ClubEventReadService` treated every `cancelled` `members` record as readable',
            'Updated `backend/app/Services/Events/ClubEventReadService.php`',
            'Added a focused regression to `backend/tests/Feature/Milestone3foEventsMeetingsDatabaseReadApiFoundationTest.php`',
            'Member browser retest hid cancelled Draft records',
            'No unresolved in-scope defect remains.',
            'No environment, database, cache, log, secret, generated, or temporary file is staged.',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fr_document_records_regression_build_changed_files_and_3fs_gate(): void
    {
        $document = $this->document();

        foreach ([
            'Baseline 3FQA governance test: passed, 9 tests, 86 assertions.',
            '3FPA isolated alignment test: passed, 5 tests, 131 assertions.',
            '3FP lifecycle backend workflow test: passed, 10 tests, 81 assertions.',
            '3FO database/read foundation test after correction: passed, 13 tests, 129 assertions.',
            '3FN authorization contract test: passed, 11 tests, 157 assertions.',
            '3FM scope gate test: passed, 9 tests, 111 assertions.',
            '`npm run build`: passed before browser verification. Final validation must rerun before commit.',
            'backend/docs/milestone_3fr_events_meetings_browser_role_verification.md',
            'backend/tests/Feature/Milestone3frEventsMeetingsBrowserRoleVerificationTest.php',
            'backend/app/Services/Events/ClubEventReadService.php',
            'backend/tests/Feature/Milestone3foEventsMeetingsDatabaseReadApiFoundationTest.php',
            '3FS may open only after 3FR final validation passes',
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
