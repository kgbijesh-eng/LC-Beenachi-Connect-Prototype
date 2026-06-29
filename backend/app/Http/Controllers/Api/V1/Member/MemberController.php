<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    private const EXTENDED_STRING_RULES = [
        'salutation',
        'first_name',
        'middle_name',
        'last_name',
        'occupation',
        'spouse_name',
        'membership_type',
        'membership_category',
        'membership_program',
        'membership_sponsor_name',
        'home_phone',
        'alternate_phone',
        'address_line_1',
        'address_line_2',
        'city_locality',
        'district',
        'state',
        'postal_code',
        'country',
    ];

    private const SENSITIVE_PROFILE_ROLE_SLUGS = [
        'president-admin',
        'secretary',
        'treasurer',
    ];

    public function index(Club $club): JsonResponse
    {
        $members = $club->members()
            ->orderBy('full_name')
            ->get()
            ->map(fn (Member $member): array => $this->memberPayload($member, false))
            ->values()
            ->all();

        return response()->json([
            'members' => $members,
        ]);
    }

    public function own(Request $request, Club $club): JsonResponse
    {
        $user = $request->user();

        $hasActiveMembership = $user->clubMemberships()
            ->where('club_id', $club->id)
            ->where('status', 'active')
            ->exists();

        if (! $hasActiveMembership) {
            return response()->json([
                'message' => 'This action is unauthorized.',
            ], 403);
        }

        $members = $user->members()
            ->where('club_id', $club->id)
            ->get();

        if ($members->count() !== 1) {
            return response()->json([
                'message' => $members->isEmpty()
                    ? 'Member record not linked.'
                    : 'Multiple member records are linked to this account.',
            ], $members->isEmpty() ? 403 : 500);
        }

        $member = $members->firstOrFail();

        return response()->json([
            'member' => $this->memberPayload($member, true),
        ]);
    }
    public function show(Request $request, Club $club, Member $member): JsonResponse
    {
        if ($member->club_id !== $club->id) {
            return response()->json([
                'message' => 'Member does not belong to this club.',
            ], 404);
        }

        return response()->json([
            'member' => $this->memberPayload(
                $member,
                $this->canViewSensitiveProfile($request, $club, $member)
            ),
        ]);
    }

    public function store(Request $request, Club $club): JsonResponse
    {
        $request->merge($this->normalizeMemberInput($request->all()));

        $validated = $request->validate([
            'club_id' => ['prohibited'],
            'current_age' => ['prohibited'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'member_code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('members', 'member_code')
                    ->where('club_id', $club->id),
            ],
            'full_name' => ['required', 'string', 'max:255'],
            ...$this->extendedProfileRules(),
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'joined_at' => ['nullable', 'date'],
            'blood_group' => ['nullable', 'string', 'max:255'],
            'blood_donor_consent' => ['nullable', 'boolean'],
        ]);

        $validated['club_id'] = $club->id;
        $validated['status'] = $validated['status'] ?? 'active';
        $validated['blood_donor_consent'] = $validated['blood_donor_consent'] ?? false;

        $member = Member::create($validated);

        return response()->json([
            'member' => $this->memberPayload($member, true),
        ], 201);
    }

    public function update(Request $request, Club $club, Member $member): JsonResponse
    {
        if ($member->club_id !== $club->id) {
            return response()->json([
                'message' => 'Member does not belong to this club.',
            ], 404);
        }

        $request->merge($this->normalizeMemberInput($request->all()));

        $validated = $request->validate([
            'club_id' => ['prohibited'],
            'current_age' => ['prohibited'],
            'user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'member_code' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('members', 'member_code')
                    ->where('club_id', $club->id)
                    ->ignore($member->id),
            ],
            'full_name' => ['sometimes', 'required', 'string', 'max:255'],
            ...$this->extendedProfileRules(true),
            'phone' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'status' => ['sometimes', 'required', 'string', Rule::in(['active', 'inactive'])],
            'joined_at' => ['sometimes', 'nullable', 'date'],
            'blood_group' => ['sometimes', 'nullable', 'string', 'max:255'],
            'blood_donor_consent' => ['sometimes', 'boolean'],
        ]);

        $member->update($validated);

        return response()->json([
            'member' => $this->memberPayload($member->refresh(), true),
        ]);
    }

    private function memberPayload(Member $member, bool $includeSensitive): array
    {
        $payload = [
            'id' => $member->id,
            'club_id' => $member->club_id,
            'user_id' => $member->user_id,
            'member_code' => $member->member_code,
            'full_name' => $member->full_name,
            'salutation' => $member->salutation,
            'first_name' => $member->first_name,
            'middle_name' => $member->middle_name,
            'last_name' => $member->last_name,
            'gender' => $member->gender,
            'occupation' => $member->occupation,
            'membership_type' => $member->membership_type,
            'membership_category' => $member->membership_category,
            'membership_program' => $member->membership_program,
            'status' => $member->status,
            'joined_at' => $member->joined_at?->toDateString(),
            'blood_group' => $member->blood_group,
            'blood_donor_consent' => $member->blood_donor_consent,
        ];

        if (! $includeSensitive) {
            return $payload;
        }

        return [
            ...$payload,
            'birth_date' => $member->birth_date?->toDateString(),
            'spouse_name' => $member->spouse_name,
            'wedding_anniversary' => $member->wedding_anniversary?->toDateString(),
            'membership_sponsor_name' => $member->membership_sponsor_name,
            'phone' => $member->phone,
            'home_phone' => $member->home_phone,
            'alternate_phone' => $member->alternate_phone,
            'email' => $member->email,
            'address_line_1' => $member->address_line_1,
            'address_line_2' => $member->address_line_2,
            'city_locality' => $member->city_locality,
            'district' => $member->district,
            'state' => $member->state,
            'postal_code' => $member->postal_code,
            'country' => $member->country,
        ];
    }

    private function extendedProfileRules(bool $sometimes = false): array
    {
        $optional = $sometimes ? ['sometimes', 'nullable'] : ['nullable'];
        $rules = [];

        foreach (self::EXTENDED_STRING_RULES as $field) {
            $rules[$field] = [...$optional, 'string', 'max:255'];
        }

        $rules['birth_date'] = [...$optional, 'date'];
        $rules['wedding_anniversary'] = [...$optional, 'date'];
        $rules['gender'] = [...$optional, Rule::in(['male', 'female', 'other'])];

        return $rules;
    }

    private function normalizeMemberInput(array $input): array
    {
        foreach ($input as $field => $value) {
            if (! is_string($value)) {
                continue;
            }

            $value = str_replace('_x000D_', ' ', $value);
            $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);

            if ($value === '') {
                $input[$field] = null;
                continue;
            }

            $input[$field] = match ($field) {
                'member_code' => strtoupper($value),
                'email' => strtolower($value),
                'gender' => $this->normalizeGender($value),
                default => $value,
            };
        }

        return $input;
    }

    private function normalizeGender(string $value): string
    {
        return match (strtolower($value)) {
            'm', 'male' => 'male',
            'f', 'female' => 'female',
            'o', 'other', 'non-binary', 'nonbinary' => 'other',
            default => strtolower($value),
        };
    }

    private function canViewSensitiveProfile(Request $request, Club $club, Member $member): bool
    {
        $user = $request->user();

        if (! $user) {
            return false;
        }

        if ($member->user_id !== null && $member->user_id === $user->id) {
            return true;
        }

        $roleSlug = $user->clubMemberships()
            ->with('role')
            ->where('club_id', $club->id)
            ->where('status', 'active')
            ->first()
            ?->role
            ?->slug;

        return in_array($roleSlug, self::SENSITIVE_PROFILE_ROLE_SLUGS, true);
    }
}
