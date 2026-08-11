<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\ClubAccountingIntegrationActivation;
use App\Models\ClubAccountingPeriod;
use App\Models\ClubAccountingSourceIntegration;
use App\Models\ClubAccountingSourceIntegrationEvent;
use App\Models\ClubAccountingSourceMapping;
use App\Models\ClubCharge;
use App\Models\ClubJournalEntry;
use App\Models\LedgerAccount;
use App\Models\MemberPayment;
use App\Models\PaymentAllocationJournalLine;
use App\Models\User;
use App\Services\Finance\Exceptions\AccountingIntegrationConflictException;
use App\Services\Finance\Exceptions\AccountingIntegrationException;
use App\Services\Finance\Exceptions\JournalIdempotencyConflictException;
use App\Services\Finance\Exceptions\JournalSourceEventConflictException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SourceAccountingIntegrationService
{
    public function __construct(private ClubJournalPostingService $journals, private JournalReversalService $reversals) {}

    public function enabled(int $clubId, string $sourceType): bool
    {
        return $this->activation($clubId, $sourceType, false) !== null;
    }

    public function recordChargeCoverageDecision(ClubCharge $charge, User $actor): bool
    {
        if ($charge->status !== 'issued' || ! $charge->issued_at) {
            throw new AccountingIntegrationException('Coverage can be decided only for an issued charge.');
        }

        return $this->recordCoverageDecision($charge->club_id, AccountingIntegrationConfigurationService::SOURCE_CHARGE, $charge->public_id, $charge->issued_at->toDateString(), $actor);
    }

    public function recordPaymentCoverageDecision(MemberPayment $payment, User $actor): bool
    {
        if ($payment->status !== 'recorded') {
            throw new AccountingIntegrationException('Coverage can be decided only for a recorded payment.');
        }

        return $this->recordCoverageDecision($payment->club_id, AccountingIntegrationConfigurationService::SOURCE_PAYMENT, $payment->public_id, $payment->payment_date->format('Y-m-d'), $actor);
    }

    public function postCharge(ClubCharge $charge, User $actor): ClubAccountingSourceIntegration
    {
        $this->requireOuterTransaction();
        $requestedVersion = $charge->lock_version;
        $charge = ClubCharge::query()->whereKey($charge->id)->lockForUpdate()->firstOrFail();
        if ($charge->status !== 'issued' || ! $charge->issued_at || $charge->lock_version !== $requestedVersion) {
            throw new AccountingIntegrationException('Only an issued charge may be posted.');
        }
        $binding = $this->chargeBinding($charge);

        if (! FundCode::tryFrom((string) $charge->fund_code)) {
            throw new AccountingIntegrationException('The issued charge lacks a canonical frozen fund.');
        }

        return $this->post($charge->club_id, AccountingIntegrationConfigurationService::SOURCE_CHARGE, $charge->public_id, $charge->lock_version, $charge->issued_at->toDateString(), $charge->category, (int) collect($binding['member_charges'])->sum('original_amount_paise'), $actor, null, $binding, 'Charge issued', 'income', [$charge->fund_code => (int) collect($binding['member_charges'])->sum('original_amount_paise')]);
    }

    public function postPayment(MemberPayment $payment, User $actor): ClubAccountingSourceIntegration
    {
        $this->requireOuterTransaction();
        $requestedVersion = $payment->lock_version;
        $payment = MemberPayment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
        if ($payment->status !== 'recorded' || $payment->lock_version !== $requestedVersion) {
            throw new AccountingIntegrationException('Only a recorded payment may be posted.');
        }
        $binding = $this->paymentBinding($payment);
        $allocated = (int) collect($binding['allocations'])->sum('amount_paise');
        if ($allocated !== $payment->amount_paise) {
            throw new AccountingIntegrationException('The payment must be fully allocated before accounting integration.');
        }

        $groups = $payment->allocations->groupBy('fund_code')->map(fn ($rows): int => (int) $rows->sum('amount_paise'))->all();
        if ($groups === [] || collect(array_keys($groups))->contains(fn ($fund) => ! FundCode::tryFrom((string) $fund))) {
            throw new AccountingIntegrationException('Every payment allocation requires a canonical frozen fund.');
        }

        return $this->post($payment->club_id, AccountingIntegrationConfigurationService::SOURCE_PAYMENT, $payment->public_id, $payment->lock_version, $payment->payment_date->format('Y-m-d'), $payment->payment_mode, $allocated, $actor, strtolower((string) $payment->idempotency_key), $binding, 'Member payment recorded', 'asset', $groups, $payment);
    }

    public function validateChargeReplay(ClubCharge $charge): ?ClubAccountingSourceIntegration
    {
        $binding = $this->chargeBinding($charge);

        return $this->validateSourceReplay(['club_id' => $charge->club_id, 'source_type' => AccountingIntegrationConfigurationService::SOURCE_CHARGE, 'source_id' => $charge->public_id, 'source_version' => $charge->lock_version, 'date' => $charge->issued_at->toDateString(), 'source_key' => $charge->category, 'amount' => (int) collect($binding['member_charges'])->sum('original_amount_paise'), 'request_key' => null, 'binding' => $binding, 'terminal' => $charge->status === 'cancelled']);
    }

    public function validatePaymentReplay(MemberPayment $payment): ?ClubAccountingSourceIntegration
    {
        $integration = ClubAccountingSourceIntegration::query()
            ->where('club_id', $payment->club_id)
            ->where('source_type', AccountingIntegrationConfigurationService::SOURCE_PAYMENT)
            ->where('source_identifier', $payment->public_id)
            ->first();
        $committedVersion = $payment->status === 'voided' && $integration
            ? $integration->source_version
            : $payment->lock_version;
        $binding = $this->paymentBinding($payment, $committedVersion);

        return $this->validateSourceReplay(['club_id' => $payment->club_id, 'source_type' => AccountingIntegrationConfigurationService::SOURCE_PAYMENT, 'source_id' => $payment->public_id, 'source_version' => $committedVersion, 'current_source_version' => $payment->lock_version, 'date' => $payment->payment_date->format('Y-m-d'), 'source_key' => $payment->payment_mode, 'amount' => $payment->amount_paise, 'request_key' => strtolower((string) $payment->idempotency_key), 'binding' => $binding, 'terminal' => $payment->status === 'voided']);
    }

    public function reverse(int $clubId, string $sourceType, string $sourceId, string $sourceAccountingDate, string $sourceCommittedAt, string $reversalDate, User $actor, string $reason): void
    {
        $this->requireOuterTransaction();
        $this->assertTerminalSource($clubId, $sourceType, $sourceId);
        $integration = ClubAccountingSourceIntegration::query()->with('journalEntry')->where('club_id', $clubId)->where('source_type', $sourceType)->where('source_identifier', $sourceId)->lockForUpdate()->first();
        if (! $integration) {
            if ($this->coverageRequired($clubId, $sourceType, $sourceId, $sourceAccountingDate)) {
                throw new AccountingIntegrationException('An in-coverage source is missing its committed journal linkage.');
            }

            return;
        }
        if (! $integration->journalEntry) {
            throw new AccountingIntegrationException('A linked source journal is required before reversal.');
        }
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 1000) {
            throw new AccountingIntegrationException('A valid normalized reversal reason is required.');
        }
        $periods = ClubAccountingPeriod::query()->where('club_id', $clubId)->where('start_date', '<=', $reversalDate)->where('end_date', '>=', $reversalDate)->where('status', ClubAccountingPeriod::STATUS_OPEN)->lockForUpdate()->get();
        if ($periods->count() !== 1) {
            throw new AccountingIntegrationException('One open reversal period is required.');
        }
        $journalKey = $this->journalKey($clubId, $sourceType, $sourceId, 'reversed');
        try {
            $reversal = $this->reversals->reverse(new JournalReversalRequest($clubId, $integration->journal_entry_id, $periods->first()->id, $reversalDate, $actor->id, $reason, $sourceType, $sourceId, 'reversed', $journalKey));
        } catch (JournalIdempotencyConflictException|JournalSourceEventConflictException $exception) {
            throw new AccountingIntegrationConflictException('The source reversal replay bindings changed.', 0, $exception);
        }
        $event = ClubAccountingSourceIntegrationEvent::query()->where('source_integration_id', $integration->id)->where('event_type', 'integration_reversal')->lockForUpdate()->first();
        if ($event) {
            $valid = $event->reversal_journal_entry_id === $reversal->id
                && ($event->payload['reversal_journal_public_id'] ?? null) === $reversal->public_id
                && $event->reversal_reference === $reversal->reference
                && $event->reversal_effective_date?->format('Y-m-d') === $reversalDate
                && $event->recorded_by_user_id === $actor->id
                && $event->reversal_reason === $reason
                && $reversal->reversal_of_entry_id === $integration->journal_entry_id
                && $reversal->source_type === $sourceType
                && $reversal->source_id === $sourceId
                && $reversal->source_event === 'reversed'
                && strtolower($reversal->idempotency_key) === $journalKey;
            if (! $valid) {
                throw new AccountingIntegrationConflictException('The source reversal replay bindings changed.');
            }

            return;
        }
        ClubAccountingSourceIntegrationEvent::create(['club_id' => $clubId, 'source_integration_id' => $integration->id, 'event_type' => 'integration_reversal', 'reversal_journal_entry_id' => $reversal->id, 'reversal_reference' => $reversal->reference, 'reversal_effective_date' => $reversalDate, 'reversal_reason' => $reason, 'payload' => ['reversal_journal_public_id' => $reversal->public_id], 'recorded_by_user_id' => $actor->id, 'recorded_at' => now()]);
    }

    public function chargeBinding(ClubCharge $charge, ?int $sourceVersion = null): array
    {
        $charge->loadMissing(['memberCharges' => fn ($query) => $query->orderBy('id')]);

        return ['charge_public_id' => strtolower($charge->public_id), 'category' => $charge->category, 'fund_code' => $charge->fund_code, 'amount_per_member_paise' => (int) $charge->amount_paise, 'source_version' => $sourceVersion ?? (int) $charge->lock_version, 'member_charges' => $charge->memberCharges->map(fn ($row) => ['member_charge_id' => $row->id, 'member_id' => $row->member_id, 'original_amount_paise' => (int) $row->original_amount_paise])->values()->all()];
    }

    public function paymentBinding(MemberPayment $payment, ?int $sourceVersion = null): array
    {
        $payment->loadMissing(['allocations' => fn ($query) => $query->orderBy('id')]);

        return ['payment_public_id' => strtolower($payment->public_id), 'member_id' => $payment->member_id, 'payment_mode' => $payment->payment_mode, 'payment_amount_paise' => (int) $payment->amount_paise, 'source_request_idempotency_key' => strtolower((string) $payment->idempotency_key), 'source_version' => $sourceVersion ?? (int) $payment->lock_version, 'allocations' => $payment->allocations->map(fn ($row) => ['allocation_id' => $row->id, 'member_charge_id' => $row->member_charge_id, 'fund_code' => $row->fund_code, 'amount_paise' => (int) $row->amount_paise])->values()->all()];
    }

    private function post(int $clubId, string $sourceType, string $sourceId, int $sourceVersion, string $date, string $sourceKey, int $amount, User $actor, ?string $sourceRequestKey, array $binding, string $description, string $mappedType, array $fundGroups, ?MemberPayment $payment = null): ClubAccountingSourceIntegration
    {
        $existing = ClubAccountingSourceIntegration::query()->where('club_id', $clubId)->where('source_type', $sourceType)->where('source_identifier', $sourceId)->lockForUpdate()->first();
        if ($existing) {
            return $this->validateCommittedReplay($existing, ['club_id' => $clubId, 'source_type' => $sourceType, 'source_id' => $sourceId, 'source_version' => $sourceVersion, 'date' => $date, 'source_key' => $sourceKey, 'amount' => $amount, 'request_key' => $sourceRequestKey, 'binding' => $binding, 'terminal' => false]);
        }
        $activation = $this->activation($clubId, $sourceType, true);
        if (! $activation || $date < $activation->coverage_start_date->format('Y-m-d')) {
            throw new AccountingIntegrationException('Accounting integration is not enabled for the source date.');
        }
        $coverage = DB::table('club_accounting_source_coverage_decisions')->where('club_id', $clubId)->where('source_type', $sourceType)->where('source_identifier', $sourceId)->lockForUpdate()->first();
        if (! $coverage || ! $coverage->integration_required || $coverage->decision_reason !== 'integration_required' || $coverage->integration_activation_id !== $activation->id || $coverage->terminal_activation_event_id !== null) {
            throw new AccountingIntegrationException('The persisted source coverage decision does not authorize integration.');
        }
        $mapping = $this->mapping($activation, $sourceKey, $date, $mappedType);
        $receivable = LedgerAccount::query()->where('club_id', $clubId)->where('code', 'MEMBER-RECEIVABLES')->where('account_type', LedgerAccount::TYPE_ASSET)->where('currency', 'INR')->where('is_active', true)->lockForUpdate()->get();
        if ($receivable->count() !== 1) {
            throw new AccountingIntegrationException('One active MEMBER-RECEIVABLES account is required.');
        }
        $periods = ClubAccountingPeriod::query()->where('club_id', $clubId)->where('start_date', '<=', $date)->where('end_date', '>=', $date)->lockForUpdate()->get();
        if ($periods->count() !== 1 || $periods->first()->status !== ClubAccountingPeriod::STATUS_OPEN) {
            throw new AccountingIntegrationException('One open accounting period covering the source date is required.');
        }
        $journalKey = $this->journalKey($clubId, $sourceType, $sourceId, 'committed');
        $checksum = $this->checksum(['club_id' => $clubId, 'source_type' => $sourceType, 'source_public_id' => $sourceId, 'accounting_date' => $date, 'source_binding' => $binding, 'activation_id' => $activation->id, 'mapping_id' => $mapping->id, 'mapping_version' => $activation->mapping_version, 'mapped_account_id' => $mapping->ledger_account_id, 'accounting_period_id' => $periods->first()->id, 'journal_idempotency_key' => $journalKey]);
        $debit = $sourceType === AccountingIntegrationConfigurationService::SOURCE_CHARGE ? $receivable->first()->id : $mapping->ledger_account_id;
        $credit = $sourceType === AccountingIntegrationConfigurationService::SOURCE_CHARGE ? $mapping->ledger_account_id : $receivable->first()->id;
        ksort($fundGroups, SORT_STRING);
        $lines = [];
        foreach ($fundGroups as $fund => $groupAmount) {
            $lines[] = new JournalLineInput($debit, 'debit', $groupAmount, fundCode: $fund);
            $lines[] = new JournalLineInput($credit, 'credit', $groupAmount, fundCode: $fund);
        }
        $journal = $this->journals->post(new JournalPostingRequest($clubId, $periods->first()->id, $date, $description, 'INR', $sourceType, $sourceId, 'committed', $journalKey, $actor->id, $lines, metadata: ['mapping_version' => $activation->mapping_version, 'integration_checksum' => $checksum, 'source_binding' => $binding]));
        $integration = ClubAccountingSourceIntegration::create(['club_id' => $clubId, 'source_type' => $sourceType, 'source_identifier' => $sourceId, 'source_version' => $sourceVersion, 'accounting_date' => $date, 'integration_activation_id' => $activation->id, 'source_mapping_id' => $mapping->id, 'canonical_source_key' => $sourceKey, 'source_amount_paise' => $amount, 'source_binding' => $binding, 'mapping_version' => $activation->mapping_version, 'journal_entry_id' => $journal->id, 'journal_reference' => $journal->reference, 'source_request_idempotency_key' => $sourceRequestKey, 'journal_idempotency_key' => $journalKey, 'integration_checksum' => $checksum, 'committed_by_user_id' => $actor->id, 'committed_at' => now()]);
        ClubAccountingSourceIntegrationEvent::create(['club_id' => $clubId, 'source_integration_id' => $integration->id, 'event_type' => 'integration_success', 'payload' => ['journal_entry_id' => $journal->id, 'journal_public_id' => $journal->public_id, 'journal_reference' => $journal->reference, 'checksum' => $checksum], 'recorded_by_user_id' => $actor->id, 'recorded_at' => now()]);
        if ($payment) {
            $byFund = $journal->lines->groupBy('fund_code');
            foreach ($payment->allocations as $allocation) {
                $pair = $byFund->get($allocation->fund_code);
                if (! $pair || $pair->count() !== 2) {
                    throw new AccountingIntegrationException('Payment journal allocation traceability is incomplete.');
                }
                PaymentAllocationJournalLine::create(['club_id' => $clubId, 'payment_allocation_id' => $allocation->id, 'journal_line_id' => $pair->firstWhere('direction', 'debit')->id, 'line_role' => PaymentJournalLineRole::ASSET_DEBIT->value, 'amount_paise' => $allocation->amount_paise]);
                PaymentAllocationJournalLine::create(['club_id' => $clubId, 'payment_allocation_id' => $allocation->id, 'journal_line_id' => $pair->firstWhere('direction', 'credit')->id, 'line_role' => PaymentJournalLineRole::RECEIVABLES_CREDIT->value, 'amount_paise' => $allocation->amount_paise]);
            }
        }

        return $integration->load('journalEntry');
    }

    private function validateSourceReplay(array $contract): ?ClubAccountingSourceIntegration
    {
        $existing = ClubAccountingSourceIntegration::query()->where('club_id', $contract['club_id'])->where('source_type', $contract['source_type'])->where('source_identifier', $contract['source_id'])->first();
        if ($existing) {
            return $this->validateCommittedReplay($existing, $contract);
        }
        if ($this->coverageRequired($contract['club_id'], $contract['source_type'], $contract['source_id'], $contract['date'])) {
            throw new AccountingIntegrationException('An historically covered source is missing its journal linkage.');
        }

        return null;
    }

    private function validateCommittedReplay(ClubAccountingSourceIntegration $existing, array $contract): ClubAccountingSourceIntegration
    {
        $existing->load(['activation', 'mapping', 'journalEntry.lines']);
        $journal = $existing->journalEntry;
        $mapping = $existing->mapping;
        $activation = $existing->activation;
        $period = $journal ? ClubAccountingPeriod::query()->find($journal->accounting_period_id) : null;
        $coverage = DB::table('club_accounting_source_coverage_decisions')->where('club_id', $contract['club_id'])->where('source_type', $contract['source_type'])->where('source_identifier', $contract['source_id'])->first();
        $journalKey = $this->journalKey($contract['club_id'], $contract['source_type'], $contract['source_id'], 'committed');
        $checksum = $mapping && $period ? $this->checksum(['club_id' => $contract['club_id'], 'source_type' => $contract['source_type'], 'source_public_id' => $contract['source_id'], 'accounting_date' => $contract['date'], 'source_binding' => $contract['binding'], 'activation_id' => $existing->integration_activation_id, 'mapping_id' => $existing->source_mapping_id, 'mapping_version' => $existing->mapping_version, 'mapped_account_id' => $mapping->ledger_account_id, 'accounting_period_id' => $period->id, 'journal_idempotency_key' => $journalKey]) : null;
        $receivable = LedgerAccount::query()->where('club_id', $contract['club_id'])->where('code', 'MEMBER-RECEIVABLES')->value('id');
        $expectedDebit = $contract['source_type'] === AccountingIntegrationConfigurationService::SOURCE_CHARGE ? $receivable : $mapping?->ledger_account_id;
        $expectedCredit = $contract['source_type'] === AccountingIntegrationConfigurationService::SOURCE_CHARGE ? $mapping?->ledger_account_id : $receivable;
        $lines = $journal?->lines?->sortBy('line_number')->values()->map(fn ($line) => [$line->line_number, $line->ledger_account_id, $line->direction, (int) $line->amount_paise, $line->fund_code])->all();
        $fundGroups = $contract['source_type'] === AccountingIntegrationConfigurationService::SOURCE_CHARGE
            ? [($contract['binding']['fund_code'] ?? '') => $contract['amount']]
            : collect($contract['binding']['allocations'] ?? [])->groupBy('fund_code')->map(fn ($rows) => (int) collect($rows)->sum('amount_paise'))->all();
        ksort($fundGroups, SORT_STRING);
        $expectedLines = [];
        $lineNumber = 1;
        foreach ($fundGroups as $fund => $groupAmount) {
            $expectedLines[] = [$lineNumber++, $expectedDebit, 'debit', $groupAmount, $fund];
            $expectedLines[] = [$lineNumber++, $expectedCredit, 'credit', $groupAmount, $fund];
        }
        $currentVersion = $contract['current_source_version'] ?? $contract['source_version'];
        $versionValid = $contract['terminal']
            ? $contract['source_version'] === $existing->source_version && $currentVersion === $existing->source_version + 1
            : $contract['source_version'] === $existing->source_version && $currentVersion === $existing->source_version;
        $terminalValid = ! $contract['terminal'] || $this->validTerminalReplay($existing, $journal);
        $valid = $existing->club_id === $contract['club_id'] && $existing->source_type === $contract['source_type'] && $existing->source_identifier === $contract['source_id'] && $versionValid && $existing->accounting_date->format('Y-m-d') === $contract['date'] && $existing->canonical_source_key === $contract['source_key'] && $existing->source_amount_paise === $contract['amount'] && $existing->source_binding === $contract['binding'] && strtolower((string) $existing->source_request_idempotency_key) === strtolower((string) $contract['request_key']) && strtolower($existing->journal_idempotency_key) === $journalKey && $coverage && (bool) $coverage->integration_required && $coverage->decision_reason === 'integration_required' && $coverage->integration_activation_id === $existing->integration_activation_id && $coverage->terminal_activation_event_id === null && $activation && $activation->club_id === $contract['club_id'] && $activation->source_type === $contract['source_type'] && $activation->mapping_version === $existing->mapping_version && $activation->coverage_start_date->format('Y-m-d') <= $contract['date'] && $mapping && $mapping->club_id === $contract['club_id'] && $mapping->source_type === $contract['source_type'] && $mapping->canonical_source_key === $contract['source_key'] && $mapping->mapping_version === $existing->mapping_version && $mapping->effective_date->format('Y-m-d') <= $contract['date'] && (! $mapping->expires_at || $mapping->expires_at->format('Y-m-d') >= $contract['date']) && $period && $checksum && hash_equals($checksum, $existing->integration_checksum)
            && $journal->club_id === $contract['club_id'] && $journal->source_type === $contract['source_type'] && $journal->source_id === $contract['source_id'] && $journal->source_event === 'committed' && strtolower($journal->idempotency_key) === $journalKey && $journal->effective_date->format('Y-m-d') === $contract['date'] && $journal->reference === $existing->journal_reference && $journal->posted_by_user_id === $existing->committed_by_user_id && ($journal->status === ClubJournalEntry::STATUS_POSTED || ($contract['terminal'] && $journal->status === ClubJournalEntry::STATUS_REVERSED)) && ($journal->metadata['integration_checksum'] ?? null) === $existing->integration_checksum && ($journal->metadata['source_binding'] ?? null) === $existing->source_binding && $lines === $expectedLines;
        $valid = $valid && $terminalValid;
        if (! $valid) {
            throw new AccountingIntegrationConflictException('The accounting integration replay bindings changed.');
        }

        return $existing->load('journalEntry');
    }

    private function validTerminalReplay(ClubAccountingSourceIntegration $integration, ?ClubJournalEntry $original): bool
    {
        if (! $original || $original->status !== ClubJournalEntry::STATUS_REVERSED) {
            return false;
        }
        $reversals = ClubJournalEntry::query()->with(['lines' => fn ($query) => $query->orderBy('line_number')->orderBy('id')])->where('reversal_of_entry_id', $original->id)->get();
        $events = $integration->events()->where('event_type', 'integration_reversal')->get();
        if ($reversals->count() !== 1 || $events->count() !== 1) {
            return false;
        }
        $reversal = $reversals->first();
        $event = $events->first();
        $opposite = $original->lines->sortBy('line_number')->values()->map(fn ($line) => [$line->ledger_account_id, $line->direction === 'debit' ? 'credit' : 'debit', (int) $line->amount_paise])->all();
        $actual = $reversal->lines->map(fn ($line) => [$line->ledger_account_id, $line->direction, (int) $line->amount_paise])->all();
        $expectedKey = $this->journalKey($integration->club_id, $integration->source_type, $integration->source_identifier, 'reversed');

        return $reversal->club_id === $integration->club_id
            && $reversal->source_type === $integration->source_type
            && $reversal->source_id === $integration->source_identifier
            && $reversal->source_event === 'reversed'
            && strtolower($reversal->idempotency_key) === $expectedKey
            && $actual === $opposite
            && $event->reversal_journal_entry_id === $reversal->id
            && ($event->payload['reversal_journal_public_id'] ?? null) === $reversal->public_id
            && $event->reversal_reference === $reversal->reference
            && $event->reversal_effective_date?->format('Y-m-d') === $reversal->effective_date->format('Y-m-d')
            && $event->recorded_by_user_id === $reversal->posted_by_user_id
            && $event->reversal_reason === ($reversal->metadata['reversal_reason'] ?? null);
    }

    private function activation(int $clubId, string $sourceType, bool $lock): ?ClubAccountingIntegrationActivation
    {
        $query = ClubAccountingIntegrationActivation::query()->with('events')->where('club_id', $clubId)->where('source_type', $sourceType);
        if ($lock) {
            $query->lockForUpdate();
        }
        $rows = $query->get()->reject(fn ($row) => $row->events->whereIn('event_type', ['revoked', 'invalidated'])->isNotEmpty());
        if ($rows->count() > 1) {
            throw new AccountingIntegrationException('Accounting integration activation is ambiguous.');
        }

        return $rows->first();
    }

    private function mapping(ClubAccountingIntegrationActivation $activation, string $key, string $date, string $type): ClubAccountingSourceMapping
    {
        $rows = ClubAccountingSourceMapping::query()->with('events')->where('club_id', $activation->club_id)->where('source_type', $activation->source_type)->where('canonical_source_key', $key)->where('mapping_version', $activation->mapping_version)->where('effective_date', '<=', $date)->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', $date))->lockForUpdate()->get()->reject(fn ($row) => $row->events->whereIn('event_type', ['revoked', 'invalidated'])->isNotEmpty());
        if ($rows->count() !== 1) {
            throw new AccountingIntegrationException('One exact current accounting source mapping is required.');
        }
        $mapping = $rows->first();
        $account = LedgerAccount::query()->whereKey($mapping->ledger_account_id)->lockForUpdate()->first();
        if (! $account || ! $account->is_active || $account->club_id !== $activation->club_id || $account->currency !== 'INR' || $account->account_type !== $type || ($activation->source_type === AccountingIntegrationConfigurationService::SOURCE_PAYMENT && ! preg_match('/\A(?:CASH|BANK)-/', $account->code))) {
            throw new AccountingIntegrationException('The mapped ledger account is unavailable or invalid.');
        }

        return $mapping;
    }

    private function assertTerminalSource(int $clubId, string $sourceType, string $sourceId): void
    {
        $status = $sourceType === AccountingIntegrationConfigurationService::SOURCE_CHARGE
            ? ClubCharge::query()->where('club_id', $clubId)->where('public_id', $sourceId)->lockForUpdate()->value('status')
            : MemberPayment::query()->where('club_id', $clubId)->where('public_id', $sourceId)->lockForUpdate()->value('status');
        $required = $sourceType === AccountingIntegrationConfigurationService::SOURCE_CHARGE ? 'cancelled' : 'voided';
        if ($status !== $required) {
            throw new AccountingIntegrationException("The source must already be {$required} before reversal.");
        }
    }

    private function checksum(array $binding): string
    {
        return hash('sha256', json_encode($binding, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function journalKey(int $clubId, string $type, string $id, string $event): string
    {
        return $this->uuid(hash('sha256', "journal|{$clubId}|{$type}|{$id}|{$event}"));
    }

    private function uuid(string $hash): string
    {
        return substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-4'.substr($hash, 13, 3).'-8'.substr($hash, 17, 3).'-'.substr($hash, 20, 12);
    }

    private function requireOuterTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new AccountingIntegrationException('Source accounting must execute inside the authoritative source transaction.');
        }
    }

    private function recordCoverageDecision(int $clubId, string $sourceType, string $sourceId, string $accountingDate, User $actor): bool
    {
        $this->requireOuterTransaction();
        $existing = DB::table('club_accounting_source_coverage_decisions')->where('club_id', $clubId)->where('source_type', $sourceType)->where('source_identifier', $sourceId)->lockForUpdate()->first();
        if ($existing) {
            return (bool) $existing->integration_required;
        }
        $activation = ClubAccountingIntegrationActivation::query()->with('events')->where('club_id', $clubId)->where('source_type', $sourceType)->lockForUpdate()->first();
        $terminal = $activation?->events->whereIn('event_type', ['revoked', 'invalidated'])->sortBy('id')->first();
        if (! $activation) {
            [$required, $reason, $terminalId] = [false, 'not_activated', null];
        } elseif ($accountingDate < $activation->coverage_start_date->format('Y-m-d')) {
            [$required, $reason, $terminalId] = [false, 'before_coverage', null];
        } elseif ($terminal) {
            [$required, $reason, $terminalId] = [false, 'after_terminal_event', $terminal->id];
        } else {
            [$required, $reason, $terminalId] = [true, 'integration_required', null];
        }
        DB::table('club_accounting_source_coverage_decisions')->insert(['public_id' => (string) Str::orderedUuid(), 'club_id' => $clubId, 'source_type' => $sourceType, 'source_identifier' => $sourceId, 'accounting_date' => $accountingDate, 'integration_required' => $required, 'integration_activation_id' => $activation?->id, 'terminal_activation_event_id' => $terminalId, 'decision_reason' => $reason, 'decided_by_user_id' => $actor->id, 'decided_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        return $required;
    }

    private function coverageRequired(int $clubId, string $sourceType, string $sourceId, string $accountingDate): bool
    {
        $decision = DB::table('club_accounting_source_coverage_decisions')->where('club_id', $clubId)->where('source_type', $sourceType)->where('source_identifier', $sourceId)->lockForUpdate()->first();
        if ($decision) {
            return (bool) $decision->integration_required;
        }
        $activations = ClubAccountingIntegrationActivation::query()->where('club_id', $clubId)->where('source_type', $sourceType)->lockForUpdate()->get();
        if ($activations->isNotEmpty() && $activations->every(fn ($activation) => $accountingDate < $activation->coverage_start_date->format('Y-m-d'))) {
            return false;
        }
        throw new AccountingIntegrationException('Legacy source coverage is ambiguous without a persisted decision.');
    }
}
