<?php

namespace App\Http\Resources\Announcements;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class ClubAnnouncementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $management = (bool) $request->attributes->get('announcements_management_reader', false);
        $now = $request->attributes->get('announcements_effective_now');
        if (! $now instanceof Carbon) {
            $now = Carbon::now('UTC');
        }
        $timezone = $this->club?->timezone ?: 'UTC';
        $role = match ($this->published_by_role) {
            'president' => 'President',
            'secretary' => 'Secretary',
            default => null,
        };

        $data = [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'priority' => $this->priority,
            'effective_status' => $this->replacement_effective_status ?: $this->effectiveStatus($now),
            'published_at' => $this->published_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'club_timezone' => $timezone,
            'issued_by' => $this->club?->name,
            'published_by_role' => $role,
        ];

        if ($this->safe_replaced_by_announcement_id !== null) {
            $data['replaced_by_announcement_id'] = (int) $this->safe_replaced_by_announcement_id;
        }
        if ($this->safe_replaces_announcement_id !== null) {
            $data['replaces_announcement_id'] = (int) $this->safe_replaces_announcement_id;
        }

        if (array_key_exists('current_user_is_read', $this->resource->getAttributes())
            && array_key_exists('current_user_read_at', $this->resource->getAttributes())) {
            $data['is_read'] = (bool) $this->current_user_is_read;
            $data['read_at'] = $this->current_user_read_at?->toISOString();
        }

        if ($management) {
            $data += [
                'club_id' => $this->club_id,
                'audience' => $this->audience,
                'stored_status' => $this->status,
                'scheduled_publish_at' => $this->scheduled_publish_at?->toISOString(),
                'withdrawn_at' => $this->withdrawn_at?->toISOString(),
                'withdrawal_reason' => $this->withdrawal_reason,
                'archived_at' => $this->archived_at?->toISOString(),
                'replaces_announcement_id' => $this->replaces_announcement_id,
                'direct_replacement_announcement_ids' => $this->management_direct_replacement_ids ?? [],
                'published_by_user_id' => $this->published_by_user_id,
                'created_by_user_id' => $this->created_by_user_id,
                'updated_by_user_id' => $this->updated_by_user_id,
                'withdrawn_by_user_id' => $this->withdrawn_by_user_id,
                'archived_by_user_id' => $this->archived_by_user_id,
                'lock_version' => $this->lock_version,
                'created_at' => $this->created_at?->toISOString(),
                'updated_at' => $this->updated_at?->toISOString(),
            ];
        }

        return $data;
    }
}
