<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Club;
use App\Models\ClubAccountingCutoverPackage;
use App\Models\ClubAccountingPeriod;
use App\Models\ClubExpense;
use App\Models\ClubJournalEntry;
use App\Models\ClubJournalLine;
use App\Models\LedgerAccount;
use App\Models\User;
use App\Services\Finance\Exceptions\ClubExpenseConflictException;
use App\Services\Finance\Exceptions\ClubExpenseException;
use Illuminate\Support\Facades\DB;
use App\Services\Access\OrganisationTransactionContext;
use App\Services\Access\OrganisationTransactionGuard;
use App\Services\Access\TransactionActorAuthorisation;
use Illuminate\Support\Str;

final class ClubExpenseRecordingService
{
    public const SOURCE_TYPE = 'club_expense';

    public function __construct(private readonly ClubJournalPostingService $journals) {}

    public function record(Club $club, User $actor, array $input): ClubExpense
    {
        $canonical = $this->canonical($input);

        return app(OrganisationTransactionGuard::class)->run($club, function (OrganisationTransactionContext $context) use ($club, $actor, $canonical): ClubExpense {
            app(TransactionActorAuthorisation::class)->requirePermission($context, $club->id, $actor, 'finance.club.expenses.create');
            $lockedClub = Club::query()->whereKey($club->id)->lockForUpdate()->firstOrFail();
            $existing = ClubExpense::query()->where('club_id', $lockedClub->id)->where('idempotency_key', $canonical['idempotency_key'])->lockForUpdate()->first();
            if ($existing) {
                if (! hash_equals($existing->request_digest, $canonical['request_digest'])) {
                    throw new ClubExpenseConflictException('The expense idempotency key conflicts with a changed request.');
                }
                $loaded = $this->loaded($existing);
                $this->assertReplayProvenance($loaded, $canonical);

                return $loaded;
            }

            $period = ClubAccountingPeriod::query()->where('club_id', $lockedClub->id)->where('start_date', '<=', $canonical['accounting_date'])->where('end_date', '>=', $canonical['accounting_date'])->lockForUpdate()->first();
            if (! $period) {
                throw new ClubExpenseException('The accounting date is outside an available Lions Year period.');
            }
            if ($period->status !== ClubAccountingPeriod::STATUS_OPEN) {
                throw new ClubExpenseException('The selected accounting period is closed.');
            }
            $coverage = ClubAccountingCutoverPackage::query()->where('club_id', $lockedClub->id)->where('status', 'posted')->whereNotNull('opening_journal_entry_id')->where('cutover_date', '<=', $canonical['accounting_date'])->lockForUpdate()->first();
            if (! $coverage) {
                throw new ClubExpenseException('Authoritative accounting is not active for the selected date.');
            }

            $correctedFrom = null;
            if ($canonical['corrected_from_expense']) {
                $correctedFrom = ClubExpense::query()->where('club_id', $lockedClub->id)->where('public_id', $canonical['corrected_from_expense'])->lockForUpdate()->first();
                if (! $correctedFrom || ! $correctedFrom->reversal()->exists() || $correctedFrom->correctedReplacement()->exists()) {
                    throw new ClubExpenseConflictException('The corrected expense source is unavailable or already has a replacement.');
                }
            }

            $accounts = LedgerAccount::query()->where('club_id', $lockedClub->id)->whereIn('public_id', [$canonical['expense_account'], $canonical['payment_account']])->lockForUpdate()->get()->keyBy(fn (LedgerAccount $account) => strtolower($account->public_id));
            $expenseAccount = $accounts->get($canonical['expense_account']);
            $paymentAccount = $accounts->get($canonical['payment_account']);
            $this->validateAccounts($lockedClub, $expenseAccount, $paymentAccount);

            $expense = ClubExpense::create([
                'club_id' => $lockedClub->id, 'accounting_date' => $canonical['accounting_date'],
                'expense_account_id' => $expenseAccount->id, 'payment_account_id' => $paymentAccount->id,
                'fund_code' => $canonical['fund_code'], 'amount_paise' => $canonical['amount_paise'], 'currency' => LedgerAccount::CURRENCY_INR,
                'payee' => $canonical['payee'], 'description' => $canonical['description'], 'external_reference' => $canonical['external_reference'],
                'idempotency_key' => $canonical['idempotency_key'], 'request_digest' => $canonical['request_digest'], 'created_by_user_id' => $actor->id,
                'corrected_from_expense_id' => $correctedFrom?->id,
            ]);

            $journal = $this->journals->postWithin($context, new JournalPostingRequest(
                clubId: $lockedClub->id, accountingPeriodId: $period->id, effectiveDate: $canonical['accounting_date'],
                description: $canonical['description'], currency: LedgerAccount::CURRENCY_INR,
                sourceType: self::SOURCE_TYPE, sourceId: $expense->public_id, sourceEvent: 'recorded',
                idempotencyKey: $canonical['idempotency_key'], postingActorUserId: $actor->id,
                lines: [
                    new JournalLineInput($expenseAccount->id, ClubJournalLine::DIRECTION_DEBIT, $canonical['amount_paise'], $canonical['description'], $canonical['fund_code']),
                    new JournalLineInput($paymentAccount->id, ClubJournalLine::DIRECTION_CREDIT, $canonical['amount_paise'], $canonical['description'], $canonical['fund_code']),
                ],
                metadata: ['expense_public_id' => $expense->public_id, 'payee' => $canonical['payee'], 'external_reference' => $canonical['external_reference']],
            ));
            $expense->updateQuietly(['journal_entry_id' => $journal->id]);

            return $this->loaded($expense);
        }, 3);
    }

    public function options(Club $club): array
    {
        $accounts = LedgerAccount::query()->where('club_id', $club->id)->where('is_active', true)->where('currency', LedgerAccount::CURRENCY_INR)->orderBy('code')->get();
        return [
            'expense_accounts' => $accounts->where('account_type', LedgerAccount::TYPE_EXPENSE)->where('statement_classification', StatementClassification::EXPENSES->value)->map(fn ($a) => $this->account($a))->values()->all(),
            'payment_accounts' => $accounts->where('account_type', LedgerAccount::TYPE_ASSET)->where('statement_classification', StatementClassification::CASH_AND_BANK->value)->map(fn ($a) => $this->account($a))->values()->all(),
            'funds' => array_map(fn (FundCode $fund) => $fund->value, FundCode::cases()),
            'currency' => LedgerAccount::CURRENCY_INR,
        ];
    }

    private function canonical(array $input): array
    {
        $key = strtolower(trim((string) $input['idempotency_key']));
        $expense = strtolower(trim((string) $input['expense_account']));
        $payment = strtolower(trim((string) $input['payment_account']));
        $payee = isset($input['payee']) ? trim((string) $input['payee']) : null;
        $external = isset($input['external_reference']) ? trim((string) $input['external_reference']) : null;
        $correctedFrom = isset($input['corrected_from_expense']) ? strtolower(trim((string) $input['corrected_from_expense'])) : null;
        $canonical = ['accounting_date' => $input['accounting_date'], 'expense_account' => $expense, 'payment_account' => $payment, 'fund_code' => $input['fund'], 'amount_paise' => (int) $input['amount_paise'], 'payee' => $payee === '' ? null : $payee, 'description' => trim((string) $input['description']), 'external_reference' => $external === '' ? null : $external, 'idempotency_key' => $key, 'corrected_from_expense' => $correctedFrom === '' ? null : $correctedFrom];
        if (! Str::isUuid($key) || ! Str::isUuid($expense) || ! Str::isUuid($payment) || ($canonical['corrected_from_expense'] !== null && ! Str::isUuid($canonical['corrected_from_expense'])) || ! FundCode::tryFrom($canonical['fund_code']) || $canonical['amount_paise'] < 1) {
            throw new ClubExpenseException('The expense request is invalid.');
        }
        $canonical['request_digest'] = hash('sha256', json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        return $canonical;
    }

    private function validateAccounts(Club $club, ?LedgerAccount $expense, ?LedgerAccount $payment): void
    {
        if (! $expense || ! $payment || $expense->club_id !== $club->id || $payment->club_id !== $club->id) throw new ClubExpenseException('The selected accounts are unavailable for this club.');
        if (! $expense->is_active || $expense->currency !== LedgerAccount::CURRENCY_INR || $expense->account_type !== LedgerAccount::TYPE_EXPENSE || $expense->statement_classification !== StatementClassification::EXPENSES->value) throw new ClubExpenseException('The selected expense account is not eligible.');
        if (! $payment->is_active || $payment->currency !== LedgerAccount::CURRENCY_INR || $payment->account_type !== LedgerAccount::TYPE_ASSET || $payment->statement_classification !== StatementClassification::CASH_AND_BANK->value) throw new ClubExpenseException('The selected payment account is not eligible.');
    }

    private function assertReplayProvenance(ClubExpense $expense, array $canonical): void
    {
        $journal = $expense->journalEntry;
        $lines = $journal?->lines?->sortBy('line_number')->values();
        $validStatus = $journal?->status === ClubJournalEntry::STATUS_POSTED;

        if ($journal?->status === ClubJournalEntry::STATUS_REVERSED) {
            $reversal = $expense->reversal;
            $reversalJournal = $reversal?->journalEntry;
            $reversalLines = $reversalJournal?->lines?->sortBy('line_number')->values();
            $validStatus = $reversalJournal
                && $reversal->club_expense_id === $expense->id
                && $reversal->reversal_journal_entry_id === $reversalJournal->id
                && $reversalJournal->club_id === $expense->club_id
                && $reversalJournal->reversal_of_entry_id === $journal->id
                && $reversalJournal->source_type === ClubExpenseReversalService::SOURCE_TYPE
                && $reversalJournal->source_id === $expense->public_id
                && $reversalJournal->source_event === 'reversed'
                && strtolower((string) $reversalJournal->idempotency_key) === strtolower((string) $reversal->idempotency_key)
                && $reversalJournal->status === ClubJournalEntry::STATUS_POSTED
                && $reversalJournal->currency === LedgerAccount::CURRENCY_INR
                && $reversalLines?->count() === 2
                && $reversalLines[0]->club_id === $expense->club_id
                && $reversalLines[1]->club_id === $expense->club_id
                && $reversalLines[0]->ledger_account_id === $expense->expense_account_id
                && $reversalLines[0]->direction === ClubJournalLine::DIRECTION_CREDIT
                && $reversalLines[1]->ledger_account_id === $expense->payment_account_id
                && $reversalLines[1]->direction === ClubJournalLine::DIRECTION_DEBIT
                && $reversalLines[0]->amount_paise === $expense->amount_paise
                && $reversalLines[1]->amount_paise === $expense->amount_paise
                && $reversalLines[0]->fund_code === $expense->fund_code
                && $reversalLines[1]->fund_code === $expense->fund_code;
        }

        $valid = $journal
            && $expense->journal_entry_id === $journal->id
            && $journal->club_id === $expense->club_id
            && $journal->source_type === self::SOURCE_TYPE
            && $journal->source_id === $expense->public_id
            && $journal->source_event === 'recorded'
            && strtolower((string) $journal->idempotency_key) === $canonical['idempotency_key']
            && $validStatus
            && $journal->currency === LedgerAccount::CURRENCY_INR
            && $expense->currency === LedgerAccount::CURRENCY_INR
            && $lines?->count() === 2
            && $lines[0]->club_id === $expense->club_id
            && $lines[1]->club_id === $expense->club_id
            && $lines[0]->ledger_account_id === $expense->expense_account_id
            && $lines[0]->direction === ClubJournalLine::DIRECTION_DEBIT
            && $lines[1]->ledger_account_id === $expense->payment_account_id
            && $lines[1]->direction === ClubJournalLine::DIRECTION_CREDIT
            && $lines[0]->amount_paise === $expense->amount_paise
            && $lines[1]->amount_paise === $expense->amount_paise
            && $lines[0]->fund_code === $expense->fund_code
            && $lines[1]->fund_code === $expense->fund_code;

        if (! $valid) {
            throw new ClubExpenseConflictException('The stored expense journal provenance is invalid.');
        }
    }

    private function account(LedgerAccount $account): array { return ['id' => $account->public_id, 'code' => $account->code, 'name' => $account->name]; }
    private function loaded(ClubExpense $expense): ClubExpense { return $expense->fresh()->load(['expenseAccount', 'paymentAccount', 'journalEntry.lines', 'reversal.journalEntry.lines', 'correctedReplacement.journalEntry', 'correctedFrom']); }
}
