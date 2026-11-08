<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Club;
use App\Models\ClubAccountingPeriod;
use App\Models\ClubExpense;
use App\Models\ClubExpenseReversal;
use App\Models\ClubJournalEntry;
use App\Models\ClubJournalLine;
use App\Models\User;
use App\Services\Finance\Exceptions\ClubExpenseReversalConflictException;
use App\Services\Finance\Exceptions\ClubExpenseReversalException;
use Illuminate\Support\Facades\DB;
use App\Services\Access\OrganisationTransactionContext;
use App\Services\Access\OrganisationTransactionGuard;
use Illuminate\Support\Str;

final class ClubExpenseReversalService
{
    public const SOURCE_TYPE = 'club_expense_reversal';

    public function __construct(private readonly JournalReversalService $reversals) {}

    public function reverse(Club $club, string $expensePublicId, User $actor, array $input): ClubExpense
    {
        $canonical = $this->canonical($input);
        return app(OrganisationTransactionGuard::class)->run($club, function (OrganisationTransactionContext $context) use ($club, $expensePublicId, $actor, $canonical): ClubExpense {
            $lockedClub = Club::query()->whereKey($club->id)->lockForUpdate()->firstOrFail();
            $expense = ClubExpense::query()->where('club_id', $lockedClub->id)->where('public_id', strtolower(trim($expensePublicId)))->lockForUpdate()->firstOrFail();
            $existingByKey = ClubExpenseReversal::query()->where('club_id', $lockedClub->id)->where('idempotency_key', $canonical['idempotency_key'])->lockForUpdate()->first();
            if ($existingByKey) {
                if ($existingByKey->club_expense_id !== $expense->id || ! hash_equals($existingByKey->request_digest, $canonical['request_digest'])) {
                    throw new ClubExpenseReversalConflictException('The reversal idempotency key conflicts with a changed request.');
                }
                $this->assertReplayIntegrity($lockedClub, $expense, $existingByKey);
                return $this->loaded($expense);
            }
            if (ClubExpenseReversal::query()->where('club_id', $lockedClub->id)->where('club_expense_id', $expense->id)->lockForUpdate()->exists()) {
                throw new ClubExpenseReversalConflictException('The expense has already been reversed.');
            }

            $journal = ClubJournalEntry::query()->where('club_id', $lockedClub->id)->whereKey($expense->journal_entry_id)->lockForUpdate()->first();
            $lines = $journal ? ClubJournalLine::query()->where('club_id', $lockedClub->id)->where('journal_entry_id', $journal->id)->orderBy('line_number')->lockForUpdate()->get() : collect();
            $this->assertIntegrity($lockedClub, $expense, $journal, $lines);
            $period = ClubAccountingPeriod::query()->where('club_id', $lockedClub->id)->where('start_date', '<=', $canonical['accounting_date'])->where('end_date', '>=', $canonical['accounting_date'])->lockForUpdate()->first();
            if (! $period || $period->status !== ClubAccountingPeriod::STATUS_OPEN) {
                throw new ClubExpenseReversalException('The reversal date must belong to an available open Lions Year period.');
            }

            $reversalJournal = $this->reversals->reverseWithin($context, new JournalReversalRequest(
                clubId: $lockedClub->id, originalEntryId: $journal->id, reversalAccountingPeriodId: $period->id,
                effectiveDate: $canonical['accounting_date'], reversalActorUserId: $actor->id,
                reason: $canonical['reason'], sourceType: self::SOURCE_TYPE, sourceId: $expense->public_id,
                sourceEvent: 'reversed', idempotencyKey: $canonical['idempotency_key'],
            ));
            ClubExpenseReversal::create([
                'club_id' => $lockedClub->id, 'club_expense_id' => $expense->id,
                'reversal_journal_entry_id' => $reversalJournal->id, 'accounting_date' => $canonical['accounting_date'],
                'reason' => $canonical['reason'], 'idempotency_key' => $canonical['idempotency_key'],
                'request_digest' => $canonical['request_digest'], 'created_by_user_id' => $actor->id,
            ]);
            return $this->loaded($expense);
        }, 3);
    }

    private function canonical(array $input): array
    {
        $canonical = ['accounting_date' => (string) $input['accounting_date'], 'reason' => trim((string) $input['reason']), 'idempotency_key' => strtolower(trim((string) $input['idempotency_key']))];
        if (! Str::isUuid($canonical['idempotency_key']) || $canonical['reason'] === '' || mb_strlen($canonical['reason']) > 1000 || str_contains($canonical['reason'], "\0")) throw new ClubExpenseReversalException('The expense reversal request is invalid.');
        $canonical['request_digest'] = hash('sha256', json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        return $canonical;
    }

    private function assertIntegrity(Club $club, ClubExpense $expense, ?ClubJournalEntry $journal, $lines): void
    {
        if (! $journal || $journal->club_id !== $club->id || $journal->source_type !== ClubExpenseRecordingService::SOURCE_TYPE || $journal->source_id !== $expense->public_id || $journal->source_event !== 'recorded' || $journal->status !== ClubJournalEntry::STATUS_POSTED || $journal->reversal_of_entry_id !== null || $lines->count() !== 2) throw new ClubExpenseReversalException('The expense accounting provenance is invalid.');
        $debit = $lines->firstWhere('direction', ClubJournalLine::DIRECTION_DEBIT); $credit = $lines->firstWhere('direction', ClubJournalLine::DIRECTION_CREDIT);
        if (! $debit || ! $credit || $debit->club_id !== $club->id || $credit->club_id !== $club->id || $debit->ledger_account_id !== $expense->expense_account_id || $credit->ledger_account_id !== $expense->payment_account_id || $debit->amount_paise !== $expense->amount_paise || $credit->amount_paise !== $expense->amount_paise || $debit->fund_code !== $expense->fund_code || $credit->fund_code !== $expense->fund_code) throw new ClubExpenseReversalException('The expense accounting provenance is invalid.');
    }

    private function assertReplayIntegrity(Club $club, ClubExpense $expense, ClubExpenseReversal $record): void
    {
        $original = ClubJournalEntry::query()->where('club_id', $club->id)->whereKey($expense->journal_entry_id)->lockForUpdate()->first();
        $reversal = ClubJournalEntry::query()->where('club_id', $club->id)->whereKey($record->reversal_journal_entry_id)->lockForUpdate()->first();
        $lines = $reversal ? ClubJournalLine::query()->where('club_id', $club->id)->where('journal_entry_id', $reversal->id)->orderBy('line_number')->lockForUpdate()->get() : collect();
        if (! $original || ! $reversal || $original->club_id !== $club->id || $original->status !== ClubJournalEntry::STATUS_REVERSED || $reversal->club_id !== $club->id || $reversal->reversal_of_entry_id !== $original->id || $reversal->source_type !== self::SOURCE_TYPE || $reversal->source_id !== $expense->public_id || $reversal->source_event !== 'reversed' || $reversal->idempotency_key !== $record->idempotency_key || $lines->count() !== 2) {
            throw new ClubExpenseReversalException('The expense reversal provenance is invalid.');
        }
        foreach ($lines as $line) {
            $isExpense = $line->ledger_account_id === $expense->expense_account_id && $line->direction === ClubJournalLine::DIRECTION_CREDIT;
            $isPayment = $line->ledger_account_id === $expense->payment_account_id && $line->direction === ClubJournalLine::DIRECTION_DEBIT;
            if ((! $isExpense && ! $isPayment) || $line->club_id !== $club->id || $line->amount_paise !== $expense->amount_paise || $line->fund_code !== $expense->fund_code) throw new ClubExpenseReversalException('The expense reversal provenance is invalid.');
        }
    }

    private function loaded(ClubExpense $expense): ClubExpense
    {
        return $expense->fresh()->load(['expenseAccount', 'paymentAccount', 'creator', 'journalEntry.lines.ledgerAccount', 'reversal.journalEntry.lines.ledgerAccount', 'reversal.creator', 'correctedReplacement.expenseAccount', 'correctedReplacement.paymentAccount', 'correctedReplacement.journalEntry', 'correctedFrom']);
    }
}
