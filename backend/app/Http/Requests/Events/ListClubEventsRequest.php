<?php

namespace App\Http\Requests\Events;

use App\Models\ClubEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListClubEventsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scope' => ['sometimes', Rule::in(['upcoming', 'past', 'all'])],
            'type' => ['sometimes', Rule::in(ClubEvent::types())],
            'status' => ['sometimes', Rule::in(ClubEvent::statuses())],
            'visibility' => ['sometimes', Rule::in(ClubEvent::visibilities())],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'q' => ['sometimes', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'sort' => ['sometimes', Rule::in(['starts_at'])],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
        ];
    }
}
