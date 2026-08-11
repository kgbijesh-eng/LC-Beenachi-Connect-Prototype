<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Api\V1\Announcements\ClubAnnouncementController;
use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubAnnouncement;
use App\Models\ClubAnnouncementPublicationDecision;
use App\Models\ClubMembership;
use App\Models\Role;
use App\Models\User;
use App\Services\Announcements\ClubAnnouncementWriteService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class Milestone3iiwAnnouncementMutationServiceFoundationTest extends TestCase
{
    use RefreshDatabase;

    private ClubAnnouncementWriteService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->service = app(ClubAnnouncementWriteService::class);
    }

    public function test_exact_internal_surface_and_resolution_are_non_mutating(): void
    {
        $before = [ClubAnnouncement::count(), ClubAnnouncementPublicationDecision::count(), AuditLog::count()];
        $methods = collect((new ReflectionClass(ClubAnnouncementWriteService::class))->getMethods(\ReflectionMethod::IS_PUBLIC))
            ->filter(fn ($method) => $method->getDeclaringClass()->getName() === ClubAnnouncementWriteService::class)
            ->pluck('name')->sort()->values()->all();

        $this->assertSame(['__construct', 'approve', 'archive', 'createDraft', 'createReplacementDraft', 'publish', 'rejectApproval', 'revokeApproval', 'updateDraft', 'withdraw'], $methods);
        $this->assertSame($before, [ClubAnnouncement::count(), ClubAnnouncementPublicationDecision::count(), AuditLog::count()]);

        $routes = collect(Route::getRoutes())->filter(fn ($route) => str_contains($route->uri(), 'clubs/{club}/announcements'));
        $this->assertCount(12, $routes);
        $readRoute = $routes->first(fn ($route) => $route->uri() === 'api/v1/clubs/{club}/announcements/{announcement}/read');
        $this->assertNotNull($readRoute);
        $this->assertContains('PUT', $readRoute->methods());
        $this->assertCount(0, $routes->filter(fn ($route) => in_array('DELETE', $route->methods(), true)));
        $controllerMethods = collect((new ReflectionClass(ClubAnnouncementController::class))->getMethods(\ReflectionMethod::IS_PUBLIC))
            ->filter(fn ($method) => $method->getDeclaringClass()->getName() === ClubAnnouncementController::class)
            ->pluck('name')->sort()->values()->all();
        $this->assertSame(['approve', 'archive', 'index', 'markRead', 'publish', 'rejectApproval', 'revokeApproval', 'show', 'store', 'storeReplacement', 'update', 'withdraw'], $controllerMethods);
    }

    public function test_creation_authorization_exact_attributes_and_canonical_content(): void
    {
        $club = $this->club('CREATE');
        $president = $this->actor($club, 'president-admin');
        $created = $this->service->createDraft($club, $president, [
            'title' => "\u{2003}അറിയിപ്പ്\u{2003}",
            'body' => "  First\r\nSecond\r  ",
            'priority' => 'high',
            'audience' => 'club-members',
            'scheduled_publish_at' => '2026-07-20T12:30:00+05:30',
            'expires_at' => null,
        ]);
        $this->assertSame('അറിയിപ്പ്', $created->title);
        $this->assertSame("  First\nSecond\n  ", $created->body);
        $this->assertSame('2026-07-20 07:00:00', $created->scheduled_publish_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame($club->id, $created->club_id);
        $this->assertSame($president->id, $created->created_by_user_id);
        $this->assertSame(1, $created->lock_version);
        $this->assertDatabaseCount('audit_logs', 1);

        foreach ([['member', 'active'], ['treasurer', 'active'], ['secretary', 'inactive']] as [$slug, $status]) {
            try {
                $this->service->createDraft($club, $this->actor($club, $slug, $status), $this->draftInput());
                $this->fail('Expected authorization failure.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
        $this->assertDatabaseCount('audit_logs', 1);

        foreach ([
            array_merge($this->draftInput(), ['club_id' => $club->id]),
            array_merge($this->draftInput(), ['title' => []]),
            array_merge($this->draftInput(), ['priority' => true]),
            array_merge($this->draftInput(), ['scheduled_publish_at' => '2026-02-30T12:00:00+00:00']),
            array_merge($this->draftInput(), ['body' => " \u{2003}\n "]),
        ] as $invalid) {
            $this->expectValidation(fn () => $this->service->createDraft($club, $president, $invalid));
        }
    }

    public function test_partial_updates_no_op_stale_cross_club_and_body_whitespace_materiality(): void
    {
        $club = $this->club('UPDATE');
        $other = $this->club('OTHER');
        $secretary = $this->actor($club, 'secretary');
        $draft = $this->service->createDraft($club, $secretary, $this->draftInput());

        $updated = $this->service->updateDraft($club, $draft, $secretary, 1, ['body' => " Body\r\n"]);
        $this->assertSame(" Body\n", $updated->body);
        $this->assertSame(2, $updated->lock_version);
        $this->expectValidation(fn () => $this->service->updateDraft($club, $updated, $secretary, 2, []));
        $this->expectValidation(fn () => $this->service->updateDraft($club, $updated, $secretary, 2, ['body' => " Body\r\n"]));
        $this->expectConflict(fn () => $this->service->updateDraft($club, $updated, $secretary, 1, ['title' => 'Stale']));

        try {
            $this->service->updateDraft($other, $updated, $secretary, 2, ['title' => 'Cross club']);
            $this->fail('Expected concealed identity failure.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
        $this->assertSame(2, AuditLog::count());
    }

    public function test_routine_and_gated_publication_decision_finality_and_provenance(): void
    {
        Carbon::setTestNow('2026-07-20 12:00:00 UTC');
        $club = $this->club('DECIDE');
        $maker = $this->actor($club, 'president-admin');
        $checker = $this->actor($club, 'secretary');

        $routine = $this->service->createDraft($club, $maker, $this->draftInput());
        $this->expectConflict(fn () => $this->service->approve($club, $routine, $checker, 1));
        $routine = $this->service->publish($club, $routine, $checker, 1);
        $this->assertSame('published', $routine->status);
        $this->assertSame('2026-07-20 12:00:00', $routine->published_at->utc()->format('Y-m-d H:i:s'));

        $gated = $this->service->createDraft($club, $maker, array_merge($this->draftInput(), ['priority' => 'urgent']));
        $this->expectConflict(fn () => $this->service->publish($club, $gated, $checker, 1));
        $approval = $this->service->approve($club, $gated, $checker, 1, null);
        $this->assertSame($maker->id, $approval->maker_user_id);
        $this->assertSame('president', $approval->maker_role);
        $this->assertSame('secretary', $approval->decided_by_role);
        $this->expectConflict(fn () => $this->service->approve($club, $gated, $checker, 1));
        $gated = $this->service->publish($club, $gated, $maker, 1);
        $this->assertSame(2, $gated->lock_version);

        $rejected = $this->service->createDraft($club, $maker, array_merge($this->draftInput(), ['audience' => 'officers']));
        $decision = $this->service->rejectApproval($club, $rejected, $checker, 1, 'Needs correction');
        $this->assertSame('rejected', $decision->decision);
        $this->expectConflict(fn () => $this->service->approve($club, $rejected, $checker, 1));
        $this->expectValidation(fn () => $this->service->rejectApproval($club, $this->service->createDraft($club, $maker, array_merge($this->draftInput(), ['priority' => 'urgent'])), $checker, 1, '   '));
    }

    public function test_revocation_withdrawal_archive_and_terminal_replays(): void
    {
        $club = $this->club('LIFE');
        $maker = $this->actor($club, 'president-admin');
        $checker = $this->actor($club, 'secretary');
        $draft = $this->service->createDraft($club, $maker, array_merge($this->draftInput(), ['priority' => 'urgent']));
        $approval = $this->service->approve($club, $draft, $checker, 1, 'Reviewed');
        $revocation = $this->service->revokeApproval($club, $draft, $checker, 1, $approval, 'Approval withdrawn');
        $this->assertSame($approval->id, $revocation->supersedes_decision_id);
        $this->expectConflict(fn () => $this->service->revokeApproval($club, $draft, $checker, 1, $approval, 'Again'));
        $this->expectConflict(fn () => $this->service->publish($club, $draft, $maker, 1));

        $routine = $this->service->createDraft($club, $maker, $this->draftInput());
        $published = $this->service->publish($club, $routine, $maker, 1);
        $this->expectValidation(fn () => $this->service->withdraw($club, $published, $checker, 2, '  '));
        $withdrawn = $this->service->withdraw($club, $published, $checker, 2, 'Reason retained');
        $this->assertSame('Reason retained', $withdrawn->withdrawal_reason);
        $archived = $this->service->archive($club, $withdrawn, $maker, 3);
        $this->assertSame('archived', $archived->status);
        $this->expectConflict(fn () => $this->service->archive($club, $archived, $maker, 4));
    }

    public function test_replacement_creation_publication_and_truthful_reads(): void
    {
        Carbon::setTestNow('2026-07-20 12:00:00 UTC');
        $club = $this->club('REPLACE');
        $maker = $this->actor($club, 'president-admin');
        $member = $this->actor($club, 'member');
        $source = $this->published($club, $maker, ['expires_at' => '2026-07-20 11:00:00']);
        $replacement = $this->service->createReplacementDraft($club, $source, $maker, [
            'title' => 'Corrected notice', 'body' => 'Corrected body', 'priority' => 'normal',
            'scheduled_publish_at' => null, 'expires_at' => null,
        ]);
        $this->assertSame($source->id, $replacement->replaces_announcement_id);
        $this->assertSame($source->audience, $replacement->audience);
        $this->expectConflict(fn () => $this->service->createReplacementDraft($club, $source, $maker, [
            'title' => 'Branch', 'body' => 'Branch body', 'priority' => 'normal',
        ]));
        $replacement = $this->service->publish($club, $replacement, $maker, 1);
        $this->assertSame('published', $source->fresh()->status);
        $this->assertNull($source->fresh()->withdrawn_at);

        Sanctum::actingAs($member);
        $current = $this->getJson("/api/v1/clubs/{$club->id}/announcements?scope=current&per_page=1")->assertOk();
        $current->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $replacement->id)
            ->assertJsonPath('data.0.replaces_announcement_id', $source->id);
        $history = $this->getJson("/api/v1/clubs/{$club->id}/announcements?scope=history")->assertOk();
        $history->assertJsonPath('data.0.id', $source->id)->assertJsonPath('data.0.effective_status', 'replaced')
            ->assertJsonPath('data.0.replaced_by_announcement_id', $replacement->id);

        Sanctum::actingAs($maker);
        $management = $this->getJson("/api/v1/clubs/{$club->id}/announcements/{$source->id}")->assertOk();
        $management->assertJsonPath('data.direct_replacement_announcement_ids.0', $replacement->id);
    }

    public function test_concealed_successor_does_not_affect_ordinary_projection(): void
    {
        $club = $this->club('CONCEAL');
        $president = $this->actor($club, 'president-admin');
        $member = $this->actor($club, 'member');
        $source = $this->published($club, $president);
        $this->published($club, $president, ['audience' => 'officers', 'replaces_announcement_id' => $source->id]);

        Sanctum::actingAs($member);
        $payload = $this->getJson("/api/v1/clubs/{$club->id}/announcements?scope=current")->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $source->id)->json('data.0');
        $this->assertSame('published', $payload['effective_status']);
        $this->assertArrayNotHasKey('replaced_by_announcement_id', $payload);
    }

    public function test_audit_failure_rolls_back_domain_and_decision_persistence(): void
    {
        $club = $this->club('ROLLBACK');
        $president = $this->actor($club, 'president-admin');
        AuditLog::creating(fn () => throw new RuntimeException('Synthetic audit failure'));
        try {
            $this->service->createDraft($club, $president, $this->draftInput());
            $this->fail('Expected synthetic audit failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic audit failure', $exception->getMessage());
        } finally {
            AuditLog::flushEventListeners();
        }
        $this->assertDatabaseCount('club_announcements', 0);
        $this->assertDatabaseCount('club_announcement_publication_decisions', 0);
        $this->assertDatabaseCount('audit_logs', 0);

        $checker = $this->actor($club, 'secretary');
        $draft = $this->service->createDraft($club, $president, array_merge($this->draftInput(), ['priority' => 'urgent']));
        AuditLog::creating(fn () => throw new RuntimeException('Synthetic decision audit failure'));
        try {
            $this->service->approve($club, $draft, $checker, 1);
            $this->fail('Expected synthetic decision audit failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic decision audit failure', $exception->getMessage());
        } finally {
            AuditLog::flushEventListeners();
        }
        $this->assertDatabaseCount('club_announcements', 1);
        $this->assertDatabaseCount('club_announcement_publication_decisions', 0);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    private function club(string $code): Club
    {
        return Club::query()->create(['name' => 'Synthetic '.$code, 'code' => $code.'-'.uniqid(), 'timezone' => 'UTC', 'is_active' => true]);
    }

    private function actor(Club $club, string $roleSlug, string $status = 'active'): User
    {
        $user = User::factory()->create();
        ClubMembership::query()->create([
            'club_id' => $club->id, 'user_id' => $user->id,
            'role_id' => Role::query()->where('slug', $roleSlug)->firstOrFail()->id,
            'status' => $status, 'joined_at' => now(),
        ]);

        return $user;
    }

    private function draftInput(): array
    {
        return ['title' => 'Synthetic notice', 'body' => 'Synthetic body', 'priority' => 'normal', 'audience' => 'club-members'];
    }

    private function published(Club $club, User $actor, array $overrides = []): ClubAnnouncement
    {
        return ClubAnnouncement::query()->create(array_merge([
            'club_id' => $club->id, 'title' => 'Published notice', 'body' => 'Published body',
            'priority' => 'normal', 'audience' => 'club-members', 'status' => 'published',
            'published_at' => '2026-07-20 10:00:00', 'published_by_user_id' => $actor->id,
            'published_by_role' => 'president', 'created_by_user_id' => $actor->id,
            'updated_by_user_id' => $actor->id, 'lock_version' => 2,
        ], $overrides));
    }

    private function expectValidation(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected validation failure.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }

    private function expectConflict(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected conflict.');
        } catch (ConflictHttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
    }
}
