<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubAccountingPeriod;
use App\Models\ClubJournalEntry;
use App\Models\ClubMembership;
use App\Models\User;
use App\Services\Finance\Exceptions\InvalidAccountingPeriodCloseRequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\AccountingAdministrationFixture as Fixture;
use Tests\Support\FinancialOperationFixture;
use Tests\Support\FinancialPostgresTestCase;
use Tests\Support\IntegrationConfigurationFixture;

class AccountingAdministrationConcurrencyTest extends FinancialPostgresTestCase
{
    public static function suspensions(): array
    {
        $out = [];
        foreach (Fixture::actions() as $api) {
            foreach ([true, false] as $first) {
                $out[$api.'-suspension-first-'.(int) $first] = [$api, $first];
            }
        }

return $out;
    }

    #[DataProvider('suspensions')]
    public function test_suspension_and_each_mutation_or_replay_share_club_lock(string $api, bool $first): void
    {
        $p = Fixture::prepare($api);
        $before = Fixture::snapshot($p['club']);
        $r = $this->race([...$p, 'mode' => $first ? 'suspend' : 'operation'], [...$p, 'mode' => $first ? 'operation' : 'suspend']);
        $this->assertSame('success', $r[0]['status']);
        $this->assertSame($first ? 'rejected' : 'success', $r[1]['status']);
        if ($first) {
            $this->assertSame('ORGANISATION_SUSPENDED', $r[1]['reason']);
            $this->assertSame($before, Fixture::snapshot($p['club']));
        }
        $this->assertFalse(Club::findOrFail($p['club'])->is_active);
        $this->assertSame($before['club_journal_entries'], Fixture::snapshot($p['club'])['club_journal_entries']);
    }

    public static function duplicates(): array
    {
        $out = [];
        foreach (['account', 'period', 'close'] as $api) {
            foreach ([false, true] as $conflict) {
                $out[$api.'-conflict-'.(int) $conflict] = [$api, $conflict];
            }
        }

return $out;
    }

    #[DataProvider('duplicates')]
    public function test_duplicate_requests_have_one_mutation_and_exact_replay_or_conflict(string $api, bool $conflict): void
    {
        $p = Fixture::prepare($api);
        $two = $p;
        if ($conflict) {
            $other = User::factory()->create();
            $two['actor'] = $other->id;
            $membership = ClubMembership::where('club_id', $p['club'])->where('user_id', $p['actor'])->firstOrFail();
            ClubMembership::create(['club_id' => $p['club'], 'user_id' => $other->id, 'role_id' => $membership->role_id, 'status' => 'active']);
        }
        $journals = ClubJournalEntry::where('club_id', $p['club'])->count();
        $r = $this->race([...$p, 'mode' => 'operation'], [...$two, 'mode' => 'operation']);
        $this->assertSame('success', $r[0]['status']);
        $this->assertSame($conflict ? 'domain_rejected' : 'success', $r[1]['status']);
        $this->assertSame($journals, ClubJournalEntry::where('club_id', $p['club'])->count());
        if ($api === 'account') {
            $this->assertSame(1, DB::table('ledger_accounts')->where('club_id', $p['club'])->where('code', 'B5-CASH')->count());
        } elseif ($api === 'period') {
            $this->assertSame(1, ClubAccountingPeriod::where('club_id', $p['club'])->where('lions_year', '2028-29')->count());
        } else {
            $this->assertSame($p['actor'], ClubAccountingPeriod::findOrFail($p['period'])->closed_by_user_id);
            $this->assertSame(1, AuditLog::where('club_id', $p['club'])->where('action', 'finance.accounting_period.closed')->count());
        }
    }

    public static function peers(): array
    {
        $out = [];
        foreach (['post', 'reverse', 'collections-issue', 'collections-payment', 'expense', 'income', 'correction-replacement'] as $api) {
            foreach ([true, false] as $first) {
                $out[$api.'-close-first-'.(int) $first] = [$api, $first];
            }
        }

return $out;
    }

    #[DataProvider('peers')]
    public function test_close_serialises_against_real_b3_financial_entrypoints(string $api, bool $first): void
    {
        $p = (new FinancialOperationFixture)->prepare($api);
        $before = ClubJournalEntry::where('club_id', $p['club'])->count();
        $close = [...$p, 'action' => 'close', 'mode' => 'operation'];
        $finance = [...$p, 'mode' => 'financial'];
        $r = $this->race($first ? $close : $finance, $first ? $finance : $close);
        $this->assertSame('success', $r[0]['status']);
        $this->assertSame($first ? 'domain_rejected' : 'success', $r[1]['status']);
        $this->assertSame($before + ($first ? 0 : FinancialOperationFixture::journalDelta($api)), ClubJournalEntry::where('club_id', $p['club'])->count());
        $this->assertSame('closed', ClubAccountingPeriod::findOrFail($p['period'])->status);
        $this->assertSame(1, AuditLog::where('club_id', $p['club'])->where('action', 'finance.accounting_period.closed')->count());
        $this->assertSame([], DB::select("SELECT journal_entry_id FROM club_journal_lines WHERE club_id=? GROUP BY journal_entry_id,fund_code HAVING sum(CASE WHEN direction='debit' THEN amount_paise ELSE -amount_paise END)<>0", [$p['club']]));
    }

    public static function configurations(): array
    {
        $out = [];
        foreach (['map', 'activate', 'revokeActivation', 'approve'] as $api) {
            foreach ([true, false] as $first) {
                $out[$api.'-close-first-'.(int) $first] = [$api, $first];
            }
        }

return $out;
    }

    #[DataProvider('configurations')]
    public function test_b4_configuration_keeps_its_contract_against_close(string $api, bool $first): void
    {
        $p = IntegrationConfigurationFixture::prepare($api);
        $close = [...$p, 'action' => 'close', 'mode' => 'operation'];
        $config = [...$p, 'mode' => 'configuration'];
        $before = ClubJournalEntry::where('club_id', $p['club'])->count();
        $r = $this->race($first ? $close : $config, $first ? $config : $close);
        $this->assertSame(['success', 'success'], array_column($r, 'status'));
        $this->assertSame('closed', ClubAccountingPeriod::findOrFail($p['period'])->status);
        $this->assertSame($before, ClubJournalEntry::where('club_id', $p['club'])->count());
    }

    public function test_foreign_period_is_not_locked_before_rejection(): void
    {
        $p = Fixture::prepare('close');
        $foreign = Club::create(['name' => 'Foreign', 'code' => 'FOREIGN']);
        $period = ClubAccountingPeriod::create(['club_id' => $foreign->id, 'lions_year' => '2027-28', 'start_date' => '2027-07-01', 'end_date' => '2028-06-30', 'status' => 'open', 'created_by_user_id' => $p['actor']]);
        $before = Fixture::snapshot($p['club']);
        // Independently held real foreign row; timeout makes an accidental FOR UPDATE fail the test.
        config(['database.connections.b5_foreign_peer' => config('database.connections.pgsql')]);
        DB::purge('b5_foreign_peer');
        $peer = DB::connection('b5_foreign_peer');
        $peer->beginTransaction();
        $peer->table('club_accounting_periods')->where('id', $period->id)->lockForUpdate()->first();
        try {
            DB::statement("SET lock_timeout='750ms'");
            try {
                Fixture::perform([...$p, 'period' => $period->id]);
                $this->fail('Foreign period must reject');
            } catch (InvalidAccountingPeriodCloseRequestException) {
                $this->addToAssertionCount(1);
            }$this->assertSame($before, Fixture::snapshot($p['club']));
        } finally {
            DB::statement('RESET lock_timeout');
            $peer->rollBack();
            DB::purge('b5_foreign_peer');
        }
    }

    private function race(array $one, array $two): array
    {
        $dir = sys_get_temp_dir().'/b5-race-'.Str::uuid();
        mkdir($dir, 0700, true);
        $workers = [];
        foreach (['one' => $one, 'two' => $two] as $name => $p) {
            file_put_contents($dir.'/'.$name.'.json', json_encode([...$p, 'hold' => $name === 'one'], JSON_THROW_ON_ERROR));
            $workers[$name] = new Process([PHP_BINARY, base_path('tests/Support/AccountingAdministrationWorker.php'), $dir.'/'.$name.'.json', $dir, $name], base_path(), env: ['INSTANCE_ORGANISATION_CODE' => $p['code']], timeout: 40);
        }
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
            $this->await(function () use ($holder, $waiter): bool {
                DB::select('SELECT pg_stat_clear_snapshot()');

                return (int) DB::selectOne("SELECT count(*) AS n FROM pg_stat_activity WHERE pid=? AND wait_event_type='Lock' AND ?=ANY(pg_blocking_pids(pid))", [$waiter, $holder])->n === 1;
            });
            file_put_contents($dir.'/one.finish', '1');
            $out = [];
            foreach ($workers as $name => $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
                $out[] = json_decode(file_get_contents($dir.'/'.$name.'.result.json'), true);
            }
            $this->assertSame([0, 0], array_column($out, 'depth'));
            fwrite(STDOUT, "\nB5_LOCK_EDGE ".json_encode(['one' => $one['mode'].':'.$one['action'], 'two' => $two['mode'].':'.$two['action'], 'holder' => $holder, 'waiter' => $waiter, 'edge_observed' => true, 'outcomes' => array_column($out, 'status')], JSON_THROW_ON_ERROR)."\n");

            return $out;
        } finally {
            foreach (['one', 'two'] as $n) {
                file_put_contents($dir.'/'.$n.'.finish', '1');
            }foreach ($workers as $w) {
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
                $this->fail('Required B5 PostgreSQL lock edge/barrier missing.');
            }usleep(5000);
        }
    }
}
