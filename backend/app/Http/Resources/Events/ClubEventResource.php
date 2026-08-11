<?php

namespace App\Http\Resources\Events;

use App\Models\ClubEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClubEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isManagement = (bool) $request->attributes->get('events_management_reader', false);

        $data = [
            'id' => $this->id,
            'club_id' => $this->club_id,
            'type' => $this->type,
            'title' => $this->title,
            'description' => $this->description,
            'is_all_day' => $this->is_all_day,
            'starts_at' => $this->starts_at?->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),
            'start_date' => $this->is_all_day ? $this->start_date?->format('Y-m-d') : null,
            'end_date' => $this->is_all_day ? $this->effectiveEndDate() : null,
            'venue' => $this->venue,
            'status' => $this->status,
            'timezone' => $this->club?->timezone,
        ];

        if ($isManagement) {
            $data = array_merge($data, [
                'visibility' => $this->visibility,
                'published_at' => $this->published_at?->toISOString(),
                'cancelled_at' => $this->cancelled_at?->toISOString(),
                'cancellation_reason' => $this->cancellation_reason,
                'completed_at' => $this->completed_at?->toISOString(),
                'created_by_user_id' => $this->created_by_user_id,
                'updated_by_user_id' => $this->updated_by_user_id,
                'lock_version' => $this->lock_version,
                'created_at' => $this->created_at?->toISOString(),
                'updated_at' => $this->updated_at?->toISOString(),
            ]);
        }

        if (! $isManagement && $this->status === ClubEvent::STATUS_CANCELLED) {
            unset($data['description']);
        }

        return $data;
    }

    private function effectiveEndDate(): ?string
    {
        return ($this->end_date ?? $this->start_date)?->format('Y-m-d');
    }
}
