<?php

namespace App\Http\Resources\Announcements;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClubAnnouncementPublicationDecisionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'announcement_id' => $this->club_announcement_id,
            'announcement_lock_version' => $this->announcement_lock_version,
            'decision' => $this->decision,
            'reason' => $this->reason,
            'decided_at' => $this->decided_at?->toISOString(),
        ];
    }
}
