<?php

namespace App\Http\Requests\Projects;

use App\Models\ClubProject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClubProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lock_version' => ['required', 'integer', 'min:1'],
            'title' => ['sometimes', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'objective' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'service_category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'visibility' => ['sometimes', Rule::in(ClubProject::visibilities())],
            'planned_start_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'planned_end_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'lead_member_id' => ['sometimes', 'nullable', 'integer', 'exists:members,id'],
            'expected_beneficiary_count' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'actual_start_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'actual_end_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'actual_beneficiary_count' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'impact_summary' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'club_id' => ['prohibited'],
            'record_type' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }
}