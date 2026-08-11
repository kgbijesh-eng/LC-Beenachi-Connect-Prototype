<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubAccountingCutoverPackage;
use App\Models\ClubAccountingPeriod;
use App\Models\ClubAccountingSourceIntegration;
use App\Models\ClubCharge;
use App\Models\LedgerAccount;
use App\Models\Member;
use App\Models\MemberCharge;
use App\Models\MemberPayment;
use App\Models\PaymentAllocation;
use App\Models\PaymentReceipt;
use App\Models\User;
use App\Services\Finance\AccountingIntegrationConfigurationService;
use App\Services\Finance\ClubJournalPostingService;
use App\Services\Finance\JournalLineInput;
use App\Services\Finance\JournalPostingRequest;
use App\Services\FinanceCollections\FinanceWriteService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class Milestone3iizhPostgresConcurrencyTest extends TestCase
{
    public function test_real_charge_and_payment_services_are_concurrency_safe_on_postgresql(): void
    {
        if (env('RUN_3IIZH_POSTGRES_CONCURRENCY') !== '1') {
            $this->markTestSkipped('Requires explicit isolated PostgreSQL opt-in.');
        }
        $this->assertSame('pgsql', DB::getDriverName(), 'This test never falls back to SQLite.');

        foreach (['charge_identical', 'charge_changed', 'payment_identical', 'payment_changed'] as $scenario) {
            $f = $this->fixture($scenario);
            $write = app(FinanceWriteService::class);
            $charge = $this->draftCharge($f);
            if (str_starts_with($scenario, 'payment')) {
                $charge = $write->issue($f['actor'], $f['club'], $charge, 1);
            }
            $base = ['action' => str_starts_with($scenario, 'charge') ? 'charge' : 'payment', 'actor_id' => $f['actor']->id, 'club_id' => $f['club']->id, 'charge_id' => $charge->id, 'version' => 1, 'member_id' => $f['member']->id];
            if ($base['action'] === 'payment') {
                $memberCharge = MemberCharge::where('club_charge_id', $charge->id)->firstOrFail();
                $base['payload'] = $this->paymentPayload($memberCharge, 50000);
            }
            $other = $base;
            if ($scenario === 'charge_changed') {
                $other['version'] = 99;
            }
            if ($scenario === 'payment_changed') {
                $other['payload']['amount_paise'] = 40000;
                $other['payload']['allocations'][0]['amount_paise'] = 40000;
            }
            $results = $this->race($scenario, $base, $other, $base['action'] === 'charge' ? ['club_charges', $charge->id] : ['clubs', $f['club']->id]);
            $expected = str_ends_with($scenario, 'identical') ? ['resolved', 'resolved'] : ['conflict', 'resolved'];
            $this->assertSame($expected, collect($results)->pluck('outcome')->sort()->values()->all());
            if ($base['action'] === 'charge') {
                $this->assertSame(1, ClubCharge::whereKey($charge->id)->where('status', 'issued')->count());
                $this->assertSame(1, MemberCharge::where('club_charge_id', $charge->id)->count());
                $this->assertSame(1, ClubAccountingSourceIntegration::where('club_id', $f['club']->id)->where('source_type', 'member_charge')->count());
            } else {
                $this->assertSame(1, MemberPayment::where('club_id', $f['club']->id)->count());
                $this->assertSame(1, PaymentAllocation::where('club_id', $f['club']->id)->count());
                $this->assertSame(1, PaymentReceipt::where('club_id', $f['club']->id)->count());
                $this->assertSame(1, ClubAccountingSourceIntegration::where('club_id', $f['club']->id)->where('source_type', 'member_payment')->count());
            }
        }
    }

    public function test_activation_and_configuration_races_never_leave_partial_accounting_on_postgresql(): void
    {
        if (env('RUN_3IIZH_POSTGRES_CONCURRENCY') !== '1') {
            $this->markTestSkipped('Requires explicit isolated PostgreSQL opt-in.');
        }
        $this->assertSame('pgsql', DB::getDriverName());

        foreach (['activation_charge', 'activation_payment'] as $scenario) {
            $f = $this->fixture($scenario, false);
            $charge = $this->draftCharge($f);
            $source = ['action' => 'charge', 'actor_id' => $f['actor']->id, 'club_id' => $f['club']->id, 'charge_id' => $charge->id, 'version' => 1, 'member_id' => $f['member']->id];
            $sourceType = 'member_charge';
            if ($scenario === 'activation_payment') {
                $issued = app(FinanceWriteService::class)->issue($f['actor'], $f['club'], $charge, 1);
                $sourceType = 'member_payment';
                $source = ['action' => 'payment', 'actor_id' => $f['actor']->id, 'club_id' => $f['club']->id, 'member_id' => $f['member']->id, 'payload' => $this->paymentPayload(MemberCharge::where('club_charge_id', $issued->id)->firstOrFail(), 50000)];
            }
            $activate = ['action' => 'activate', 'actor_id' => $f['actor']->id, 'club_id' => $f['club']->id, 'source_type' => $sourceType, 'reference' => 'RACE-'.$scenario];
            $results = $this->race($scenario, $source, $activate, ['clubs', $f['club']->id]);
            $this->assertFalse(collect($results)->contains('outcome', 'error'));
            $activation = DB::table('club_accounting_integration_activations')->where('club_id', $f['club']->id)->where('source_type', $sourceType)->first();
            $sourceRow = $sourceType === 'member_charge' ? ClubCharge::find($charge->id) : MemberPayment::where('club_id', $f['club']->id)->first();
            if ($activation && $sourceRow && ($sourceType === 'member_payment' || $sourceRow->status === 'issued')) {
                $this->assertDatabaseHas('club_accounting_source_integrations', ['club_id' => $f['club']->id, 'source_type' => $sourceType, 'source_identifier' => $sourceRow->public_id]);
            }
            $this->assertSame(0, DB::table('club_accounting_source_integrations as i')->leftJoin('club_journal_entries as j', 'j.id', '=', 'i.journal_entry_id')->whereNull('j.id')->count());
        }
    }

    public function test_mapping_and_activation_revocation_races_serialize_with_source_posting_on_postgresql(): void
    {
        if (env('RUN_3IIZH_POSTGRES_CONCURRENCY') !== '1') {
            $this->markTestSkipped('Requires explicit isolated PostgreSQL opt-in.');
        }
        foreach (['mapping_create_charge', 'mapping_revoke_payment', 'activation_revoke_charge'] as $scenario) {
            $f = $this->fixture($scenario);
            $charge = $this->draftCharge($f);
            if ($scenario === 'mapping_revoke_payment') {
                $charge = app(FinanceWriteService::class)->issue($f['actor'], $f['club'], $charge, 1);
                $source = ['action' => 'payment', 'actor_id' => $f['actor']->id, 'club_id' => $f['club']->id, 'member_id' => $f['member']->id, 'payload' => $this->paymentPayload(MemberCharge::where('club_charge_id', $charge->id)->firstOrFail(), 50000)];
                $mapping = DB::table('club_accounting_source_mappings')->where('club_id', $f['club']->id)->where('source_type', 'member_payment')->first();
                $configuration = ['action' => 'revoke_mapping', 'actor_id' => $f['actor']->id, 'club_id' => $f['club']->id, 'mapping_id' => $mapping->id, 'reference' => 'RACE-REVOKE-MAP-'.$f['club']->id];
            } else {
                $source = ['action' => 'charge', 'actor_id' => $f['actor']->id, 'club_id' => $f['club']->id, 'charge_id' => $charge->id, 'version' => 1, 'member_id' => $f['member']->id];
                if ($scenario === 'mapping_create_charge') {
                    $configuration = ['action' => 'map', 'actor_id' => $f['actor']->id, 'club_id' => $f['club']->id, 'source_type' => 'member_charge', 'source_key' => 'membership_dues', 'account_id' => $f['income']->id, 'reference' => 'RACE-MAP-'.$f['club']->id];
                } else {
                    $activation = DB::table('club_accounting_integration_activations')->where('club_id', $f['club']->id)->where('source_type', 'member_charge')->first();
                    $configuration = ['action' => 'revoke_activation', 'actor_id' => $f['actor']->id, 'club_id' => $f['club']->id, 'activation_id' => $activation->id, 'reference' => 'RACE-REVOKE-ACT-'.$f['club']->id];
                }
            }
            $results = $this->race($scenario, $source, $configuration, ['clubs', $f['club']->id]);
            $this->assertFalse(collect($results)->contains('outcome', 'error'));
            $this->assertSame(0, DB::table('club_accounting_source_integrations as i')->leftJoin('club_journal_entries as j', 'j.id', '=', 'i.journal_entry_id')->whereNull('j.id')->count());
            $this->assertSame(0, DB::table('club_journal_entries as j')->whereIn('j.source_type', ['member_charge', 'member_payment'])->whereNull('j.reversal_of_entry_id')->whereNotExists(fn ($q) => $q->selectRaw('1')->from('club_accounting_source_integrations as i')->whereColumn('i.journal_entry_id', 'j.id'))->count());
        }
    }

    public function test_duplicate_terminal_and_reversal_attempts_commit_one_event_on_postgresql(): void
    {
        if (env('RUN_3IIZH_POSTGRES_CONCURRENCY') !== '1') {
            $this->markTestSkipped('Requires explicit isolated PostgreSQL opt-in.');
        }
        $f = $this->fixture('duplicate_terminal');
        $activation = DB::table('club_accounting_integration_activations')->where('club_id', $f['club']->id)->where('source_type', 'member_payment')->first();
        $request = ['action' => 'revoke_activation', 'actor_id' => $f['actor']->id, 'club_id' => $f['club']->id, 'activation_id' => $activation->id, 'reference' => 'DUP-REV-A'];
        $other = $request;
        $other['reference'] = 'DUP-REV-B';
        $results = $this->race('duplicate_activation_revocation', $request, $other, ['clubs', $f['club']->id]);
        $this->assertSame(['conflict', 'resolved'], collect($results)->pluck('outcome')->sort()->values()->all());
        $this->assertSame(1, DB::table('club_accounting_integration_activation_events')->where('integration_activation_id', $activation->id)->count());

        $charge = app(FinanceWriteService::class)->issue($f['actor'], $f['club'], $this->draftCharge($f), 1);
        $cancel = ['action' => 'cancel_charge', 'actor_id' => $f['actor']->id, 'club_id' => $f['club']->id, 'charge_id' => $charge->id, 'version' => $charge->lock_version, 'reason' => 'Concurrent correction'];
        $results = $this->race('duplicate_charge_reversal', $cancel, $cancel, ['clubs', $f['club']->id]);
        $this->assertSame(['conflict', 'resolved'], collect($results)->pluck('outcome')->sort()->values()->all());
        $integration = ClubAccountingSourceIntegration::where('source_identifier', $charge->public_id)->firstOrFail();
        $this->assertSame(1, DB::table('club_accounting_source_integration_events')->where('source_integration_id', $integration->id)->where('event_type', 'integration_reversal')->count());
    }

    private function race(string $scenario, array $oneRequest, array $twoRequest, array $lock): array
    {
        $barrier = sys_get_temp_dir().DIRECTORY_SEPARATOR.'3iizh-pg-'.$scenario.'-'.Str::lower(Str::random(8));
        mkdir($barrier, 0700, true);
        foreach (['one' => $oneRequest, 'two' => $twoRequest] as $name => $request) {
            file_put_contents($barrier.DIRECTORY_SEPARATOR.$name.'.json', json_encode($request, JSON_THROW_ON_ERROR));
        }
        $worker = base_path('tests/Support/Milestone3iizhPostgresConcurrencyWorker.php');
        $one = new Process([PHP_BINARY, $worker, $barrier.DIRECTORY_SEPARATOR.'one.json', $barrier, 'one'], base_path(), timeout: 40);
        $two = new Process([PHP_BINARY, $worker, $barrier.DIRECTORY_SEPARATOR.'two.json', $barrier, 'two'], base_path(), timeout: 40);
        DB::beginTransaction();
        DB::select("select id from {$lock[0]} where id = ? for update", [$lock[1]]);
        try {
            $one->start();
            $two->start();
            $this->await([$barrier.DIRECTORY_SEPARATOR.'one.ready', $barrier.DIRECTORY_SEPARATOR.'two.ready']);
            file_put_contents($barrier.DIRECTORY_SEPARATOR.'one.release', '1');
            file_put_contents($barrier.DIRECTORY_SEPARATOR.'two.release', '1');
            $this->await([$barrier.DIRECTORY_SEPARATOR.'one.attempting', $barrier.DIRECTORY_SEPARATOR.'two.attempting']);
            usleep(100_000);
            $released = hrtime(true);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        $one->wait();
        $two->wait();
        $results = array_map(fn ($name) => json_decode((string) file_get_contents($barrier.DIRECTORY_SEPARATOR.$name.'.result.json'), true, flags: JSON_THROW_ON_ERROR), ['one', 'two']);
        $this->assertTrue(collect($results)->every(fn ($r) => $r['started_at_ns'] < $released));
        $this->assertTrue(max(array_column($results, 'started_at_ns')) < min(array_column($results, 'finished_at_ns')));

        foreach (glob($barrier.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($barrier);

        return $results;
    }

    private function await(array $files): void
    {
        $until = microtime(true) + 20;
        while (! collect($files)->every(fn ($f) => is_file($f))) {
            if (microtime(true) > $until) {
                $this->fail('Concurrency barrier timed out.');
            } usleep(5_000);
        }
    }

    private function fixture(string $key, bool $activate = true): array
    {
        CarbonImmutable::setTestNow('2027-07-05 10:00:00');
        $actor = User::factory()->create();
        $club = Club::create(['name' => $key, 'code' => 'C'.substr(hash('sha256', $key), 0, 6), 'timezone' => 'Asia/Kolkata', 'is_active' => true]);
        $member = Member::create(['club_id' => $club->id, 'member_code' => 'M'.$club->id, 'full_name' => 'Member', 'status' => 'active']);
        foreach ([['receivable', 'MEMBER-RECEIVABLES', 'asset', 'debit'], ['bank', 'BANK-OPERATING', 'asset', 'debit'], ['income', 'INCOME-DUES', 'income', 'credit'], ['equity', 'OPENING-BALANCE-EQUITY', 'equity', 'credit']] as [$name, $code, $type, $normal]) {
            $$name = LedgerAccount::create(['club_id' => $club->id, 'code' => $code, 'name' => $code, 'account_type' => $type, 'normal_balance' => $normal, 'currency' => 'INR', 'is_active' => true, 'created_by_user_id' => $actor->id]);
        }
        $period = ClubAccountingPeriod::create(['club_id' => $club->id, 'lions_year' => '2027-28', 'start_date' => '2027-07-01', 'end_date' => '2028-06-30', 'status' => 'open', 'created_by_user_id' => $actor->id]);
        $opening = app(ClubJournalPostingService::class)->post(new JournalPostingRequest($club->id, $period->id, '2027-07-01', 'Opening', 'INR', 'accounting_opening_package', 'opening-'.$club->id, 'activated', (string) Str::uuid(), $actor->id, [new JournalLineInput($receivable->id, 'debit', 1, fundCode: 'GENERAL_CLUB_FUND'), new JournalLineInput($equity->id, 'credit', 1, fundCode: 'GENERAL_CLUB_FUND')]));
        ClubAccountingCutoverPackage::create(['club_id' => $club->id, 'lions_year' => '2027-28', 'cutover_date' => '2027-07-01', 'chart_version' => 'CLUB-COA-2026-01', 'package_version' => 1, 'status' => 'posted', 'currency' => 'INR', 'canonical_checksum' => str_repeat('a', 64), 'reconciliation_difference_paise' => 0, 'dr033_classification' => 'OPENING-BALANCE-EQUITY', 'activation_ready' => true, 'opening_journal_entry_id' => $opening->id, 'posted_at' => now(), 'posted_by_user_id' => $actor->id, 'created_by_user_id' => $actor->id, 'updated_by_user_id' => $actor->id]);
        $config = app(AccountingIntegrationConfigurationService::class);
        foreach ([['member_charge', 'membership_dues', $income], ['member_payment', 'bank_transfer', $bank]] as $i => [$source, $sourceKey, $account]) {
            $config->map($club->id, $source, $sourceKey, $account, 'MAP-V1', '2027-07-01', "MAP-{$key}-{$i}", $actor);
            if ($activate) {
                $config->activate($club->id, $source, '2027-07-01', 'MAP-V1', "ACT-{$key}-{$i}", $actor);
            }
        }

        return compact('actor', 'club', 'member', 'bank', 'income');
    }

    private function draftCharge(array $f): ClubCharge
    {
        return app(FinanceWriteService::class)->createCharge($f['actor'], $f['club'], ['title' => 'Dues', 'category' => 'membership_dues', 'amount_paise' => 50000, 'due_date' => '2027-07-31', 'financial_year' => '2027-28', 'member_ids' => [$f['member']->id], 'assign_all_active_members' => false]);
    }

    private function paymentPayload(MemberCharge $charge, int $amount): array
    {
        return ['payment_date' => '2027-07-06', 'payment_mode' => 'bank_transfer', 'external_reference' => 'BANK', 'amount_paise' => $amount, 'allocations' => [['member_charge_id' => $charge->id, 'amount_paise' => $amount]], 'idempotency_key' => (string) Str::uuid()];
    }
}
