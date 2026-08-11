<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Club;
use App\Models\ClubExpense;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class ClubExpenseHistoryService
{
    public function list(Club $club, array $filters): LengthAwarePaginator
    {
        $query = $this->base($club)->orderByDesc('accounting_date')->orderByDesc('id');
        foreach (['date_from' => '>=', 'date_to' => '<='] as $key => $operator) {
            if (! empty($filters[$key])) $query->where('accounting_date', $operator, $filters[$key]);
        }
        if (! empty($filters['expense_account'])) $query->whereHas('expenseAccount', fn (Builder $q) => $q->where('public_id', strtolower($filters['expense_account'])));
        if (! empty($filters['payment_account'])) $query->whereHas('paymentAccount', fn (Builder $q) => $q->where('public_id', strtolower($filters['payment_account'])));
        if (! empty($filters['fund'])) $query->where('fund_code', $filters['fund']);
        if (! empty($filters['external_reference'])) $query->where('external_reference', $filters['external_reference']);
        if (! empty($filters['search'])) {
            $search = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($filters['search'])).'%';
            $query->where(fn (Builder $q) => $q->where('payee', 'like', $search)->orWhere('description', 'like', $search)->orWhere('external_reference', 'like', $search));
        }
        if (($filters['status'] ?? null) === 'reversed') $query->whereHas('reversal');
        if (($filters['status'] ?? null) === 'posted') $query->whereDoesntHave('reversal');
        return $query->paginate((int) ($filters['per_page'] ?? 25))->withQueryString();
    }

    public function find(Club $club, string $publicId): ClubExpense
    {
        return $this->base($club)->where('public_id', strtolower(trim($publicId)))->firstOrFail();
    }

    private function base(Club $club): Builder
    {
        return ClubExpense::query()->where('club_id', $club->id)->with([
            'expenseAccount', 'paymentAccount', 'creator',
            'journalEntry.lines.ledgerAccount', 'journalEntry.reversals.lines.ledgerAccount',
            'reversal.journalEntry.lines.ledgerAccount', 'reversal.creator',
            'correctedReplacement.expenseAccount', 'correctedReplacement.paymentAccount', 'correctedReplacement.journalEntry',
            'correctedFrom',
        ]);
    }
}
