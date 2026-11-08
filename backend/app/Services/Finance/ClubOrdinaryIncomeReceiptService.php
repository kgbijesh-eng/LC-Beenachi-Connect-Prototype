<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\{Club, ClubAccountingCutoverPackage, ClubAccountingPeriod, ClubJournalLine, ClubOrdinaryIncomeReceipt, LedgerAccount, User};
use App\Services\Finance\Exceptions\{ClubOrdinaryIncomeReceiptConflictException, ClubOrdinaryIncomeReceiptException};
use Illuminate\Support\Facades\DB;
use App\Services\Access\OrganisationTransactionContext;
use App\Services\Access\OrganisationTransactionGuard;
use Illuminate\Support\Str;

final class ClubOrdinaryIncomeReceiptService
{
    public const SOURCE_TYPE = 'club_ordinary_income_receipt';
    public const SUBTYPES = ['sponsorship', 'advertising', 'event_proceeds', 'interest', 'facility_income', 'other_approved_ordinary_income'];

    public function __construct(private readonly ClubJournalPostingService $journals) {}

    public function record(Club $club, User $actor, array $input): ClubOrdinaryIncomeReceipt
    {
        $canonical = $this->canonical($input);
        return app(OrganisationTransactionGuard::class)->run($club,
            fn (OrganisationTransactionContext $context): ClubOrdinaryIncomeReceipt => $this->recordBody($context, $club, $actor, $canonical), 3);
    }

    public function recordWithin(OrganisationTransactionContext $context, Club $club, User $actor, array $input): ClubOrdinaryIncomeReceipt
    {
        $canonical = $this->canonical($input);
        return app(OrganisationTransactionGuard::class)->within($context, $club,
            fn (): ClubOrdinaryIncomeReceipt => DB::transaction(
                fn (): ClubOrdinaryIncomeReceipt => $this->recordBody($context, $club, $actor, $canonical), 3));
    }

    private function recordBody(OrganisationTransactionContext $context, Club $club, User $actor, array $canonical): ClubOrdinaryIncomeReceipt
    {
        $lockedClub = Club::query()->whereKey($club->id)->lockForUpdate()->firstOrFail();
        $existing = ClubOrdinaryIncomeReceipt::query()->where('club_id', $lockedClub->id)->where('idempotency_key', $canonical['idempotency_key'])->lockForUpdate()->first();
        if ($existing) {
            if (! hash_equals($existing->request_digest, $canonical['request_digest'])) throw new ClubOrdinaryIncomeReceiptConflictException('The ordinary income receipt idempotency key conflicts with a changed request.');
            $loaded = $this->loaded($existing);
            $this->assertReplayProvenance($loaded, $canonical);
            return $loaded;
        }
        ['period' => $period, 'covered' => $covered] = $this->lockedPostingWindow($context, $lockedClub, $canonical['accounting_date']);
        if (! $period || $period->status !== ClubAccountingPeriod::STATUS_OPEN) throw new ClubOrdinaryIncomeReceiptException('The receipt date must belong to an open accounting period.');
        if (! $covered) throw new ClubOrdinaryIncomeReceiptException('Authoritative accounting is not active for the selected date.');
        $correctedFrom = null;
        if ($canonical['corrected_from_receipt'] ?? null) {
            $correctedFrom = ClubOrdinaryIncomeReceipt::query()->where('club_id', $lockedClub->id)->where('public_id', $canonical['corrected_from_receipt'])->lockForUpdate()->first();
            if (! $correctedFrom || ! $correctedFrom->correction()->exists() || $correctedFrom->correctedReplacement()->exists()) throw new ClubOrdinaryIncomeReceiptConflictException('The corrected ordinary income receipt is unavailable or already has a replacement.');
        }
        $accounts = LedgerAccount::query()->where('club_id', $lockedClub->id)->whereIn('public_id', [$canonical['receiving_account'], $canonical['income_account']])->lockForUpdate()->get()->keyBy(fn ($account) => strtolower($account->public_id));
        $receiving = $accounts->get($canonical['receiving_account']); $income = $accounts->get($canonical['income_account']);
        $this->validateAccounts($lockedClub, $receiving, $income);
        $receipt = ClubOrdinaryIncomeReceipt::create([
            'club_id' => $lockedClub->id, 'subtype' => $canonical['subtype'], 'accounting_date' => $canonical['accounting_date'],
            'receiving_account_id' => $receiving->id, 'income_account_id' => $income->id, 'fund_code' => $canonical['fund_code'],
            'amount_paise' => $canonical['amount_paise'], 'currency' => LedgerAccount::CURRENCY_INR, 'description' => $canonical['description'],
            'payer_name' => $canonical['payer_name'], 'external_reference' => $canonical['external_reference'], 'event_label' => $canonical['event_label'],
            'idempotency_key' => $canonical['idempotency_key'], 'request_digest' => $canonical['request_digest'], 'created_by_user_id' => $actor->id, 'corrected_from_receipt_id' => $correctedFrom?->id,
        ]);
        $journal = $this->journals->postWithin($context, new JournalPostingRequest(
            clubId: $lockedClub->id, accountingPeriodId: $period->id, effectiveDate: $canonical['accounting_date'], description: $canonical['description'], currency: LedgerAccount::CURRENCY_INR,
            sourceType: self::SOURCE_TYPE, sourceId: $receipt->public_id, sourceEvent: 'recorded', idempotencyKey: $canonical['idempotency_key'], postingActorUserId: $actor->id,
            lines: [
                new JournalLineInput($receiving->id, ClubJournalLine::DIRECTION_DEBIT, $canonical['amount_paise'], $canonical['description'], $canonical['fund_code']),
                new JournalLineInput($income->id, ClubJournalLine::DIRECTION_CREDIT, $canonical['amount_paise'], $canonical['description'], $canonical['fund_code']),
            ],
            metadata: ['ordinary_income_receipt_public_id' => $receipt->public_id, 'subtype' => $canonical['subtype'], 'payer_name' => $canonical['payer_name'], 'external_reference' => $canonical['external_reference'], 'event_label' => $canonical['event_label']],
        ));
        $receipt->updateQuietly(['journal_entry_id' => $journal->id]);
        return $this->loaded($receipt);

    }

    public function options(Club $club): array
    {
        $accounts = LedgerAccount::query()->where('club_id', $club->id)->where('is_active', true)->where('currency', LedgerAccount::CURRENCY_INR)->orderBy('code')->get();
        $map = fn ($a) => ['id' => $a->public_id, 'code' => $a->code, 'name' => $a->name];
        return ['subtypes' => self::SUBTYPES, 'receiving_accounts' => $accounts->where('account_type', LedgerAccount::TYPE_ASSET)->where('statement_classification', StatementClassification::CASH_AND_BANK->value)->map($map)->values()->all(), 'income_accounts' => $accounts->where('account_type', LedgerAccount::TYPE_INCOME)->where('statement_classification', StatementClassification::INCOME->value)->map($map)->values()->all(), 'funds' => array_map(fn (FundCode $fund) => $fund->value, FundCode::cases()), 'currency' => LedgerAccount::CURRENCY_INR, 'qualification' => 'Use this only for earned, unrestricted, non-refundable club income.'];
    }

    /** @return array{period: ?ClubAccountingPeriod, covered: bool} */
    public function lockedPostingWindow(OrganisationTransactionContext $context, Club $club, string $accountingDate): array
    {
        $context->assertUsable($club);
        $period = ClubAccountingPeriod::query()->where('club_id', $club->id)->where('start_date', '<=', $accountingDate)->where('end_date', '>=', $accountingDate)->lockForUpdate()->first();
        $covered = ClubAccountingCutoverPackage::query()->where('club_id', $club->id)->where('status', 'posted')->whereNotNull('opening_journal_entry_id')->where('cutover_date', '<=', $accountingDate)->lockForUpdate()->exists();
        return compact('period', 'covered');
    }

    public function assertPersistedProvenance(ClubOrdinaryIncomeReceipt $receipt): void
    {
        $canonical = $this->assertPersistedRequestProvenance($receipt);
        $this->assertReplayProvenance($receipt, $canonical);
    }

    public function assertPersistedRequestProvenance(ClubOrdinaryIncomeReceipt $receipt): array
    {
        $input = ['subtype' => $receipt->subtype, 'accounting_date' => $receipt->accounting_date->format('Y-m-d'), 'receiving_account' => $receipt->receivingAccount->public_id, 'income_account' => $receipt->incomeAccount->public_id, 'fund' => $receipt->fund_code, 'amount_paise' => $receipt->amount_paise, 'description' => $receipt->description, 'payer_name' => $receipt->payer_name, 'external_reference' => $receipt->external_reference, 'event_label' => $receipt->event_label, 'idempotency_key' => $receipt->idempotency_key, 'corrected_from_receipt' => $receipt->correctedFrom?->public_id];
        $canonical = $this->canonical($input);
        if (! hash_equals($receipt->request_digest, $canonical['request_digest'])) throw new ClubOrdinaryIncomeReceiptConflictException('The stored ordinary income receipt request provenance is invalid.');
        return $canonical;
    }

    private function canonical(array $input): array
    {
        $nullable = fn (string $key) => ($value = trim((string) ($input[$key] ?? ''))) === '' ? null : $value;
        $correctedFrom = strtolower(trim((string) ($input['corrected_from_receipt'] ?? '')));
        $canonical = ['subtype' => strtolower(trim((string) $input['subtype'])), 'accounting_date' => $input['accounting_date'], 'receiving_account' => strtolower(trim((string) $input['receiving_account'])), 'income_account' => strtolower(trim((string) $input['income_account'])), 'fund_code' => $input['fund'], 'amount_paise' => (int) $input['amount_paise'], 'description' => trim((string) $input['description']), 'payer_name' => $nullable('payer_name'), 'external_reference' => $nullable('external_reference'), 'event_label' => $nullable('event_label'), 'idempotency_key' => strtolower(trim((string) $input['idempotency_key']))];
        if ($correctedFrom !== '') $canonical['corrected_from_receipt'] = $correctedFrom;
        if (! in_array($canonical['subtype'], self::SUBTYPES, true) || ! Str::isUuid($canonical['receiving_account']) || ! Str::isUuid($canonical['income_account']) || ! Str::isUuid($canonical['idempotency_key']) || (isset($canonical['corrected_from_receipt']) && ! Str::isUuid($canonical['corrected_from_receipt'])) || ! FundCode::tryFrom($canonical['fund_code']) || $canonical['amount_paise'] < 1 || $canonical['description'] === '') throw new ClubOrdinaryIncomeReceiptException('The ordinary income receipt request is invalid.');
        if (in_array($canonical['subtype'], ['sponsorship', 'advertising', 'facility_income'], true) && $canonical['payer_name'] === null) throw new ClubOrdinaryIncomeReceiptException('Payer or source name is required for this receipt subtype.');
        $canonical['request_digest'] = hash('sha256', json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        return $canonical;
    }

    private function validateAccounts(Club $club, ?LedgerAccount $receiving, ?LedgerAccount $income): void
    {
        if (! $receiving || ! $income || $receiving->club_id !== $club->id || $income->club_id !== $club->id) throw new ClubOrdinaryIncomeReceiptException('The selected accounts are unavailable for this club.');
        if (! $receiving->is_active || $receiving->currency !== LedgerAccount::CURRENCY_INR || $receiving->account_type !== LedgerAccount::TYPE_ASSET || $receiving->statement_classification !== StatementClassification::CASH_AND_BANK->value) throw new ClubOrdinaryIncomeReceiptException('The selected receiving account is not eligible.');
        if (! $income->is_active || $income->currency !== LedgerAccount::CURRENCY_INR || $income->account_type !== LedgerAccount::TYPE_INCOME || $income->statement_classification !== StatementClassification::INCOME->value) throw new ClubOrdinaryIncomeReceiptException('The selected income account is not eligible.');
    }

    private function assertReplayProvenance(ClubOrdinaryIncomeReceipt $receipt, array $canonical): void
    {
        $journal = $receipt->journalEntry;
        $lines = $journal?->lines?->sortBy('line_number')->values();
        $valid = $journal
            && $receipt->journal_entry_id === $journal->id
            && $journal->club_id === $receipt->club_id
            && $journal->source_type === self::SOURCE_TYPE
            && $journal->source_id === $receipt->public_id
            && $journal->source_event === 'recorded'
            && strtolower((string) $journal->idempotency_key) === $canonical['idempotency_key']
            && $journal->status === 'posted'
            && $journal->currency === LedgerAccount::CURRENCY_INR
            && $lines?->count() === 2
            && $lines[0]->ledger_account_id === $receipt->receiving_account_id
            && $lines[0]->direction === ClubJournalLine::DIRECTION_DEBIT
            && $lines[1]->ledger_account_id === $receipt->income_account_id
            && $lines[1]->direction === ClubJournalLine::DIRECTION_CREDIT
            && $lines[0]->amount_paise === $receipt->amount_paise
            && $lines[1]->amount_paise === $receipt->amount_paise
            && $lines[0]->fund_code === $receipt->fund_code
            && $lines[1]->fund_code === $receipt->fund_code;
        if (! $valid) throw new ClubOrdinaryIncomeReceiptConflictException('The stored ordinary income receipt journal provenance is invalid.');
    }

    private function loaded(ClubOrdinaryIncomeReceipt $receipt): ClubOrdinaryIncomeReceipt { return $receipt->fresh()->load(['receivingAccount', 'incomeAccount', 'journalEntry.lines.ledgerAccount', 'creator', 'correction.reversalJournal.lines.ledgerAccount', 'correction.creator', 'correction.replacementReceipt.journalEntry', 'correctedFrom']); }
}
