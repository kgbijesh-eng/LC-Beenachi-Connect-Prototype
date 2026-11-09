<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\{Club, ClubAccountingPeriod, ClubJournalEntry, ClubJournalLine, ClubOrdinaryIncomeReceipt, ClubOrdinaryIncomeReceiptCorrection, LedgerAccount, User};
use App\Services\Finance\Exceptions\{AlreadyReversedJournalException, ClubOrdinaryIncomeReceiptConflictException, ClubOrdinaryIncomeReceiptCorrectionConflictException, ClubOrdinaryIncomeReceiptCorrectionException, JournalPostingException, JournalReversalException};
use Illuminate\Support\Facades\DB;
use App\Services\Access\OrganisationTransactionContext;
use App\Services\Access\OrganisationTransactionGuard;
use App\Services\Access\TransactionActorAuthorisation;
use Illuminate\Support\Str;
use Throwable;

final class ClubOrdinaryIncomeReceiptCorrectionService
{
    private ?array $replacementAdmission = null;

    public const SOURCE_TYPE = 'club_ordinary_income_receipt_correction';

    public function __construct(private readonly JournalReversalService $reversals, private readonly ClubOrdinaryIncomeReceiptService $receipts) {}

    public function correct(Club $club, string $receiptPublicId, User $actor, array $input): ClubOrdinaryIncomeReceipt
    {
        $receiptPublicId = strtolower(trim($receiptPublicId));
        $canonical = $this->canonical($receiptPublicId, $input);

        try {
            return app(OrganisationTransactionGuard::class)->run($club, function (OrganisationTransactionContext $context) use ($club, $receiptPublicId, $actor, $canonical): ClubOrdinaryIncomeReceipt {
                app(TransactionActorAuthorisation::class)->requirePermission($context, $club->id, $actor, 'finance.club.ordinary_income_receipts.reverse');
                $lockedClub = Club::query()->whereKey($club->id)->lockForUpdate()->firstOrFail();
                $receipt = ClubOrdinaryIncomeReceipt::query()->where('club_id', $lockedClub->id)->where('public_id', $receiptPublicId)->lockForUpdate()->firstOrFail();
                $existing = ClubOrdinaryIncomeReceiptCorrection::query()->where('club_id', $lockedClub->id)->where('idempotency_key', $canonical['idempotency_key'])->lockForUpdate()->first();
                if ($existing) {
                    if ($existing->original_receipt_id !== $receipt->id || ! hash_equals($existing->request_digest, $canonical['request_digest'])) throw new ClubOrdinaryIncomeReceiptCorrectionConflictException('The correction idempotency key conflicts with a changed request.', 'ORDINARY_INCOME_RECEIPT_CORRECTION_CHANGED_RETRY');
                    $this->assertReplayIntegrity($lockedClub, $receipt, $existing, $canonical);
                    return $this->loaded($receipt);
                }
                if (ClubOrdinaryIncomeReceiptCorrection::query()->where('club_id', $lockedClub->id)->where('original_receipt_id', $receipt->id)->lockForUpdate()->exists()) throw new ClubOrdinaryIncomeReceiptCorrectionConflictException('The ordinary income receipt has already been corrected.', 'ORDINARY_INCOME_RECEIPT_ALREADY_CORRECTED');

                $journal = ClubJournalEntry::query()->where('club_id', $lockedClub->id)->whereKey($receipt->journal_entry_id)->lockForUpdate()->first();
                $lines = $journal ? ClubJournalLine::query()->where('club_id', $lockedClub->id)->where('journal_entry_id', $journal->id)->orderBy('line_number')->orderBy('id')->lockForUpdate()->get() : collect();
                $receipt->load(['receivingAccount', 'incomeAccount', 'journalEntry.lines', 'correctedFrom']);
                try { $this->receipts->assertPersistedRequestProvenance($receipt); }
                catch (ClubOrdinaryIncomeReceiptConflictException $exception) { throw new ClubOrdinaryIncomeReceiptCorrectionException('The ordinary income receipt accounting provenance is invalid.', 'ORDINARY_INCOME_RECEIPT_ORIGINAL_PROVENANCE_INVALID', 422, $exception); }
                $this->assertOriginalIntegrity($lockedClub, $receipt, $journal, $lines);
                ['period' => $period, 'covered' => $covered] = $this->receipts->lockedPostingWindow($context, $lockedClub, $canonical['accounting_date']);
                if (! $period || $period->status !== ClubAccountingPeriod::STATUS_OPEN) throw new ClubOrdinaryIncomeReceiptCorrectionException('The reversal date must belong to an available open Lions Year period.', 'ORDINARY_INCOME_RECEIPT_CORRECTION_PERIOD_INVALID');
                if (! $covered) throw new ClubOrdinaryIncomeReceiptCorrectionException('Authoritative accounting is not active for the reversal date.', 'ORDINARY_INCOME_RECEIPT_CORRECTION_COVERAGE_MISSING');

                try { $reversal = $this->reversals->reverseWithin($context, new JournalReversalRequest(
                    clubId: $lockedClub->id, originalEntryId: $journal->id, reversalAccountingPeriodId: $period->id,
                    effectiveDate: $canonical['accounting_date'], reversalActorUserId: $actor->id, reason: $canonical['reason'],
                    sourceType: self::SOURCE_TYPE, sourceId: $receipt->public_id, sourceEvent: 'reversed', idempotencyKey: $canonical['idempotency_key'],
                )); } catch (AlreadyReversedJournalException $exception) { throw new ClubOrdinaryIncomeReceiptCorrectionConflictException('The ordinary income receipt has already been corrected.', 'ORDINARY_INCOME_RECEIPT_ALREADY_CORRECTED', $exception); }
                catch (JournalReversalException|JournalPostingException $exception) { throw new ClubOrdinaryIncomeReceiptCorrectionException('The correction reversal could not be posted.', 'ORDINARY_INCOME_RECEIPT_CORRECTION_POSTING_FAILED', 422, $exception); }
                $correction = ClubOrdinaryIncomeReceiptCorrection::create([
                    'club_id' => $lockedClub->id, 'original_receipt_id' => $receipt->id, 'original_journal_entry_id' => $journal->id,
                    'reversal_journal_entry_id' => $reversal->id, 'accounting_date' => $canonical['accounting_date'], 'reason' => $canonical['reason'],
                    'idempotency_key' => $canonical['idempotency_key'], 'request_digest' => $canonical['request_digest'], 'created_by_user_id' => $actor->id,
                ]);
                if ($canonical['replacement'] !== null) {
                    $replacement = $canonical['replacement'];
                    $replacement['idempotency_key'] = (string) Str::uuid();
                    $replacement['corrected_from_receipt'] = $receipt->public_id;
                    $this->replacementAdmission = [$context, $actor->id, $replacement];
                    try {
                        $replacementReceipt = $this->receipts->recordWithin($context, $lockedClub, $actor, $replacement, $this);
                    } finally {
                        $this->replacementAdmission = null;
                    }
                    $correction->bindReplacementDuringConstruction($replacementReceipt);
                }
                return $this->loaded($receipt);
            }, 3);
        } catch (ClubOrdinaryIncomeReceiptCorrectionException|ClubOrdinaryIncomeReceiptConflictException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            if ($this->isConstraintConflict($exception)) throw new ClubOrdinaryIncomeReceiptCorrectionConflictException('The correction conflicts with another committed request.', 'ORDINARY_INCOME_RECEIPT_CORRECTION_CONCURRENCY_CONFLICT', $exception);
            throw $exception;
        }
    }

    public function assertReplacementAdmission(OrganisationTransactionContext $context, User $actor, array $input): void
    {
        if ($this->replacementAdmission !== [$context, $actor->id, $input]) {
            throw new \Illuminate\Auth\Access\AuthorizationException();
        }
    }

    private function canonical(string $receiptPublicId, array $input): array
    {
        $nullable = fn (array $source, string $key) => ($value = trim((string) ($source[$key] ?? ''))) === '' ? null : $value;
        $replacement = isset($input['replacement']) && is_array($input['replacement']) ? $input['replacement'] : null;
        if ($replacement !== null) {
            $replacement = [
                'subtype' => strtolower(trim((string) ($replacement['subtype'] ?? ''))), 'accounting_date' => (string) ($replacement['accounting_date'] ?? ''),
                'receiving_account' => strtolower(trim((string) ($replacement['receiving_account'] ?? ''))), 'income_account' => strtolower(trim((string) ($replacement['income_account'] ?? ''))),
                'fund' => (string) ($replacement['fund'] ?? ''), 'amount_paise' => (int) ($replacement['amount_paise'] ?? 0), 'description' => trim((string) ($replacement['description'] ?? '')),
                'payer_name' => $nullable($replacement, 'payer_name'), 'external_reference' => $nullable($replacement, 'external_reference'), 'event_label' => $nullable($replacement, 'event_label'),
            ];
        }
        $canonical = ['original_receipt' => $receiptPublicId, 'accounting_date' => (string) ($input['accounting_date'] ?? ''), 'reason' => trim((string) ($input['reason'] ?? '')), 'mode' => $replacement === null ? 'reversal_only' : 'reversal_plus_replacement', 'replacement' => $replacement, 'idempotency_key' => strtolower(trim((string) ($input['idempotency_key'] ?? '')))];
        if (! Str::isUuid($receiptPublicId) || ! Str::isUuid($canonical['idempotency_key']) || $canonical['reason'] === '' || mb_strlen($canonical['reason']) > 1000 || str_contains($canonical['reason'], "\0")) throw new ClubOrdinaryIncomeReceiptCorrectionException('The ordinary income receipt correction request is invalid.', 'ORDINARY_INCOME_RECEIPT_CORRECTION_VALIDATION_FAILED');
        $canonical['request_digest'] = hash('sha256', json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        return $canonical;
    }

    private function assertOriginalIntegrity(Club $club, ClubOrdinaryIncomeReceipt $receipt, ?ClubJournalEntry $journal, $lines): void
    {
        $receiving = $receipt->receivingAccount; $income = $receipt->incomeAccount;
        $valid = $journal && $receiving && $income && $receipt->club_id === $club->id && $receipt->currency === LedgerAccount::CURRENCY_INR && $receipt->amount_paise > 0
            && $receiving->club_id === $club->id && $income->club_id === $club->id
            && $receiving->currency === $receipt->currency && $income->currency === $receipt->currency
            && $receiving->account_type === LedgerAccount::TYPE_ASSET && $receiving->statement_classification === StatementClassification::CASH_AND_BANK->value
            && $income->account_type === LedgerAccount::TYPE_INCOME && $income->statement_classification === StatementClassification::INCOME->value
            && $receipt->journal_entry_id === $journal->id && $journal->club_id === $club->id && $journal->source_type === ClubOrdinaryIncomeReceiptService::SOURCE_TYPE
            && $journal->source_id === $receipt->public_id && $journal->source_event === 'recorded' && $journal->status === ClubJournalEntry::STATUS_POSTED
            && $journal->reversal_of_entry_id === null && $journal->currency === LedgerAccount::CURRENCY_INR && strtolower((string) $journal->idempotency_key) === strtolower((string) $receipt->idempotency_key)
            && $lines->count() === 2 && $lines[0]->line_number === 1 && $lines[1]->line_number === 2
            && $lines[0]->club_id === $club->id && $lines[1]->club_id === $club->id
            && $lines[0]->ledger_account_id === $receipt->receiving_account_id && $lines[0]->direction === ClubJournalLine::DIRECTION_DEBIT
            && $lines[1]->ledger_account_id === $receipt->income_account_id && $lines[1]->direction === ClubJournalLine::DIRECTION_CREDIT
            && $lines[0]->amount_paise === $receipt->amount_paise && $lines[1]->amount_paise === $receipt->amount_paise
            && $lines[0]->fund_code === $receipt->fund_code && $lines[1]->fund_code === $receipt->fund_code;
        if (! $valid) throw new ClubOrdinaryIncomeReceiptCorrectionException('The ordinary income receipt accounting provenance is invalid.', 'ORDINARY_INCOME_RECEIPT_ORIGINAL_PROVENANCE_INVALID');
    }

    private function assertReplayIntegrity(Club $club, ClubOrdinaryIncomeReceipt $receipt, ClubOrdinaryIncomeReceiptCorrection $correction, array $canonical): void
    {
        $original = ClubJournalEntry::query()->where('club_id', $club->id)->whereKey($correction->original_journal_entry_id)->lockForUpdate()->first();
        $originalLines = $original ? ClubJournalLine::query()->where('club_id', $club->id)->where('journal_entry_id', $original->id)->orderBy('line_number')->lockForUpdate()->get() : collect();
        $receipt->load(['receivingAccount', 'incomeAccount', 'correctedFrom']);
        try { $this->receipts->assertPersistedRequestProvenance($receipt); }
        catch (ClubOrdinaryIncomeReceiptConflictException $exception) { throw new ClubOrdinaryIncomeReceiptCorrectionException('The stored correction provenance is invalid.', 'ORDINARY_INCOME_RECEIPT_CORRECTION_PROVENANCE_INVALID', 422, $exception); }
        if (! $original || $original->status !== ClubJournalEntry::STATUS_REVERSED) throw new ClubOrdinaryIncomeReceiptCorrectionException('The stored correction provenance is invalid.', 'ORDINARY_INCOME_RECEIPT_CORRECTION_PROVENANCE_INVALID');
        $original->status = ClubJournalEntry::STATUS_POSTED;
        $this->assertOriginalIntegrity($club, $receipt, $original, $originalLines);
        $reversal = ClubJournalEntry::query()->where('club_id', $club->id)->whereKey($correction->reversal_journal_entry_id)->lockForUpdate()->first();
        $reversalLines = $reversal ? ClubJournalLine::query()->where('club_id', $club->id)->where('journal_entry_id', $reversal->id)->orderBy('line_number')->lockForUpdate()->get() : collect();
        $reversalPeriod = $reversal ? ClubAccountingPeriod::query()->whereKey($reversal->accounting_period_id)->lockForUpdate()->first() : null;
        $expectedDescription = sprintf('Reversal of %s: %s', $original->reference, $canonical['reason']);
        $valid = Str::isUuid((string) $correction->public_id) && $correction->created_at !== null && $correction->updated_at !== null
            && $correction->club_id === $club->id && $correction->original_receipt_id === $receipt->id && $correction->original_journal_entry_id === $receipt->journal_entry_id
            && $correction->accounting_date->format('Y-m-d') === $canonical['accounting_date'] && $correction->reason === $canonical['reason']
            && $correction->created_by_user_id > 0 && strtolower((string) $correction->idempotency_key) === $canonical['idempotency_key'] && hash_equals($correction->request_digest, $canonical['request_digest'])
            && $reversal && $reversal->club_id === $club->id && $reversal->reversal_of_entry_id === $original->id && $reversal->source_type === self::SOURCE_TYPE
            && $reversal->source_id === $receipt->public_id && $reversal->source_event === 'reversed' && $reversal->status === ClubJournalEntry::STATUS_POSTED
            && $reversal->effective_date->format('Y-m-d') === $correction->accounting_date->format('Y-m-d') && $reversalPeriod && $reversalPeriod->club_id === $club->id
            && $reversal->effective_date->format('Y-m-d') >= $reversalPeriod->start_date->format('Y-m-d') && $reversal->effective_date->format('Y-m-d') <= $reversalPeriod->end_date->format('Y-m-d')
            && $reversal->posted_by_user_id === $correction->created_by_user_id && $reversal->description === $expectedDescription
            && $reversal->currency === LedgerAccount::CURRENCY_INR && ($reversal->metadata['reversal_reason'] ?? null) === $correction->reason
            && ($reversal->metadata['reversal_original_entry_public_id'] ?? null) === $original->public_id
            && strtolower((string) $reversal->idempotency_key) === strtolower((string) $correction->idempotency_key) && $reversalLines->count() === 2;
        foreach ($reversalLines as $index => $line) { $source = $originalLines[$index] ?? null; $opposite = $source?->direction === ClubJournalLine::DIRECTION_DEBIT ? ClubJournalLine::DIRECTION_CREDIT : ClubJournalLine::DIRECTION_DEBIT; $valid = $valid && $source && $line->line_number === $source->line_number && $line->club_id === $club->id && $line->ledger_account_id === $source->ledger_account_id && $line->amount_paise === $source->amount_paise && $line->fund_code === $source->fund_code && $line->direction === $opposite && $line->reversal_of_line_id === $source->id; }
        if ($correction->replacement_receipt_id !== null) {
            $replacement = ClubOrdinaryIncomeReceipt::query()->whereKey($correction->replacement_receipt_id)->lockForUpdate()->first()?->load(['receivingAccount', 'incomeAccount', 'journalEntry.lines', 'correctedFrom']);
            $expected = $canonical['replacement'];
            $valid = $valid && $expected !== null && $replacement && $replacement->club_id === $club->id && $replacement->corrected_from_receipt_id === $receipt->id && $replacement->journal_entry_id !== $receipt->journal_entry_id
                && $replacement->subtype === $expected['subtype'] && $replacement->accounting_date->format('Y-m-d') === $expected['accounting_date']
                && strtolower($replacement->receivingAccount->public_id) === $expected['receiving_account'] && strtolower($replacement->incomeAccount->public_id) === $expected['income_account']
                && $replacement->fund_code === $expected['fund'] && $replacement->currency === LedgerAccount::CURRENCY_INR && $replacement->amount_paise === $expected['amount_paise']
                && $replacement->description === $expected['description'] && $replacement->payer_name === $expected['payer_name'] && $replacement->external_reference === $expected['external_reference'] && $replacement->event_label === $expected['event_label'];
            if ($replacement) { try { $this->receipts->assertPersistedProvenance($replacement); } catch (ClubOrdinaryIncomeReceiptConflictException $exception) { throw new ClubOrdinaryIncomeReceiptCorrectionException('The stored replacement provenance is invalid.', 'ORDINARY_INCOME_RECEIPT_CORRECTION_PROVENANCE_INVALID', 422, $exception); } }
        }
        $valid = $valid && (($canonical['replacement'] === null) === ($correction->replacement_receipt_id === null));
        if (! $valid) throw new ClubOrdinaryIncomeReceiptCorrectionException('The stored correction provenance is invalid.', 'ORDINARY_INCOME_RECEIPT_CORRECTION_PROVENANCE_INVALID');
    }

    private function loaded(ClubOrdinaryIncomeReceipt $receipt): ClubOrdinaryIncomeReceipt
    {
        return $receipt->fresh()->load(['receivingAccount', 'incomeAccount', 'journalEntry.lines.ledgerAccount', 'creator', 'correction.reversalJournal.lines.ledgerAccount', 'correction.creator', 'correction.replacementReceipt.receivingAccount', 'correction.replacementReceipt.incomeAccount', 'correction.replacementReceipt.journalEntry', 'correctedFrom']);
    }

    private function isConstraintConflict(Throwable $exception): bool { return $exception instanceof \Illuminate\Database\QueryException && in_array((string) $exception->getCode(), ['23000', '23505'], true); }
}
