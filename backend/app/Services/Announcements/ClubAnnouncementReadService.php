<?php

namespace App\Services\Announcements;

use App\Models\Club;
use App\Models\ClubAnnouncement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ClubAnnouncementReadService
{
    private const RELATIONS = ['club', 'creator', 'updater', 'publisher', 'withdrawer', 'archiver', 'replacedAnnouncement'];

    public function listForClub(Club $club, array $filters, bool $management, Carbon $now): LengthAwarePaginator
    {
        $this->assertCompatibleFilters($filters, $management);

        $query = ClubAnnouncement::query()->with(self::RELATIONS)->where('club_id', $club->id);
        $this->applyScope($query, $filters['scope'], $management, $now);
        $this->applyFilters($query, $filters, $club, $now);
        $this->applyOrdering($query, $filters, $management);

        $page = $query->paginate(perPage: $filters['per_page'], page: $filters['page']);
        foreach ($page->items() as $announcement) {
            $announcement->assertReadableIntegrity($club, $now);
        }

        return $page;
    }

    public function canReadDetail(Club $club, ClubAnnouncement $announcement, bool $management, Carbon $now): bool
    {
        if ((int) $announcement->club_id !== (int) $club->id) {
            return false;
        }
        $announcement->loadMissing(self::RELATIONS);
        $announcement->assertReadableIntegrity($club, $now);
        if ($management) {
            return true;
        }

        return $announcement->status === ClubAnnouncement::STATUS_PUBLISHED
            && $announcement->audience === ClubAnnouncement::AUDIENCE_CLUB_MEMBERS;
    }

    private function applyScope(Builder $query, string $scope, bool $management, Carbon $now): void
    {
        if (! $management) {
            $query->where('status', ClubAnnouncement::STATUS_PUBLISHED)
                ->where('audience', ClubAnnouncement::AUDIENCE_CLUB_MEMBERS);
            $scope === 'history'
                ? $query->whereNotNull('expires_at')->where('expires_at', '<=', $now)
                : $query->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $now));

            return;
        }

        if ($scope === 'all') {
            return;
        }
        if ($scope === 'current') {
            $query->where(function (Builder $q) use ($now): void {
                $q->where('status', ClubAnnouncement::STATUS_DRAFT)
                    ->orWhere(fn (Builder $q) => $q->where('status', ClubAnnouncement::STATUS_PUBLISHED)
                        ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $now)));
            });

            return;
        }
        $query->where(function (Builder $q) use ($now): void {
            $q->where('status', ClubAnnouncement::STATUS_WITHDRAWN)
                ->orWhere(fn (Builder $q) => $q->where('status', ClubAnnouncement::STATUS_PUBLISHED)
                    ->whereNotNull('expires_at')->where('expires_at', '<=', $now));
        });
    }

    private function applyFilters(Builder $query, array $filters, Club $club, Carbon $now): void
    {
        foreach (['priority', 'status', 'audience'] as $field) {
            if (array_key_exists($field, $filters)) {
                $query->where($field, $filters[$field]);
            }
        }
        if (isset($filters['effective_state'])) {
            $query->where('status', ClubAnnouncement::STATUS_PUBLISHED);
            $filters['effective_state'] === 'expired'
                ? $query->whereNotNull('expires_at')->where('expires_at', '<=', $now)
                : $query->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $now));
        }
        if (array_key_exists('scheduled', $filters)) {
            $filters['scheduled'] ? $query->whereNotNull('scheduled_publish_at') : $query->whereNull('scheduled_publish_at');
        }
        if (isset($filters['q']) && $filters['q'] !== '') {
            $literal = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']);
            $query->whereRaw("LOWER(title) LIKE LOWER(?) ESCAPE '!'", ['%'.$literal.'%']);
        }

        $timezone = $club->timezone ?: 'UTC';
        if (isset($filters['published_from'])) {
            $from = Carbon::createFromFormat('Y-m-d', $filters['published_from'], $timezone)->startOfDay()->utc();
            $query->where('published_at', '>=', $from);
        }
        if (isset($filters['published_to'])) {
            $toExclusive = Carbon::createFromFormat('Y-m-d', $filters['published_to'], $timezone)->addDay()->startOfDay()->utc();
            $query->where('published_at', '<', $toExclusive);
        }
    }

    private function applyOrdering(Builder $query, array $filters, bool $management): void
    {
        $direction = $filters['direction'];
        if (! $management) {
            $query->orderBy('published_at', $direction)->orderBy('id', $direction);

            return;
        }
        if ($filters['scope'] === 'current') {
            $query->orderByRaw("case when status = 'draft' then 0 else 1 end")
                ->orderByRaw('case when status = ? then updated_at end desc', [ClubAnnouncement::STATUS_DRAFT])
                ->orderBy('published_at', $direction)->orderBy('id', $direction);

            return;
        }
        if ($filters['scope'] === 'history') {
            $query->orderByRaw('coalesce(withdrawn_at, published_at) '.$direction)->orderBy('id', $direction);

            return;
        }
        $query->orderBy('updated_at', 'desc')->orderBy('id', 'desc');
    }

    private function assertCompatibleFilters(array $filters, bool $management): void
    {
        if (! $management) {
            return;
        }
        $scope = $filters['scope'];
        $status = $filters['status'] ?? null;
        $effective = $filters['effective_state'] ?? null;
        $invalid = ($scope === 'current' && in_array($status, [ClubAnnouncement::STATUS_WITHDRAWN, ClubAnnouncement::STATUS_ARCHIVED], true))
            || ($scope === 'history' && in_array($status, [ClubAnnouncement::STATUS_DRAFT, ClubAnnouncement::STATUS_ARCHIVED], true))
            || ($effective !== null && $status !== null && $status !== ClubAnnouncement::STATUS_PUBLISHED)
            || ($scope === 'current' && $effective === 'expired')
            || ($scope === 'history' && $effective === 'current');
        if ($invalid) {
            throw ValidationException::withMessages(['filters' => 'The requested announcement filters are incompatible.']);
        }
    }
}
