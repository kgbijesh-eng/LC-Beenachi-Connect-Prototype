<?php

namespace App\Http\Requests\Members;

use App\Models\Club;
use Illuminate\Foundation\Http\FormRequest;

class MemberDataImportCommitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $club = $this->route('club');

        if (! $user || ! $club instanceof Club) {
            return false;
        }

        return app(\App\Services\Access\ClubPermissionService::class)->effectiveOffice($user, (int) $club->id) !== null;
    }

    public function rules(): array
    {
        return [
            'rows' => ['required', 'array', 'min:1', 'max:500'],
            'rows.*' => ['required', 'array'],

            'commit' => ['missing'],
            'import' => ['missing'],
            'dry_run' => ['missing'],
            'file' => ['missing'],
            'upload' => ['missing'],
            'xlsx' => ['missing'],
            'club_id' => ['missing'],
            'created_by_user_id' => ['missing'],
            'finance' => ['missing'],
            'payments' => ['missing'],
            'receipts' => ['missing'],
            'ledger' => ['missing'],
        ];
    }
}
