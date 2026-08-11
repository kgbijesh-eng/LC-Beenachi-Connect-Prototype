<?php

namespace App\Http\Requests\Reminders;

use Illuminate\Foundation\Http\FormRequest;

class TransitionClubReminderRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'lock_version' => ['required','integer','min:1'],
            'cancellation_reason' => ['sometimes','nullable','string','max:1000'],
        ];
    }
}
