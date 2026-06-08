<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\Member;
use App\Models\User;
use App\Services\Finance\MemberLedgerBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberLedgerBalanceController extends Controller
{
    private const BALANCE_LOOKUP_ROLE_SLUGS = [
        'president-admin',
        'secretary',
        'treasurer',
    ];

    public function own(Request $request, Club $club, MemberLedgerBalanceService $balances): JsonResponse
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
            'balance' => $balances->calculateForMember($member),
        ]);
    }

    public function show(Request $request, Club $club, Member $member, MemberLedgerBalanceService $balances): JsonResponse
    {
        if ($member->club_id !== $club->id) {
            return response()->json([
                'message' => 'Member does not belong to this club.',
            ], 404);
        }

        if (! $this->userCanReadMemberBalance($request->user(), $club, $member)) {
            return response()->json([
                'message' => 'This action is unauthorized.',
            ], 403);
        }

        return response()->json([
            'balance' => $balances->calculateForMember($member),
        ]);
    }

    private function userCanReadMemberBalance(User $user, Club $club, Member $member): bool
    {
        $roleSlug = $this->activeRoleSlug($user, $club);

        if (in_array($roleSlug, self::BALANCE_LOOKUP_ROLE_SLUGS, true)) {
            return true;
        }

        return $roleSlug === 'member'
            && $member->user_id !== null
            && $member->user_id === $user->id;
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