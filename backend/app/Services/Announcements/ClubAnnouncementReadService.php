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
    private const RELATIONS = ['club', 'creator', 'updater', 'publisher', 'withdrawer', 'archiver', 'replacedAnnouncement', 'replacements'];

    public function listForClub(Club $club, array $filters, bool $management, array $readablePublishedAudiences, Carbon $now): LengthAwarePaginator
    {
        $this->assertCompatibleFilters($filters, $management);

        $query = ClubAnnouncement::query()->with(self::RELATIONS)->where('club_id', $club->id);
        $this->applyScope($query, $filters['scope'], $management, $readablePublishedAudiences, $now);
        $this->applyFilters($query, $filters, $club, $now);
        $this->applyOrdering($query, $filters, $management);

        $page = $query->paginate(perPage: $filters['per_page'], page: $filters['page']);
        foreach ($page->items() as $announcement) {
            $announcement->assertReadableIntegrity($club, $now);
            $this->applyReplacementContext($announcement, $management, $readablePublishedAudiences);
        }

        return $page;
    }

    public function canReadDetail(Club $club, ClubAnnouncement $announcement, bool $management, array $readablePublishedAudiences, Carbon $now): bool
    {
        if ((int) $announcement->club_id !== (int) $club->id) {
            return false;
        }
        $announcement->loadMissing(self::RELATIONS);
        $announcement->assertReadableIntegrity($club, $now);
        if ($management) {
            $this->applyReplacementContext($announcement, true, $readablePublishedAudiences);

            return true;
        }
        $readable = $announcement->status === ClubAnnouncement::STATUS_PUBLISHED
            && in_array($announcement->audience, $readablePublishedAudiences, true);
        if ($readable) {
            $this->applyReplacementContext($announcement, false, $readablePublishedAudiences);
        }

        return $readable;
    }

    private function applyScope(Builder $query, string $scope, bool $management, array $readablePublishedAudiences, Carbon $now): void
    {
        if (! $management) {
            $query->where('status', ClubAnnouncement::STATUS_PUBLISHED)
                ->whereIn('audience', $readablePublishedAudiences);
            if ($scope === 'history') {
                $query->where(fn (Builder $q) => $q
                    ->where(fn (Builder $q) => $q->whereNotNull('expires_at')->where('expires_at', '<=', $now))
                    ->orWhere(fn (Builder $q) => $this->whereHasVisiblePublishedReplacement($q)));
            } else {
                $query->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $now));
                $this->whereHasNoVisiblePublishedReplacement($query);
            }

            return;
        }

        if ($scope === 'all') {
            return;
        }
        if ($scope === 'current') {
            $query->where(function (Builder $q) use ($now): void {
                $q->where('status', ClubAnnouncement::STATUS_DRAFT)
                    ->orWhere(fn (Builder $q) => $q->where('status', ClubAnnouncement::STATUS_PUBLISHED)
                        ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $now))
                        ->where(fn (Builder $q) => $this->whereHasNoVisiblePublishedReplacement($q)));
            });

            return;
        }
        $query->where(function (Builder $q) use ($now): void {
            $q->where('status', ClubAnnouncement::STATUS_WITHDRAWN)
                ->orWhere(fn (Builder $q) => $q->where('status', ClubAnnouncement::STATUS_PUBLISHED)
                    ->where(fn (Builder $q) => $q
                        ->where(fn (Builder $q) => $q->whereNotNull('expires_at')->where('expires_at', '<=', $now))
                        ->orWhere(fn (Builder $q) => $this->whereHasVisiblePublishedReplacement($q))));
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

    private function whereHasVisiblePublishedReplacement(Builder $query): Builder
    {
        return $query->whereExists(function ($replacement): void {
            $replacement->selectRaw('1')->from('club_announcements as replacement')
                ->whereColumn('replacement.replaces_announcement_id', 'club_announcements.id')
                ->whereColumn('replacement.club_id', 'club_announcements.club_id')
                ->whereColumn('replacement.audience', 'club_announcements.audience')
                ->where('replacement.status', ClubAnnouncement::STATUS_PUBLISHED);
        });
    }

    private function whereHasNoVisiblePublishedReplacement(Builder $query): Builder
    {
        return $query->whereNotExists(function ($replacement): void {
            $replacement->selectRaw('1')->from('club_announcements as replacement')
                ->whereColumn('replacement.replaces_announcement_id', 'club_announcements.id')
                ->whereColumn('replacement.club_id', 'club_announcements.club_id')
                ->whereColumn('replacement.audience', 'club_announcements.audience')
                ->where('replacement.status', ClubAnnouncement::STATUS_PUBLISHED);
        });
    }

    private function applyReplacementContext(ClubAnnouncement $announcement, bool $management, array $readablePublishedAudiences): void
    {
        $direct = $announcement->replacements
            ->filter(fn (ClubAnnouncement $candidate): bool => (int) $candidate->club_id === (int) $announcement->club_id)
            ->sortBy('id')->values();
        $published = $direct->first(fn (ClubAnnouncement $candidate): bool => $candidate->status === ClubAnnouncement::STATUS_PUBLISHED
            && $candidate->audience === $announcement->audience
        );

        $announcement->setAttribute('replacement_effective_status', $published ? 'replaced' : null);
        $announcement->setAttribute('safe_replaced_by_announcement_id', $published?->id);
        $safePredecessor = $announcement->replacedAnnouncement !== null
            && (int) $announcement->replacedAnnouncement->club_id === (int) $announcement->club_id
            && ($management || ($announcement->replacedAnnouncement->status === ClubAnnouncement::STATUS_PUBLISHED
                && $announcement->replacedAnnouncement->audience === $announcement->audience
                && in_array($announcement->replacedAnnouncement->audience, $readablePublishedAudiences, true)));
        $announcement->setAttribute('safe_replaces_announcement_id', $safePredecessor ? $announcement->replaces_announcement_id : null);
        $announcement->setAttribute('management_direct_replacement_ids', $management ? $direct->pluck('id')->map(fn ($id) => (int) $id)->all() : null);
    }
}
