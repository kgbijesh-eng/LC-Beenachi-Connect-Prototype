<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubAccountingIntegrationActivation;
use App\Models\ClubAccountingIntegrationProposalDecision;
use App\Models\ClubAccountingSourceMapping;
use App\Models\ClubJournalEntry;
use App\Models\User;
use App\Services\Finance\AccountingIntegrationConfigurationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\FinancialOperationFixture;
use Tests\Support\FinancialPostgresTestCase;
use Tests\Support\IntegrationConfigurationFixture as Fixture;

class IntegrationConfigurationConcurrencyTest extends FinancialPostgresTestCase
{
    public static function suspension(): array
    {
        $cases = [];
        foreach (Fixture::actions() as $action) {
            foreach ([true, false] as $first) {
                $cases[$action.'-suspend-first-'.(int) $first] = [$action, $first];
            }
        }

return $cases;
    }

    #[DataProvider('suspension')]
    public function test_suspension_and_every_mutation_or_replay_linearise_on_club(string $action, bool $suspensionFirst): void
    {
        $p = Fixture::prepare($action);
        $before = Fixture::snapshot($p['club']);
        $result = $this->race([...$p, 'mode' => $suspensionFirst ? 'suspend' : 'operation'], [...$p, 'mode' => $suspensionFirst ? 'operation' : 'suspend']);
        $this->assertSame(['success', $suspensionFirst ? 'rejected' : 'success'], array_column($result, 'status'));
        if ($suspensionFirst) {
            $this->assertSame('ORGANISATION_SUSPENDED', $result[1]['reason']);
        }
        $this->assertFalse(Club::findOrFail($p['club'])->is_active);
        if ($suspensionFirst || $action === 'propose-replay') {
            $this->assertSame($before, Fixture::snapshot($p['club']));
        }
        $this->assertSame($before['club_journal_entries'], Fixture::snapshot($p['club'])['club_journal_entries']);
    }

    public static function duplicates(): array
    {
        return [['propose'], ['approve'], ['activate']];
    }

    #[DataProvider('duplicates')]
    public function test_duplicate_requests_and_decisions_preserve_exact_winner_and_audit(string $action): void
    {
        $p = Fixture::prepare($action);
        $result = $this->race([...$p, 'mode' => 'operation'], [...$p, 'mode' => 'operation']);
        $this->assertSame(['success', $action === 'propose' ? 'success' : 'domain_rejected'], array_column($result, 'status'));
        if ($action === 'propose') {
            $this->assertSame($result[0]['id'], $result[1]['id']);
            $this->assertSame(1, DB::table('audit_logs')->where('club_id', $p['club'])->count());
        }
        if ($action === 'approve') {
            $this->assertSame(1, ClubAccountingIntegrationProposalDecision::where('club_id', $p['club'])->count());
            $this->assertSame(1, ClubAccountingSourceMapping::where('club_id', $p['club'])->count());
            $this->assertSame(2, DB::table('audit_logs')->where('club_id', $p['club'])->count());
        }
        if ($action === 'activate') {
            $this->assertSame(1, DB::table('club_accounting_integration_current_activations')->where('club_id', $p['club'])->count());
        }
    }

    public static function sourceRaces(): array
    {
        $cases = [];
        foreach (['activate', 'revokeActivation', 'revokeMapping'] as $action) {
            foreach ([true, false] as $first) {
                $cases[$action.'-config-first-'.(int) $first] = [$action, $first];
            }
        }

return $cases;
    }

    #[DataProvider('sourceRaces')]
    public function test_configuration_and_b3_source_posting_preserve_historical_coverage(string $action, bool $configurationFirst): void
    {
        $source = (new FinancialOperationFixture)->prepare('collections-issue');
        $activation = ClubAccountingIntegrationActivation::where('club_id', $source['club'])->where('source_type', 'member_charge')->firstOrFail();
        if ($action === 'activate') {
            app(AccountingIntegrationConfigurationService::class)->revokeActivation($activation, User::findOrFail($source['actor']), 'BEFORE-RACE', 'Synthetic prerequisite');
        }
        $p = [...$source, 'action' => $action, 'mapping_version' => 'B3-V1', 'reference' => 'B4-RACE-'.Str::uuid(), 'activation' => $activation->id, 'mapping' => ClubAccountingSourceMapping::where('club_id', $source['club'])->where('source_type', 'member_charge')->value('id')];
        // Reactivation uses the existing B3 mapping version, preserving source provenance.
        $before = ClubJournalEntry::where('club_id', $source['club'])->count();
        $config = [...$p, 'mode' => 'operation'];
        $source['mode'] = 'source';
        $result = $configurationFirst ? $this->race($config, $source) : $this->race($source, $config);
        $expected = $action === 'revokeMapping' && $configurationFirst || $action === 'activate' && ! $configurationFirst ? 'domain_rejected' : 'success';
        $this->assertSame(['success', $expected], array_column($result, 'status'));
        $posted = $action === 'activate' ? $configurationFirst : ! $configurationFirst;
        $this->assertSame($before + (int) $posted, ClubJournalEntry::where('club_id', $source['club'])->count());
        if ($action === 'revokeActivation') {
            $this->assertSame(0, DB::table('club_accounting_integration_current_activations')->where('club_id', $source['club'])->where('source_type', 'member_charge')->count());
        }
        $this->assertSame($posted ? 1 : 0, DB::table('club_accounting_source_integrations')->where('club_id', $source['club'])->where('source_type', 'member_charge')->count());
        if (! ($action === 'revokeMapping' && $configurationFirst)) {
            $decision = DB::table('club_accounting_source_coverage_decisions')->where('club_id', $source['club'])->where('source_type', 'member_charge')->first();
            $this->assertNotNull($decision);
            $this->assertSame($posted, (bool) $decision->integration_required);
        }
    }

    private function race(array $one, array $two): array
    {
        $dir = sys_get_temp_dir().'/b4-race-'.Str::uuid();
        mkdir($dir, 0700, true);
        $processes = [];
        foreach (['one' => $one, 'two' => $two] as $name => $p) {
            file_put_contents($dir.'/'.$name.'.json', json_encode([...$p, 'hold' => $name === 'one'], JSON_THROW_ON_ERROR));
            $processes[$name] = new Process([PHP_BINARY, base_path('tests/Support/IntegrationConfigurationWorker.php'), $dir.'/'.$name.'.json', $dir, $name], base_path(), env: ['INSTANCE_ORGANISATION_CODE' => $p['code']], timeout: 40);
        }
        try {
            $processes['one']->start();
            $this->await(fn () => is_file($dir.'/one.ready'));
            file_put_contents($dir.'/one.start', '1');
            $this->await(fn () => is_file($dir.'/one.locked'));
            $processes['two']->start();
            $this->await(fn () => is_file($dir.'/two.ready'));
            $holder = json_decode(file_get_contents($dir.'/one.ready'), true)['pid'];
            $waiter = json_decode(file_get_contents($dir.'/two.ready'), true)['pid'];
            file_put_contents($dir.'/two.start', '1');
            $this->await(function () use ($holder, $waiter): bool {
                DB::select('SELECT pg_stat_clear_snapshot()');

                return (int) DB::selectOne("SELECT count(*) AS n FROM pg_stat_activity WHERE pid=? AND wait_event_type='Lock' AND ?=ANY(pg_blocking_pids(pid))", [$waiter, $holder])->n === 1;
            });
            file_put_contents($dir.'/one.finish', '1');
            $results = [];
            foreach ($processes as $name => $w) {
                $w->wait();
                $this->assertTrue($w->isSuccessful(), $w->getErrorOutput().$w->getOutput());
                $results[] = json_decode(file_get_contents($dir.'/'.$name.'.result.json'), true, flags: JSON_THROW_ON_ERROR);
            }
            $this->assertSame([0, 0], array_column($results, 'depth'));
            fwrite(STDOUT, "\nB4_LOCK_EDGE ".json_encode(['actions' => [$one['mode'].':'.$one['action'], $two['mode'].':'.$two['action']], 'holder' => $holder, 'waiter' => $waiter, 'edge_observed' => true, 'outcomes' => array_column($results, 'status')], JSON_THROW_ON_ERROR)."\n");

            return $results;
        } finally {
            foreach (['one', 'two'] as $name) {
                file_put_contents($dir.'/'.$name.'.finish', '1');
            } foreach ($processes as $w) {
                if ($w->isRunning()) {
                    $w->stop(5);
                }
            }
        }
    }

    private function await(callable $condition): void
    {
        $end = microtime(true) + 20;
        while (! $condition()) {
            if (microtime(true) >= $end) {
                $this->fail('Real B4 PostgreSQL blocking edge/barrier not observed.');
            } usleep(5000);
        }
    }
}
