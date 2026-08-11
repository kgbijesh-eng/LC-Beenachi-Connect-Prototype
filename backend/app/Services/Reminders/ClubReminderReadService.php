<?php

namespace App\Services\Reminders;

use App\Models\Club;
use App\Models\ClubReminder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ClubReminderReadService
{
    public function listForClub(Club $club, array $filters, bool $management): LengthAwarePaginator
    {
        $query = ClubReminder::query()->with('club')->where('club_id', $club->id);
        $this->applyVisibility($query, $management);
        $this->applyFilters($query, $filters, $club);
        $this->applyOrdering($query, $club);

        return $query->paginate(
            perPage: min((int) ($filters['per_page'] ?? 25), 100),
            page: (int) ($filters['page'] ?? 1)
        );
    }

    public function canRead(ClubReminder $reminder, bool $management): bool
    {
        if ($management) return true;

        return $reminder->visibility === ClubReminder::VISIBILITY_MEMBERS
            && (
                in_array($reminder->status, [ClubReminder::STATUS_ACTIVE, ClubReminder::STATUS_COMPLETED], true)
                || ($reminder->status === ClubReminder::STATUS_CANCELLED && $reminder->activated_at !== null)
            );
    }

    private function applyVisibility(Builder $query, bool $management): void
    {
        if ($management) return;
        $query->where('visibility', ClubReminder::VISIBILITY_MEMBERS)
            ->where(function (Builder $q): void {
                $q->whereIn('status', [ClubReminder::STATUS_ACTIVE, ClubReminder::STATUS_COMPLETED])
                    ->orWhere(fn (Builder $q) => $q->where('status', ClubReminder::STATUS_CANCELLED)->whereNotNull('activated_at'));
            });
    }

    private function applyFilters(Builder $query, array $filters, Club $club): void
    {
        foreach (['status', 'priority', 'visibility', 'is_all_day'] as $field) {
            if (array_key_exists($field, $filters)) $query->where($field, $filters[$field]);
        }
        if (isset($filters['q'])) $query->where('title', 'like', '%'.trim($filters['q']).'%');

        $timezone = $club->timezone ?: 'UTC';
        if (isset($filters['date_from'])) {
            $from = Carbon::createFromFormat('Y-m-d', $filters['date_from'], $timezone)->startOfDay()->utc();
            $query->where(fn (Builder $q) => $q->whereDate('due_date', '>=', $filters['date_from'])->orWhere('due_at', '>=', $from));
        }
        if (isset($filters['date_to'])) {
            $to = Carbon::createFromFormat('Y-m-d', $filters['date_to'], $timezone)->endOfDay()->utc();
            $query->where(fn (Builder $q) => $q->whereDate('due_date', '<=', $filters['date_to'])->orWhere('due_at', '<=', $to));
        }
        if (array_key_exists('overdue', $filters)) {
            $now = Carbon::now('UTC');
            $localDate = $now->copy()->timezone($timezone)->toDateString();
            $operator = $filters['overdue'] ? '<' : '>=';
            $query->where('status', ClubReminder::STATUS_ACTIVE)
                ->where(function (Builder $q) use ($now, $localDate, $operator): void {
                    $q->where(fn (Builder $q) => $q->where('is_all_day', true)->whereDate('due_date', $operator, $localDate))
                        ->orWhere(fn (Builder $q) => $q->where('is_all_day', false)->where('due_at', $operator, $now));
                });
        }
    }

    private function applyOrdering(Builder $query, Club $club): void
    {
        $now = Carbon::now('UTC');
        $localDate = $now->copy()->timezone($club->timezone ?: 'UTC')->toDateString();
        $query->orderByRaw(
            "case
                when status = 'active' and ((is_all_day = 1 and due_date < ?) or (is_all_day = 0 and due_at < ?)) then 0
                when status = 'active' then 1
                when status in ('completed','cancelled') then 2
                when status = 'draft' then 3
                else 4 end",
            [$localDate, $now]
        )->orderByRaw("case when status = 'active' then coalesce(due_at, due_date) end asc")
            ->orderByRaw("case when status in ('completed','cancelled') then coalesce(completed_at, cancelled_at) end desc")
            ->orderBy('id');
    }
}
