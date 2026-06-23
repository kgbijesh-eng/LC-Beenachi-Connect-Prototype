<?php

namespace App\Services\Projects;

use App\Models\Club;
use App\Models\ClubProject;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ClubProjectReadService
{
    public function listForClub(Club $club, array $filters, bool $isManagement): LengthAwarePaginator
    {
        $query = ClubProject::query()
            ->with(['club', 'leadMember'])
            ->where('club_id', $club->id);

        $this->applyVisibilityRules($query, $isManagement);
        $this->applyFilters($query, $filters, $isManagement);
        $this->applyOrdering($query, $filters);

        return $query->paginate(
            perPage: min((int) ($filters['per_page'] ?? 20), 50),
            page: (int) ($filters['page'] ?? 1)
        );
    }

    public function canRead(ClubProject $project, bool $isManagement): bool
    {
        if ($isManagement) {
            return true;
        }

        return $project->visibility === ClubProject::VISIBILITY_MEMBERS
            && (
                in_array($project->status, [
                    ClubProject::STATUS_PLANNED,
                    ClubProject::STATUS_ACTIVE,
                    ClubProject::STATUS_COMPLETED,
                ], true)
                || ($project->status === ClubProject::STATUS_CANCELLED && $project->planned_at !== null)
            );
    }

    private function applyVisibilityRules(Builder $query, bool $isManagement): void
    {
        if ($isManagement) {
            return;
        }

        $query
            ->where('visibility', ClubProject::VISIBILITY_MEMBERS)
            ->where(function (Builder $query): void {
                $query
                    ->whereIn('status', [
                        ClubProject::STATUS_PLANNED,
                        ClubProject::STATUS_ACTIVE,
                        ClubProject::STATUS_COMPLETED,
                    ])
                    ->orWhere(function (Builder $query): void {
                        $query
                            ->where('status', ClubProject::STATUS_CANCELLED)
                            ->whereNotNull('planned_at');
                    });
            });
    }

    private function applyFilters(Builder $query, array $filters, bool $isManagement): void
    {
        $scope = $filters['scope'] ?? null;
        if ($scope !== null && $scope !== 'all') {
            $query->where('status', $scope);
        }

        if (isset($filters['record_type'])) {
            $query->where('record_type', $filters['record_type']);
        }

        if ($isManagement && isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if ($isManagement && isset($filters['visibility'])) {
            $query->where('visibility', $filters['visibility']);
        }

        if (isset($filters['service_category'])) {
            $query->where('service_category', $filters['service_category']);
        }

        if (isset($filters['from'])) {
            $query->where(function (Builder $query) use ($filters): void {
                $query
                    ->where('planned_start_date', '>=', $filters['from'])
                    ->orWhere('actual_start_date', '>=', $filters['from']);
            });
        }

        if (isset($filters['to'])) {
            $query->where(function (Builder $query) use ($filters): void {
                $query
                    ->where('planned_start_date', '<=', $filters['to'])
                    ->orWhere('actual_start_date', '<=', $filters['to']);
            });
        }

        if (isset($filters['q']) && trim((string) $filters['q']) !== '') {
            $term = '%'.trim((string) $filters['q']).'%';
            $query->where(function (Builder $query) use ($term): void {
                $query
                    ->where('title', 'like', $term)
                    ->orWhere('objective', 'like', $term)
                    ->orWhere('location', 'like', $term);
            });
        }
    }

    private function applyOrdering(Builder $query, array $filters): void
    {
        $direction = $filters['direction'] ?? 'asc';

        $query
            ->orderByRaw("case status when 'draft' then 0 when 'planned' then 1 when 'active' then 2 when 'completed' then 3 when 'cancelled' then 4 else 5 end")
            ->orderByRaw('coalesce(planned_start_date, actual_start_date, date(created_at)) '.$direction)
            ->orderBy('id', $direction);
    }
}