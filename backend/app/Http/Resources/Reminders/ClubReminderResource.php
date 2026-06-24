<?php

namespace App\Http\Resources\Reminders;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClubReminderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $management = (bool) $request->attributes->get('reminders_management_reader', false);
        $timezone = $this->club?->timezone ?? 'UTC';
        $data = [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority,
            'status' => $this->status,
            'is_all_day' => $this->is_all_day,
            'due_date' => $this->is_all_day ? $this->due_date?->format('Y-m-d') : null,
            'due_at' => $this->is_all_day ? null : $this->due_at?->toISOString(),
            'due_at_club_timezone' => $this->is_all_day ? null : $this->due_at?->copy()->timezone($timezone)->toISOString(),
            'club_timezone' => $timezone,
            'overdue' => $this->isOverdue(),
            'activated_at' => $this->activated_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
        ];
        if ($management) {
            $data += [
                'club_id' => $this->club_id,
                'visibility' => $this->visibility,
                'cancellation_reason' => $this->cancellation_reason,
                'created_by_user_id' => $this->created_by_user_id,
                'updated_by_user_id' => $this->updated_by_user_id,
                'activated_by_user_id' => $this->activated_by_user_id,
                'completed_by_user_id' => $this->completed_by_user_id,
                'cancelled_by_user_id' => $this->cancelled_by_user_id,
                'lock_version' => $this->lock_version,
                'created_at' => $this->created_at?->toISOString(),
                'updated_at' => $this->updated_at?->toISOString(),
            ];
        }
        if (! $management && $this->status === 'cancelled') unset($data['description']);
        return $data;
    }
}
