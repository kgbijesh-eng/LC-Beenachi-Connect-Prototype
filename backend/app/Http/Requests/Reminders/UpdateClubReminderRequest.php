<?php

namespace App\Http\Requests\Reminders;

use App\Models\ClubReminder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClubReminderRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'lock_version' => ['required','integer','min:1'],
            'title' => ['sometimes','string','max:200'],
            'description' => ['sometimes','nullable','string','max:5000'],
            'priority' => ['sometimes',Rule::in(ClubReminder::priorities())],
            'visibility' => ['sometimes',Rule::in(ClubReminder::visibilities())],
            'is_all_day' => ['sometimes','boolean'],
            'due_date' => ['sometimes','nullable','date_format:Y-m-d'],
            'due_at' => ['sometimes','nullable','date_format:Y-m-d\TH:i:sP'],
            'club_id' => ['prohibited'], 'status' => ['prohibited'],
        ];
    }
}
