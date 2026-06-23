<?php

namespace App\Http\Resources\Projects;

use App\Models\ClubProject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClubProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isManagement = (bool) $request->attributes->get('projects_management_reader', false);

        $data = [
            'id' => $this->id,
            'record_type' => $this->record_type,
            'title' => $this->title,
            'description' => $this->description,
            'objective' => $this->objective,
            'service_category' => $this->service_category,
            'status' => $this->status,
            'planned_start_date' => $this->planned_start_date?->format('Y-m-d'),
            'planned_end_date' => $this->planned_end_date?->format('Y-m-d'),
            'actual_start_date' => $this->actual_start_date?->format('Y-m-d'),
            'actual_end_date' => $this->actual_end_date?->format('Y-m-d'),
            'location' => $this->location,
            'lead_member' => $this->leadMember ? [
                'id' => $this->leadMember->id,
                'member_code' => $this->leadMember->member_code,
                'full_name' => $this->leadMember->full_name,
            ] : null,
            'expected_beneficiary_count' => $this->expected_beneficiary_count,
            'actual_beneficiary_count' => $this->actual_beneficiary_count,
            'impact_summary' => $this->impact_summary,
            'planned_at' => $this->planned_at?->toISOString(),
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
        ];

        if ($isManagement) {
            $data = array_merge($data, [
                'club_id' => $this->club_id,
                'visibility' => $this->visibility,
                'cancellation_reason' => $this->cancellation_reason,
                'created_by_user_id' => $this->created_by_user_id,
                'updated_by_user_id' => $this->updated_by_user_id,
                'planned_by_user_id' => $this->planned_by_user_id,
                'started_by_user_id' => $this->started_by_user_id,
                'completed_by_user_id' => $this->completed_by_user_id,
                'cancelled_by_user_id' => $this->cancelled_by_user_id,
                'lock_version' => $this->lock_version,
                'created_at' => $this->created_at?->toISOString(),
                'updated_at' => $this->updated_at?->toISOString(),
            ]);
        }

        if (! $isManagement && $this->status === ClubProject::STATUS_CANCELLED) {
            unset($data['description'], $data['objective'], $data['impact_summary']);
        }

        return $data;
    }
}