<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\V1\Announcements\ClubAnnouncementController;
use App\Models\Club;
use App\Models\ClubAnnouncement;
use App\Models\ClubAnnouncementPublicationDecision;
use App\Models\Role;
use App\Models\User;
use App\Support\Announcements\ClubAnnouncementFingerprint;
use Database\Seeders\DefaultRolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use LogicException;
use ReflectionClass;
use Tests\TestCase;

class Milestone3iiuAnnouncementDecisionPermissionFoundationTest extends TestCase
{
    use RefreshDatabase;

    private const MUTATION_PERMISSIONS = [
        'announcements.drafts.manage',
        'announcements.replace',
        'announcements.approve',
        'announcements.publish',
        'announcements.withdraw',
        'announcements.archive',
    ];

    public function test_exact_portable_schema_indexes_and_foreign_keys(): void
    {
        $table = 'club_announcement_publication_decisions';
        $this->assertTrue(Schema::hasTable($table));
        $this->assertSame([
            'id', 'club_id', 'club_announcement_id', 'announcement_lock_version',
            'content_fingerprint', 'maker_user_id', 'maker_role', 'decision',
            'decided_by_user_id', 'decided_by_role', 'supersedes_decision_id',
            'reason', 'decided_at', 'created_at',
        ], Schema::getColumnListing($table));
        $this->assertFalse(Schema::hasColumn($table, 'updated_at'));
        $this->assertFalse(Schema::hasColumn($table, 'deleted_at'));

        $columns = collect(Schema::getColumns($table))->keyBy('name');
        $this->assertSame('varchar', $columns['content_fingerprint']['type_name']);
        $migration = file_get_contents(database_path('migrations/2026_07_20_000002_create_club_announcement_publication_decisions_table.php'));
        $this->assertStringContainsString("char('content_fingerprint', 64)", $migration);
        foreach (['maker_role', 'decision', 'decided_by_role'] as $field) {
            $this->assertStringContainsString("string('{$field}', 20)", $migration);
        }
        foreach (['club_id', 'club_announcement_id', 'announcement_lock_version', 'content_fingerprint', 'maker_user_id', 'maker_role', 'decision', 'decided_by_user_id', 'decided_by_role', 'decided_at', 'created_at'] as $required) {
            $this->assertFalse($columns[$required]['nullable'], "{$required} must be non-nullable.");
        }
        $this->assertTrue($columns['supersedes_decision_id']['nullable']);
        $this->assertTrue($columns['reason']['nullable']);

        $indexes = collect(Schema::getIndexes($table));
        $indexColumns = $indexes->pluck('columns')->map(fn ($value) => array_values($value))->all();
        $this->assertContains(['club_id', 'club_announcement_id', 'announcement_lock_version'], $indexColumns);
        $this->assertContains(['club_announcement_id', 'decision', 'decided_at'], $indexColumns);
        $this->assertContains(['content_fingerprint'], $indexColumns);
        $supersedes = $indexes->first(fn (array $index): bool => $index['columns'] === ['supersedes_decision_id']);
        $this->assertNotNull($supersedes);
        $this->assertTrue($supersedes['unique']);

        $foreignKeys = collect(Schema::getForeignKeys($table))->keyBy(fn (array $key) => $key['columns'][0]);
        foreach (['club_id', 'club_announcement_id', 'maker_user_id', 'decided_by_user_id', 'supersedes_decision_id'] as $column) {
            $this->assertTrue($foreignKeys->has($column), "Missing foreign key for {$column}.");
            $this->assertSame('restrict', strtolower($foreignKeys[$column]['on_delete']));
        }
    }

    public function test_constants_relationships_reason_rules_and_append_only_model_guards(): void
    {
        $this->assertSame(['approved', 'rejected', 'revoked'], ClubAnnouncementPublicationDecision::decisions());
        $this->assertSame(['president', 'secretary'], ClubAnnouncementPublicationDecision::roles());

        [$club, $announcement, $maker, $checker] = $this->context();
        $approval = $this->decision($club, $announcement, $maker, $checker, ['reason' => null]);
        $approvalWithReason = $this->decision($club, $announcement, $maker, $checker, [
            'content_fingerprint' => str_repeat('b', 64),
            'reason' => '  Optional approval context  ',
        ]);
        $this->assertNull($approval->reason);
        $this->assertSame('  Optional approval context  ', $approvalWithReason->reason);
        $this->assertTrue($approval->club->is($club));
        $this->assertTrue($approval->announcement->is($announcement));
        $this->assertTrue($approval->maker->is($maker));
        $this->assertTrue($approval->decisionActor->is($checker));
        $this->assertTrue($announcement->publicationDecisions->contains($approval));

        $this->assertCreationFails($club, $announcement, $maker, $checker, ['decision' => 'unknown']);
        $this->assertCreationFails($club, $announcement, $maker, $checker, ['maker_role' => 'treasurer']);
        $this->assertCreationFails($club, $announcement, $maker, $checker, ['decided_by_role' => 'member']);
        $this->assertCreationFails($club, $announcement, $maker, $checker, ['decision' => 'rejected', 'reason' => null]);
        $this->assertCreationFails($club, $announcement, $maker, $checker, ['decision' => 'rejected', 'reason' => " \t\n "]);
        $this->assertCreationFails($club, $announcement, $maker, $checker, ['decision' => 'revoked', 'reason' => 'Valid reason', 'supersedes_decision_id' => null]);
        $this->assertCreationFails($club, $announcement, $maker, $checker, ['reason' => ' ', 'content_fingerprint' => str_repeat('c', 64)]);
        $this->assertCreationFails($club, $announcement, $maker, $checker, ['supersedes_decision_id' => $approval->id, 'content_fingerprint' => str_repeat('d', 64)]);

        try {
            $approval->update(['reason' => 'Changed']);
            $this->fail('Decision update should be blocked.');
        } catch (LogicException) {
            $this->assertDatabaseHas('club_announcement_publication_decisions', ['id' => $approval->id, 'reason' => null]);
        }

        $this->expectException(LogicException::class);
        $approval->delete();
    }

    public function test_fingerprint_format_is_enforced_during_model_creation(): void
    {
        [$club, $announcement, $maker, $checker] = $this->context();
        foreach ([str_repeat('A', 64), str_repeat('g', 64), str_repeat('a', 63), str_repeat('a', 65)] as $invalid) {
            $this->assertCreationFails($club, $announcement, $maker, $checker, ['content_fingerprint' => $invalid]);
        }
        $valid = $this->decision($club, $announcement, $maker, $checker, ['content_fingerprint' => str_repeat('0', 64)]);
        $this->assertSame(str_repeat('0', 64), $valid->content_fingerprint);
    }

    public function test_unique_non_null_supersession_and_service_reserved_compatibility(): void
    {
        [$club, $announcement, $maker, $checker] = $this->context();
        $approval = $this->decision($club, $announcement, $maker, $checker);
        $revocation = $this->decision($club, $announcement, $maker, $checker, [
            'decision' => 'revoked',
            'content_fingerprint' => str_repeat('b', 64),
            'supersedes_decision_id' => $approval->id,
            'reason' => 'Approval withdrawn.',
        ]);
        $this->assertTrue($revocation->supersededDecision->is($approval));
        $this->assertTrue($approval->revocation->is($revocation));

        try {
            $this->decision($club, $announcement, $maker, $checker, [
                'decision' => 'revoked',
                'content_fingerprint' => str_repeat('c', 64),
                'supersedes_decision_id' => $approval->id,
                'reason' => 'Duplicate revocation.',
            ]);
            $this->fail('A decision must not be superseded twice.');
        } catch (QueryException) {
            $this->assertDatabaseCount('club_announcement_publication_decisions', 2);
        }

        $other = ClubAnnouncement::factory()->create(['club_id' => $club->id, 'created_by_user_id' => $maker->id]);
        $crossAnnouncement = $this->decision($club, $other, $maker, $checker, [
            'decision' => 'revoked',
            'content_fingerprint' => str_repeat('d', 64),
            'supersedes_decision_id' => $revocation->id,
            'reason' => 'Schema permits; future locked service must reject.',
        ]);
        $this->assertNotSame($crossAnnouncement->club_announcement_id, $crossAnnouncement->supersededDecision->club_announcement_id);

        DB::table('club_announcement_publication_decisions')->where('id', $crossAnnouncement->id)
            ->update(['supersedes_decision_id' => $crossAnnouncement->id]);
        $this->assertSame($crossAnnouncement->id, DB::table('club_announcement_publication_decisions')->where('id', $crossAnnouncement->id)->value('supersedes_decision_id'));
    }

    public function test_fingerprint_is_canonical_stable_and_every_material_value_remains_material(): void
    {
        $utility = new ClubAnnouncementFingerprint;
        $base = $this->fingerprintAnnouncement();
        $this->assertSame('ANNOUNCEMENT-FINGERPRINT-2026-01', ClubAnnouncementFingerprint::VERSION);
        $expectedJson = '{"fingerprint_contract_version":"ANNOUNCEMENT-FINGERPRINT-2026-01","announcement_id":41,"club_id":9,"lock_version":3,"title":"അറിയിപ്പ്","body":"First\nSecond","priority":"high","audience":"club-members","scheduled_publish_at":"2026-07-20T06:30:00Z","expires_at":"2026-07-21T06:30:00Z","replaces_announcement_id":17}';
        $this->assertSame($expectedJson, $utility->canonicalJson($base));
        $this->assertSame(hash('sha256', $expectedJson), $utility->fingerprint($base));

        $equivalent = $this->fingerprintAnnouncement([
            'title' => "\u{2003}അറിയിപ്പ്\u{00A0}",
            'body' => "First\rSecond",
            'scheduled_publish_at' => '2026-07-20T12:00:00+05:30',
            'expires_at' => '2026-07-21T02:30:00-04:00',
        ]);
        $this->assertSame($utility->fingerprint($base), $utility->fingerprint($equivalent));

        foreach ([
            ['title' => 'മറ്റൊരു അറിയിപ്പ്'],
            ['body' => 'First Second'],
            ['body' => " First\nSecond"],
            ['body' => "First\nSecond "],
            ['priority' => 'urgent'],
            ['audience' => 'officers'],
            ['scheduled_publish_at' => '2026-07-20T06:30:01Z'],
            ['expires_at' => '2026-07-21T06:30:01Z'],
            ['replaces_announcement_id' => 18],
            ['lock_version' => 4],
        ] as $change) {
            $this->assertNotSame($utility->fingerprint($base), $utility->fingerprint($this->fingerprintAnnouncement($change)), 'Material change did not alter fingerprint: '.json_encode($change));
        }

        $unrelated = $this->fingerprintAnnouncement(['created_by_user_id' => 999, 'updated_by_user_id' => 998]);
        $unrelated->created_at = '2030-01-01 00:00:00';
        $unrelated->updated_at = '2030-01-02 00:00:00';
        $unrelated->setAttribute('content_fingerprint', str_repeat('f', 64));
        $this->assertSame($utility->fingerprint($base), $utility->fingerprint($unrelated));
    }

    public function test_exact_permissions_are_seeded_without_creating_write_routes_or_records(): void
    {
        $this->seed(DefaultRolesAndPermissionsSeeder::class);
        $this->seed(DefaultRolesAndPermissionsSeeder::class);

        foreach (['president-admin', 'secretary'] as $slug) {
            $permissions = Role::where('slug', $slug)->firstOrFail()->permissions->pluck('slug');
            foreach (self::MUTATION_PERMISSIONS as $permission) {
                $this->assertTrue($permissions->contains($permission), "{$slug} lacks {$permission}");
            }
            $this->assertTrue($permissions->contains('announcements.view'));
            $this->assertTrue($permissions->contains('announcements.management.view'));
        }
        foreach (['treasurer', 'member'] as $slug) {
            $permissions = Role::where('slug', $slug)->firstOrFail()->permissions->pluck('slug');
            foreach (self::MUTATION_PERMISSIONS as $permission) {
                $this->assertFalse($permissions->contains($permission), "{$slug} unexpectedly has {$permission}");
            }
            $this->assertTrue($permissions->contains('announcements.view'));
            $this->assertFalse($permissions->contains('announcements.management.view'));
        }
        $this->assertDatabaseCount('permissions', 55);
        $this->assertDatabaseCount('role_permissions', 110);
        $this->assertDatabaseHas('permissions', ['slug' => 'finance.accounting.integration.configure']);
        $this->assertSame(0, Role::query()->whereHas('permissions', fn ($query) => $query->where('slug', 'finance.accounting.integration.configure'))->count());

        $routes = collect(Route::getRoutes())->filter(fn ($route) => str_contains($route->uri(), 'clubs/{club}/announcements'));
        $this->assertCount(12, $routes);
        $readRoute = $routes->first(fn ($route) => $route->uri() === 'api/v1/clubs/{club}/announcements/{announcement}/read');
        $this->assertNotNull($readRoute);
        $this->assertContains('PUT', $readRoute->methods());
        $this->assertCount(0, $routes->filter(fn ($route) => in_array('DELETE', $route->methods(), true)));
        $publicEndpoints = collect((new ReflectionClass(ClubAnnouncementController::class))->getMethods(\ReflectionMethod::IS_PUBLIC))
            ->filter(fn ($method) => $method->getDeclaringClass()->getName() === ClubAnnouncementController::class)
            ->pluck('name')->sort()->values()->all();
        $this->assertSame(['approve', 'archive', 'index', 'markRead', 'publish', 'rejectApproval', 'revokeApproval', 'show', 'store', 'storeReplacement', 'update', 'withdraw'], $publicEndpoints);

        $beforeAnnouncements = ClubAnnouncement::count();
        $beforeDecisions = ClubAnnouncementPublicationDecision::count();
        $unsaved = $this->fingerprintAnnouncement();
        (new ClubAnnouncementFingerprint)->fingerprint($unsaved);
        $unsaved->publicationDecisions();
        $this->assertSame($beforeAnnouncements, ClubAnnouncement::count());
        $this->assertSame($beforeDecisions, ClubAnnouncementPublicationDecision::count());
    }

    private function context(): array
    {
        $club = Club::query()->create(['name' => 'Decision Test Club', 'code' => 'DTC-'.fake()->unique()->numerify('####'), 'timezone' => 'UTC', 'is_active' => true]);
        $maker = User::factory()->create();
        $checker = User::factory()->create();
        $announcement = ClubAnnouncement::factory()->create(['club_id' => $club->id, 'created_by_user_id' => $maker->id, 'updated_by_user_id' => $maker->id]);

        return [$club, $announcement, $maker, $checker];
    }

    private function decision(Club $club, ClubAnnouncement $announcement, User $maker, User $checker, array $overrides = []): ClubAnnouncementPublicationDecision
    {
        return ClubAnnouncementPublicationDecision::query()->create(array_merge([
            'club_id' => $club->id,
            'club_announcement_id' => $announcement->id,
            'announcement_lock_version' => $announcement->lock_version,
            'content_fingerprint' => str_repeat('a', 64),
            'maker_user_id' => $maker->id,
            'maker_role' => 'president',
            'decision' => 'approved',
            'decided_by_user_id' => $checker->id,
            'decided_by_role' => 'secretary',
            'supersedes_decision_id' => null,
            'reason' => null,
            'decided_at' => '2026-07-20 12:00:00',
        ], $overrides));
    }

    private function assertCreationFails(Club $club, ClubAnnouncement $announcement, User $maker, User $checker, array $overrides): void
    {
        $before = ClubAnnouncementPublicationDecision::count();
        try {
            $this->decision($club, $announcement, $maker, $checker, $overrides);
            $this->fail('Invalid decision record was accepted.');
        } catch (LogicException) {
            $this->assertSame($before, ClubAnnouncementPublicationDecision::count());
        }
    }

    private function fingerprintAnnouncement(array $overrides = []): ClubAnnouncement
    {
        $scheduledPublishAt = $overrides['scheduled_publish_at'] ?? '2026-07-20T06:30:00Z';
        $expiresAt = $overrides['expires_at'] ?? '2026-07-21T06:30:00Z';
        unset($overrides['scheduled_publish_at'], $overrides['expires_at']);

        $announcement = new ClubAnnouncement(array_merge([
            'club_id' => 9,
            'title' => "\u{2003}അറിയിപ്പ്\u{00A0}",
            'body' => "First\r\nSecond",
            'priority' => 'high',
            'audience' => 'club-members',
            'replaces_announcement_id' => 17,
            'created_by_user_id' => 1,
            'updated_by_user_id' => 2,
            'lock_version' => 3,
        ], $overrides));
        $announcement->id = 41;
        $announcement->setRawAttributes(array_merge($announcement->getAttributes(), [
            'scheduled_publish_at' => $scheduledPublishAt,
            'expires_at' => $expiresAt,
        ]), true);

        return $announcement;
    }
}
