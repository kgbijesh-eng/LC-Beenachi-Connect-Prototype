<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Club;
use App\Models\ClubAccountingPeriod;
use App\Models\ClubJournalEntry;
use App\Models\ClubJournalLine;
use App\Models\LedgerAccount;
use App\Models\User;
use App\Services\Finance\Exceptions\ClosedAccountingPeriodException;
use App\Services\Finance\Exceptions\InactiveLedgerAccountException;
use App\Services\Finance\Exceptions\InvalidAccountingPeriodException;
use App\Services\Finance\Exceptions\InvalidEffectiveDateException;
use App\Services\Finance\Exceptions\InvalidJournalRequestException;
use App\Services\Finance\Exceptions\InvalidMonetaryValueException;
use App\Services\Finance\Exceptions\JournalClubScopeViolationException;
use App\Services\Finance\Exceptions\JournalIdempotencyConflictException;
use App\Services\Finance\Exceptions\JournalSequenceException;
use App\Services\Finance\Exceptions\JournalSourceEventConflictException;
use App\Services\Finance\Exceptions\UnbalancedJournalException;
use App\Services\Finance\Exceptions\UnsupportedJournalCurrencyException;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ClubJournalPostingService
{
    private const MAX_SEQUENCE = 999999;

    public function post(JournalPostingRequest $request): ClubJournalEntry
    {
        $normalized = $this->validateAndNormalize($request);

        return DB::transaction(function () use ($request, $normalized): ClubJournalEntry {
            $club = Club::query()->whereKey($request->clubId)->lockForUpdate()->first();
            if (! $club) {
                throw new InvalidJournalRequestException('The journal posting request is invalid.');
            }

            $clubCode = trim((string) $club->code);
            if (! preg_match('/\A[A-Za-z0-9][A-Za-z0-9_-]{0,39}\z/', $clubCode)) {
                throw new JournalSequenceException('A journal reference cannot be allocated.');
            }

            $period = ClubAccountingPeriod::query()
                ->whereKey($request->accountingPeriodId)
                ->lockForUpdate()
                ->first();
            if (! $period) {
                throw new InvalidAccountingPeriodException('The accounting period is invalid.');
            }
            if ($period->club_id !== $club->id) {
                throw new JournalClubScopeViolationException('The journal request violates club scope.');
            }
            if ($period->status !== ClubAccountingPeriod::STATUS_OPEN) {
                throw new ClosedAccountingPeriodException('The accounting period is closed.');
            }
            if ($normalized['effective_date'] < $period->start_date->format('Y-m-d')
                || $normalized['effective_date'] > $period->end_date->format('Y-m-d')) {
                throw new InvalidEffectiveDateException('The effective date is outside the accounting period.');
            }

            if (! User::query()->whereKey($request->postingActorUserId)->exists()) {
                throw new InvalidJournalRequestException('The journal posting request is invalid.');
            }

            if ($request->reversalOfEntryId !== null) {
                $reversalOf = ClubJournalEntry::query()->whereKey($request->reversalOfEntryId)->lockForUpdate()->first();
                if (! $reversalOf || $reversalOf->club_id !== $club->id) {
                    throw new JournalClubScopeViolationException('The journal request violates club scope.');
                }
            }

            $accounts = $this->lockAndValidateAccounts($request, $club);
            $this->validateFundUsage($normalized['lines'], $accounts);
            $existing = $this->resolveReplay($club, $normalized);
            if ($existing) {
                return $existing;
            }

            $lastSequence = (int) ClubJournalEntry::query()
                ->where('club_id', $club->id)
                ->where('accounting_period_id', $period->id)
                ->max('sequence_number');
            $sequence = $lastSequence + 1;
            if ($sequence < 1 || $sequence > self::MAX_SEQUENCE) {
                throw new JournalSequenceException('A journal reference cannot be allocated.');
            }

            $reference = sprintf('%s/%s/JRN/%06d', $clubCode, $period->lions_year, $sequence);
            if (strlen($reference) > 80) {
                throw new JournalSequenceException('A journal reference cannot be allocated.');
            }

            $entry = ClubJournalEntry::create([
                'club_id' => $club->id,
                'accounting_period_id' => $period->id,
                'sequence_number' => $sequence,
                'reference' => $reference,
                'effective_date' => $normalized['effective_date'],
                'posting_date' => $normalized['effective_date'],
                'status' => ClubJournalEntry::STATUS_DRAFT,
                'description' => $normalized['description'],
                'currency' => ClubJournalEntry::CURRENCY_INR,
                'source_type' => $normalized['source_type'],
                'source_id' => $normalized['source_id'],
                'source_event' => $normalized['source_event'],
                'idempotency_key' => $normalized['idempotency_key'],
                'reversal_of_entry_id' => $normalized['reversal_of_entry_id'],
                'created_by_user_id' => $request->postingActorUserId,
                'metadata' => $normalized['metadata'],
            ]);

            foreach ($normalized['lines'] as $index => $line) {
                ClubJournalLine::create([
                    'club_id' => $club->id,
                    'journal_entry_id' => $entry->id,
                    'ledger_account_id' => $accounts->get($line['account_id'])->id,
                    'line_number' => $index + 1,
                    'direction' => $line['direction'],
                    'amount_paise' => $line['amount_paise'],
                    'description' => $line['description'],
                    'fund_code' => $line['fund_code'],
                    'opening_schedule_row_id' => $line['opening_schedule_row_id'],
                    'reversal_of_line_id' => $line['reversal_of_line_id'],
                ]);
            }

            $entry->update([
                'status' => ClubJournalEntry::STATUS_POSTED,
                'posted_at' => now(),
                'posted_by_user_id' => $request->postingActorUserId,
            ]);

            return $this->canonicalEntry($entry);
        }, 3);
    }

    private function validateAndNormalize(JournalPostingRequest $request): array
    {
        if ($request->clubId < 1 || $request->accountingPeriodId < 1 || $request->postingActorUserId < 1
            || ($request->reversalOfEntryId !== null && $request->reversalOfEntryId < 1)) {
            throw new InvalidJournalRequestException('The journal posting request is invalid.');
        }
        if ($request->currency !== LedgerAccount::CURRENCY_INR) {
            throw new UnsupportedJournalCurrencyException('The journal currency is unsupported.');
        }

        $effectiveDate = DateTimeImmutable::createFromFormat('!Y-m-d', $request->effectiveDate);
        if (! $effectiveDate || $effectiveDate->format('Y-m-d') !== $request->effectiveDate) {
            throw new InvalidEffectiveDateException('The effective date is invalid.');
        }

        $description = trim($request->description);
        $sourceType = trim($request->sourceType);
        $sourceId = trim($request->sourceId);
        $sourceEvent = trim($request->sourceEvent);
        $idempotencyKey = strtolower(trim($request->idempotencyKey));
        if ($description === '' || $sourceType === '' || $sourceId === '' || $sourceEvent === ''
            || ! Str::isUuid($idempotencyKey)
            || strlen($sourceType) > 80 || strlen($sourceId) > 120 || strlen($sourceEvent) > 80) {
            throw new InvalidJournalRequestException('The journal posting request is invalid.');
        }
        if (count($request->lines) < 2 || ! array_is_list($request->lines)) {
            throw new InvalidJournalRequestException('A journal requires at least two ordered lines.');
        }

        $debits = 0;
        $credits = 0;
        $fundTotals = [];
        $lines = [];
        foreach ($request->lines as $line) {
            if (! $line instanceof JournalLineInput || $line->accountId < 1
                || ! in_array($line->direction, [ClubJournalLine::DIRECTION_DEBIT, ClubJournalLine::DIRECTION_CREDIT], true)) {
                throw new InvalidJournalRequestException('A journal line is invalid.');
            }
            if ($line->amountPaise < 1) {
                throw new InvalidMonetaryValueException('A journal amount is invalid.');
            }
            $fund = $line->fundCode === null ? null : FundCode::tryFrom($line->fundCode);
            if (! $fund) {
                throw new InvalidJournalRequestException('A journal line fund is invalid.');
            }

            if ($line->direction === ClubJournalLine::DIRECTION_DEBIT) {
                $debits = $this->checkedAdd($debits, $line->amountPaise);
                $fundTotals[$fund->value]['debit'] = $this->checkedAdd($fundTotals[$fund->value]['debit'] ?? 0, $line->amountPaise);
            } else {
                $credits = $this->checkedAdd($credits, $line->amountPaise);
                $fundTotals[$fund->value]['credit'] = $this->checkedAdd($fundTotals[$fund->value]['credit'] ?? 0, $line->amountPaise);
            }
            $lineDescription = $line->description === null ? null : trim($line->description);
            $lines[] = [
                'account_id' => $line->accountId,
                'direction' => $line->direction,
                'amount_paise' => $line->amountPaise,
                'description' => $lineDescription === '' ? null : $lineDescription,
                'fund_code' => $fund->value,
                'opening_schedule_row_id' => $line->openingScheduleRowId,
                'reversal_of_line_id' => $line->reversalOfLineId,
            ];
        }

        if ($debits === 0 || $credits === 0) {
            throw new InvalidJournalRequestException('A journal requires debit and credit lines.');
        }
        if ($debits !== $credits) {
            throw new UnbalancedJournalException('The journal is not balanced.');
        }
        foreach ($fundTotals as $totals) {
            if (($totals['debit'] ?? 0) !== ($totals['credit'] ?? 0)) {
                throw new UnbalancedJournalException('The journal is not balanced within each fund.');
            }
        }

        return [
            'effective_date' => $effectiveDate->format('Y-m-d'),
            'description' => $description,
            'currency' => LedgerAccount::CURRENCY_INR,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'source_event' => $sourceEvent,
            'idempotency_key' => $idempotencyKey,
            'posting_actor_user_id' => $request->postingActorUserId,
            'club_id' => $request->clubId,
            'accounting_period_id' => $request->accountingPeriodId,
            'reversal_of_entry_id' => $request->reversalOfEntryId,
            'metadata' => $request->metadata,
            'lines' => $lines,
        ];
    }

    private function checkedAdd(int $total, int $amount): int
    {
        if ($amount > PHP_INT_MAX - $total) {
            throw new InvalidMonetaryValueException('A journal amount is invalid.');
        }

        return $total + $amount;
    }

    private function lockAndValidateAccounts(JournalPostingRequest $request, Club $club): Collection
    {
        $accountIds = collect($request->lines)->map(fn (JournalLineInput $line): int => $line->accountId)->unique()->values();
        $accounts = LedgerAccount::query()->whereIn('id', $accountIds)->lockForUpdate()->get()->keyBy('id');
        if ($accounts->count() !== $accountIds->count()) {
            throw new InvalidJournalRequestException('A journal account is invalid.');
        }

        foreach ($accounts as $account) {
            if ($account->club_id !== $club->id) {
                throw new JournalClubScopeViolationException('The journal request violates club scope.');
            }
            if (! $account->is_active) {
                throw new InactiveLedgerAccountException('A journal account is inactive.');
            }
            if ($account->currency !== LedgerAccount::CURRENCY_INR) {
                throw new UnsupportedJournalCurrencyException('The journal currency is unsupported.');
            }
        }

        return $accounts;
    }

    private function resolveReplay(Club $club, array $normalized): ?ClubJournalEntry
    {
        $byKey = ClubJournalEntry::query()
            ->where('club_id', $club->id)
            ->where('idempotency_key', $normalized['idempotency_key'])
            ->lockForUpdate()
            ->first();
        $bySource = ClubJournalEntry::query()
            ->where('club_id', $club->id)
            ->where('source_type', $normalized['source_type'])
            ->where('source_id', $normalized['source_id'])
            ->where('source_event', $normalized['source_event'])
            ->lockForUpdate()
            ->first();

        if (! $byKey && ! $bySource) {
            return null;
        }
        if ($byKey && $bySource && ! $byKey->is($bySource)) {
            throw new JournalIdempotencyConflictException('The journal replay conflicts with an existing posting.');
        }
        if (! $byKey && $bySource) {
            throw new JournalSourceEventConflictException('The source event has already been posted.');
        }
        if ($byKey && ! $bySource) {
            throw new JournalIdempotencyConflictException('The idempotency key conflicts with an existing posting.');
        }

        $existing = $this->canonicalEntry($byKey);
        if ($this->semanticEntry($existing) !== $normalized) {
            throw new JournalIdempotencyConflictException('The journal replay conflicts with an existing posting.');
        }

        return $existing;
    }

    private function validateFundUsage(array $lines, Collection $accounts): void
    {
        $templates = collect(app(ApprovedLedgerAccountTemplateCatalog::class)->definitions())->keyBy('templateCode');
        foreach ($lines as $line) {
            $account = $accounts->get($line['account_id']);
            if ($account->origin_kind !== AccountOriginKind::CATALOGUE->value) {
                continue;
            }
            $template = $templates->get($account->origin_template_code);
            if (! $template) {
                throw new InvalidJournalRequestException('A catalogue account template identity is invalid.');
            }
            $allowed = match ($template->fundUsageRule) {
                FundUsageRule::ANY_APPROVED_FUND->value => true,
                FundUsageRule::GENERAL_ONLY->value => $line['fund_code'] === FundCode::GENERAL_CLUB_FUND->value,
                FundUsageRule::SERVICE_ONLY->value => $line['fund_code'] === FundCode::SERVICE_ACTIVITY_FUND->value,
                FundUsageRule::FELLOWSHIP_MEMBER_ONLY->value => $line['fund_code'] === FundCode::FELLOWSHIP_MEMBER_ACTIVITY_FUND->value,
                default => false,
            };
            if (! $allowed) {
                throw new InvalidJournalRequestException('The journal fund is not permitted for an account.');
            }
        }
    }

    private function semanticEntry(ClubJournalEntry $entry): array
    {
        return [
            'effective_date' => $entry->effective_date->format('Y-m-d'),
            'description' => $entry->description,
            'currency' => $entry->currency,
            'source_type' => $entry->source_type,
            'source_id' => $entry->source_id,
            'source_event' => $entry->source_event,
            'idempotency_key' => strtolower($entry->idempotency_key),
            'posting_actor_user_id' => $entry->posted_by_user_id,
            'club_id' => $entry->club_id,
            'accounting_period_id' => $entry->accounting_period_id,
            'reversal_of_entry_id' => $entry->reversal_of_entry_id,
            'metadata' => $entry->metadata,
            'lines' => $entry->lines->map(fn (ClubJournalLine $line): array => [
                'account_id' => $line->ledger_account_id,
                'direction' => $line->direction,
                'amount_paise' => $line->amount_paise,
                'description' => $line->description,
                'fund_code' => $line->fund_code,
                'opening_schedule_row_id' => $line->opening_schedule_row_id,
                'reversal_of_line_id' => $line->reversal_of_line_id,
            ])->all(),
        ];
    }

    private function canonicalEntry(ClubJournalEntry $entry): ClubJournalEntry
    {
        return $entry->fresh()->load([
            'accountingPeriod',
            'lines' => fn ($query) => $query->orderBy('line_number')->orderBy('id'),
        ]);
    }
}
