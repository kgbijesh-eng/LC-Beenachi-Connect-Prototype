<?php

namespace App\Services\Announcements;

use App\Models\Club;
use App\Models\ClubAnnouncement;
use App\Models\ClubAnnouncementRead;
use App\Models\User;
use App\Services\Access\ClubPermissionService;
use App\Services\Access\OrganisationTransactionGuard;
use App\Services\Access\TransactionActorAuthorisation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ClubAnnouncementReadStateService
{
    private const READ_ONLY_OFFICER_AUDIENCE_ROLES = ['treasurer'];

    public function __construct(
        private readonly ClubAnnouncementReadService $announcements,
        private readonly OrganisationTransactionGuard $transactions,
        private readonly ClubPermissionService $permissions,
        private readonly TransactionActorAuthorisation $actorAuthorisation,
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

    public function readablePublishedAudiences(User $user, Club $club, bool $management): array
    {
        if ($management) {
            return ClubAnnouncement::audiences();
        }

        $membership = $user->clubMemberships()
            ->with('role')
            ->where('club_id', $club->id)
            ->where('status', 'active')
            ->first();

        if ($membership !== null && in_array($membership->role?->slug, self::READ_ONLY_OFFICER_AUDIENCE_ROLES, true)) {
            return [
                ClubAnnouncement::AUDIENCE_CLUB_MEMBERS,
                ClubAnnouncement::AUDIENCE_OFFICERS,
            ];
        }

        return [ClubAnnouncement::AUDIENCE_CLUB_MEMBERS];
    }

    public function markRead(Club $club, ClubAnnouncement $announcement, User $user): ?ClubAnnouncementRead
    {
        return $this->transactions->run($club, function ($context) use ($club, $announcement, $user): ?ClubAnnouncementRead {
            $this->actorAuthorisation->requirePermission($context, $club->id, $user, 'announcements.view');
            $management = $this->permissions->userHasPermission($user, ClubAnnouncement::PERMISSION_MANAGEMENT_VIEW, $club->id);
            $readablePublishedAudiences = $this->readablePublishedAudiences($user, $club, $management);
            $locked = ClubAnnouncement::query()
                ->where('club_id', $club->id)
                ->whereKey($announcement->id)
                ->lockForUpdate()
                ->first();

            $now = Carbon::now('UTC');
            if ($locked === null || ! $this->announcements->canMarkRead($club, $locked, $readablePublishedAudiences, $now)) {
                return null;
            }

            $timestamp = $now->copy();
            DB::table('club_announcement_reads')->insertOrIgnore([
                'club_id' => $club->id,
                'user_id' => $user->id,
                'club_announcement_id' => $locked->id,
                'read_at' => $now,
                'created_at' => $now,
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
