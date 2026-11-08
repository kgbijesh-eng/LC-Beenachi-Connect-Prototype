<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ClubAccountingSourceMapping;
use App\Models\ClubJournalEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\FinancialPostgresTestCase;
use Tests\Support\SourceCoverageFixture as Fixture;

class SourceCoverageActivationConcurrencyTest extends FinancialPostgresTestCase
{
    public static function races(): array
    {
        $cases = [];
        foreach (['member_charge', 'member_payment'] as $type) {
            foreach (['activate', 'revokeActivation', 'revokeMapping'] as $action) {
                foreach ([true, false] as $first) {
                    $cases[$type.'-'.$action.'-config-first-'.(int) $first] = [$type, $action, $first];
                }
            }
        }

        return $cases;
    }

    #[DataProvider('races')]
    public function test_source_and_configuration_have_exact_club_lock_winner_outcomes(string $type, string $action, bool $configFirst): void
    {
        $p = Fixture::prepare($type, $action === 'activate' ? 'terminal' : 'active');
        $before = ClubJournalEntry::where('club_id', $p['club'])->count();
        $configuration = [...$p, 'mode' => 'operation', 'action' => $action, 'mapping' => ClubAccountingSourceMapping::where('club_id', $p['club'])->where('source_type', $type)->value('id')];
        $source = [...$p, 'mode' => 'coverage-source'];
        $r = $configFirst ? $this->race($configuration, $source) : $this->race($source, $configuration);
        $loserRejected = $action === 'activate' && ! $configFirst || $action === 'revokeMapping' && $configFirst;
        $this->assertSame(['success', $loserRejected ? 'domain_rejected' : 'success'], array_column($r, 'status'));
        $posted = $action === 'activate' ? $configFirst : ! $configFirst;
        $this->assertSame($before + (int) $posted, ClubJournalEntry::where('club_id', $p['club'])->count());
        $this->assertSame($posted ? 1 : 0, DB::table('club_accounting_source_integrations')->where('club_id', $p['club'])->where('source_type', $type)->count());
        if ($action === 'revokeMapping' && $configFirst) {
            $this->assertNull(Fixture::decision($p));
        } else {
            $d = Fixture::decision($p);
            $this->assertNotNull($d);
            $this->assertSame($posted, (bool) $d->integration_required);
            if ($posted && $action === 'activate') {
                $this->assertNotSame($p['activation'], $d->integration_activation_id);
            }
        }
        if ($action === 'revokeActivation') {
            $this->assertSame(0, DB::table('club_accounting_integration_current_activations')->where('club_id', $p['club'])->where('source_type', $type)->count());
        }
        $this->assertSame([], DB::select("SELECT journal_entry_id FROM club_journal_lines WHERE club_id=? GROUP BY journal_entry_id,fund_code HAVING sum(CASE WHEN direction='debit' THEN amount_paise ELSE -amount_paise END)<>0", [$p['club']]));
    }

    public static function types(): array
    {
        return [['member_charge'], ['member_payment']];
    }

    #[DataProvider('types')]
    public function test_terminal_trigger_waits_for_slot_then_releases_waiting_source_atomically(string $type): void
    {
        $p = Fixture::prepare($type);
        $before = ClubJournalEntry::where('club_id', $p['club'])->count();
        config(['database.connections.coverage_slot_peer' => config('database.connections.'.DB::getDefaultConnection())]);
        $peer = DB::connection('coverage_slot_peer');
        $peer->beginTransaction();
        $peer->table('club_accounting_integration_current_activations')->where('club_id', $p['club'])->where('source_type', $type)->lockForUpdate()->first();
        $peerPid = $peer->selectOne('SELECT pg_backend_pid() AS pid')->pid;
        [$dir,$workers] = $this->workers([...$p, 'mode' => 'operation', 'action' => 'revokeActivation'], [...$p, 'mode' => 'coverage-source'], false);
        try {
            $workers['one']->start();
            $this->await(fn () => is_file($dir.'/one.ready'));
            file_put_contents($dir.'/one.start', '1');
            $revoker = json_decode(file_get_contents($dir.'/one.ready'), true)['pid'];
            $this->await(fn () => $this->blocked($revoker, $peerPid));
            $workers['two']->start();
            $this->await(fn () => is_file($dir.'/two.ready'));
            file_put_contents($dir.'/two.start', '1');
            $source = json_decode(file_get_contents($dir.'/two.ready'), true)['pid'];
            $this->await(fn () => $this->blocked($source, $revoker));
            $this->assertSame(0, DB::table('club_accounting_integration_activation_events')->where('club_id', $p['club'])->count());
            $this->assertNull(Fixture::decision($p));
            $peer->rollBack();
            $r = $this->finish($dir, $workers);
            $this->assertSame(['success', 'success'], array_column($r, 'status'));
            $this->assertSame(0, DB::table('club_accounting_integration_current_activations')->where('club_id', $p['club'])->where('source_type', $type)->count());
            $this->assertSame(1, DB::table('club_accounting_integration_activation_events')->where('club_id', $p['club'])->count());
            $this->assertFalse((bool) Fixture::decision($p)->integration_required);
            $this->assertSame($before, ClubJournalEntry::where('club_id', $p['club'])->count());
            fwrite(STDOUT, "\nB4_IMPLICIT_SLOT_EDGE ".json_encode(['source_type' => $type, 'peer' => $peerPid, 'revoker' => $revoker, 'source' => $source, 'both_edges_observed' => true, 'outcomes' => array_column($r, 'status')], JSON_THROW_ON_ERROR)."\n");
        } finally {
            if ($peer->transactionLevel() > 0) {
                $peer->rollBack();
            } DB::purge('coverage_slot_peer');
            $this->stop($dir, $workers);
        }
    }

    private function workers(array $one, array $two, bool $hold = true): array
    {
        $dir = sys_get_temp_dir().'/coverage-race-'.Str::uuid();
        mkdir($dir, 0700, true);
        $workers = [];
        foreach (['one' => $one, 'two' => $two] as $name => $p) {
            file_put_contents($dir.'/'.$name.'.json', json_encode([...$p, 'hold' => $hold && $name === 'one'], JSON_THROW_ON_ERROR));
            $workers[$name] = new Process([PHP_BINARY, base_path('tests/Support/IntegrationConfigurationWorker.php'), $dir.'/'.$name.'.json', $dir, $name], base_path(), env: ['INSTANCE_ORGANISATION_CODE' => $p['code']], timeout: 40);
        }

        return [$dir, $workers];
    }

    private function race(array $one, array $two): array
    {
        [$dir,$workers] = $this->workers($one, $two);
        try {
            $workers['one']->start();
            $this->await(fn () => is_file($dir.'/one.ready'));
            file_put_contents($dir.'/one.start', '1');
            $this->await(fn () => is_file($dir.'/one.locked'));
            $workers['two']->start();
            $this->await(fn () => is_file($dir.'/two.ready'));
            $holder = json_decode(file_get_contents($dir.'/one.ready'), true)['pid'];
            $waiter = json_decode(file_get_contents($dir.'/two.ready'), true)['pid'];
            file_put_contents($dir.'/two.start', '1');
            $this->await(fn () => $this->blocked($waiter, $holder));
            file_put_contents($dir.'/one.finish', '1');
            $r = $this->finish($dir, $workers);
            fwrite(STDOUT, "\nB4_SOURCE_LOCK_EDGE ".json_encode(['source_type' => $one['source_type'], 'actions' => [$one['mode'].':'.$one['action'], $two['mode'].':'.$two['action']], 'holder' => $holder, 'waiter' => $waiter, 'edge_observed' => true, 'outcomes' => array_column($r, 'status')], JSON_THROW_ON_ERROR)."\n");

            return $r;
        } finally {
            $this->stop($dir, $workers);
        }
    }

    private function finish(string $dir, array $workers): array
    {
        $r = [];
        foreach ($workers as $name => $w) {
            $w->wait();
            $this->assertTrue($w->isSuccessful(), $w->getErrorOutput().$w->getOutput());
            $r[] = json_decode(file_get_contents($dir.'/'.$name.'.result.json'), true, flags: JSON_THROW_ON_ERROR);
        } $this->assertSame([0, 0], array_column($r, 'depth'));

        return $r;
    }

    private function stop(string $dir, array $workers): void
    {
        foreach (['one', 'two'] as $name) {
            file_put_contents($dir.'/'.$name.'.finish', '1');
        } foreach ($workers as $w) {
            if ($w->isRunning()) {
                $w->stop(5);
            }
        }
    }

    private function blocked(int $waiter, int $holder): bool
    {
        DB::select('SELECT pg_stat_clear_snapshot()');

        return (int) DB::selectOne("SELECT count(*) AS n FROM pg_stat_activity WHERE pid=? AND wait_event_type='Lock' AND ?=ANY(pg_blocking_pids(pid))", [$waiter, $holder])->n === 1;
    }

    private function await(callable $condition): void
    {
        $end = microtime(true) + 20;
        while (! $condition()) {
            if (microtime(true) >= $end) {
                $this->fail('Required coverage PostgreSQL edge/barrier not observed.');
            } usleep(5000);
        }
    }
}
