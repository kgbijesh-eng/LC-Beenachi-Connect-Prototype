<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Club;
use App\Models\ClubAccountingIntegrationActivation;
use App\Models\ClubCharge;
use App\Models\LedgerAccount;
use App\Models\Member;
use App\Models\MemberCharge;
use App\Models\User;
use App\Services\Finance\AccountingIntegrationConfigurationService as Configuration;
use App\Services\FinanceCollections\FinanceWriteService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Real committed prerequisites; all financial calls own their normal B1 roots. */
final class SourceCoverageFixture
{
    public static function prepare(string $type, string $state = 'active', string $coverage = '2027-07-01'): array
    {
        $p = (new FinancialOperationFixture)->prepare('post', 'SC');
        $p['action'] = 'source-'.$type;
        $p['source_type'] = $type;
        $p['source_key'] = $type === 'member_charge' ? 'membership_dues' : 'bank_transfer';
        $p['source_date'] = '2027-08-12';
        $p['commit_date'] = '2027-08-12';
        $p['mapping_version'] = 'SC-V1';
        $p['coverage_date'] = $coverage;
        $p['reference'] = 'SC-'.Str::uuid();
        LedgerAccount::create(['club_id' => $p['club'], 'code' => 'MEMBER-RECEIVABLES', 'name' => 'Receivables', 'account_type' => 'asset', 'normal_balance' => 'debit', 'currency' => 'INR', 'is_active' => true, 'created_by_user_id' => $p['actor'], 'origin_kind' => 'custom', 'statement_classification' => 'RECEIVABLES']);
        $config = app(Configuration::class);
        $actor = User::findOrFail($p['actor']);
        $p['mapped_account'] = $type === 'member_charge' ? $p['income'] : $p['bank'];
        $config->map($p['club'], $type, $p['source_key'], LedgerAccount::findOrFail($p['mapped_account']), $p['mapping_version'], '2027-07-01', 'MAP-'.$p['reference'], $actor);
        $charge = app(FinanceWriteService::class)->createCharge($actor, Club::findOrFail($p['club']), ['title' => 'Coverage probe', 'category' => 'membership_dues', 'amount_paise' => 100, 'due_date' => '2027-08-31', 'financial_year' => '2027-28', 'member_ids' => [$p['member']], 'assign_all_active_members' => false]);
        $p['charge'] = $charge->id;
        $p['version'] = $charge->lock_version;
        if ($type === 'member_payment') {
            $charge = app(FinanceWriteService::class)->issue($actor, Club::findOrFail($p['club']), $charge, $p['version']);
            $p['payment_input'] = ['payment_date' => $p['source_date'], 'payment_mode' => 'bank_transfer', 'amount_paise' => 100, 'idempotency_key' => $p['key'], 'allocations' => [['member_charge_id' => MemberCharge::where('club_charge_id', $charge->id)->value('id'), 'amount_paise' => 100]]];
        }
        if ($state !== 'none') {
            $p['activation'] = $config->activate($p['club'], $type, $coverage, $p['mapping_version'], 'ACT-'.$p['reference'], $actor)->id;
            if (in_array($state, ['terminal', 'reactivated', 'multiple-terminal'], true)) {
                self::revoke($p);
            }
            if (in_array($state, ['reactivated', 'multiple-terminal'], true)) {
                self::activateNext($p, 'SC-V2', $coverage);
            }
            if ($state === 'multiple-terminal') {
                self::revoke($p);
            }
        }

        return $p;
    }

    public static function activateNext(array &$p, string $version, string $coverage, bool $differentAccount = true): void
    {
        $actor = User::findOrFail($p['actor']);
        $config = app(Configuration::class);
        if ($differentAccount) {
            $income = $p['source_type'] === 'member_charge';
            $account = LedgerAccount::create(['club_id' => $p['club'], 'code' => ($income ? 'INCOME-' : 'BANK-').strtoupper(Str::random(8)), 'name' => 'Next-generation mapping', 'account_type' => $income ? 'income' : 'asset', 'normal_balance' => $income ? 'credit' : 'debit', 'currency' => 'INR', 'is_active' => true, 'created_by_user_id' => $p['actor'], 'origin_kind' => 'custom', 'statement_classification' => $income ? 'INCOME' : 'CASH_AND_BANK']);
            $p['mapped_account'] = $account->id;
        }
        $p['mapping_version'] = $version;
        $p['coverage_date'] = $coverage;
        $config->map($p['club'], $p['source_type'], $p['source_key'], LedgerAccount::findOrFail($p['mapped_account']), $version, '2027-07-01', 'NEXT-MAP-'.Str::uuid(), $actor);
        $p['activation'] = $config->activate($p['club'], $p['source_type'], $coverage, $version, 'NEXT-ACT-'.Str::uuid(), $actor)->id;
    }

    public static function revoke(array $p): void
    {
        app(Configuration::class)->revokeActivation(ClubAccountingIntegrationActivation::findOrFail($p['activation']), User::findOrFail($p['actor']), 'REVOKE-'.Str::uuid(), 'Synthetic lifecycle transition');
    }

    public static function perform(array $p): mixed
    {
        Carbon::setTestNow($p['commit_date'].' 10:00:00');
        CarbonImmutable::setTestNow($p['commit_date'].' 10:00:00');
        $service = app(FinanceWriteService::class);
        $club = Club::findOrFail($p['club']);
        $actor = User::findOrFail($p['actor']);
        if ($p['source_type'] === 'member_charge') {
            return $service->issue($actor, $club, ClubCharge::findOrFail($p['charge']), $p['version']);
        }

        return $service->recordPayment($actor, $club, Member::findOrFail($p['member']), [...$p['payment_input'], 'payment_date' => $p['source_date']])['payment'];
    }

    public static function correct(array $p, mixed $source): mixed
    {
        Carbon::setTestNow('2027-08-20 10:00:00');
        CarbonImmutable::setTestNow('2027-08-20 10:00:00');
        $service = app(FinanceWriteService::class);
        $actor = User::findOrFail($p['actor']);
        $club = Club::findOrFail($p['club']);

        return $p['source_type'] === 'member_charge'
            ? $service->cancel($actor, $club, $source->fresh(), $source->fresh()->lock_version, 'Coverage correction')
            : $service->void($actor, $club, $source->fresh(), $source->fresh()->lock_version, 'Coverage correction');
    }

    public static function decision(array $p): ?object
    {
        return DB::table('club_accounting_source_coverage_decisions')->where('club_id', $p['club'])->where('source_type', $p['source_type'])->first();
    }
}
