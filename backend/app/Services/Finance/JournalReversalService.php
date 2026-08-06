<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubAccountingPeriod;
use App\Models\ClubJournalEntry;
use App\Models\ClubJournalLine;
use App\Models\User;
use App\Services\Finance\Exceptions\AlreadyReversedJournalException;
use App\Services\Finance\Exceptions\ClosedReversalPeriodException;
use App\Services\Finance\Exceptions\InvalidReversalActorException;
use App\Services\Finance\Exceptions\InvalidReversalDateException;
use App\Services\Finance\Exceptions\InvalidReversalPeriodException;
use App\Services\Finance\Exceptions\InvalidReversalReasonException;
use App\Services\Finance\Exceptions\InvalidReversalRequestException;
use App\Services\Finance\Exceptions\JournalIdempotencyConflictException;
use App\Services\Finance\Exceptions\JournalSourceEventConflictException;
use App\Services\Finance\Exceptions\OriginalEntryNotPostedException;
use App\Services\Finance\Exceptions\OriginalJournalEntryNotFoundException;
use App\Services\Finance\Exceptions\ReversalClubScopeViolationException;
use App\Services\Finance\Exceptions\ReversalLifecycleTransitionException;
use App\Services\Finance\Exceptions\ReversalLinkConflictException;
use App\Services\Finance\Exceptions\ReversalOfReversalProhibitedException;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final class JournalReversalService
{
    public function __construct(private readonly ClubJournalPostingService $posting) {}

    public function reverse(JournalReversalRequest $request): ClubJournalEntry
    {
        $normalized = $this->validateAndNormalize($request);

        return DB::transaction(function () use ($request, $normalized): ClubJournalEntry {
            $club = Club::query()->whereKey($request->clubId)->lockForUpdate()->first();
            if (! $club) {
                throw new ReversalClubScopeViolationException('The reversal club is invalid.');
            }

            $original = ClubJournalEntry::query()->whereKey($request->originalEntryId)->lockForUpdate()->first();
            if (! $original) {
                throw new OriginalJournalEntryNotFoundException('The original journal entry is unavailable.');
            }
            if ($original->club_id !== $club->id) {
                throw new ReversalClubScopeViolationException('The reversal request violates club scope.');
            }
            $originalLines = ClubJournalLine::query()
                ->where('journal_entry_id', $original->id)
                ->orderBy('line_number')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if (! User::query()->whereKey($request->reversalActorUserId)->exists()) {
                throw new InvalidReversalActorException('The reversal actor is invalid.');
            }
            $period = ClubAccountingPeriod::query()->whereKey($request->reversalAccountingPeriodId)->lockForUpdate()->first();
            if (! $period) {
                throw new InvalidReversalPeriodException('The reversal period is invalid.');
            }
            if ($period->club_id !== $club->id) {
                throw new ReversalClubScopeViolationException('The reversal request violates club scope.');
            }
            if ($period->status !== ClubAccountingPeriod::STATUS_OPEN) {
                throw new ClosedReversalPeriodException('The reversal period is closed.');
            }
            if ($normalized['effective_date'] < $period->start_date->format('Y-m-d')
                || $normalized['effective_date'] > $period->end_date->format('Y-m-d')) {
                throw new InvalidReversalDateException('The reversal effective date is outside the target period.');
            }

            $existing = $this->resolveReplay($club, $original, $originalLines, $normalized);
            if ($existing) {
                return $existing;
            }
            if ($original->reversal_of_entry_id !== null) {
                throw new ReversalOfReversalProhibitedException('Reversal entries cannot be reversed.');
            }
            if ($original->status === ClubJournalEntry::STATUS_REVERSED || $original->reversals()->exists()) {
                throw new AlreadyReversedJournalException('The journal entry has already been reversed.');
            }
            if ($original->status !== ClubJournalEntry::STATUS_POSTED) {
                throw new OriginalEntryNotPostedException('Only posted journal entries can be reversed.');
            }
            if ($originalLines->count() < 2) {
                throw new ReversalLinkConflictException('The original journal entry is incomplete.');
            }

            $reversal = $this->posting->post(new JournalPostingRequest(
                clubId: $club->id,
                accountingPeriodId: $period->id,
                effectiveDate: $normalized['effective_date'],
                description: sprintf('Reversal of %s: %s', $original->reference, $normalized['reason']),
                currency: ClubJournalEntry::CURRENCY_INR,
                sourceType: $normalized['source_type'],
                sourceId: $normalized['source_id'],
                sourceEvent: $normalized['source_event'],
                idempotencyKey: $normalized['idempotency_key'],
                postingActorUserId: $request->reversalActorUserId,
                lines: $originalLines->map(fn (ClubJournalLine $line): JournalLineInput => new JournalLineInput(
                    $line->ledger_account_id,
                    $line->direction === ClubJournalLine::DIRECTION_DEBIT ? ClubJournalLine::DIRECTION_CREDIT : ClubJournalLine::DIRECTION_DEBIT,
                    $line->amount_paise,
                    $line->description,
                    $line->fund_code,
                    null,
                    $line->id,
                ))->all(),
                reversalOfEntryId: $original->id,
                metadata: [
                    'reversal_reason' => $normalized['reason'],
                    'reversal_original_entry_public_id' => $original->public_id,
                ],
            ));

            try {
                $original->update([
                    'status' => ClubJournalEntry::STATUS_REVERSED,
                    'reversed_by_user_id' => $request->reversalActorUserId,
                ]);
            } catch (LogicException $exception) {
                throw new ReversalLifecycleTransitionException('The original journal entry could not be transitioned.', 0, $exception);
            }

            AuditLog::create([
                'club_id' => $club->id,
                'user_id' => $request->reversalActorUserId,
                'action' => 'finance.journal.reversed',
                'auditable_type' => ClubJournalEntry::class,
                'auditable_id' => $original->id,
                'metadata' => [
                    'reversal_entry_public_id' => $reversal->public_id,
                    'reversal_reason' => $normalized['reason'],
                    'source_type' => $normalized['source_type'],
                    'source_id' => $normalized['source_id'],
                    'source_event' => $normalized['source_event'],
                ],
            ]);

            return $this->canonicalEntry($reversal);
        }, 3);
    }

    private function validateAndNormalize(JournalReversalRequest $request): array
    {
        if ($request->clubId < 1 || $request->originalEntryId < 1 || $request->reversalAccountingPeriodId < 1 || $request->reversalActorUserId < 1) {
            throw new InvalidReversalRequestException('The reversal request is invalid.');
        }
        $effectiveDate = DateTimeImmutable::createFromFormat('!Y-m-d', $request->effectiveDate);
        if (! $effectiveDate || $effectiveDate->format('Y-m-d') !== $request->effectiveDate) {
            throw new InvalidReversalDateException('The reversal effective date is invalid.');
        }

        $reason = trim($request->reason);
        if ($reason === '' || mb_strlen($reason) > 1000 || str_contains($reason, "\0")) {
            throw new InvalidReversalReasonException('The reversal reason is invalid.');
        }
        $sourceType = trim($request->sourceType);
        $sourceId = trim($request->sourceId);
        $sourceEvent = trim($request->sourceEvent);
        $idempotencyKey = strtolower(trim($request->idempotencyKey));
        if ($sourceType === '' || $sourceId === '' || $sourceEvent === ''
            || strlen($sourceType) > 80 || strlen($sourceId) > 120 || strlen($sourceEvent) > 80
            || ! Str::isUuid($idempotencyKey)) {
            throw new InvalidReversalRequestException('The reversal request is invalid.');
        }

        return [
            'club_id' => $request->clubId,
            'original_entry_id' => $request->originalEntryId,
            'reversal_accounting_period_id' => $request->reversalAccountingPeriodId,
            'effective_date' => $effectiveDate->format('Y-m-d'),
            'reversal_actor_user_id' => $request->reversalActorUserId,
            'reason' => $reason,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'source_event' => $sourceEvent,
            'idempotency_key' => $idempotencyKey,
        ];
    }

    private function resolveReplay(Club $club, ClubJournalEntry $original, $originalLines, array $normalized): ?ClubJournalEntry
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
            throw new JournalIdempotencyConflictException('The reversal replay conflicts with an existing posting.');
        }
        if (! $byKey && $bySource) {
            throw new JournalSourceEventConflictException('The reversal source event has already been posted.');
        }
        if ($byKey && ! $bySource) {
            throw new JournalIdempotencyConflictException('The reversal idempotency key conflicts with an existing posting.');
        }

        $existing = $this->canonicalEntry($byKey);
        if ($this->semanticReversal($existing) !== $this->expectedSemanticReversal($original, $originalLines, $normalized)) {
            throw new JournalIdempotencyConflictException('The reversal replay conflicts with an existing posting.');
        }

        return $existing;
    }

    private function expectedSemanticReversal(ClubJournalEntry $original, $originalLines, array $normalized): array
    {
        return [
            'club_id' => $normalized['club_id'],
            'reversal_of_entry_id' => $original->id,
            'accounting_period_id' => $normalized['reversal_accounting_period_id'],
            'effective_date' => $normalized['effective_date'],
            'currency' => ClubJournalEntry::CURRENCY_INR,
            'source_type' => $normalized['source_type'],
            'source_id' => $normalized['source_id'],
            'source_event' => $normalized['source_event'],
            'idempotency_key' => $normalized['idempotency_key'],
            'posting_actor_user_id' => $normalized['reversal_actor_user_id'],
            'description' => sprintf('Reversal of %s: %s', $original->reference, $normalized['reason']),
            'metadata' => [
                'reversal_reason' => $normalized['reason'],
                'reversal_original_entry_public_id' => $original->public_id,
            ],
            'lines' => $originalLines->map(fn (ClubJournalLine $line): array => [
                'account_id' => $line->ledger_account_id,
                'direction' => $line->direction === ClubJournalLine::DIRECTION_DEBIT ? ClubJournalLine::DIRECTION_CREDIT : ClubJournalLine::DIRECTION_DEBIT,
                'amount_paise' => $line->amount_paise,
                'description' => $line->description,
            ])->all(),
        ];
    }

    private function semanticReversal(ClubJournalEntry $entry): array
    {
        return [
            'club_id' => $entry->club_id,
            'reversal_of_entry_id' => $entry->reversal_of_entry_id,
            'accounting_period_id' => $entry->accounting_period_id,
            'effective_date' => $entry->effective_date->format('Y-m-d'),
            'currency' => $entry->currency,
            'source_type' => $entry->source_type,
            'source_id' => $entry->source_id,
            'source_event' => $entry->source_event,
            'idempotency_key' => strtolower($entry->idempotency_key),
            'posting_actor_user_id' => $entry->posted_by_user_id,
            'description' => $entry->description,
            'metadata' => $entry->metadata,
            'lines' => $entry->lines->map(fn (ClubJournalLine $line): array => [
                'account_id' => $line->ledger_account_id,
                'direction' => $line->direction,
                'amount_paise' => $line->amount_paise,
                'description' => $line->description,
            ])->all(),
        ];
    }

    private function canonicalEntry(ClubJournalEntry $entry): ClubJournalEntry
    {
        return $entry->fresh()->load([
            'accountingPeriod',
            'lines' => fn ($query) => $query->orderBy('line_number')->orderBy('id'),
            'reversalOf',
        ]);
    }
}
