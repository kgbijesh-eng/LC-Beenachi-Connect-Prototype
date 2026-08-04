<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\ClubAccountingIntegrationActivation;
use App\Models\ClubAccountingPeriod;
use App\Models\ClubAccountingSourceIntegration;
use App\Models\ClubCharge;
use App\Models\ClubJournalEntry;
use App\Models\LedgerAccount;
use App\Models\MemberPayment;
use Illuminate\Support\Facades\DB;

final class AccountingIntegrationReconciliationService
{
    public function __construct(private SourceAccountingIntegrationService $sources) {}

    public function defects(int $clubId): array
    {
        $rows = ClubAccountingSourceIntegration::query()->with(['activation.events', 'mapping.events', 'mapping.ledgerAccount', 'journalEntry' => fn ($q) => $q->with(['lines' => fn ($lines) => $lines->orderBy('line_number')->orderBy('id')])])->where('club_id', $clubId)->get();
        $defects = collect();

        $this->inspectCoverageDecisions($clubId, $defects);
        $this->inspectSources($clubId, $defects);

        ClubJournalEntry::query()->where('club_id', $clubId)->whereIn('source_type', [AccountingIntegrationConfigurationService::SOURCE_CHARGE, AccountingIntegrationConfigurationService::SOURCE_PAYMENT])->whereNull('reversal_of_entry_id')->each(function ($journal) use ($rows, $defects): void {
            if (! $rows->contains(fn ($row) => $row->journal_entry_id === $journal->id)) {
                $this->add($defects, 'journal_without_source_link', $journal->public_id);
            }
        });

        $rows->each(fn (ClubAccountingSourceIntegration $row) => $this->inspect($row, $defects));

        ClubJournalEntry::query()->where('club_id', $clubId)->whereNotNull('reversal_of_entry_id')->whereIn('source_type', [AccountingIntegrationConfigurationService::SOURCE_CHARGE, AccountingIntegrationConfigurationService::SOURCE_PAYMENT])->each(function ($reversal) use ($rows, $defects): void {
            if (! $rows->contains(fn ($row) => $row->journal_entry_id === $reversal->reversal_of_entry_id)) {
                $this->add($defects, 'unrelated_reversal_journal', $reversal->public_id);
            }
        });

        return $defects->values()->all();
    }

    private function inspect(ClubAccountingSourceIntegration $row, $defects): void
    {
        $journal = $row->journalEntry;
        $source = $row->source_type === AccountingIntegrationConfigurationService::SOURCE_CHARGE
            ? ClubCharge::query()->with(['memberCharges' => fn ($q) => $q->orderBy('id')])->where('public_id', $row->source_identifier)->first()
            : MemberPayment::query()->with(['allocations' => fn ($q) => $q->orderBy('id')])->where('public_id', $row->source_identifier)->first();
        if (! $source) {
            $this->add($defects, 'missing_source', $row->public_id);

            return;
        }
        if ($source->club_id !== $row->club_id) {
            $this->add($defects, 'cross_club_source_identity', $row->public_id);
        }
        $binding = $row->source_type === AccountingIntegrationConfigurationService::SOURCE_CHARGE ? $this->sources->chargeBinding($source, $row->source_version) : $this->sources->paymentBinding($source, $row->source_version);
        $sourceDate = $row->source_type === AccountingIntegrationConfigurationService::SOURCE_CHARGE ? $source->issued_at?->toDateString() : $source->payment_date->format('Y-m-d');
        $sourceAmount = $row->source_type === AccountingIntegrationConfigurationService::SOURCE_CHARGE ? (int) $source->memberCharges->sum('original_amount_paise') : (int) $source->amount_paise;
        $sourceKey = $row->source_type === AccountingIntegrationConfigurationService::SOURCE_CHARGE ? $source->category : $source->payment_mode;
        if ($binding !== $row->source_binding) {
            $this->add($defects, 'canonical_source_binding_mismatch', $row->public_id);
        }
        if ($row->source_type === AccountingIntegrationConfigurationService::SOURCE_PAYMENT && strtolower((string) $row->source_request_idempotency_key) !== ($binding['source_request_idempotency_key'] ?? null)) {
            $this->add($defects, 'source_request_idempotency_mismatch', $row->public_id);
        }
        if ($row->source_type === AccountingIntegrationConfigurationService::SOURCE_CHARGE && $row->source_request_idempotency_key !== null) {
            $this->add($defects, 'unexpected_charge_request_idempotency', $row->public_id);
        }
        if ($sourceDate !== $row->accounting_date->format('Y-m-d')) {
            $this->add($defects, 'source_accounting_date_mismatch', $row->public_id);
        }
        if ($sourceAmount !== $row->source_amount_paise) {
            $this->add($defects, 'source_amount_mismatch', $row->public_id);
        }
        if ($sourceKey !== $row->canonical_source_key) {
            $this->add($defects, 'canonical_source_key_mismatch', $row->public_id);
        }
        if ($source->lock_version < $row->source_version) {
            $this->add($defects, 'source_version_mismatch', $row->public_id);
        }

        if (! $journal || $journal->club_id !== $row->club_id) {
            $this->add($defects, 'invalid_journal_link', $row->public_id);

            return;
        }
        $terminal = $row->source_type === AccountingIntegrationConfigurationService::SOURCE_CHARGE ? $source->status === 'cancelled' : $source->status === 'voided';
        if ($journal->source_type !== $row->source_type || $journal->source_id !== $row->source_identifier || $journal->source_event !== 'committed' || $journal->effective_date->format('Y-m-d') !== $row->accounting_date->format('Y-m-d') || $journal->currency !== 'INR' || $journal->reference !== $row->journal_reference || strtolower($journal->idempotency_key) !== strtolower($row->journal_idempotency_key) || $journal->posted_by_user_id !== $row->committed_by_user_id || (! $terminal && $journal->status !== ClubJournalEntry::STATUS_POSTED) || ($terminal && $journal->status !== ClubJournalEntry::STATUS_REVERSED)) {
            $this->add($defects, 'journal_binding_mismatch', $row->public_id);
        }
        $metadata = $journal->metadata ?? [];
        if (($metadata['mapping_version'] ?? null) !== $row->mapping_version || ($metadata['integration_checksum'] ?? null) !== $row->integration_checksum || ($metadata['source_binding'] ?? null) !== $row->source_binding) {
            $this->add($defects, 'journal_metadata_mismatch', $row->public_id);
        }

        $activation = $row->activation;
        $mapping = $row->mapping;
        $coverage = DB::table('club_accounting_source_coverage_decisions')->where('club_id', $row->club_id)->where('source_type', $row->source_type)->where('source_identifier', $row->source_identifier)->first();
        if (! $coverage || ! $coverage->integration_required || $coverage->decision_reason !== 'integration_required' || $coverage->integration_activation_id !== $row->integration_activation_id || $coverage->terminal_activation_event_id !== null || $coverage->club_id !== $row->club_id || $coverage->source_type !== $row->source_type || $coverage->accounting_date !== $row->accounting_date->format('Y-m-d')) {
            $this->add($defects, 'coverage_decision_mismatch', $row->public_id);
        }
        if (! $activation || $activation->club_id !== $row->club_id || $activation->source_type !== $row->source_type || $activation->mapping_version !== $row->mapping_version || $activation->coverage_start_date->gt($row->accounting_date)) {
            $this->add($defects, 'historical_activation_mismatch', $row->public_id);
        }
        if (! $mapping || $mapping->club_id !== $row->club_id || $mapping->source_type !== $row->source_type || $mapping->canonical_source_key !== $row->canonical_source_key || $mapping->mapping_version !== $row->mapping_version || $mapping->effective_date->gt($row->accounting_date) || ($mapping->expires_at && $mapping->expires_at->lt($row->accounting_date))) {
            $this->add($defects, 'historical_mapping_mismatch', $row->public_id);
        }

        $period = ClubAccountingPeriod::query()->find($journal->accounting_period_id);
        if (! $period || $period->club_id !== $row->club_id || $row->accounting_date->lt($period->start_date) || $row->accounting_date->gt($period->end_date)) {
            $this->add($defects, 'wrong_accounting_period', $row->public_id);
        }
        $expectedChecksum = $mapping && $period ? hash('sha256', json_encode(['club_id' => $row->club_id, 'source_type' => $row->source_type, 'source_public_id' => $row->source_identifier, 'accounting_date' => $row->accounting_date->format('Y-m-d'), 'source_binding' => $row->source_binding, 'activation_id' => $row->integration_activation_id, 'mapping_id' => $row->source_mapping_id, 'mapping_version' => $row->mapping_version, 'mapped_account_id' => $mapping->ledger_account_id, 'accounting_period_id' => $period->id, 'journal_idempotency_key' => $row->journal_idempotency_key], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)) : null;
        if (! $expectedChecksum || ! hash_equals($expectedChecksum, $row->integration_checksum)) {
            $this->add($defects, 'integration_checksum_mismatch', $row->public_id);
        }

        $debits = $journal->lines->where('direction', 'debit')->values();
        $credits = $journal->lines->where('direction', 'credit')->values();
        $receivable = LedgerAccount::query()->where('club_id', $row->club_id)->where('code', 'MEMBER-RECEIVABLES')->value('id');
        $expectedDebit = $row->source_type === AccountingIntegrationConfigurationService::SOURCE_CHARGE ? $receivable : $mapping?->ledger_account_id;
        $expectedCredit = $row->source_type === AccountingIntegrationConfigurationService::SOURCE_CHARGE ? $mapping?->ledger_account_id : $receivable;
        $actualLines = $journal->lines->map(fn ($line) => [$line->line_number, $line->ledger_account_id, $line->direction, (int) $line->amount_paise])->values()->all();
        $expectedLines = [[1, $expectedDebit, 'debit', $row->source_amount_paise], [2, $expectedCredit, 'credit', $row->source_amount_paise]];
        if ($debits->count() !== 1 || $credits->count() !== 1 || $actualLines !== $expectedLines) {
            $this->add($defects, 'journal_lines_mismatch', $row->public_id);
        }

        if (DB::table('club_accounting_source_integrations')->where('club_id', $row->club_id)->where('source_type', $row->source_type)->where('source_identifier', $row->source_identifier)->count() !== 1 || DB::table('club_accounting_source_integrations')->where('journal_entry_id', $row->journal_entry_id)->count() !== 1) {
            $this->add($defects, 'duplicated_linkage', $row->public_id);
        }
        $success = $row->events()->where('event_type', 'integration_success')->get();
        if ($success->count() !== 1 || $success->first()->recorded_by_user_id !== $row->committed_by_user_id || ($success->first()->payload['journal_entry_id'] ?? null) !== $journal->id || ($success->first()->payload['journal_public_id'] ?? null) !== $journal->public_id || ($success->first()->payload['journal_reference'] ?? null) !== $journal->reference || ($success->first()->payload['checksum'] ?? null) !== $row->integration_checksum) {
            $this->add($defects, 'integration_success_event_mismatch', $row->public_id);
        }

        $this->inspectReversal($row, $journal, $terminal, $defects);
    }

    private function inspectReversal(ClubAccountingSourceIntegration $row, ClubJournalEntry $original, bool $terminal, $defects): void
    {
        $reversals = ClubJournalEntry::query()->with(['lines' => fn ($q) => $q->orderBy('line_number')->orderBy('id')])->where('reversal_of_entry_id', $original->id)->get();
        $events = $row->events()->where('event_type', 'integration_reversal')->get();
        if ($terminal && ($reversals->count() !== 1 || $events->count() !== 1)) {
            $this->add($defects, 'missing_required_reversal', $row->public_id);

            return;
        }
        if (! $terminal && ($reversals->isNotEmpty() || $events->isNotEmpty())) {
            $this->add($defects, 'orphan_reversal_journal', $row->public_id);

            return;
        }
        if (! $terminal) {
            return;
        }
        $reversal = $reversals->first();
        $event = $events->first();
        $opposite = $original->lines->map(fn ($line) => [$line->ledger_account_id, $line->direction === 'debit' ? 'credit' : 'debit', (int) $line->amount_paise])->values()->all();
        $actual = $reversal->lines->map(fn ($line) => [$line->ledger_account_id, $line->direction, (int) $line->amount_paise])->values()->all();
        if ($reversal->source_type !== $row->source_type || $reversal->source_id !== $row->source_identifier || $reversal->source_event !== 'reversed' || $reversal->reversal_of_entry_id !== $original->id || $actual !== $opposite) {
            $this->add($defects, 'invalid_reversal_journal', $row->public_id);
        }
        $expectedKey = $this->journalKey($row->club_id, $row->source_type, $row->source_identifier, 'reversed');
        if ($event->reversal_journal_entry_id !== $reversal->id || ($event->payload['reversal_journal_public_id'] ?? null) !== $reversal->public_id || $event->reversal_reference !== $reversal->reference || $event->reversal_effective_date?->format('Y-m-d') !== $reversal->effective_date->format('Y-m-d') || $event->recorded_by_user_id !== $reversal->posted_by_user_id || $event->reversal_reason !== ($reversal->metadata['reversal_reason'] ?? null) || strtolower($reversal->idempotency_key) !== $expectedKey) {
            $this->add($defects, 'integration_reversal_event_mismatch', $row->public_id);
        }
    }

    private function add($defects, string $type, string $identity): void
    {
        $defects->push(['type' => $type, 'identity' => $identity]);
    }

    private function inspectCoverageDecisions(int $clubId, $defects): void
    {
        foreach (DB::table('club_accounting_source_coverage_decisions')->where('club_id', $clubId)->get() as $decision) {
            $source = $decision->source_type === AccountingIntegrationConfigurationService::SOURCE_CHARGE
                ? ClubCharge::query()->where('public_id', $decision->source_identifier)->first()
                : MemberPayment::query()->where('public_id', $decision->source_identifier)->first();
            if (! $source) {
                $this->add($defects, 'coverage_decision_source_missing', $decision->public_id);

                continue;
            }
            $date = $decision->source_type === AccountingIntegrationConfigurationService::SOURCE_CHARGE ? $source->issued_at?->toDateString() : $source->payment_date->format('Y-m-d');
            if ($source->club_id !== $decision->club_id || $date !== $decision->accounting_date) {
                $this->add($defects, 'coverage_decision_source_mismatch', $decision->public_id);
            }
            $integrations = ClubAccountingSourceIntegration::query()->with('journalEntry')->where('club_id', $decision->club_id)->where('source_type', $decision->source_type)->where('source_identifier', $decision->source_identifier)->get();
            if ($decision->integration_required) {
                if ($integrations->count() !== 1) {
                    $this->add($defects, 'covered_source_without_integration', $decision->public_id);
                } elseif (! $integrations->first()->journalEntry) {
                    $this->add($defects, 'covered_source_without_journal', $decision->public_id);
                } elseif ($integrations->first()->integration_activation_id !== $decision->integration_activation_id || $decision->terminal_activation_event_id !== null) {
                    $this->add($defects, 'covered_source_activation_mismatch', $decision->public_id);
                }
            } elseif ($integrations->isNotEmpty()) {
                $this->add($defects, 'outside_coverage_source_has_integration', $decision->public_id);
            }
        }
    }

    private function inspectSources(int $clubId, $defects): void
    {
        foreach ([[AccountingIntegrationConfigurationService::SOURCE_CHARGE, ClubCharge::query()->where('club_id', $clubId)->whereNotNull('issued_at')->whereIn('status', ['issued', 'closed', 'cancelled'])->get()], [AccountingIntegrationConfigurationService::SOURCE_PAYMENT, MemberPayment::query()->where('club_id', $clubId)->whereIn('status', ['recorded', 'voided'])->get()]] as [$type, $sources]) {
            $activations = ClubAccountingIntegrationActivation::query()->where('club_id', $clubId)->where('source_type', $type)->get();
            foreach ($sources as $source) {
                $date = $type === AccountingIntegrationConfigurationService::SOURCE_CHARGE ? $source->issued_at?->toDateString() : $source->payment_date->format('Y-m-d');
                $decision = DB::table('club_accounting_source_coverage_decisions')->where('club_id', $clubId)->where('source_type', $type)->where('source_identifier', $source->public_id)->first();
                if (! $decision && ! ($activations->isNotEmpty() && $activations->every(fn ($activation) => $date < $activation->coverage_start_date->format('Y-m-d')))) {
                    $this->add($defects, 'source_without_coverage_decision', $source->public_id);
                } elseif ($decision && ($decision->accounting_date !== $date || $decision->source_identifier !== $source->public_id || $decision->source_type !== $type)) {
                    $this->add($defects, 'source_coverage_decision_mismatch', $source->public_id);
                }
            }
        }
    }

    private function journalKey(int $clubId, string $type, string $id, string $event): string
    {
        $hash = hash('sha256', "journal|{$clubId}|{$type}|{$id}|{$event}");

        return substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-4'.substr($hash, 13, 3).'-8'.substr($hash, 17, 3).'-'.substr($hash, 20, 12);
    }
}
