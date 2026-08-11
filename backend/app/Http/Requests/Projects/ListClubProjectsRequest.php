<?php

namespace App\Http\Requests\Projects;

use App\Models\ClubProject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListClubProjectsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scope' => ['sometimes', Rule::in(['planned', 'active', 'completed', 'cancelled', 'all'])],
            'record_type' => ['sometimes', Rule::in(ClubProject::recordTypes())],
            'status' => ['sometimes', Rule::in(ClubProject::statuses())],
            'visibility' => ['sometimes', Rule::in(ClubProject::visibilities())],
            'service_category' => ['sometimes', 'string', 'max:100'],
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
            'q' => ['sometimes', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
        ];
    }
}