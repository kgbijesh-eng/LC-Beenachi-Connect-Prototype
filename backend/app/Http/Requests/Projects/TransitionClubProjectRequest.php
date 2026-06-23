<?php

namespace App\Http\Requests\Projects;

use Illuminate\Foundation\Http\FormRequest;

class TransitionClubProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lock_version' => ['required', 'integer', 'min:1'],
            'actual_start_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'actual_end_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'actual_beneficiary_count' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'impact_summary' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'cancellation_reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}