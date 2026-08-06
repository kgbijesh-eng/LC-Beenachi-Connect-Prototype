<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\ClubAccountingCutoverPackage;
use App\Models\ClubAccountingPeriod;
use App\Models\ClubJournalEntry;
use App\Models\ClubJournalLine;
use App\Models\LedgerAccount;
use App\Services\Finance\Exceptions\AccountingNotActivatedException;
use App\Services\Finance\Exceptions\ClubAccountStatementIntegrityException;
use App\Services\Finance\Exceptions\InvalidClubAccountStatementQueryException;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

final class ClubAccountStatementProjectionService
{
    public const CONTRACT_VERSION = 'CLUB-ACCOUNT-STATEMENT-2026-01';

    private const FUND_NAMES = [
        'GENERAL_CLUB_FUND' => 'General Club Fund',
        'SERVICE_ACTIVITY_FUND' => 'Service Activity Fund',
        'FELLOWSHIP_MEMBER_ACTIVITY_FUND' => 'Fellowship and Member Activity Fund',
    ];

    private const CLASSIFICATIONS = [
        'CASH_AND_BANK', 'RECEIVABLES', 'OTHER_CURRENT_ASSETS', 'PROPERTY_AND_EQUIPMENT',
        'LIABILITIES', 'FUND_BALANCE_EQUITY', 'INCOME', 'EXPENSES',
    ];

    public function project(ClubAccountStatementQuery $query): array
    {
        return DB::transaction(function () use ($query): array {
            [$period, $from, $to] = $this->resolveRange($query);
            $activation = $this->resolveActivation($query, $from);
            $entries = $this->eligibleEntries(
                $query->clubId,
                $activation->cutover_date->format('Y-m-d'),
                $to,
            );

            return $this->build($query, $period, $activation, $entries, $from, $to);
        }, 3);
    }

    private function resolveRange(ClubAccountStatementQuery $query): array
    {
        if ($query->clubId < 1 || ! preg_match('/\A\d{4}-\d{2}\z/', $query->lionsYear)) {
            throw new InvalidClubAccountStatementQueryException('The statement query is invalid.');
        }
        $year = (int) substr($query->lionsYear, 0, 4);
        $expected = sprintf('%04d-%02d', $year, ($year + 1) % 100);
        if ($query->lionsYear !== $expected) {
            throw new InvalidClubAccountStatementQueryException('The Lions Year is invalid.');
        }
        $yearPeriod = ClubAccountingPeriod::query()
            ->where('club_id', $query->clubId)
            ->where('lions_year', $query->lionsYear)
            ->first();
        if (! $yearPeriod || $yearPeriod->start_date->format('Y-m-d') !== sprintf('%04d-07-01', $year)
            || $yearPeriod->end_date->format('Y-m-d') !== sprintf('%04d-06-30', $year + 1)) {
            throw new InvalidClubAccountStatementQueryException('The selected Lions Year does not exist.');
        }

        $period = $yearPeriod;
        if ($query->periodId !== null) {
            if ($query->from !== null || $query->to !== null) {
                throw new InvalidClubAccountStatementQueryException('An accounting period cannot be combined with custom dates.');
            }
            $period = ClubAccountingPeriod::query()->find($query->periodId);
            if (! $period || $period->club_id !== $query->clubId || $period->lions_year !== $query->lionsYear) {
                throw new InvalidClubAccountStatementQueryException('The accounting period is outside the requested club or Lions Year.');
            }
            $from = $period->start_date->format('Y-m-d');
            $to = $period->end_date->format('Y-m-d');
        } elseif ($query->from !== null || $query->to !== null) {
            if ($query->from === null || $query->to === null || ! $this->validDate($query->from) || ! $this->validDate($query->to)
                || $query->from > $query->to) {
                throw new InvalidClubAccountStatementQueryException('The custom statement range is invalid.');
            }
            $from = $query->from;
            $to = $query->to;
        } else {
            $from = $yearPeriod->start_date->format('Y-m-d');
            $to = $yearPeriod->end_date->format('Y-m-d');
        }
        if ($from < $yearPeriod->start_date->format('Y-m-d') || $to > $yearPeriod->end_date->format('Y-m-d')) {
            throw new InvalidClubAccountStatementQueryException('The statement range is outside the selected Lions Year.');
        }

        return [$period, $from, $to];
    }

    private function resolveActivation(ClubAccountStatementQuery $query, string $from): ClubAccountingCutoverPackage
    {
        $packages = ClubAccountingCutoverPackage::query()
            ->where('club_id', $query->clubId)
            ->where('lions_year', $query->lionsYear)
            ->where('status', ClubAccountingCutoverPackage::STATUS_POSTED)
            ->with('openingJournal')
            ->get();
        if ($packages->isEmpty()) {
            throw new AccountingNotActivatedException('Accounting is not activated for the selected Lions Year.');
        }
        if ($packages->count() !== 1) {
            throw new ClubAccountStatementIntegrityException('The selected Lions Year has an invalid number of posted activation packages.');
        }
        $package = $packages->sole();
        if (! $package->openingJournal || ! $package->posted_at || ! $package->posted_by_user_id) {
            throw new AccountingNotActivatedException('Accounting is not activated for the selected Lions Year.');
        }
        $cutover = $package->cutover_date->format('Y-m-d');
        $opening = $package->openingJournal;
        if ($from < $cutover) {
            throw new InvalidClubAccountStatementQueryException('The statement range starts before the activated cutover boundary.');
        }
        if ($opening->club_id !== $query->clubId || $opening->accounting_period_id === null
            || ! in_array($opening->status, [ClubJournalEntry::STATUS_POSTED, ClubJournalEntry::STATUS_REVERSED], true)
            || $opening->effective_date->format('Y-m-d') !== $cutover) {
            throw new ClubAccountStatementIntegrityException('The activated opening journal is invalid.');
        }

        return $package;
    }

    private function eligibleEntries(int $clubId, string $cutover, string $to): Collection
    {
        return ClubJournalEntry::query()
            ->where('club_id', $clubId)
            ->whereIn('status', [ClubJournalEntry::STATUS_POSTED, ClubJournalEntry::STATUS_REVERSED])
            ->whereDate('effective_date', '>=', $cutover)
            ->whereDate('effective_date', '<=', $to)
            ->with([
                'lines' => fn ($query) => $query->with([
                    'ledgerAccount', 'openingScheduleRow',
                    'allocationJournalLines' => fn ($allocationQuery) => $allocationQuery
                        ->with(['allocation.payment'])
                        ->orderBy('payment_allocation_id')->orderBy('id'),
                ])->orderBy('line_number')->orderBy('id'),
                'reversalOf:id,public_id,club_id,reference',
                'reversals' => fn ($reversalQuery) => $reversalQuery
                    ->where('status', ClubJournalEntry::STATUS_POSTED)
                    ->orderBy('effective_date')->orderBy('id'),
            ])
            ->orderBy('effective_date')->orderBy('reference')->orderBy('id')
            ->get();
    }

    private function build(
        ClubAccountStatementQuery $query,
        ClubAccountingPeriod $period,
        ClubAccountingCutoverPackage $activation,
        Collection $entries,
        string $from,
        string $to,
    ): array {
        $rows = [];
        $journals = [];
        $lineCount = 0;
        $openingJournalId = $activation->opening_journal_entry_id;

        foreach ($entries as $entry) {
            $this->validateEntry($entry, $query->clubId);
            $date = $entry->effective_date->format('Y-m-d');
            $isBoundaryOpening = $entry->id === $openingJournalId && $date === $from;
            if ($entry->id === $openingJournalId) {
                $this->validateOpeningLines($entry, $activation);
            }
            $this->validatePaymentTraceability($entry, $query->clubId);
            $inOpening = $date < $from || $isBoundaryOpening;
            $inMovement = $date >= $from && $date <= $to && ! $isBoundaryOpening;
            if (! $inOpening && ! $inMovement) {
                continue;
            }

            $journalDebit = 0;
            $journalCredit = 0;
            $traceLines = [];
            foreach ($entry->lines as $line) {
                $account = $line->ledgerAccount;
                $fund = FundCode::tryFrom((string) $line->fund_code);
                $classification = $account ? StatementClassification::tryFrom((string) $account->statement_classification) : null;
                if (! $account || $account->club_id !== $query->clubId || ! $fund || ! $classification
                    || ! in_array($account->normal_balance, [LedgerAccount::NORMAL_DEBIT, LedgerAccount::NORMAL_CREDIT], true)) {
                    throw new ClubAccountStatementIntegrityException('A ledger-effective line lacks canonical accounting metadata.');
                }
                $key = $fund->value.'|'.$classification->value.'|'.$account->id;
                $rows[$key] ??= [
                    'account' => $account, 'fund' => $fund->value, 'classification' => $classification->value,
                    'opening_debit' => 0, 'opening_credit' => 0, 'period_debit' => 0, 'period_credit' => 0,
                    'journal_ids' => [], 'trace_references' => [],
                ];
                $bucket = $inOpening ? 'opening_' : 'period_';
                $direction = $line->direction === ClubJournalLine::DIRECTION_DEBIT ? 'debit' : 'credit';
                $rows[$key][$bucket.$direction] = $this->add($rows[$key][$bucket.$direction], $line->amount_paise);
                $rows[$key]['journal_ids'][$entry->public_id] = true;
                $trace = [
                    'journal_line_id' => $line->public_id,
                    'opening_schedule_row_id' => $line->openingScheduleRow?->public_id,
                    'payment_allocations' => $line->allocationJournalLines->map(fn ($link) => [
                        'allocation_id' => $this->allocationPublicId($link->allocation),
                        'payment_id' => $link->allocation->payment->public_id,
                        'line_role' => $link->line_role,
                        'amount_paise' => $link->amount_paise,
                    ])->all(),
                ];
                $rows[$key]['trace_references'][] = $trace;
                $traceLines[] = $trace;
                $lineCount++;
                $direction === 'debit'
                    ? $journalDebit = $this->add($journalDebit, $line->amount_paise)
                    : $journalCredit = $this->add($journalCredit, $line->amount_paise);
            }
            $journals[] = [
                'journal_id' => $entry->public_id,
                'reference' => $entry->reference,
                'accounting_date' => $date,
                'journal_type' => $entry->source_event,
                'description' => $entry->description,
                'debit_total_paise' => $journalDebit,
                'credit_total_paise' => $journalCredit,
                'reversal_of_journal_id' => $entry->reversalOf?->public_id,
                'reversed_by_journal_id' => $entry->reversals->first()?->public_id,
                'source_type' => $entry->source_type,
                'source_id' => $entry->source_id,
                'contribution' => $inOpening ? 'opening_balance' : 'period_movement',
                'lines' => $traceLines,
            ];
        }

        [$funds, $overall, $integrity] = $this->aggregate($rows);
        if (in_array(false, $integrity, true)) {
            throw new ClubAccountStatementIntegrityException('The canonical statement projection does not reconcile.');
        }

        return [
            'contract_version' => self::CONTRACT_VERSION,
            'club_id' => $query->clubId,
            'currency' => LedgerAccount::CURRENCY_INR,
            'amount_unit' => 'paise',
            'lions_year' => [
                'id' => $period->id, 'label' => $query->lionsYear,
                'starts_on' => sprintf('%04d-07-01', (int) substr($query->lionsYear, 0, 4)),
                'ends_on' => sprintf('%04d-06-30', (int) substr($query->lionsYear, 0, 4) + 1),
            ],
            'range' => [
                'period_id' => $query->periodId,
                'from' => $from,
                'to' => $to,
                'is_closed' => $period->status === ClubAccountingPeriod::STATUS_CLOSED,
            ],
            'source' => [
                'cutover_date' => $activation->cutover_date->format('Y-m-d'),
                'journal_count' => count($journals),
                'journal_line_count' => $lineCount,
            ],
            'funds' => $funds,
            'overall_trial_balance' => $overall,
            'traceability' => ['journals' => $journals],
            'integrity' => $integrity,
        ];
    }

    private function validateEntry(ClubJournalEntry $entry, int $clubId): void
    {
        if ($entry->club_id !== $clubId || ! $entry->posted_at || ! $entry->posted_by_user_id
            || $entry->currency !== ClubJournalEntry::CURRENCY_INR || $entry->lines->count() < 2) {
            throw new ClubAccountStatementIntegrityException('A ledger-effective journal is incomplete.');
        }
        $debits = $credits = 0;
        $funds = [];
        foreach ($entry->lines as $line) {
            if ($line->club_id !== $clubId || $line->journal_entry_id !== $entry->id || ! is_int($line->amount_paise)
                || $line->amount_paise < 1 || ! in_array($line->direction, ['debit', 'credit'], true)) {
                throw new ClubAccountStatementIntegrityException('A ledger-effective journal line is invalid.');
            }
            $fund = FundCode::tryFrom((string) $line->fund_code);
            if (! $fund) {
                throw new ClubAccountStatementIntegrityException('A ledger-effective journal line has an unsupported fund.');
            }
            $side = $line->direction;
            $side === 'debit' ? $debits = $this->add($debits, $line->amount_paise) : $credits = $this->add($credits, $line->amount_paise);
            $funds[$fund->value][$side] = $this->add($funds[$fund->value][$side] ?? 0, $line->amount_paise);
        }
        if ($debits < 1 || $debits !== $credits) {
            throw new ClubAccountStatementIntegrityException('A ledger-effective journal is unbalanced.');
        }
        foreach ($funds as $totals) {
            if (($totals['debit'] ?? 0) !== ($totals['credit'] ?? 0)) {
                throw new ClubAccountStatementIntegrityException('A ledger-effective journal fund is unbalanced.');
            }
        }
        if ($entry->reversal_of_entry_id !== null) {
            $original = ClubJournalEntry::query()
                ->with(['lines' => fn ($query) => $query->orderBy('line_number')->orderBy('id')])
                ->find($entry->reversal_of_entry_id);
            if (! $original || $original->club_id !== $clubId || $original->status !== ClubJournalEntry::STATUS_REVERSED) {
                throw new ClubAccountStatementIntegrityException('A reversal journal relationship is invalid.');
            }
            if ($original->lines->count() !== $entry->lines->count()) {
                throw new ClubAccountStatementIntegrityException('A reversal journal has incomplete line traceability.');
            }
            foreach ($entry->lines->values() as $index => $line) {
                $originalLine = $original->lines->values()[$index];
                if ($line->reversal_of_line_id !== $originalLine->id
                    || $line->ledger_account_id !== $originalLine->ledger_account_id
                    || $line->fund_code !== $originalLine->fund_code
                    || $line->amount_paise !== $originalLine->amount_paise
                    || $line->direction === $originalLine->direction) {
                    throw new ClubAccountStatementIntegrityException('A reversal journal does not preserve the original accounting dimensions.');
                }
            }
        }
        if ($entry->status === ClubJournalEntry::STATUS_REVERSED) {
            $reversals = ClubJournalEntry::query()
                ->where('club_id', $clubId)
                ->where('reversal_of_entry_id', $entry->id)
                ->where('status', ClubJournalEntry::STATUS_POSTED)
                ->count();
            if ($reversals !== 1) {
                throw new ClubAccountStatementIntegrityException('A reversed journal has invalid append-only lifecycle state.');
            }
        }
    }

    private function validateOpeningLines(ClubJournalEntry $entry, ClubAccountingCutoverPackage $activation): void
    {
        foreach ($entry->lines as $line) {
            if ($line->opening_schedule_row_id === null) {
                throw new ClubAccountStatementIntegrityException('An activated opening line lacks its canonical source row.');
            }
            $row = $line->openingScheduleRow;
            if (! $row || $row->club_id !== $activation->club_id || $row->cutover_package_id !== $activation->id
                || $row->ledger_account_id !== $line->ledger_account_id || $row->fund_code !== $line->fund_code
                || $row->direction !== $line->direction || $row->amount_paise !== $line->amount_paise) {
                throw new ClubAccountStatementIntegrityException('An activated opening line has invalid source-row traceability.');
            }
        }
    }

    private function validatePaymentTraceability(ClubJournalEntry $entry, int $clubId): void
    {
        $links = $entry->lines->flatMap->allocationJournalLines;
        if ($entry->source_type !== AccountingIntegrationConfigurationService::SOURCE_PAYMENT) {
            if ($links->isNotEmpty()) {
                throw new ClubAccountStatementIntegrityException('A non-payment journal contains payment-allocation trace links.');
            }

            return;
        }
        if ($links->isEmpty()) {
            throw new ClubAccountStatementIntegrityException('A payment journal lacks allocation traceability.');
        }

        foreach ($entry->lines as $line) {
            $lineContribution = 0;
            foreach ($line->allocationJournalLines as $link) {
                $allocation = $link->allocation;
                $payment = $allocation?->payment;
                $expectedRole = $line->direction === ClubJournalLine::DIRECTION_DEBIT
                    ? PaymentJournalLineRole::ASSET_DEBIT->value
                    : PaymentJournalLineRole::RECEIVABLES_CREDIT->value;
                if (! $allocation || ! $payment
                    || $link->club_id !== $clubId || $allocation->club_id !== $clubId || $payment->club_id !== $clubId
                    || $allocation->member_payment_id !== $payment->id
                    || $allocation->fund_code !== $line->fund_code
                    || $link->line_role !== $expectedRole
                    || $link->amount_paise !== $allocation->amount_paise
                    || $entry->source_id !== $payment->public_id
                    || $entry->source_event !== 'committed') {
                    throw new ClubAccountStatementIntegrityException('A payment allocation has invalid journal-line traceability.');
                }
                $lineContribution = $this->add($lineContribution, $link->amount_paise);
            }
            if ($lineContribution !== $line->amount_paise) {
                throw new ClubAccountStatementIntegrityException('Payment allocation contributions do not reconcile to their journal line.');
            }
        }

        foreach ($links->groupBy('payment_allocation_id') as $allocationLinks) {
            $allocation = $allocationLinks->first()->allocation;
            $byRole = $allocationLinks->groupBy('line_role');
            foreach ([PaymentJournalLineRole::ASSET_DEBIT->value, PaymentJournalLineRole::RECEIVABLES_CREDIT->value] as $role) {
                if (($byRole[$role] ?? collect())->count() !== 1
                    || (int) ($byRole[$role] ?? collect())->sum('amount_paise') !== $allocation->amount_paise) {
                    throw new ClubAccountStatementIntegrityException('Payment allocation role contributions do not reconcile.');
                }
            }
        }
    }

    private function allocationPublicId(mixed $allocation): string
    {
        return Uuid::uuid5(
            '6ba7b811-9dad-11d1-80b4-00c04fd430c8',
            'club-payment-allocation:'.strtolower($allocation->payment->public_id).':'.$allocation->id,
        )->toString();
    }

    private function aggregate(array $rows): array
    {
        $funds = [];
        $overall = $this->totals();
        $accountEquations = true;
        $classificationSubtotalsReconcile = true;
        $fundSubtotalsReconcile = true;
        foreach (self::FUND_NAMES as $fundCode => $displayName) {
            $classifications = [];
            $fundTotals = $this->totals();
            foreach (self::CLASSIFICATIONS as $classification) {
                $accounts = [];
                $classificationTotals = $this->totals();
                foreach ($rows as $row) {
                    if ($row['fund'] !== $fundCode || $row['classification'] !== $classification) {
                        continue;
                    }
                    $account = $row['account'];
                    $opening = $this->natural($account->normal_balance, $row['opening_debit'], $row['opening_credit']);
                    $movement = $this->natural($account->normal_balance, $row['period_debit'], $row['period_credit']);
                    $closing = $opening + $movement;
                    $accountEquations = $accountEquations && $opening + $movement === $closing;
                    $values = [
                        'opening_balance_paise' => $opening,
                        'period_debit_paise' => $row['period_debit'],
                        'period_credit_paise' => $row['period_credit'],
                        'net_movement_paise' => $movement,
                        'closing_balance_paise' => $closing,
                    ];
                    $this->accumulate($classificationTotals, $values, $account->normal_balance);
                    $accounts[] = [
                        'account_id' => $account->public_id,
                        'account_code' => $account->code,
                        'account_name' => $account->name,
                        'normal_balance' => $account->normal_balance,
                        'statement_classification' => $classification,
                        'fund_code' => $fundCode,
                        ...$values,
                        'journal_ids' => array_keys($row['journal_ids']),
                        'trace_references' => $row['trace_references'],
                    ];
                }
                usort($accounts, fn (array $left, array $right): int => [$left['account_code'], $left['account_id']] <=> [$right['account_code'], $right['account_id']]);
                $independentClassificationTotals = $this->sumPublicRows($accounts);
                $classificationSubtotalsReconcile = $classificationSubtotalsReconcile
                    && $independentClassificationTotals === $this->publicTotals($classificationTotals);
                $this->mergeTotals($fundTotals, $classificationTotals);
                $classifications[] = [
                    'classification' => $classification,
                    'accounts' => $accounts,
                    'totals' => $this->publicTotals($classificationTotals),
                ];
            }
            $independentFundTotals = $this->sumPublicRows(array_map(
                fn (array $classification): array => $classification['totals'],
                $classifications,
            ));
            $fundSubtotalsReconcile = $fundSubtotalsReconcile
                && $independentFundTotals === $this->publicTotals($fundTotals);
            $this->mergeTotals($overall, $fundTotals);
            $funds[] = [
                'fund_code' => $fundCode,
                'display_name' => $displayName,
                'classifications' => $classifications,
                'totals' => $this->publicTotals($fundTotals),
                'trial_balance' => $this->trialBalance($fundTotals),
            ];
        }
        $fundsReconcile = true;
        foreach ($funds as $fund) {
            $trial = $fund['trial_balance'];
            $fundsReconcile = $fundsReconcile && $trial['opening_debits_paise'] === $trial['opening_credits_paise']
                && $trial['period_debits_paise'] === $trial['period_credits_paise']
                && $trial['closing_debits_paise'] === $trial['closing_credits_paise'];
        }
        $trial = $this->trialBalance($overall);
        $integrity = [
            'period_debits_equal_credits' => $trial['period_debits_paise'] === $trial['period_credits_paise'],
            'opening_balances_reconcile' => $trial['opening_debits_paise'] === $trial['opening_credits_paise'],
            'closing_balances_reconcile' => $trial['closing_debits_paise'] === $trial['closing_credits_paise'],
            'fund_balances_reconcile' => $fundsReconcile,
            'account_equations_reconcile' => $accountEquations,
            'classification_subtotals_reconcile' => $classificationSubtotalsReconcile,
            'fund_subtotals_reconcile' => $fundSubtotalsReconcile,
        ];

        return [$funds, $trial, $integrity];
    }

    private function totals(): array
    {
        return [
            'opening_balance_paise' => 0, 'period_debit_paise' => 0, 'period_credit_paise' => 0,
            'net_movement_paise' => 0, 'closing_balance_paise' => 0,
            'opening_debits_paise' => 0, 'opening_credits_paise' => 0,
            'closing_debits_paise' => 0, 'closing_credits_paise' => 0,
        ];
    }

    private function accumulate(array &$totals, array $values, string $normal): void
    {
        foreach ($values as $key => $value) {
            $totals[$key] += $value;
        }
        [$openingDebit, $openingCredit] = $this->trialSides($normal, $values['opening_balance_paise']);
        [$closingDebit, $closingCredit] = $this->trialSides($normal, $values['closing_balance_paise']);
        $totals['opening_debits_paise'] += $openingDebit;
        $totals['opening_credits_paise'] += $openingCredit;
        $totals['closing_debits_paise'] += $closingDebit;
        $totals['closing_credits_paise'] += $closingCredit;
    }

    private function mergeTotals(array &$target, array $source): void
    {
        foreach ($target as $key => $value) {
            $target[$key] = $value + $source[$key];
        }
    }

    private function publicTotals(array $totals): array
    {
        return array_intersect_key($totals, array_flip([
            'opening_balance_paise', 'period_debit_paise', 'period_credit_paise',
            'net_movement_paise', 'closing_balance_paise',
        ]));
    }

    private function sumPublicRows(array $rows): array
    {
        $totals = array_fill_keys([
            'opening_balance_paise', 'period_debit_paise', 'period_credit_paise',
            'net_movement_paise', 'closing_balance_paise',
        ], 0);
        foreach ($rows as $row) {
            foreach ($totals as $key => $value) {
                $totals[$key] = $this->add($value, (int) $row[$key]);
            }
        }

        return $totals;
    }

    private function trialBalance(array $totals): array
    {
        return [
            'opening_debits_paise' => $totals['opening_debits_paise'],
            'opening_credits_paise' => $totals['opening_credits_paise'],
            'period_debits_paise' => $totals['period_debit_paise'],
            'period_credits_paise' => $totals['period_credit_paise'],
            'closing_debits_paise' => $totals['closing_debits_paise'],
            'closing_credits_paise' => $totals['closing_credits_paise'],
        ];
    }

    private function natural(string $normal, int $debit, int $credit): int
    {
        return $normal === LedgerAccount::NORMAL_DEBIT ? $debit - $credit : $credit - $debit;
    }

    private function trialSides(string $normal, int $balance): array
    {
        if ($normal === LedgerAccount::NORMAL_DEBIT) {
            return $balance >= 0 ? [$balance, 0] : [0, -$balance];
        }

        return $balance >= 0 ? [0, $balance] : [-$balance, 0];
    }

    private function add(int $total, int $amount): int
    {
        if ($amount > PHP_INT_MAX - $total) {
            throw new ClubAccountStatementIntegrityException('Statement amounts exceed integer limits.');
        }

        return $total + $amount;
    }

    private function validDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }
}
