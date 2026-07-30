<?php

namespace App\Services\Announcements;

use App\Models\Club;
use App\Models\ClubAnnouncement;
use App\Models\ClubAnnouncementRead;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ClubAnnouncementReadStateService
{
    public function __construct(
        private readonly ClubAnnouncementReadService $announcements,
    ) {
    }

    public function attachToList(iterable $announcements, Club $club, User $user): void
    {
        $items = collect($announcements);
        $ids = $items->pluck('id')->map(fn ($id): int => (int) $id)->unique()->values();
        $reads = $ids->isEmpty()
            ? collect()
            : ClubAnnouncementRead::query()
                ->where('club_id', $club->id)
                ->where('user_id', $user->id)
                ->whereIn('club_announcement_id', $ids)
                ->get()
                ->keyBy('club_announcement_id');

        foreach ($items as $announcement) {
            $this->attach($announcement, $reads->get($announcement->id));
        }
    }

    public function attachToDetail(ClubAnnouncement $announcement, Club $club, User $user): void
    {
        $read = ClubAnnouncementRead::query()
            ->where('club_id', $club->id)
            ->where('user_id', $user->id)
            ->where('club_announcement_id', $announcement->id)
            ->first();

        $this->attach($announcement, $read);
    }

    public function markRead(
        Club $club,
        ClubAnnouncement $announcement,
        User $user,
        array $readablePublishedAudiences,
        Carbon $now,
    ): ?ClubAnnouncementRead {
        return DB::transaction(function () use ($club, $announcement, $user, $readablePublishedAudiences, $now): ?ClubAnnouncementRead {
            $locked = ClubAnnouncement::query()
                ->where('club_id', $club->id)
                ->whereKey($announcement->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null || ! $this->announcements->canMarkRead($club, $locked, $readablePublishedAudiences, $now)) {
                return null;
            }

            $timestamp = $now->copy();
            DB::table('club_announcement_reads')->insertOrIgnore([
                'club_id' => $club->id,
                'user_id' => $user->id,
                'club_announcement_id' => $locked->id,
                'read_at' => $timestamp,
                'created_at' => $timestamp,
            ]);

            return ClubAnnouncementRead::query()
                ->where('club_id', $club->id)
                ->where('user_id', $user->id)
                ->where('club_announcement_id', $locked->id)
                ->firstOrFail();
        });
    }

    private function attach(ClubAnnouncement $announcement, ?ClubAnnouncementRead $read): void
    {
        $announcement->setAttribute('current_user_is_read', $read !== null);
        $announcement->setAttribute('current_user_read_at', $read?->read_at);
    }
}
