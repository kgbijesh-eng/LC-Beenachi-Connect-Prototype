<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Club;
use App\Models\ClubAccountingCutoverPackage;
use App\Models\ClubAccountingIntegrationActivation;
use App\Models\ClubAccountingIntegrationActivationEvent;
use App\Models\ClubAccountingSourceMapping;
use App\Models\ClubAccountingSourceMappingEvent;
use App\Models\ClubCharge;
use App\Models\LedgerAccount;
use App\Models\MemberPayment;
use App\Models\User;
use App\Services\Finance\Exceptions\AccountingIntegrationException;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class AccountingIntegrationConfigurationService
{
    public const SOURCE_CHARGE = 'member_charge';

    public const SOURCE_PAYMENT = 'member_payment';

    public function activate(int $clubId, string $sourceType, string $coverageStartDate, string $mappingVersion, string $reference, User $actor): ClubAccountingIntegrationActivation
    {
        $this->source($sourceType);
        $coverageStartDate = $this->date($coverageStartDate, 'coverage start date');
        $mappingVersion = $this->text($mappingVersion, 40, 'mapping version');
        $reference = $this->text($reference, 120, 'activation reference');

        return DB::transaction(function () use ($clubId, $sourceType, $coverageStartDate, $mappingVersion, $reference, $actor): ClubAccountingIntegrationActivation {
            $this->configurationLock($clubId);
            $package = ClubAccountingCutoverPackage::query()->where('club_id', $clubId)->where('status', ClubAccountingCutoverPackage::STATUS_POSTED)->whereNotNull('opening_journal_entry_id')->lockForUpdate()->get();
            if ($package->count() !== 1 || ! $package->first()->openingJournal()->where('status', 'posted')->exists()) {
                throw new AccountingIntegrationException('One posted opening package with a valid opening journal is required.');
            }
            $current = ClubAccountingIntegrationActivation::query()->with('events')->where('club_id', $clubId)->where('source_type', $sourceType)->lockForUpdate()->get()->reject(fn ($row) => $row->events->whereIn('event_type', ['revoked', 'invalidated'])->isNotEmpty());
            if ($current->isNotEmpty()) {
                throw new AccountingIntegrationException('The source integration activation already exists.');
            }
            $opening = $package->first();
            if ($coverageStartDate < $opening->cutover_date->format('Y-m-d')) {
                throw new AccountingIntegrationException('Accounting coverage cannot precede the opening cutover boundary.');
            }

            $sourceTable = $sourceType === self::SOURCE_CHARGE ? 'club_charges' : 'member_payments';
            $dateColumn = $sourceType === self::SOURCE_CHARGE ? 'issued_at' : 'payment_date';
            $statuses = $sourceType === self::SOURCE_CHARGE ? ['issued', 'closed', 'cancelled'] : ['recorded', 'voided'];
            $unintegrated = DB::table("{$sourceTable} as s")
                ->leftJoin('club_accounting_source_coverage_decisions as d', fn ($join) => $join->on('d.club_id', '=', 's.club_id')->on('d.source_identifier', '=', 's.public_id')->where('d.source_type', $sourceType))
                ->leftJoin('club_accounting_source_integrations as i', fn ($join) => $join->on('i.club_id', '=', 's.club_id')->on('i.source_identifier', '=', 's.public_id')->where('i.source_type', $sourceType))
                ->where('s.club_id', $clubId)->whereIn('s.status', $statuses)->whereDate("s.{$dateColumn}", '>=', $coverageStartDate)
                ->where(fn ($q) => $q->whereNull('d.id')->orWhere('d.integration_required', false)->orWhere(fn ($x) => $x->where('d.integration_required', true)->whereNull('i.id')))->exists();
            if ($unintegrated) {
                throw new AccountingIntegrationException('Activation cannot cover pre-existing unjournalled source records.');
            }

            return ClubAccountingIntegrationActivation::create(['club_id' => $clubId, 'source_type' => $sourceType, 'coverage_start_date' => $coverageStartDate, 'mapping_version' => $mappingVersion, 'opening_package_id' => $opening->id, 'opening_journal_entry_id' => $opening->opening_journal_entry_id, 'activation_reference' => $reference, 'activated_by_user_id' => $actor->id, 'activated_at' => now()]);
        });
    }

    public function map(int $clubId, string $sourceType, string $sourceKey, LedgerAccount $account, string $mappingVersion, string $effectiveDate, string $reference, User $actor, ?string $expiresAt = null): ClubAccountingSourceMapping
    {
        $this->source($sourceType);
        $sourceKey = $this->sourceKey($sourceType, $sourceKey);
        $mappingVersion = $this->text($mappingVersion, 40, 'mapping version');
        $effectiveDate = $this->date($effectiveDate, 'mapping effective date');
        $expiresAt = $expiresAt === null ? null : $this->date($expiresAt, 'mapping expiry date');
        $reference = $this->text($reference, 120, 'mapping reference');
        if ($expiresAt !== null && $expiresAt < $effectiveDate) {
            throw new AccountingIntegrationException('Mapping expiry cannot precede its effective date.');
        }

        return DB::transaction(function () use ($clubId, $sourceType, $sourceKey, $account, $mappingVersion, $effectiveDate, $reference, $actor, $expiresAt): ClubAccountingSourceMapping {
            $this->configurationLock($clubId);
            $account = LedgerAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            if ($account->club_id !== $clubId || ! $account->is_active || $account->currency !== LedgerAccount::CURRENCY_INR) {
                throw new AccountingIntegrationException('The mapped account must be an active same-club INR account.');
            }
            if ($sourceType === self::SOURCE_CHARGE && $account->account_type !== LedgerAccount::TYPE_INCOME) {
                throw new AccountingIntegrationException('Charge mappings require an income account.');
            }
            if ($sourceType === self::SOURCE_PAYMENT && ($account->account_type !== LedgerAccount::TYPE_ASSET || ! preg_match('/\A(?:CASH|BANK)-/', $account->code))) {
                throw new AccountingIntegrationException('Payment mappings require an approved cash or bank asset account.');
            }
            $overlap = ClubAccountingSourceMapping::query()->with('events')->where('club_id', $clubId)->where('source_type', $sourceType)->where('canonical_source_key', $sourceKey)->where('mapping_version', $mappingVersion)->where('effective_date', '<=', $expiresAt ?? '9999-12-31')->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', $effectiveDate))->lockForUpdate()->get()->reject(fn ($row) => $row->events->whereIn('event_type', ['revoked', 'invalidated'])->isNotEmpty());
            if ($overlap->isNotEmpty()) {
                throw new AccountingIntegrationException('An overlapping current source mapping already exists.');
            }

            return ClubAccountingSourceMapping::create(['club_id' => $clubId, 'source_type' => $sourceType, 'canonical_source_key' => $sourceKey, 'ledger_account_id' => $account->id, 'mapping_version' => $mappingVersion, 'effective_date' => $effectiveDate, 'expires_at' => $expiresAt, 'approval_reference' => $reference, 'created_by_user_id' => $actor->id, 'approved_at' => now()]);
        });
    }

    public function revokeActivation(ClubAccountingIntegrationActivation $activation, User $actor, string $reference, string $reason): ClubAccountingIntegrationActivationEvent
    {
        $reference = $this->text($reference, 120, 'activation event reference');
        $reason = $this->reason($reason);

        return DB::transaction(function () use ($activation, $actor, $reference, $reason): ClubAccountingIntegrationActivationEvent {
            $this->configurationLock($activation->club_id);
            $activation = ClubAccountingIntegrationActivation::query()->whereKey($activation->id)->lockForUpdate()->firstOrFail();
            if ($activation->events()->whereIn('event_type', ['revoked', 'invalidated'])->exists()) {
                throw new AccountingIntegrationException('The integration activation is already terminal.');
            }

            return ClubAccountingIntegrationActivationEvent::create(['club_id' => $activation->club_id, 'integration_activation_id' => $activation->id, 'event_type' => 'revoked', 'event_reference' => $reference, 'reason' => $reason, 'recorded_by_user_id' => $actor->id, 'recorded_at' => now()]);
        });
    }

    public function revokeMapping(ClubAccountingSourceMapping $mapping, User $actor, string $reference, string $reason): ClubAccountingSourceMappingEvent
    {
        $reference = $this->text($reference, 120, 'mapping event reference');
        $reason = $this->reason($reason);

        return DB::transaction(function () use ($mapping, $actor, $reference, $reason): ClubAccountingSourceMappingEvent {
            $this->configurationLock($mapping->club_id);
            $mapping = ClubAccountingSourceMapping::query()->whereKey($mapping->id)->lockForUpdate()->firstOrFail();
            if ($mapping->events()->whereIn('event_type', ['revoked', 'invalidated'])->exists()) {
                throw new AccountingIntegrationException('The source mapping is already terminal.');
            }

            return ClubAccountingSourceMappingEvent::create(['club_id' => $mapping->club_id, 'source_mapping_id' => $mapping->id, 'event_type' => 'revoked', 'event_reference' => $reference, 'reason' => $reason, 'recorded_by_user_id' => $actor->id, 'recorded_at' => now()]);
        });
    }

    public function configurationLock(int $clubId): Club
    {
        return Club::query()->whereKey($clubId)->lockForUpdate()->firstOrFail();
    }

    private function source(string $sourceType): void
    {
        if (! in_array($sourceType, [self::SOURCE_CHARGE, self::SOURCE_PAYMENT], true)) {
            throw new AccountingIntegrationException('The accounting source type is invalid.');
        }
    }

    private function date(string $value, string $field): string
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (! $parsed || $parsed->format('Y-m-d') !== $value) {
            throw new AccountingIntegrationException("The {$field} must be an exact Y-m-d date.");
        }

        return $value;
    }

    private function text(string $value, int $maximum, string $field): string
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > $maximum) {
            throw new AccountingIntegrationException("The {$field} is invalid.");
        }

        return $value;
    }

    private function reason(string $reason): string
    {
        return $this->text($reason, 1000, 'revocation reason');
    }

    private function sourceKey(string $sourceType, string $sourceKey): string
    {
        $sourceKey = $this->text($sourceKey, 80, 'canonical source key');
        $allowed = $sourceType === self::SOURCE_CHARGE ? ClubCharge::CATEGORIES : MemberPayment::MODES;
        if (! in_array($sourceKey, $allowed, true)) {
            throw new AccountingIntegrationException('The canonical source key is invalid for the source type.');
        }

        return $sourceKey;
    }
}
