<?php

namespace App\Http\Requests\Members;

use App\Models\Club;
use Illuminate\Foundation\Http\FormRequest;

class MemberDataImportPreviewRequest extends FormRequest
{
    private const IMPORT_PREVIEW_ROLE_SLUGS = [
        'president-admin',
        'secretary',
        'treasurer',
    ];

    public function authorize(): bool
    {
        $user = $this->user();
        $club = $this->route('club');

        if (! $user || ! $club instanceof Club) {
            return false;
        }

        $roleSlug = $user->clubMemberships()
            ->with('role')
            ->where('club_id', $club->id)
            ->where('status', 'active')
            ->first()
            ?->role
            ?->slug;

        return in_array($roleSlug, self::IMPORT_PREVIEW_ROLE_SLUGS, true);
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
