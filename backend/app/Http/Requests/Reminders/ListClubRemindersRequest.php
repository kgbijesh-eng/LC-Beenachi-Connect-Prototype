<?php

namespace App\Http\Requests\Reminders;

use App\Models\ClubReminder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListClubRemindersRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::in(ClubReminder::statuses())],
            'priority' => ['sometimes', Rule::in(ClubReminder::priorities())],
            'visibility' => ['sometimes', Rule::in(ClubReminder::visibilities())],
            'overdue' => ['sometimes', 'boolean'],
            'date_from' => ['sometimes', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'is_all_day' => ['sometimes', 'boolean'],
            'q' => ['sometimes', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
