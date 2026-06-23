<?php

namespace App\Http\Requests\Projects;

use App\Models\ClubProject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClubProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'record_type' => ['required', Rule::in(ClubProject::recordTypes())],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'objective' => ['nullable', 'string', 'max:2000'],
            'service_category' => ['nullable', 'string', 'max:100'],
            'visibility' => ['required', Rule::in(ClubProject::visibilities())],
            'planned_start_date' => ['nullable', 'date_format:Y-m-d'],
            'planned_end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:planned_start_date'],
            'location' => ['nullable', 'string', 'max:255'],
            'lead_member_id' => ['nullable', 'integer', 'exists:members,id'],
            'expected_beneficiary_count' => ['nullable', 'integer', 'min:0'],
            'club_id' => ['prohibited'],
            'status' => ['prohibited'],
            'lock_version' => ['prohibited'],
            'actual_start_date' => ['prohibited'],
            'actual_end_date' => ['prohibited'],
            'actual_beneficiary_count' => ['prohibited'],
            'planned_at' => ['prohibited'],
            'started_at' => ['prohibited'],
            'completed_at' => ['prohibited'],
            'cancelled_at' => ['prohibited'],
        ];
    }
}