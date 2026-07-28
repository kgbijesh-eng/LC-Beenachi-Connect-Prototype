<?php

namespace App\Http\Requests\Announcements;

use App\Models\ClubAnnouncement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClubAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lock_version' => ['required', 'integer', 'min:1'],
            'title' => ['sometimes', 'string', 'max:160'],
            'body' => ['sometimes', 'string', 'max:5000'],
            'priority' => ['sometimes', Rule::in(ClubAnnouncement::priorities())],
            'audience' => ['sometimes', Rule::in(ClubAnnouncement::audiences())],
            'expires_at' => ['sometimes', 'nullable', 'date_format:Y-m-d\TH:i:sP'],
            'scheduled_publish_at' => ['prohibited'],
            'club_id' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }
}
