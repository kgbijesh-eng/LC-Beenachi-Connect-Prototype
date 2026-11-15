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
        $permissions = app(\App\Services\Access\ClubPermissionService::class);

        if ($permissions->userHasPermission($user, 'finance.club.view', (int) $club->id)) {
            return true;
        }

        return $permissions->userHasPermission($user, 'finance.self.view', (int) $club->id)
            && $member->user_id !== null
            && $member->user_id === $user->id;
    }

}