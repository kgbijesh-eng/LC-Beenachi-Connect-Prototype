<?php

namespace Tests\Feature;

use Tests\TestCase;

class Milestone3fmEventsMeetingsBackendActivationScopeGateTest extends TestCase
{
    private const VERIFIED_3FM_COMMIT = 'd3fb29775c83f8c16b99fd53808a5d0e27278eb4';

    private string $docPath = __DIR__ . '/../../docs/milestone_3fm_events_meetings_backend_activation_scope_gate.md';
    private string $repoPath = __DIR__ . '/../../..';

    public function test_3fm_document_exists_and_is_scope_gate_only(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Milestone 3FM - Events & Meetings Backend Activation Scope Gate',
            '67687dcf4bc24f8e9035ab63671b6207a4d11676',
            'documentation-and-governance scope gate only',
            'No runtime implementation is authorized in 3FM.',
            '3FM must not start 3FN or any implementation milestone.',
            'backend/docs/milestone_3fm_events_meetings_backend_activation_scope_gate.md',
            'backend/tests/Feature/Milestone3fmEventsMeetingsBackendActivationScopeGateTest.php',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fm_records_current_fail_closed_frontend_and_backend_inventory(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'The Events & Meetings workflow is not connected to a verified operational source yet.',
            'Real events and meeting records will appear here only after a verified source is connected.',
            'Current backend capability is NOT CONNECTED.',
            'no Event, Meeting, or Calendar model',
            'verifiedOperationalSourceConnected("events") is false',
            'Hardcoded fallback must never return.',
            'backend/routes/api.php',
            'backend/app/Services/Access/ClubPermissionService.php',
            'backend/app/Models/AuditLog.php',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fm_selects_unified_architecture_and_minimum_data_contract(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Option A - unified Events & Meetings entity (selected)',
            'Option B - separate Event and Meeting entities (rejected for first release)',
            'Use App\\Models\\ClubEvent, table club_events',
            'type values event and meeting',
            '| id | big integer PK |',
            '| club_id | required FK, restrict delete |',
            '| starts_at | required UTC timestamp |',
            '| status | controlled string, default draft |',
            '| visibility | required controlled string |',
            '| created_by_user_id | required FK, restrict delete |',
            '| updated_by_user_id | required FK, restrict delete |',
            '| lock_version | unsigned integer, default 1 |',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fm_defines_timezone_and_lifecycle_rules(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'Store starts_at, ends_at, lifecycle timestamps, and Laravel timestamps as UTC instants.',
            'clubs.timezone',
            'Asia/Kolkata',
            'ISO-8601/RFC 3339',
            'Draft -> Published',
            'Draft -> Cancelled',
            'Published -> Completed',
            'Published -> Cancelled',
            'Published cannot return to Draft.',
            'Completed and Cancelled are terminal and immutable.',
            'Cancellation requires a nonblank reason',
            'Delete and soft delete are excluded',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fm_defines_role_authorization_club_scope_and_stable_identity(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'events.view',
            'events.manage',
            '| Create | yes | yes | no | no |',
            '| Publish | yes | yes | no | no |',
            '| Delete | no | no | no | no |',
            'menu hiding is not authorization',
            'Every record belongs to exactly one club.',
            'event.club_id equals club.id',
            'stable backend id',
            'Title, date, list index, or array position are forbidden identities.',
            'Cross-club ID tampering returns 404',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fm_defines_api_filtering_validation_and_data_truth_contracts(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'GET /clubs/{club}/events',
            'GET /clubs/{club}/events/{event}',
            'POST /clubs/{club}/events',
            'PATCH /clubs/{club}/events/{event}',
            'POST /clubs/{club}/events/{event}/publish',
            'POST /clubs/{club}/events/{event}/cancel',
            'POST /clubs/{club}/events/{event}/complete',
            'Never expose PATCH status or DELETE.',
            'scope=upcoming|past|all',
            'Use Laravel Form Requests',
            'lock_version is required',
            'loading, records, genuine empty, failure, unauthenticated, forbidden, and rollout not-connected',
            'must not use the legacy array',
            'report success before backend 2xx',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fm_defines_audit_integrity_exclusions_tests_and_rollback(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            'events.created',
            'events.updated',
            'events.published',
            'events.cancelled',
            'events.completed',
            'same transaction',
            'lock the target row',
            'Stale/conflicting updates return 409',
            'Attendance, RSVP, guests',
            'Meeting minutes',
            'Recurring events',
            'Reminders, email, WhatsApp, push',
            'Calendar sync/Google Calendar',
            'Database tests:',
            'Authorization tests:',
            'API tests:',
            'Lifecycle/integrity tests:',
            'Audit tests:',
            'Later frontend tests:',
            'restore exact 3FL unavailable wording',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_3fm_defines_sequence_open_questions_and_authorizes_3fn_only(): void
    {
        $document = $this->scopeDocument();

        foreach ([
            '3FN - Events & Meetings Backend Authorization and Contract Approval',
            '3FO - Database and Read API Foundation',
            '3FP - Create, Update and Lifecycle Backend Workflow',
            '3FQ - Frontend Integration',
            '3FR - Human/Browser Verification',
            '3FS - Closure Review',
            'Open questions for 3FN',
            'GO - Milestone 3FN may open',
            'This verdict authorizes 3FN only.',
            'does not authorize Events & Meetings runtime implementation',
        ] as $expected) {
            $this->assertStringContainsString($expected, $document);
        }
    }

    public function test_historical_3fm_contract_blocked_mutation_and_frontend_activation(): void
    {
        foreach ([
            app_path('Http/Requests/Events/PublishClubEventRequest.php'),
            app_path('Http/Requests/Events/CancelClubEventRequest.php'),
            app_path('Http/Requests/Events/CompleteClubEventRequest.php'),
            app_path('Policies/ClubEventPolicy.php'),
        ] as $blockedPath) {
            $this->assertFileDoesNotExist($blockedPath);
        }

        $routes = file_get_contents(base_path('routes/api.php'));
        foreach ([
            "Route::delete('/clubs/{club}/events/{event}'",
        ] as $blockedRoute) {
            $this->assertStringNotContainsString($blockedRoute, $routes);
        }

        $frontend = $this->gitOutput(['show', self::VERIFIED_3FM_COMMIT.':app/page.tsx']);
        $frontend = $this->normalizeLineEndings($frontend);
        $this->assertStringContainsString('The Events & Meetings workflow is not connected to a verified operational source yet.', $frontend);
        $this->assertStringContainsString('Real events and meeting records will appear here only after a verified source is connected.', $frontend);
    }

    private function scopeDocument(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }
    private function gitOutput(array $arguments): string
    {
        $repoRoot = realpath($this->repoPath) ?: $this->repoPath;
        $command = array_merge(['git', '-C', $repoRoot], $arguments);
        $escapedCommand = implode(' ', array_map('escapeshellarg', $command));

        exec($escapedCommand . ' 2>&1', $output, $exitCode);

        $this->assertSame(0, $exitCode, sprintf(
            'Expected local Git command to succeed: %s. Output: %s',
            $escapedCommand,
            implode("\n", $output)
        ));

        return implode("\n", $output);
    }

    private function normalizeLineEndings(string $value): string
    {
        return str_replace(["\r\n", "\r"], "\n", $value);
    }
}
