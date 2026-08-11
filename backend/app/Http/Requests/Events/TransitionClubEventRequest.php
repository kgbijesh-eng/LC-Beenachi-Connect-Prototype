<?php

namespace App\Http\Requests\Events;

use Illuminate\Foundation\Http\FormRequest;

class TransitionClubEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return array_merge([
            'lock_version' => ['required', 'integer', 'min:1'],
            'cancellation_reason' => ['sometimes', 'string', 'min:1', 'max:1000'],
        ], $this->blockedFieldRules());
    }

    private function blockedFieldRules(): array
    {
        return collect([
            'club_id',
            'type',
            'title',
            'description',
            'venue',
            'visibility',
            'is_all_day',
            'start_at',
            'end_at',
            'start_date',
            'end_date',
            'status',
            'published_at',
            'cancelled_at',
            'completed_at',
            'created_by_user_id',
            'updated_by_user_id',
            'audit',
        ])->mapWithKeys(fn (string $field): array => [$field => ['prohibited']])->all();
    }
}
