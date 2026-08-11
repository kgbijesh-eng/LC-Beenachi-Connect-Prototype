<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Club;
use App\Models\ClubJournalEntry;
use App\Models\LedgerAccount;
use App\Services\Finance\Exceptions\InvalidJournalActivityCursorException;
use App\Services\Finance\Exceptions\InvalidJournalActivityQueryException;
use App\Services\Finance\Exceptions\JournalActivityIntegrityException;
use App\Services\Finance\Exceptions\JournalActivityScopeException;
use App\Services\Finance\Exceptions\UnsupportedJournalActivityCurrencyException;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use JsonException;
use Throwable;

final class ClubJournalAccountActivityService
{
    private const MIN_PAGE_SIZE = 1;

    private const MAX_PAGE_SIZE = 100;

    /**
     * Stable activity order: effective date, posted timestamp, accounting-period ID,
     * journal sequence, journal-entry ID, line number, then journal-line ID.
     */
    private const ORDER = [
        'club_journal_entries.effective_date',
        'club_journal_entries.posted_at',
        'club_journal_entries.accounting_period_id',
        'club_journal_entries.sequence_number',
        'club_journal_entries.id',
        'club_journal_lines.line_number',
        'club_journal_lines.id',
    ];

    public function query(JournalAccountActivityQuery $query): JournalAccountActivityResult
    {
        [$from, $to] = $this->validateRequest($query);

        $club = Club::query()->find($query->clubId);
        if (! $club) {
            throw new InvalidJournalActivityQueryException('The journal activity club is invalid.');
        }

        $account = LedgerAccount::query()->find($query->ledgerAccountId);
        if (! $account) {
            throw new InvalidJournalActivityQueryException('The journal activity account is invalid.');
        }
        if ($account->club_id !== $club->id) {
            throw new JournalActivityScopeException('The journal activity query violates club scope.');
        }
        if ($account->currency !== LedgerAccount::CURRENCY_INR) {
            throw new UnsupportedJournalActivityCurrencyException('The journal activity currency is unsupported.');
        }

        $context = $this->context($query, $from, $to);
        $position = $query->cursor === null ? null : $this->decodeCursor($query->cursor, $context);
        $base = $this->activityQuery($club->id, $account->id, $from, $to);
        [$debits, $credits] = $this->validateIntegrityAndTotals(clone $base, $club->id, $account->id);

        $page = clone $base;
        if ($position !== null) {
            $this->applyAfter($page, self::ORDER, $position);
        }
        foreach (self::ORDER as $column) {
            $page->orderBy($column);
        }

        $records = $page->limit($query->pageSize + 1)->get();
        $hasMore = $records->count() > $query->pageSize;
        $records = $records->take($query->pageSize)->values();
        $rows = $records->map(fn (object $record): JournalAccountActivityRow => $this->row($record))->all();
        $nextCursor = $hasMore && $records->isNotEmpty()
            ? $this->encodeCursor($context, $this->position($records->last()))
            : null;
        $movement = $account->normal_balance === LedgerAccount::NORMAL_DEBIT
            ? $debits - $credits
            : $credits - $debits;

        return new JournalAccountActivityResult(
            clubId: $club->id,
            clubCode: (string) $club->code,
            accountId: $account->id,
            accountPublicId: $account->public_id,
            accountCode: $account->code,
            accountName: $account->name,
            accountType: $account->account_type,
            normalBalance: $account->normal_balance,
            currency: $account->currency,
            fromEffectiveDate: $from,
            toEffectiveDate: $to,
            periodDebitPaise: $debits,
            periodCreditPaise: $credits,
            signedPeriodMovementPaise: $movement,
            rows: $rows,
            pageSize: $query->pageSize,
            hasMore: $hasMore,
            nextCursor: $nextCursor,
        );
    }

    /** @return array{string, string} */
    private function validateRequest(JournalAccountActivityQuery $query): array
    {
        if ($query->clubId < 1 || $query->ledgerAccountId < 1
            || $query->pageSize < self::MIN_PAGE_SIZE || $query->pageSize > self::MAX_PAGE_SIZE) {
            throw new InvalidJournalActivityQueryException('The journal activity query is invalid.');
        }
        $from = DateTimeImmutable::createFromFormat('!Y-m-d', $query->fromEffectiveDate);
        $to = DateTimeImmutable::createFromFormat('!Y-m-d', $query->toEffectiveDate);
        if (! $from || $from->format('Y-m-d') !== $query->fromEffectiveDate
            || ! $to || $to->format('Y-m-d') !== $query->toEffectiveDate
            || $from > $to || $this->lionsYear($from) !== $this->lionsYear($to)) {
            throw new InvalidJournalActivityQueryException('The journal activity date range is invalid.');
        }

        return [$from->format('Y-m-d'), $to->format('Y-m-d')];
    }

    private function lionsYear(DateTimeImmutable $date): int
    {
        return (int) $date->format('n') >= 7 ? (int) $date->format('Y') : (int) $date->format('Y') - 1;
    }

    private function activityQuery(int $clubId, int $accountId, string $from, string $to): Builder
    {
        return DB::table('club_journal_lines')
            ->join('club_journal_entries', 'club_journal_entries.id', '=', 'club_journal_lines.journal_entry_id')
            ->join('club_accounting_periods', 'club_accounting_periods.id', '=', 'club_journal_entries.accounting_period_id')
            ->join('ledger_accounts', 'ledger_accounts.id', '=', 'club_journal_lines.ledger_account_id')
            ->leftJoin('club_journal_entries as original_entry', 'original_entry.id', '=', 'club_journal_entries.reversal_of_entry_id')
            ->leftJoin('club_journal_entries as reversing_entry', function ($join): void {
                $join->on('reversing_entry.reversal_of_entry_id', '=', 'club_journal_entries.id')
                    ->where('reversing_entry.status', '=', ClubJournalEntry::STATUS_POSTED);
            })
            ->where('club_journal_entries.club_id', $clubId)
            ->where('club_journal_lines.ledger_account_id', $accountId)
            ->whereIn('club_journal_entries.status', [ClubJournalEntry::STATUS_POSTED, ClubJournalEntry::STATUS_REVERSED])
            ->whereBetween('club_journal_entries.effective_date', [$from, $to])
            ->select([
                'club_journal_entries.id as entry_id', 'club_journal_entries.public_id as entry_public_id',
                'club_journal_entries.reference', 'club_journal_entries.effective_date', 'club_journal_entries.posting_date',
                'club_journal_entries.posted_at', 'club_journal_entries.accounting_period_id',
                'club_journal_entries.sequence_number', 'club_journal_entries.status', 'club_journal_entries.description as entry_description',
                'club_journal_entries.source_type', 'club_journal_entries.source_id', 'club_journal_entries.source_event',
                'club_journal_entries.club_id as entry_club_id', 'club_journal_entries.currency as entry_currency',
                'club_accounting_periods.lions_year', 'club_accounting_periods.club_id as period_club_id',
                'ledger_accounts.club_id as account_club_id', 'ledger_accounts.currency as account_currency',
                'club_journal_lines.id as line_id', 'club_journal_lines.public_id as line_public_id',
                'club_journal_lines.club_id as line_club_id', 'club_journal_lines.line_number',
                'club_journal_lines.direction', 'club_journal_lines.amount_paise', 'club_journal_lines.description as line_description',
                'original_entry.public_id as reversal_of_public_id', 'original_entry.club_id as original_club_id',
                'reversing_entry.public_id as reversed_by_public_id', 'reversing_entry.club_id as reversing_club_id',
            ]);
    }

    /** @return array{int, int} */
    private function validateIntegrityAndTotals(Builder $query, int $clubId, int $accountId): array
    {
        try {
            $duplicateReversal = (clone $query)->reorder()
                ->select(['club_journal_entries.id', 'club_journal_lines.id'])
                ->groupBy(['club_journal_entries.id', 'club_journal_lines.id'])
                ->havingRaw('COUNT(*) > 1')
                ->exists();
            if ($duplicateReversal) {
                throw new JournalActivityIntegrityException('The journal activity has conflicting reversal links.');
            }
            $summary = $query->reorder()->selectRaw(
                'COUNT(*) AS row_count, '.
                "SUM(CASE WHEN club_journal_lines.direction = 'debit' THEN club_journal_lines.amount_paise ELSE 0 END) AS debit_total, ".
                "SUM(CASE WHEN club_journal_lines.direction = 'credit' THEN club_journal_lines.amount_paise ELSE 0 END) AS credit_total, ".
                "SUM(CASE WHEN club_journal_lines.direction NOT IN ('debit','credit') OR club_journal_lines.amount_paise < 1 ".
                "OR club_journal_entries.posted_at IS NULL OR club_journal_entries.currency <> 'INR' ".
                "OR ledger_accounts.currency <> 'INR' OR club_journal_lines.club_id <> ? OR club_journal_entries.club_id <> ? ".
                'OR club_accounting_periods.club_id <> ? OR ledger_accounts.club_id <> ? OR club_journal_lines.ledger_account_id <> ? '.
                'OR (original_entry.id IS NOT NULL AND original_entry.club_id <> ?) '.
                'OR (reversing_entry.id IS NOT NULL AND reversing_entry.club_id <> ?) THEN 1 ELSE 0 END) AS invalid_count',
                [$clubId, $clubId, $clubId, $clubId, $accountId, $clubId, $clubId],
            )->first();
        } catch (Throwable $exception) {
            throw new JournalActivityIntegrityException('The journal activity is malformed.', 0, $exception);
        }

        if (! $summary || (int) $summary->invalid_count !== 0) {
            throw new JournalActivityIntegrityException('The journal activity is malformed.');
        }
        $debits = $summary->debit_total ?? 0;
        $credits = $summary->credit_total ?? 0;
        if (! is_int($debits) || ! is_int($credits) || $debits < 0 || $credits < 0) {
            throw new JournalActivityIntegrityException('The journal activity totals are invalid.');
        }

        return [$debits, $credits];
    }

    private function row(object $record): JournalAccountActivityRow
    {
        return new JournalAccountActivityRow(
            journalEntryPublicId: $record->entry_public_id,
            journalLinePublicId: $record->line_public_id,
            journalReference: $record->reference,
            lionsYear: $record->lions_year,
            effectiveDate: $record->effective_date,
            postingDate: $record->posting_date,
            postedAt: $record->posted_at,
            sequenceNumber: (int) $record->sequence_number,
            lifecycleStatus: $record->status,
            sourceType: $record->source_type,
            sourceId: $record->source_id,
            sourceEvent: $record->source_event,
            reversalOfEntryPublicId: $record->reversal_of_public_id,
            reversedByEntryPublicId: $record->reversed_by_public_id,
            lineNumber: (int) $record->line_number,
            direction: $record->direction,
            amountPaise: (int) $record->amount_paise,
            journalDescription: $record->entry_description,
            lineDescription: $record->line_description,
        );
    }

    /** @return array<string, int|string> */
    private function position(object $record): array
    {
        return array_combine(self::ORDER, [
            $record->effective_date, $record->posted_at, (int) $record->accounting_period_id,
            (int) $record->sequence_number, (int) $record->entry_id,
            (int) $record->line_number, (int) $record->line_id,
        ]);
    }

    /** @param array<string, int|string> $position */
    private function applyAfter(Builder $query, array $columns, array $position, int $index = 0): void
    {
        $column = $columns[$index];
        $query->where(function (Builder $where) use ($columns, $position, $index, $column): void {
            $where->where($column, '>', $position[$column]);
            if ($index < count($columns) - 1) {
                $where->orWhere(function (Builder $equal) use ($columns, $position, $index, $column): void {
                    $equal->where($column, '=', $position[$column]);
                    $this->applyAfter($equal, $columns, $position, $index + 1);
                });
            }
        });
    }

    private function context(JournalAccountActivityQuery $query, string $from, string $to): string
    {
        return hash('sha256', implode('|', [$query->clubId, $query->ledgerAccountId, $from, $to]));
    }

    /** @param array<string, int|string> $position */
    private function encodeCursor(string $context, array $position): string
    {
        try {
            $json = json_encode(['v' => 1, 'context' => $context, 'position' => $position], JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new JournalActivityIntegrityException('The journal activity cursor could not be created.', 0, $exception);
        }

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    /** @return array<string, int|string> */
    private function decodeCursor(string $cursor, string $context): array
    {
        $decoded = base64_decode(strtr($cursor, '-_', '+/'), true);
        if ($decoded === false) {
            throw new InvalidJournalActivityCursorException('The journal activity cursor is invalid.');
        }
        try {
            $payload = json_decode($decoded, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidJournalActivityCursorException('The journal activity cursor is invalid.');
        }
        if (! is_array($payload) || ($payload['v'] ?? null) !== 1 || ($payload['context'] ?? null) !== $context
            || ! is_array($payload['position'] ?? null) || array_keys($payload['position']) !== self::ORDER) {
            throw new InvalidJournalActivityCursorException('The journal activity cursor is invalid.');
        }
        foreach (array_values($payload['position']) as $index => $value) {
            $expectsString = $index < 2;
            if (($expectsString && ! is_string($value)) || (! $expectsString && ! is_int($value))) {
                throw new InvalidJournalActivityCursorException('The journal activity cursor is invalid.');
            }
        }

        return $payload['position'];
    }
}
