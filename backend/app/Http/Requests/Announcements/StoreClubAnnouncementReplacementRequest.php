<?php

namespace App\Http\Requests\Announcements;

use App\Models\ClubAnnouncement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClubAnnouncementReplacementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:5000'],
            'priority' => ['required', Rule::in(ClubAnnouncement::priorities())],
            'expires_at' => ['nullable', 'date_format:Y-m-d\TH:i:sP'],
            'audience' => ['prohibited'],
            'scheduled_publish_at' => ['prohibited'],
            'club_id' => ['prohibited'],
            'status' => ['prohibited'],
            'lock_version' => ['prohibited'],
        ];
    }
}
