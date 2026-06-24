<?php

namespace App\Http\Requests\Reminders;

use App\Models\ClubReminder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClubReminderRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'title' => ['required','string','max:200'],
            'description' => ['nullable','string','max:5000'],
            'priority' => ['required',Rule::in(ClubReminder::priorities())],
            'visibility' => ['required',Rule::in(ClubReminder::visibilities())],
            'is_all_day' => ['required','boolean'],
            'due_date' => ['required_if:is_all_day,true','prohibited_if:is_all_day,false','date_format:Y-m-d'],
            'due_at' => ['required_if:is_all_day,false','prohibited_if:is_all_day,true','date_format:Y-m-d\TH:i:sP'],
            'club_id' => ['prohibited'], 'status' => ['prohibited'], 'lock_version' => ['prohibited'],
            'activated_at' => ['prohibited'], 'completed_at' => ['prohibited'], 'cancelled_at' => ['prohibited'],
        ];
    }
}
