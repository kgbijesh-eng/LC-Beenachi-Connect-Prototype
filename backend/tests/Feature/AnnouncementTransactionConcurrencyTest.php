<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubAnnouncement;
use App\Models\ClubAnnouncementPublicationDecision;
use App\Models\ClubAnnouncementRead;
use App\Models\ClubMembership;
use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use App\Services\Access\OrganisationAccessRejected;
use App\Services\Announcements\ClubAnnouncementReadStateService;
use App\Services\Announcements\ClubAnnouncementWriteService;
use Database\Seeders\DefaultRolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Process\Process;
use Tests\Support\FinancialPostgresTestCase;

class AnnouncementTransactionConcurrencyTest extends FinancialPostgresTestCase
{
    private const INPUT = ['title' => 'Race notice', 'body' => 'Synthetic race content', 'priority' => 'normal', 'audience' => 'club-members'];

    private Club $club;
    private User $maker;
    private User $checker;
    private ClubAnnouncementWriteService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DefaultRolesAndPermissionsSeeder::class);
        $this->club = Club::create(['name' => 'Synthetic announcement club', 'code' => 'ANN-RACE', 'timezone' => 'UTC', 'is_active' => true]);
        config(['instance.organisation_code' => $this->club->code]);
        $this->maker = $this->actor('president-admin');
        $this->checker = $this->actor('secretary');
        $this->service = app(ClubAnnouncementWriteService::class);
    }

    public static function suspensionCases(): iterable
    {
        foreach (['create', 'edit', 'replace', 'approve', 'reject', 'revoke', 'publish', 'withdraw', 'archive', 'mark'] as $op) {
            foreach ([true, false] as $writerFirst) {
                yield "$op-".($writerFirst ? 'writer-first' : 'suspension-first') => [$op, $writerFirst];
            }
        }
    }

    #[DataProvider('suspensionCases')]
    public function test_every_writer_serializes_with_suspension(string $op, bool $writerFirst): void
    {
        $writer = $this->prepare($op);
        $before = $this->snapshot();
        $suspend = $this->operation('suspend');
        $expected = $writerFirst ? ['ok', 'ok'] : ['ok', 'ORGANISATION_SUSPENDED'];
        $this->assertSame($expected, $this->race(...($writerFirst ? [$writer, $suspend] : [$suspend, $writer])));
        $this->assertFalse($this->club->refresh()->is_active);
        if ($writerFirst) {
            $this->assertEffect($op, $writer, $before);
        } else {
            $this->assertSame($before, $this->snapshot());
        }
    }

    public static function membershipCases(): iterable
    {
        foreach ([
            ['create', 'actor'], ['edit', 'actor'], ['publish', 'actor'],
            ['approve', 'maker'], ['approve', 'checker'], ['reject', 'checker'],
            ['revoke', 'maker'], ['revoke', 'checker'], ['mark', 'permission'], ['mark', 'audience'],
        ] as [$op, $loss]) {
            foreach ([true, false] as $writerFirst) {
                yield "$op-$loss-".($writerFirst ? 'writer-first' : 'membership-first') => [$op, $loss, $writerFirst];
            }
        }
    }

    #[DataProvider('membershipCases')]
    public function test_writer_serializes_with_test_only_membership_change(string $op, string $loss, bool $writerFirst): void
    {
        $writer = $this->prepare($op);
        $target = $loss === 'maker' ? $this->maker->id : $writer['user'];
        $update = ['role_id' => Role::where('slug', 'member')->sole()->id];
        if ($op === 'mark') {
            $reader = $this->actor('treasurer');
            $writer['user'] = $target = $reader->id;
            ClubAnnouncement::whereKey($writer['announcement'])->update(['audience' => 'officers']);
            if ($loss === 'permission') {
                $update = ['status' => 'inactive'];
            }
        }
        $change = $this->operation('membership') + ['target_user' => $target, 'membership_update' => $update];
        $before = $this->snapshot();
        $this->assertSame(
            $writerFirst ? ['ok', 'ok'] : ['ok', $loss === 'audience' ? 'ineligible' : 'http_403'],
            $this->race(...($writerFirst ? [$writer, $change] : [$change, $writer])),
        );
        $membership = ClubMembership::where('club_id', $this->club->id)->where('user_id', $target)->sole();
        foreach ($update as $field => $value) {
            $this->assertSame($value, $membership->{$field});
        }
        if ($writerFirst) {
            $this->assertEffect($op, $writer, $before);
        } else {
            $this->assertSame($before, $this->snapshot());
        }
    }

    public static function competingCases(): iterable
    {
        foreach ([
            ['create', 'create', 'ok', 'ok'],
            ['edit', 'edit', 'http_409', 'http_409'],
            ['approve', 'reject', 'http_409', 'http_409'],
            ['approve', 'publish', 'ok', 'http_409'],
            ['approve', 'edit', 'ok', 'http_409'],
            ['revoke', 'publish', 'http_409', 'http_409'],
            ['replace', 'replace', 'http_409', 'http_409'],
            ['publish', 'publish', 'http_409', 'http_409'],
            ['mark', 'mark', 'ok', 'ok'],
            ['mark', 'withdraw', 'ok', 'ineligible'],
            ['mark', 'archive', 'ok', 'ineligible'],
        ] as [$first, $second, $secondResult, $reverseSecondResult]) {
            yield "$first-$second-forward" => [$first, $second, false, $secondResult];
            yield "$first-$second-reverse" => [$first, $second, true, $reverseSecondResult];
        }
    }

    #[DataProvider('competingCases')]
    public function test_competing_writers_observe_committed_state(string $first, string $second, bool $reverse, string $peerResult): void
    {
        $left = $this->prepare($first);
        $right = $this->operation($second, $second === 'reject' ? $this->checker : $this->maker)
            + array_intersect_key($left, array_flip(['announcement', 'version', 'approval']));
        if ($second === 'replace') {
            unset($right['input']['audience']);
        }
        $before = $this->snapshot();
        $failedPublish = $reverse && $first === 'approve' && $second === 'publish';
        $expected = $failedPublish ? ['http_409', 'ok'] : ['ok', $peerResult];
        $this->assertSame($expected, $this->race(...($reverse ? [$right, $left] : [$left, $right])));
        $after = $this->snapshot();
        $successes = $failedPublish ? [$first] : ($reverse ? [$second] : [$first]);
        if (! $failedPublish && $peerResult === 'ok') {
            $successes[] = $reverse ? $first : $second;
        }
        $auditActions = array_map($this->auditAction(...), array_filter($successes, fn ($op) => $op !== 'mark'));
        $this->assertSame(array_values($auditActions), array_slice(array_column($after['audits'], 'action'), count($before['audits'])));
        $this->assertCount(count($before['markers']) + (int) in_array('mark', $successes, true), $after['markers']);
        $this->assertCount(count($before['decisions']) + count(array_intersect($successes, ['approve', 'reject', 'revoke'])), $after['decisions']);
        $this->assertCount(count($before['announcements']) + count(array_intersect($successes, ['create', 'replace'])), $after['announcements']);
        if (isset($left['announcement']) && ! in_array('replace', $successes, true)) {
            $record = ClubAnnouncement::findOrFail($left['announcement']);
            $this->assertSame($left['version'] + count(array_intersect($successes, ['edit', 'publish', 'withdraw', 'archive'])), $record->lock_version);
            foreach (['publish' => 'published', 'withdraw' => 'withdrawn', 'archive' => 'archived'] as $operation => $status) {
                if (in_array($operation, $successes, true)) {
                    $this->assertSame($status, $record->status);
                }
            }
        }
        if ($first === 'mark' && $second === 'mark') {
            $original = ClubAnnouncementRead::sole();
            $replay = app(ClubAnnouncementReadStateService::class)
                ->markRead($this->club, ClubAnnouncement::findOrFail($left['announcement']), $this->maker);
            $this->assertSame($original->read_at->toISOString(), $replay->read_at->toISOString());
        }
    }

    #[DataProvider('winnerOrders')]
    public function test_b6a_member_write_shares_club_order(bool $announcementFirst): void
    {
        $member = Member::create(['club_id' => $this->club->id, 'member_code' => 'ANN-RACE', 'full_name' => 'Synthetic member', 'status' => 'active']);
        $write = $this->operation('member-status') + $this->http('PATCH', "/api/v1/clubs/{$this->club->id}/members/{$member->id}", ['status' => 'inactive']);
        $draft = $this->prepare('create');
        $before = $this->snapshot();
        $this->assertSame($announcementFirst ? ['ok', 'http_200'] : ['http_200', 'ok'], $this->race(...($announcementFirst ? [$draft, $write] : [$write, $draft])));
        $this->assertSame('inactive', $member->refresh()->status);
        $this->assertEffect('create', $draft, $before);
    }

    public static function winnerOrders(): iterable
    {
        yield 'announcement-first' => [true];
        yield 'member-first' => [false];
    }

    #[DataProvider('winnerOrders')]
    public function test_historical_checker_loss_does_not_invalidate_approval(bool $publishFirst): void
    {
        $approval = $this->prepare('revoke');
        $publish = $this->operation('publish') + array_intersect_key($approval, array_flip(['announcement', 'version']));
        $change = $this->operation('membership') + [
            'target_user' => $this->checker->id,
            'membership_update' => ['status' => 'inactive'],
        ];
        $before = $this->snapshot();
        $this->assertSame(['ok', 'ok'], $this->race(...($publishFirst ? [$publish, $change] : [$change, $publish])));
        $this->assertSame('published', ClubAnnouncement::findOrFail($publish['announcement'])->status);
        $this->assertSame($before['decisions'], $this->snapshot()['decisions']);
        $this->assertEffect('publish', $publish, $before);
    }

    #[DataProvider('winnerOrders')]
    public function test_competing_replacements_publish_at_most_one_successor(bool $firstDraftWins): void
    {
        $replace = $this->prepare('replace');
        $source = ClubAnnouncement::findOrFail($replace['announcement']);
        $first = $this->service->createReplacementDraft($this->club, $source, $this->maker, $replace['input']);
        $second = $first->replicate();
        $second->title = 'Competing synthetic draft';
        $second->save();
        $left = $this->operation('publish') + ['announcement' => $first->id, 'version' => 1];
        $right = $this->operation('publish') + ['announcement' => $second->id, 'version' => 1];
        $before = $this->snapshot();
        [$winner, $loser] = $firstDraftWins ? [$first, $second] : [$second, $first];
        $this->assertSame(['ok', 'http_409'], $this->race(...($firstDraftWins ? [$left, $right] : [$right, $left])));
        $this->assertSame('published', $winner->refresh()->status);
        $this->assertSame('draft', $loser->refresh()->status);
        $this->assertSame($source->audience, $winner->audience);
        $this->assertEffect('publish', $firstDraftWins ? $left : $right, $before);
    }

    public function test_expiry_is_sampled_after_waiting_for_club(): void
    {
        $marker = $this->prepare('mark');
        $expires = now()->addSeconds(12)->startOfSecond();
        ClubAnnouncement::whereKey($marker['announcement'])->update(['expires_at' => $expires]);
        $other = $this->prepare('create');
        $this->assertSame(['ok', 'ineligible'], $this->race($other, $marker, function () use ($expires): void {
            $this->assertTrue(now()->lt($expires), 'Marker must start waiting before expiry.');
            $this->await(fn () => now()->gte($expires));
        }));
        $this->assertDatabaseCount('club_announcement_reads', 0);
    }

    public function test_gets_complete_while_club_is_locked(): void
    {
        $marker = $this->prepare('mark');
        $base = "/api/v1/clubs/{$this->club->id}/announcements";
        foreach ([$base, "$base/{$marker['announcement']}"] as $path) {
            $before = $this->snapshot();
            $holder = $this->prepare('create');
            $get = $this->operation('get') + $this->http('GET', $path);
            $this->assertSame(['ok', 'http_200'], $this->race($holder, $get, blocks: false));
            $this->assertEffect('create', $holder, $before);
        }
    }

    public function test_ambient_writes_are_rejected_and_leave_the_caller_transaction_intact(): void
    {
        $marker = $this->prepare('mark');
        $announcement = ClubAnnouncement::findOrFail($marker['announcement']);
        $before = $this->snapshot();
        DB::beginTransaction();
        try {
            foreach ([
                fn () => $this->service->createDraft($this->club, $this->maker, self::INPUT),
                fn () => app(ClubAnnouncementReadStateService::class)->markRead($this->club, $announcement, $this->maker),
            ] as $write) {
                try {
                    $write();
                    $this->fail('Expected B1 ambient rejection.');
                } catch (OrganisationAccessRejected $e) {
                    $this->assertSame('ORGANISATION_TRANSACTION_UNSUPPORTED', $e->reason);
                    $this->assertSame(1, DB::transactionLevel());
                }
            }
        } finally {
            DB::rollBack();
        }
        $this->assertSame($before, $this->snapshot());
    }

    public function test_foreign_announcement_and_decision_rows_are_not_locked(): void
    {
        $revoke = $this->prepare('revoke');
        $local = ClubAnnouncement::findOrFail($revoke['announcement']);
        $foreignClub = Club::create(['name' => 'Foreign club', 'code' => 'ANN-FOREIGN', 'timezone' => 'UTC', 'is_active' => true]);
        $foreign = $local->replicate();
        $foreign->club_id = $foreignClub->id;
        $foreign->save();
        $approval = ClubAnnouncementPublicationDecision::findOrFail($revoke['approval'])->replicate();
        $approval->club_id = $foreignClub->id;
        $approval->save();
        $holder = DB::build([...DB::connection()->getConfig(), 'name' => 'announcement_foreign_holder']);
        $holder->beginTransaction();
        try {
            $holder->table('club_announcements')->where('id', $foreign->id)->lockForUpdate()->first();
            $holder->table('club_announcement_publication_decisions')->where('id', $approval->id)->lockForUpdate()->first();
            DB::statement("SET lock_timeout = '1s'");
            foreach ([
                fn () => $this->service->updateDraft($this->club, $foreign, $this->maker, 1, ['title' => 'Foreign']),
                fn () => $this->service->revokeApproval($this->club, $local, $this->checker, 1, $approval, 'Foreign'),
            ] as $write) {
                try {
                    $write();
                    $this->fail('Expected a concealed foreign row.');
                } catch (HttpExceptionInterface $e) {
                    $this->assertSame(404, $e->getStatusCode());
                }
            }
            $this->service->publish($this->club, $local, $this->maker, 1);
            $this->assertSame('published', $local->refresh()->status);
        } finally {
            $holder->rollBack();
            $holder->disconnect();
            DB::statement('RESET lock_timeout');
        }
    }

    private function prepare(string $op): array
    {
        $checker = in_array($op, ['approve', 'reject', 'revoke'], true);
        $p = $this->operation($op, $checker ? $this->checker : $this->maker);
        if ($op === 'create') {
            return $p;
        }
        $input = self::INPUT;
        if ($checker) {
            $input['priority'] = 'urgent';
        }
        $announcement = $this->service->createDraft($this->club, $this->maker, $input);
        if (in_array($op, ['replace', 'withdraw', 'mark'], true)) {
            $announcement = $this->service->publish($this->club, $announcement, $this->maker, 1);
        }
        if ($op === 'replace') {
            unset($p['input']['audience']);
        }
        if ($op === 'revoke') {
            $p['approval'] = $this->service->approve($this->club, $announcement, $this->checker, 1)->id;
        }

        return $p + ['announcement' => $announcement->id, 'version' => $announcement->lock_version];
    }

    private function actor(string $role): User
    {
        $user = User::factory()->create();
        ClubMembership::create(['club_id' => $this->club->id, 'user_id' => $user->id, 'role_id' => Role::where('slug', $role)->sole()->id, 'status' => 'active']);

        return $user;
    }

    private function operation(string $op, ?User $user = null): array
    {
        return ['operation' => $op, 'club' => $this->club->id, 'user' => ($user ?? $this->maker)->id, 'input' => self::INPUT];
    }

    private function http(string $method, string $route, array $payload = []): array
    {
        return compact('method', 'route', 'payload') + ['token' => $this->maker->createToken('synthetic-announcement-worker')->plainTextToken];
    }

    private function auditAction(string $op): string
    {
        return 'announcements.'.match ($op) {
            'create' => 'created', 'edit' => 'updated', 'replace' => 'replacement_draft_created',
            'approve' => 'approval_granted', 'reject' => 'approval_rejected', 'revoke' => 'approval_revoked',
            'publish' => 'published', 'withdraw' => 'withdrawn', 'archive' => 'archived',
        };
    }

    private function assertEffect(string $op, array $writer, array $before): void
    {
        $after = $this->snapshot();
        $this->assertCount(count($before['markers']) + (int) ($op === 'mark'), $after['markers']);
        $this->assertCount(count($before['decisions']) + (int) in_array($op, ['approve', 'reject', 'revoke'], true), $after['decisions']);
        $this->assertCount(count($before['announcements']) + (int) in_array($op, ['create', 'replace'], true), $after['announcements']);
        $this->assertSame($op === 'mark' ? [] : [$this->auditAction($op)], array_slice(array_column($after['audits'], 'action'), count($before['audits'])));
        if (isset($writer['announcement'])) {
            $row = ClubAnnouncement::findOrFail($writer['announcement']);
            $this->assertSame($writer['version'] + (int) in_array($op, ['edit', 'publish', 'withdraw', 'archive'], true), $row->lock_version);
        }
        if (in_array($op, ['approve', 'reject', 'revoke'], true)) {
            $decision = end($after['decisions']);
            $this->assertSame($writer['announcement'], $decision['club_announcement_id']);
            $this->assertSame($writer['user'], $decision['decided_by_user_id']);
            $this->assertSame($writer['version'], $decision['announcement_lock_version']);
            $this->assertSame(match ($op) {
                'approve' => 'approved', 'reject' => 'rejected', 'revoke' => 'revoked',
            }, $decision['decision']);
        }
    }

    private function snapshot(): array
    {
        return [
            'announcements' => ClubAnnouncement::orderBy('id')->get()->toArray(),
            'decisions' => ClubAnnouncementPublicationDecision::orderBy('id')->get()->toArray(),
            'markers' => ClubAnnouncementRead::orderBy('id')->get()->toArray(),
            'audits' => AuditLog::where('action', 'like', 'announcements.%')->orderBy('id')->get()->toArray(),
        ];
    }

    private function race(array $first, array $second, ?callable $afterBlocking = null, bool $blocks = true): array
    {
        $dir = sys_get_temp_dir().'/b6c1-'.Str::uuid();
        mkdir($dir, 0700);
        $workers = [];
        foreach (['one' => $first, 'two' => $second] as $name => $p) {
            $path = "$dir/$name.json";
            file_put_contents($path, json_encode($p + ['hold' => $name === 'one'], JSON_THROW_ON_ERROR));
            $workers[$name] = new Process([PHP_BINARY, base_path('tests/Support/AnnouncementTransactionWorker.php'), $path, $dir, $name], base_path(), env: [
                'INSTANCE_ORGANISATION_CODE' => $this->club->code,
            ], timeout: 45);
        }
        try {
            foreach ($workers as $name => $worker) {
                $worker->start();
                $this->await(fn () => is_file("$dir/$name.ready"));
            }
            file_put_contents("$dir/one.start", '1');
            $this->await(fn () => is_file("$dir/one.locked"));
            file_put_contents("$dir/two.start", '1');
            $holder = (int) file_get_contents("$dir/one.ready");
            $waiter = (int) file_get_contents("$dir/two.ready");
            if ($blocks) {
                $this->await(fn () => (int) DB::selectOne(
                    "SELECT count(*) AS n FROM pg_stat_activity WHERE pid=? AND wait_event_type='Lock'
                     AND ?=ANY(pg_blocking_pids(pid)) AND query LIKE '%\"clubs\"%' AND query LIKE '%for update%'",
                    [$waiter, $holder],
                )->n === 1);
                if ($afterBlocking) {
                    $afterBlocking();
                }
            } else {
                $this->await(fn () => is_file("$dir/two.result"));
            }
            file_put_contents("$dir/one.finish", '1');
            $results = [];
            foreach ($workers as $name => $worker) {
                $worker->wait();
                $raw = is_file("$dir/$name.result") ? file_get_contents("$dir/$name.result") : '';
                $this->assertTrue($worker->isSuccessful(), $raw.$worker->getErrorOutput());
                $result = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
                $this->assertSame(0, $result['depth']);
                $results[] = $result['status'];
            }
            fwrite(STDOUT, "\nB6C1_LOCK_EDGE ".json_encode([$first['operation'], $second['operation'], $holder, $waiter, $blocks, $results], JSON_THROW_ON_ERROR)."\n");

            return $results;
        } finally {
            file_put_contents("$dir/one.finish", '1');
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop(5);
                }
            }
        }
    }

    private function await(callable $condition): void
    {
        $until = microtime(true) + 18;
        while (! $condition()) {
            if (microtime(true) > $until) {
                $this->fail('Expected PostgreSQL race barrier was not observed.');
            }
            usleep(5000);
        }
    }
}
