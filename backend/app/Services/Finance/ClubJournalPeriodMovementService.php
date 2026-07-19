<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Club;
use App\Models\ClubAccountingPeriod;
use App\Models\ClubJournalEntry;
use App\Models\ClubJournalLine;
use App\Models\LedgerAccount;
use App\Services\Finance\Exceptions\InvalidJournalPeriodMovementQueryException;
use App\Services\Finance\Exceptions\JournalPeriodMovementIntegrityException;
use App\Services\Finance\Exceptions\JournalPeriodMovementScopeException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ClubJournalPeriodMovementService
{
    public function summarize(JournalPeriodMovementQuery $query): JournalPeriodMovementResult
    {
        if ($query->clubId < 1 || $query->accountingPeriodId < 1) {
            throw new InvalidJournalPeriodMovementQueryException('The journal period movement query is invalid.');
        }

        return DB::transaction(function () use ($query): JournalPeriodMovementResult {
            $club = Club::query()->find($query->clubId);
            if (! $club) {
                throw new InvalidJournalPeriodMovementQueryException('The journal period movement club is invalid.');
            }
            $period = ClubAccountingPeriod::query()->find($query->accountingPeriodId);
            if (! $period) {
                throw new InvalidJournalPeriodMovementQueryException('The accounting period is invalid.');
            }
            if ($period->club_id !== $club->id) {
                throw new JournalPeriodMovementScopeException('The journal period movement query violates club scope.');
            }
            $this->validatePeriod($period);

            $entries = ClubJournalEntry::query()
                ->where('club_id', $club->id)
                ->where('accounting_period_id', $period->id)
                ->with(['lines' => fn ($builder) => $builder->orderBy('line_number')->orderBy('id')])
                ->orderBy('sequence_number')
                ->orderBy('id')
                ->get();

            return $this->summarizeEntries($club->id, $period, $entries);
        }, 3);
    }

    private function validatePeriod(ClubAccountingPeriod $period): void
    {
        if (! in_array($period->status, [ClubAccountingPeriod::STATUS_OPEN, ClubAccountingPeriod::STATUS_CLOSED], true)) {
            throw new InvalidJournalPeriodMovementQueryException('The accounting period lifecycle is invalid.');
        }
        $startYear = (int) substr($period->lions_year, 0, 4);
        $expectedYear = sprintf('%04d-%02d', $startYear, ($startYear + 1) % 100);
        if (! preg_match('/\A\d{4}-\d{2}\z/', $period->lions_year)
            || $period->lions_year !== $expectedYear
            || $period->start_date->format('Y-m-d') !== sprintf('%04d-07-01', $startYear)
            || $period->end_date->format('Y-m-d') !== sprintf('%04d-06-30', $startYear + 1)) {
            throw new InvalidJournalPeriodMovementQueryException('The accounting period boundaries are invalid.');
        }
    }

    private function summarizeEntries(int $clubId, ClubAccountingPeriod $period, Collection $entries): JournalPeriodMovementResult
    {
        $supported = 0;
        $reversedOriginals = 0;
        $postedReversals = 0;
        $drafts = 0;
        $totalDebit = 0;
        $totalCredit = 0;
        $accounts = [];

        foreach ($entries as $entry) {
            if ($entry->club_id !== $clubId || $entry->accounting_period_id !== $period->id
                || $entry->currency !== ClubJournalEntry::CURRENCY_INR) {
                throw new JournalPeriodMovementIntegrityException('The journal period contains malformed entry context.');
            }
            if ($entry->status === ClubJournalEntry::STATUS_DRAFT) {
                $drafts++;

                continue;
            }
            if ($entry->status === ClubJournalEntry::STATUS_VOIDED) {
                continue;
            }
            if (! in_array($entry->status, [ClubJournalEntry::STATUS_POSTED, ClubJournalEntry::STATUS_REVERSED], true)) {
                throw new JournalPeriodMovementIntegrityException('The journal period contains an unsupported lifecycle state.');
            }
            if (! $entry->posted_at || ! $entry->posted_by_user_id || $entry->lines->count() < 2) {
                throw new JournalPeriodMovementIntegrityException('The journal period contains an incomplete posted entry.');
            }

            $this->validateReversal($entry, $clubId);
            $supported++;
            $reversedOriginals += $entry->status === ClubJournalEntry::STATUS_REVERSED ? 1 : 0;
            $postedReversals += $entry->reversal_of_entry_id !== null ? 1 : 0;
            $entryDebit = 0;
            $entryCredit = 0;

            foreach ($entry->lines as $line) {
                $account = LedgerAccount::query()->find($line->ledger_account_id);
                if (! $account || $line->club_id !== $clubId || $account->club_id !== $clubId
                    || $line->journal_entry_id !== $entry->id || $account->currency !== LedgerAccount::CURRENCY_INR
                    || ! in_array($line->direction, [ClubJournalLine::DIRECTION_DEBIT, ClubJournalLine::DIRECTION_CREDIT], true)
                    || ! is_int($line->amount_paise) || $line->amount_paise < 1 || $line->line_number < 1) {
                    throw new JournalPeriodMovementIntegrityException('The journal period contains a malformed line.');
                }
                if (! in_array($account->normal_balance, [LedgerAccount::NORMAL_DEBIT, LedgerAccount::NORMAL_CREDIT], true)) {
                    throw new JournalPeriodMovementIntegrityException('The journal period contains a malformed account.');
                }

                $accounts[$account->id] ??= ['model' => $account, 'debit' => 0, 'credit' => 0, 'lines' => 0];
                $accounts[$account->id]['lines']++;
                if ($line->direction === ClubJournalLine::DIRECTION_DEBIT) {
                    $entryDebit = $this->add($entryDebit, $line->amount_paise);
                    $accounts[$account->id]['debit'] = $this->add($accounts[$account->id]['debit'], $line->amount_paise);
                } else {
                    $entryCredit = $this->add($entryCredit, $line->amount_paise);
                    $accounts[$account->id]['credit'] = $this->add($accounts[$account->id]['credit'], $line->amount_paise);
                }
            }
            if ($entryDebit < 1 || $entryDebit !== $entryCredit) {
                throw new JournalPeriodMovementIntegrityException('The journal period contains an unbalanced entry.');
            }
            $totalDebit = $this->add($totalDebit, $entryDebit);
            $totalCredit = $this->add($totalCredit, $entryCredit);
        }

        if ($totalDebit !== $totalCredit) {
            throw new JournalPeriodMovementIntegrityException('The journal period aggregate is unbalanced.');
        }

        $summaries = collect($accounts)->map(function (array $movement): JournalAccountMovementSummary {
            /** @var LedgerAccount $account */
            $account = $movement['model'];
            $signed = $account->normal_balance === LedgerAccount::NORMAL_DEBIT
                ? $movement['debit'] - $movement['credit']
                : $movement['credit'] - $movement['debit'];

            return new JournalAccountMovementSummary(
                accountId: $account->id,
                accountPublicId: $account->public_id,
                accountCode: $account->code,
                accountName: $account->name,
                accountType: $account->account_type,
                normalBalance: $account->normal_balance,
                isActive: $account->is_active,
                debitMovementPaise: $movement['debit'],
                creditMovementPaise: $movement['credit'],
                signedPeriodMovementPaise: $signed,
                includedLineCount: $movement['lines'],
            );
        })->sort(fn (JournalAccountMovementSummary $left, JournalAccountMovementSummary $right): int => [$left->accountCode, $left->accountId] <=> [$right->accountCode, $right->accountId])->values()->all();

        return new JournalPeriodMovementResult(
            clubId: $clubId,
            accountingPeriodId: $period->id,
            lionsYear: $period->lions_year,
            periodStartDate: $period->start_date->format('Y-m-d'),
            periodEndDate: $period->end_date->format('Y-m-d'),
            periodStatus: $period->status,
            currency: LedgerAccount::CURRENCY_INR,
            supportedEntryCount: $supported,
            reversedOriginalCount: $reversedOriginals,
            postedReversalCount: $postedReversals,
            excludedDraftCount: $drafts,
            totalDebitPaise: $totalDebit,
            totalCreditPaise: $totalCredit,
            differencePaise: $totalDebit - $totalCredit,
            affectedAccounts: $summaries,
        );
    }

    private function validateReversal(ClubJournalEntry $entry, int $clubId): void
    {
        if ($entry->reversal_of_entry_id !== null) {
            $original = ClubJournalEntry::query()->with(['lines' => fn ($builder) => $builder->orderBy('line_number')->orderBy('id')])->find($entry->reversal_of_entry_id);
            if (! $original || $original->club_id !== $clubId || $original->reversal_of_entry_id !== null
                || $original->status !== ClubJournalEntry::STATUS_REVERSED) {
                throw new JournalPeriodMovementIntegrityException('The journal period contains an invalid reversal link.');
            }
            if (! $this->oppositeLines($original->lines, $entry->lines)) {
                throw new JournalPeriodMovementIntegrityException('The journal period contains invalid reversal effects.');
            }
        }

        if ($entry->status === ClubJournalEntry::STATUS_REVERSED) {
            $reversals = ClubJournalEntry::query()->where('club_id', $clubId)
                ->where('reversal_of_entry_id', $entry->id)
                ->where('status', ClubJournalEntry::STATUS_POSTED)
                ->get();
            if ($reversals->count() !== 1) {
                throw new JournalPeriodMovementIntegrityException('The reversed original has inconsistent reversal lifecycle.');
            }
        }
    }

    private function oppositeLines(Collection $original, Collection $reversal): bool
    {
        if ($original->count() !== $reversal->count()) {
            return false;
        }
        foreach ($original->values() as $index => $line) {
            $opposite = $reversal->values()[$index];
            if ($line->ledger_account_id !== $opposite->ledger_account_id
                || $line->amount_paise !== $opposite->amount_paise
                || $line->direction === $opposite->direction) {
                return false;
            }
        }

        return true;
    }

    private function add(int $total, int $amount): int
    {
        if ($amount > PHP_INT_MAX - $total) {
            throw new JournalPeriodMovementIntegrityException('The journal period movement exceeds integer limits.');
        }

        return $total + $amount;
    }
}
