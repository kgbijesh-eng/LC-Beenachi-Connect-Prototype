<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubAccountingPeriod;
use App\Models\ClubJournalEntry;
use App\Models\ClubJournalLine;
use App\Models\ClubMembership;
use App\Models\User;
use App\Services\Finance\Exceptions\AccountingPeriodCloseConflictException;
use App\Services\Finance\Exceptions\AccountingPeriodCloseIntegrityException;
use App\Services\Finance\Exceptions\InvalidAccountingPeriodCloseRequestException;
use Illuminate\Support\Facades\DB;

final class ClubAccountingPeriodCloseService
{
    public function close(AccountingPeriodCloseRequest $request): ClubAccountingPeriod
    {
        $reason = trim($request->reason);
        if ($request->clubId < 1 || $request->accountingPeriodId < 1 || $request->closingActorUserId < 1
            || ! preg_match('/\A\d{4}-\d{2}\z/', $request->expectedLionsYear)
            || $reason === '' || mb_strlen($reason) > 1000 || str_contains($reason, "\0")) {
            throw new InvalidAccountingPeriodCloseRequestException('The accounting-period close request is invalid.');
        }

        return DB::transaction(function () use ($request, $reason): ClubAccountingPeriod {
            $club = Club::query()->whereKey($request->clubId)->lockForUpdate()->first();
            $period = ClubAccountingPeriod::query()->whereKey($request->accountingPeriodId)->lockForUpdate()->first();
            if (! $club || ! $period || $period->club_id !== $request->clubId || $period->lions_year !== $request->expectedLionsYear) {
                throw new InvalidAccountingPeriodCloseRequestException('The accounting-period close request is invalid.');
            }
            if (! User::query()->whereKey($request->closingActorUserId)->exists()
                || ! ClubMembership::query()->where('club_id', $club->id)->where('user_id', $request->closingActorUserId)->where('status', 'active')->exists()) {
                throw new InvalidAccountingPeriodCloseRequestException('The accounting-period close actor is invalid.');
            }
            $startYear = (int) substr($period->lions_year, 0, 4);
            if ($period->start_date->format('Y-m-d') !== sprintf('%04d-07-01', $startYear)
                || $period->end_date->format('Y-m-d') !== sprintf('%04d-06-30', $startYear + 1)
                || substr($period->lions_year, 5, 2) !== str_pad((string) (($startYear + 1) % 100), 2, '0', STR_PAD_LEFT)) {
                throw new AccountingPeriodCloseIntegrityException('The accounting period has invalid Lions Year boundaries.');
            }
            if ($period->status === ClubAccountingPeriod::STATUS_CLOSED) {
                if ($period->closed_by_user_id === $request->closingActorUserId) {
                    return $period->fresh();
                }
                throw new AccountingPeriodCloseConflictException('The accounting period is already closed.');
            }
            if ($period->status !== ClubAccountingPeriod::STATUS_OPEN) {
                throw new AccountingPeriodCloseConflictException('The accounting period cannot be closed.');
            }

            $entries = ClubJournalEntry::query()->where('accounting_period_id', $period->id)->lockForUpdate()->get();
            foreach ($entries as $entry) {
                if ($entry->club_id !== $club->id || ! in_array($entry->status, [ClubJournalEntry::STATUS_POSTED, ClubJournalEntry::STATUS_REVERSED, ClubJournalEntry::STATUS_VOIDED], true)) {
                    throw new AccountingPeriodCloseIntegrityException('The accounting period contains an ineligible journal entry.');
                }
                $lines = ClubJournalLine::query()->where('journal_entry_id', $entry->id)->lockForUpdate()->get();
                if (in_array($entry->status, [ClubJournalEntry::STATUS_POSTED, ClubJournalEntry::STATUS_REVERSED], true) && $lines->count() < 2) {
                    throw new AccountingPeriodCloseIntegrityException('The accounting period contains an incomplete journal entry.');
                }
                $debits = 0;
                $credits = 0;
                foreach ($lines as $line) {
                    $accountClub = DB::table('ledger_accounts')->where('id', $line->ledger_account_id)->value('club_id');
                    if ($line->club_id !== $club->id || $accountClub !== $club->id || $line->amount_paise < 1) {
                        throw new AccountingPeriodCloseIntegrityException('The accounting period contains an invalid journal line.');
                    }
                    $line->direction === ClubJournalLine::DIRECTION_DEBIT ? $debits += $line->amount_paise : $credits += $line->amount_paise;
                }
                if (in_array($entry->status, [ClubJournalEntry::STATUS_POSTED, ClubJournalEntry::STATUS_REVERSED], true) && ($debits === 0 || $debits !== $credits)) {
                    throw new AccountingPeriodCloseIntegrityException('The accounting period contains an unbalanced journal entry.');
                }
                if ($entry->reversal_of_entry_id !== null && ! ClubJournalEntry::query()->whereKey($entry->reversal_of_entry_id)->where('club_id', $club->id)->exists()) {
                    throw new AccountingPeriodCloseIntegrityException('The accounting period contains an invalid reversal link.');
                }
            }

            $period->update(['status' => ClubAccountingPeriod::STATUS_CLOSED, 'closed_at' => now(), 'closed_by_user_id' => $request->closingActorUserId]);
            AuditLog::create(['club_id' => $club->id, 'user_id' => $request->closingActorUserId, 'action' => 'finance.accounting_period.closed', 'auditable_type' => ClubAccountingPeriod::class, 'auditable_id' => $period->id, 'metadata' => ['reason' => $reason, 'lions_year' => $period->lions_year]]);

            return $period->fresh();
        }, 3);
    }
}
