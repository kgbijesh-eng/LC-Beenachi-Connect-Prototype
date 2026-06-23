<?php

namespace App\Http\Requests\Events;

use App\Models\ClubEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClubEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return array_merge([
            'lock_version' => ['required', 'integer', 'min:1'],
            'title' => ['sometimes', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'venue' => ['sometimes', 'nullable', 'string', 'max:255'],
            'visibility' => ['sometimes', Rule::in(ClubEvent::visibilities())],
            'is_all_day' => ['sometimes', 'boolean'],
            'start_at' => ['required_if:is_all_day,false', 'date', 'prohibited_if:is_all_day,true'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at', 'prohibited_if:is_all_day,true'],
            'start_date' => ['required_if:is_all_day,true', 'date_format:Y-m-d', 'prohibited_if:is_all_day,false'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date', 'prohibited_if:is_all_day,false'],
        ], $this->blockedFieldRules());
    }

    private function blockedFieldRules(): array
    {
        return collect([
            'club_id',
            'type',
            'status',
            'published_at',
            'cancelled_at',
            'cancellation_reason',
            'completed_at',
            'created_by_user_id',
            'updated_by_user_id',
            'audit',
        ])->mapWithKeys(fn (string $field): array => [$field => ['prohibited']])->all();
    }
}
