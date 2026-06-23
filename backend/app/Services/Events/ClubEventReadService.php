<?php

namespace App\Services\Events;

use App\Models\Club;
use App\Models\ClubEvent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ClubEventReadService
{
    public function listForClub(Club $club, array $filters, bool $isManagement): LengthAwarePaginator
    {
        $query = ClubEvent::query()
            ->with('club')
            ->where('club_id', $club->id);

        $this->applyVisibilityRules($query, $isManagement);
        $this->applyFilters($query, $filters, $isManagement);
        $this->applyOrdering($query, $filters);

        return $query->paginate(
            perPage: min((int) ($filters['per_page'] ?? 20), 50),
            page: (int) ($filters['page'] ?? 1)
        );
    }

    public function canRead(ClubEvent $event, bool $isManagement): bool
    {
        if ($isManagement) {
            return true;
        }

        return $event->visibility === ClubEvent::VISIBILITY_MEMBERS
            && in_array($event->status, [
                ClubEvent::STATUS_PUBLISHED,
                ClubEvent::STATUS_COMPLETED,
                ClubEvent::STATUS_CANCELLED,
            ], true);
    }

    private function applyVisibilityRules(Builder $query, bool $isManagement): void
    {
        if ($isManagement) {
            return;
        }

        $query
            ->where('visibility', ClubEvent::VISIBILITY_MEMBERS)
            ->whereIn('status', [
                ClubEvent::STATUS_PUBLISHED,
                ClubEvent::STATUS_COMPLETED,
                ClubEvent::STATUS_CANCELLED,
            ]);
    }

    private function applyFilters(Builder $query, array $filters, bool $isManagement): void
    {
        $scope = $filters['scope'] ?? 'upcoming';
        $now = Carbon::now('UTC');

        if ($scope === 'upcoming') {
            $query->where('starts_at', '>=', $now);
        } elseif ($scope === 'past') {
            $query->where('starts_at', '<', $now);
        }

        foreach (['type', 'status', 'visibility'] as $field) {
            if (array_key_exists($field, $filters) && ($isManagement || ! in_array($field, ['status', 'visibility'], true))) {
                $query->where($field, $filters[$field]);
            }
        }

        if (isset($filters['from'])) {
            $query->where('starts_at', '>=', Carbon::parse($filters['from'])->utc());
        }

        if (isset($filters['to'])) {
            $query->where('starts_at', '<=', Carbon::parse($filters['to'])->utc());
        }

        if (isset($filters['q']) && trim((string) $filters['q']) !== '') {
            $query->where('title', 'like', '%'.trim((string) $filters['q']).'%');
        }
    }

    private function applyOrdering(Builder $query, array $filters): void
    {
        $scope = $filters['scope'] ?? 'upcoming';
        $direction = $filters['direction'] ?? ($scope === 'past' ? 'desc' : 'asc');

        $query
            ->orderBy('starts_at', $direction)
            ->orderBy('id', $direction);
    }
}
