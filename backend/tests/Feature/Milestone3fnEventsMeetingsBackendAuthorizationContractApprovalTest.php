<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3fnEventsMeetingsBackendAuthorizationContractApprovalTest extends TestCase
{
    private string $docPath = __DIR__ . '/../../docs/milestone_3fn_events_meetings_backend_authorization_contract_approval.md';

    public function test_3fn_document_exists_and_is_docs_tests_only_contract_approval(): void
    {
        $document = $this->contractDocument();

        foreach ([
            'Milestone 3FN - Events & Meetings Backend Authorization and API Contract Approval',
            'milestone-3fm-events-meetings-backend-activation-scope-gate',
            'd3fb29775c83f8c16b99fd53808a5d0e27278eb4',
            'milestone-3fn-events-meetings-backend-authorization-contract-approval',
            'documentation-and-governance contract approval milestone',
            'It does not implement runtime Events functionality',
            'backend/docs/milestone_3fn_events_meetings_backend_authorization_contract_approval.md',
            'backend/tests/Feature/Milestone3fnEventsMeetingsBackendAuthorizationContractApprovalTest.php',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fn_records_repository_evidence_and_runtime_absence(): void
    {
        $document = $this->contractDocument();

        foreach ([
            'backend/routes/api.php',
            'club.permission:<slug>,club',
            'DefaultRolesAndPermissionsSeeder.php',
            'ClubPermissionService.php grants permissions only through active club memberships',
            'EnsureClubPermission.php returns 401 for unauthenticated users and 403',
            'AuditLog.php',
            'No Events, Meetings, Calendar, `ClubEvent`, `club_events`, or `/events` runtime implementation exists',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fn_approves_permission_slugs_and_role_matrix(): void
    {
        $document = $this->contractDocument();

        foreach ([
            '`events.view`',
            '`events.manage`',
            '| President/Admin (`president-admin`) | Yes | Yes |',
            '| Secretary (`secretary`) | Yes | Yes |',
            '| Treasurer (`treasurer`) | Yes | No |',
            '| Normal Member (`member`) | Yes | No |',
            'Permissions never override active membership, club scope, record status, record visibility',
            '`audit_logs.view` does not grant Events management',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fn_approves_visibility_status_and_read_rules(): void
    {
        $document = $this->contractDocument();

        foreach ([
            '`members`',
            '`management`',
            'No `public`, `board`, `pst`, `officers`, `committee`, or guest visibility is approved',
            '`draft`',
            '`published`',
            '`completed`',
            '`cancelled`',
            'Draft: management only.',
            'Published + `members`: all authorized active same-club users with `events.view`.',
            'Published + `management`: management only.',
            'Completed: preserve original visibility.',
            'Cancelled: preserve prior publication visibility with one privacy carve-out.',
            'Cancellation reason is management-only in member-facing responses.',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fn_approves_lifecycle_early_completion_and_all_day_contracts(): void
    {
        $document = $this->contractDocument();

        foreach ([
            'Draft -> Published.',
            'Draft -> Cancelled.',
            'Published -> Completed.',
            'Published -> Cancelled.',
            'Published -> Draft.',
            'Any DELETE or soft-delete workflow.',
            'Early completion is not allowed in the first release.',
            'cannot be completed before `end_at`',
            'cannot be completed before `start_at`',
            'cannot be completed before the event date range ends in the owning club\'s `clubs.timezone`',
            '`end_date` is inclusive for all-day records',
            '`is_all_day: false`',
            '`start_at`',
            '`is_all_day: true`',
            '`start_date`',
            'Reject `start_at` and `end_at` on all-day records.',
            'Mixed date and timestamp representations are rejected with 422.',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fn_approves_idempotency_concurrency_and_audit_contract(): void
    {
        $document = $this->contractDocument();

        foreach ([
            'no generic idempotency-key infrastructure',
            '3FO must not introduce idempotency infrastructure.',
            '3FP must use `lock_version` plus transactional lifecycle guards.',
            'Create requests do not support idempotency keys in the first release.',
            'Update and lifecycle requests require `lock_version`.',
            'A stale `lock_version` returns 409',
            'repeat publish/cancel/complete on an already transitioned record returns 409',
            '`events.created`',
            '`events.updated`',
            '`events.published`',
            '`events.cancelled`',
            '`events.completed`',
            'Rejected requests create no success audit.',
            '3FO does not implement mutation audit writing.',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fn_approves_routes_club_scope_stable_identity_and_errors(): void
    {
        $document = $this->contractDocument();

        foreach ([
            '`GET /api/v1/clubs/{club}/events`',
            '`GET /api/v1/clubs/{club}/events/{event}`',
            '`POST /api/v1/clubs/{club}/events`',
            '`PATCH /api/v1/clubs/{club}/events/{event}`',
            '`POST /api/v1/clubs/{club}/events/{event}/publish`',
            '`POST /api/v1/clubs/{club}/events/{event}/cancel`',
            '`POST /api/v1/clubs/{club}/events/{event}/complete`',
            'No DELETE route is approved.',
            'No `PATCH status` contract is approved.',
            '`{event}` must belong to `{club}`.',
            'payload `club_id` is rejected or ignored and never trusted',
            'Stable backend numeric IDs identify records.',
            'Title, date, venue, type, array order, list position, or frontend generated keys must never identify a record.',
            'Cross-club object access fails closed with 404',
            'Known-club permission failure returns 403',
            'Unauthenticated access returns 401.',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fn_approves_request_response_filtering_and_validation_ownership(): void
    {
        $document = $this->contractDocument();

        foreach ([
            'Create request fields:',
            '`type`: required, `event` or `meeting`.',
            '`title`: required, trimmed string, max 200.',
            '`visibility`: required, `members` or `management`.',
            'Update request fields:',
            'Requires `lock_version`.',
            'Publish request:',
            'Cancel request:',
            'Requires `cancellation_reason`, trimmed string, 1-1000.',
            'Complete request:',
            'Unknown/client-owned fields are rejected with 422',
            'Use explicit API resources; never blind serialize the model.',
            'Top-level `data` array.',
            'Top-level `data` object.',
            '401 unauthenticated.',
            '403 authenticated but missing route/action permission',
            '404 nonexistent, wrong-club, undisclosed, or unauthorized object identity.',
            '409 stale lock version',
            '`scope=upcoming|past|all`',
            '`type=event|meeting`',
            '`per_page`, default 20, max 50.',
            'Upcoming default: `starts_at asc, id asc`.',
            'Past default: `starts_at desc, id desc`.',
            'Laravel Form Requests own request shape',
            'Service/domain code owns:',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fn_defines_3fo_and_3fp_boundaries_and_deferred_features(): void
    {
        $document = $this->contractDocument();

        foreach ([
            '3FO may implement only:',
            '`club_events` migration.',
            '`App\\Models\\ClubEvent`.',
            'list controller endpoint.',
            'detail controller endpoint.',
            '3FO must not implement create, update, publish, cancel, complete',
            '3FP may implement only after 3FO is committed, pushed, remotely verified, clean',
            'create Draft;',
            'publish;',
            'cancel;',
            'complete;',
            '3FP must not implement frontend integration',
            'attendance;',
            'RSVP;',
            'meeting minutes;',
            'recurrence;',
            'reminders;',
            'external calendar integration;',
            'delete or soft delete.',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fn_preserves_frontend_data_truth_and_acceptance_criteria(): void
    {
        $document = $this->contractDocument();

        foreach ([
            'The existing 3FL Events & Meetings not-connected state remains correct through 3FN, 3FO, and 3FP.',
            'Frontend activation is not authorized until 3FQ.',
            'No milestone may restore hardcoded Events data',
            'false success',
            'dummy fallback',
            'failure-to-empty conversion',
            'Stable backend IDs are mandatory',
            'this document and its governance test are the only changed files',
            'no runtime Events implementation is added',
            'Approve events meetings authorization contract',
            'GO - Milestone 3FO may open',
            'This verdict authorizes 3FO only.',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fn_contract_still_blocks_mutation_and_frontend_activation_until_later_milestones(): void
    {
        foreach ([
            app_path('Http/Requests/Events/StoreClubEventRequest.php'),
            app_path('Http/Requests/Events/UpdateClubEventRequest.php'),
            app_path('Http/Requests/Events/PublishClubEventRequest.php'),
            app_path('Http/Requests/Events/CancelClubEventRequest.php'),
            app_path('Http/Requests/Events/CompleteClubEventRequest.php'),
            app_path('Services/Events/ClubEventWriteService.php'),
            app_path('Policies/ClubEventPolicy.php'),
        ] as $blockedPath) {
            $this->assertFileDoesNotExist($blockedPath);
        }

        $routes = file_get_contents(base_path('routes/api.php'));
        foreach ([
            "Route::post('/clubs/{club}/events'",
            "Route::patch('/clubs/{club}/events/{event}'",
            "Route::post('/clubs/{club}/events/{event}/publish'",
            "Route::post('/clubs/{club}/events/{event}/cancel'",
            "Route::post('/clubs/{club}/events/{event}/complete'",
            "Route::delete('/clubs/{club}/events/{event}'",
        ] as $blockedRoute) {
            $this->assertStringNotContainsString($blockedRoute, $routes);
        }

        $frontend = file_get_contents(base_path('../app/page.tsx'));
        $this->assertStringContainsString('The Events & Meetings workflow is not connected to a verified operational source yet.', $frontend);
        $this->assertStringContainsString('Real events and meeting records will appear here only after a verified source is connected.', $frontend);
    }

    private function contractDocument(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }
}
