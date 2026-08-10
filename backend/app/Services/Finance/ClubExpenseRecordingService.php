<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Club;
use App\Models\ClubAccountingCutoverPackage;
use App\Models\ClubAccountingPeriod;
use App\Models\ClubExpense;
use App\Models\ClubJournalLine;
use App\Models\LedgerAccount;
use App\Models\User;
use App\Services\Finance\Exceptions\ClubExpenseConflictException;
use App\Services\Finance\Exceptions\ClubExpenseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ClubExpenseRecordingService
{
    public const SOURCE_TYPE = 'club_expense';

    public function __construct(private readonly ClubJournalPostingService $journals) {}

    public function record(Club $club, User $actor, array $input): ClubExpense
    {
        $canonical = $this->canonical($input);

        return DB::transaction(function () use ($club, $actor, $canonical): ClubExpense {
            $lockedClub = Club::query()->whereKey($club->id)->lockForUpdate()->firstOrFail();
            $existing = ClubExpense::query()->where('club_id', $lockedClub->id)->where('idempotency_key', $canonical['idempotency_key'])->lockForUpdate()->first();
            if ($existing) {
                if (! hash_equals($existing->request_digest, $canonical['request_digest'])) {
                    throw new ClubExpenseConflictException('The expense idempotency key conflicts with a changed request.');
                }
                return $this->loaded($existing);
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

            $accounts = LedgerAccount::query()->whereIn('public_id', [$canonical['expense_account'], $canonical['payment_account']])->lockForUpdate()->get()->keyBy(fn (LedgerAccount $account) => strtolower($account->public_id));
            $expenseAccount = $accounts->get($canonical['expense_account']);
            $paymentAccount = $accounts->get($canonical['payment_account']);
            $this->validateAccounts($lockedClub, $expenseAccount, $paymentAccount);

            $expense = ClubExpense::create([
                'club_id' => $lockedClub->id, 'accounting_date' => $canonical['accounting_date'],
                'expense_account_id' => $expenseAccount->id, 'payment_account_id' => $paymentAccount->id,
                'fund_code' => $canonical['fund_code'], 'amount_paise' => $canonical['amount_paise'], 'currency' => LedgerAccount::CURRENCY_INR,
                'payee' => $canonical['payee'], 'description' => $canonical['description'], 'external_reference' => $canonical['external_reference'],
                'idempotency_key' => $canonical['idempotency_key'], 'request_digest' => $canonical['request_digest'], 'created_by_user_id' => $actor->id,
            ]);

            $journal = $this->journals->post(new JournalPostingRequest(
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
        $canonical = ['accounting_date' => $input['accounting_date'], 'expense_account' => $expense, 'payment_account' => $payment, 'fund_code' => $input['fund'], 'amount_paise' => (int) $input['amount_paise'], 'payee' => $payee === '' ? null : $payee, 'description' => trim((string) $input['description']), 'external_reference' => $external === '' ? null : $external, 'idempotency_key' => $key];
        if (! Str::isUuid($key) || ! Str::isUuid($expense) || ! Str::isUuid($payment) || ! FundCode::tryFrom($canonical['fund_code']) || $canonical['amount_paise'] < 1) {
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

    private function account(LedgerAccount $account): array { return ['id' => $account->public_id, 'code' => $account->code, 'name' => $account->name]; }
    private function loaded(ClubExpense $expense): ClubExpense { return $expense->fresh()->load(['expenseAccount', 'paymentAccount', 'journalEntry']); }
}
