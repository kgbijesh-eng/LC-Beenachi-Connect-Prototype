<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\Member;
use Illuminate\Http\JsonResponse;

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
