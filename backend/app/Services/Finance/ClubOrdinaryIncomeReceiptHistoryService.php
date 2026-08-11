<?php

namespace App\Services\Finance;

use App\Models\{Club, ClubOrdinaryIncomeReceipt};
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class ClubOrdinaryIncomeReceiptHistoryService
{
    public function list(Club $club, array $filters): LengthAwarePaginator
    {
        $query = $this->base($club)->orderByDesc('accounting_date')->orderByDesc('id');
        foreach (['date_from' => '>=', 'date_to' => '<='] as $key => $operator) if (! empty($filters[$key])) $query->where('accounting_date', $operator, $filters[$key]);
        foreach (['subtype', 'fund' => 'fund_code', 'external_reference'] as $key => $column) { if (is_int($key)) $key = $column; if (! empty($filters[$key])) $query->where($column, $filters[$key]); }
        foreach (['receiving_account' => 'receivingAccount', 'income_account' => 'incomeAccount'] as $key => $relation) if (! empty($filters[$key])) $query->whereHas($relation, fn (Builder $q) => $q->where('public_id', strtolower($filters[$key])));
        if (($filters['status'] ?? null) === 'posted') $query->whereDoesntHave('correction');
        if (($filters['status'] ?? null) === 'reversed') $query->whereHas('correction', fn (Builder $q) => $q->whereNull('replacement_receipt_id'));
        if (($filters['status'] ?? null) === 'replaced') $query->whereHas('correction', fn (Builder $q) => $q->whereNotNull('replacement_receipt_id'));
        if (! empty($filters['search'])) { $search = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($filters['search'])).'%'; $query->where(fn (Builder $q) => $q->where('payer_name', 'like', $search)->orWhere('description', 'like', $search)->orWhere('external_reference', 'like', $search)->orWhere('event_label', 'like', $search)); }
        return $query->paginate((int) ($filters['per_page'] ?? 25))->withQueryString();
    }
    public function find(Club $club, string $publicId): ClubOrdinaryIncomeReceipt { return $this->base($club)->where('public_id', strtolower(trim($publicId)))->firstOrFail(); }
    private function base(Club $club): Builder { return ClubOrdinaryIncomeReceipt::query()->where('club_id', $club->id)->with(['receivingAccount', 'incomeAccount', 'creator', 'journalEntry.lines.ledgerAccount', 'correction.reversalJournal.lines.ledgerAccount', 'correction.creator', 'correction.replacementReceipt.receivingAccount', 'correction.replacementReceipt.incomeAccount', 'correction.replacementReceipt.journalEntry', 'correctedFrom']); }
}
