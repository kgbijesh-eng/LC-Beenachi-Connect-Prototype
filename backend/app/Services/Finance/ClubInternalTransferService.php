<?php

declare(strict_types=1);
namespace App\Services\Finance;

use App\Models\{Club, ClubAccountingCutoverPackage, ClubAccountingPeriod, ClubInternalTransfer, ClubJournalLine, LedgerAccount, User};
use App\Services\Finance\Exceptions\{ClubInternalTransferConflictException, ClubInternalTransferException};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ClubInternalTransferService
{
    public const SOURCE_TYPE = 'club_internal_transfer';
    public function __construct(private readonly ClubJournalPostingService $journals) {}

    public function record(Club $club, User $actor, array $input): ClubInternalTransfer
    {
        $canonical = $this->canonical($input);
        return DB::transaction(function () use ($club, $actor, $canonical): ClubInternalTransfer {
            $lockedClub = Club::query()->whereKey($club->id)->lockForUpdate()->firstOrFail();
            $existing = ClubInternalTransfer::query()->where('club_id', $lockedClub->id)->where('idempotency_key', $canonical['idempotency_key'])->lockForUpdate()->first();
            if ($existing) {
                if (! hash_equals($existing->request_digest, $canonical['request_digest'])) throw new ClubInternalTransferConflictException('The transfer idempotency key conflicts with a changed request.');
                return $this->loaded($existing);
            }
            $period = ClubAccountingPeriod::query()->where('club_id', $lockedClub->id)->where('start_date', '<=', $canonical['accounting_date'])->where('end_date', '>=', $canonical['accounting_date'])->lockForUpdate()->first();
            if (! $period || $period->status !== ClubAccountingPeriod::STATUS_OPEN) throw new ClubInternalTransferException('The transfer date must belong to an open accounting period.');
            if (! ClubAccountingCutoverPackage::query()->where('club_id', $lockedClub->id)->where('status', 'posted')->whereNotNull('opening_journal_entry_id')->where('cutover_date', '<=', $canonical['accounting_date'])->lockForUpdate()->exists()) throw new ClubInternalTransferException('Authoritative accounting is not active for the selected date.');
            $accounts = LedgerAccount::query()->whereIn('public_id', [$canonical['source_account'], $canonical['destination_account']])->lockForUpdate()->get()->keyBy(fn ($account) => strtolower($account->public_id));
            $source = $accounts->get($canonical['source_account']); $destination = $accounts->get($canonical['destination_account']);
            foreach ([$source, $destination] as $account) if (! $account || $account->club_id !== $lockedClub->id || ! $account->is_active || $account->currency !== LedgerAccount::CURRENCY_INR || $account->account_type !== LedgerAccount::TYPE_ASSET || $account->statement_classification !== StatementClassification::CASH_AND_BANK->value) throw new ClubInternalTransferException('The selected cash or bank account is not eligible for this club.');
            if ($source->is($destination)) throw new ClubInternalTransferException('Source and destination accounts must differ.');
            $transfer = ClubInternalTransfer::create(['club_id' => $lockedClub->id, 'accounting_date' => $canonical['accounting_date'], 'source_account_id' => $source->id, 'destination_account_id' => $destination->id, 'fund_code' => $canonical['fund_code'], 'amount_paise' => $canonical['amount_paise'], 'currency' => LedgerAccount::CURRENCY_INR, 'description' => $canonical['description'], 'external_reference' => $canonical['external_reference'], 'idempotency_key' => $canonical['idempotency_key'], 'request_digest' => $canonical['request_digest'], 'created_by_user_id' => $actor->id]);
            $journal = $this->journals->post(new JournalPostingRequest($lockedClub->id, $period->id, $canonical['accounting_date'], $canonical['description'], LedgerAccount::CURRENCY_INR, self::SOURCE_TYPE, $transfer->public_id, 'recorded', $canonical['idempotency_key'], $actor->id, [new JournalLineInput($destination->id, ClubJournalLine::DIRECTION_DEBIT, $canonical['amount_paise'], $canonical['description'], $canonical['fund_code']), new JournalLineInput($source->id, ClubJournalLine::DIRECTION_CREDIT, $canonical['amount_paise'], $canonical['description'], $canonical['fund_code'])], metadata: ['transfer_public_id' => $transfer->public_id, 'external_reference' => $canonical['external_reference']]));
            $transfer->updateQuietly(['journal_entry_id' => $journal->id]);
            return $this->loaded($transfer);
        }, 3);
    }

    public function options(Club $club): array
    {
        $accounts = LedgerAccount::query()->where('club_id', $club->id)->where('is_active', true)->where('currency', LedgerAccount::CURRENCY_INR)->where('account_type', LedgerAccount::TYPE_ASSET)->where('statement_classification', StatementClassification::CASH_AND_BANK->value)->orderBy('code')->get()->map(fn ($a) => ['id' => $a->public_id, 'code' => $a->code, 'name' => $a->name])->all();
        return ['accounts' => $accounts, 'funds' => array_map(fn (FundCode $fund) => $fund->value, FundCode::cases()), 'currency' => LedgerAccount::CURRENCY_INR];
    }

    public function list(Club $club) { return ClubInternalTransfer::query()->where('club_id', $club->id)->with(['sourceAccount', 'destinationAccount', 'journalEntry', 'creator'])->orderByDesc('accounting_date')->orderByDesc('id')->paginate(25); }
    public function find(Club $club, string $publicId): ClubInternalTransfer { return $this->loaded(ClubInternalTransfer::query()->where('club_id', $club->id)->where('public_id', strtolower(trim($publicId)))->firstOrFail()); }
    private function canonical(array $input): array
    {
        $canonical = ['accounting_date' => $input['accounting_date'], 'source_account' => strtolower(trim((string) $input['source_account'])), 'destination_account' => strtolower(trim((string) $input['destination_account'])), 'fund_code' => $input['fund'], 'amount_paise' => (int) $input['amount_paise'], 'description' => trim((string) $input['description']), 'external_reference' => ($v = trim((string) ($input['external_reference'] ?? ''))) === '' ? null : $v, 'idempotency_key' => strtolower(trim((string) $input['idempotency_key']))];
        if (! Str::isUuid($canonical['source_account']) || ! Str::isUuid($canonical['destination_account']) || ! Str::isUuid($canonical['idempotency_key']) || $canonical['source_account'] === $canonical['destination_account'] || ! FundCode::tryFrom($canonical['fund_code']) || $canonical['amount_paise'] < 1) throw new ClubInternalTransferException('The transfer request is invalid.');
        $canonical['request_digest'] = hash('sha256', json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)); return $canonical;
    }
    private function loaded(ClubInternalTransfer $transfer): ClubInternalTransfer { return $transfer->fresh()->load(['sourceAccount', 'destinationAccount', 'journalEntry.lines.ledgerAccount', 'creator']); }
}
