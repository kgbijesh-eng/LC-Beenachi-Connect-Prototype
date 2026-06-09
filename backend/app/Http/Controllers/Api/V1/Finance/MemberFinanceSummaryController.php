<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Controller;
use App\Http\Resources\Finance\MemberFinanceSummaryResource;
use App\Models\Club;
use App\Models\Member;
use App\Models\User;
use App\Services\Finance\MemberFinanceSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberFinanceSummaryController extends Controller
{
    private const SELECTED_MEMBER_LOOKUP_ROLE_SLUGS = [
        'president-admin',
        'secretary',
        'treasurer',
    ];

    public function own(Request $request, Club $club, MemberFinanceSummaryService $summaries): JsonResponse
    {
        $member = Member::query()
            ->where('club_id', $club->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $member) {
            return response()->json([
                'message' => 'Member profile not found for authenticated user in this club.',
            ], 404);
        }

        return response()->json([
            'finance_summary' => (new MemberFinanceSummaryResource(
                $summaries->summaryForMember($member, $request->query())
            ))->resolve(),
        ]);
    }

    public function show(
        Request $request,
        Club $club,
        Member $member,
        MemberFinanceSummaryService $summaries
    ): JsonResponse {
        if ((int) $member->club_id !== (int) $club->id) {
            return response()->json([
                'message' => 'Member does not belong to this club.',
            ], 404);
        }

        if (! $this->userCanReadSelectedMemberSummary($request->user(), $club)) {
            return response()->json([
                'message' => 'This action is unauthorized.',
            ], 403);
        }

        return response()->json([
            'finance_summary' => (new MemberFinanceSummaryResource(
                $summaries->summaryForMember($member, $request->query())
            ))->resolve(),
        ]);
    }

    private function userCanReadSelectedMemberSummary(User $user, Club $club): bool
    {
        return in_array(
            $this->activeRoleSlug($user, $club),
            self::SELECTED_MEMBER_LOOKUP_ROLE_SLUGS,
            true
        );
    }

    private function activeRoleSlug(User $user, Club $club): ?string
    {
        $membership = $user->clubMemberships()
            ->with('role')
            ->where('club_id', $club->id)
            ->where('status', 'active')
            ->first();

        return $membership?->role?->slug;
    }
}
