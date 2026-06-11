<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Controller;
use App\Http\Resources\Finance\PaymentHistoryResource;
use App\Models\Club;
use App\Models\Member;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentHistoryController extends Controller
{
    private const SELECTED_MEMBER_PAYMENT_HISTORY_ROLE_SLUGS = [
        'president-admin',
        'secretary',
        'treasurer',
    ];

    private const DEFAULT_PER_PAGE = 10;

    private const MAX_PER_PAGE = 25;

    public function own(Request $request, Club $club): JsonResponse
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

        return $this->paymentHistoryResponse($request, $club, $member);
    }

    public function show(Request $request, Club $club, Member $member): JsonResponse
    {
        if ((int) $member->club_id !== (int) $club->id) {
            return response()->json([
                'message' => 'Member does not belong to this club.',
            ], 404);
        }

        if (! $this->userCanReadSelectedMemberPaymentHistory($request->user(), $club)) {
            return response()->json([
                'message' => 'This action is unauthorized.',
            ], 403);
        }

        return $this->paymentHistoryResponse($request, $club, $member);
    }

    private function paymentHistoryResponse(Request $request, Club $club, Member $member): JsonResponse
    {
        $payments = Payment::query()
            ->where('club_id', $club->id)
            ->where('member_id', $member->id)
            ->with([
                'member',
                'receivedBy',
                'receipts' => fn ($query) => $query
                    ->where('status', 'issued')
                    ->orderByDesc('receipt_date')
                    ->orderByDesc('id'),
            ])
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->paginate($this->perPage($request));

        return response()->json([
            'payment_history' => PaymentHistoryResource::collection($payments->getCollection())->resolve(),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
                'last_page' => $payments->lastPage(),
            ],
        ]);
    }

    private function perPage(Request $request): int
    {
        $requested = (int) $request->query('per_page', self::DEFAULT_PER_PAGE);

        if ($requested < 1) {
            return self::DEFAULT_PER_PAGE;
        }

        return min($requested, self::MAX_PER_PAGE);
    }

    private function userCanReadSelectedMemberPaymentHistory(User $user, Club $club): bool
    {
        return in_array(
            $this->activeRoleSlug($user, $club),
            self::SELECTED_MEMBER_PAYMENT_HISTORY_ROLE_SLUGS,
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
