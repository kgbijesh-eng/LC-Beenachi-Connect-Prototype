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
    public function index(Club $club): JsonResponse
    {
        $members = $club->members()
            ->orderBy('full_name')
            ->get()
            ->map(fn (Member $member): array => $this->memberPayload($member))
            ->values()
            ->all();

        return response()->json([
            'members' => $members,
        ]);
    }

    public function show(Club $club, Member $member): JsonResponse
    {
        if ($member->club_id !== $club->id) {
            return response()->json([
                'message' => 'Member does not belong to this club.',
            ], 404);
        }

        return response()->json([
            'member' => $this->memberPayload($member),
        ]);
    }

    public function store(Request $request, Club $club): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'member_code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('members', 'member_code')
                    ->where('club_id', $club->id),
            ],
            'full_name' => ['required', 'string', 'max:255'],
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
            'member' => $this->memberPayload($member),
        ], 201);
    }

    public function update(Request $request, Club $club, Member $member): JsonResponse
    {
        if ($member->club_id !== $club->id) {
            return response()->json([
                'message' => 'Member does not belong to this club.',
            ], 404);
        }

        $validated = $request->validate([
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
            'phone' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'status' => ['sometimes', 'required', 'string', Rule::in(['active', 'inactive'])],
            'joined_at' => ['sometimes', 'nullable', 'date'],
            'blood_group' => ['sometimes', 'nullable', 'string', 'max:255'],
            'blood_donor_consent' => ['sometimes', 'boolean'],
        ]);

        $member->update($validated);

        return response()->json([
            'member' => $this->memberPayload($member->refresh()),
        ]);
    }

    private function memberPayload(Member $member): array
    {
        return [
            'id' => $member->id,
            'club_id' => $member->club_id,
            'user_id' => $member->user_id,
            'member_code' => $member->member_code,
            'full_name' => $member->full_name,
            'phone' => $member->phone,
            'email' => $member->email,
            'status' => $member->status,
            'joined_at' => $member->joined_at?->toDateString(),
            'blood_group' => $member->blood_group,
            'blood_donor_consent' => $member->blood_donor_consent,
        ];
    }
}
