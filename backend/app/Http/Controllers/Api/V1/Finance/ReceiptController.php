<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\GenerateReceiptRequest;
use App\Http\Resources\Finance\ReceiptResource;
use App\Models\Club;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Receipt;
use App\Services\Access\ClubPermissionService;
use App\Services\Finance\PaymentRecordingService;
use App\Services\Finance\ReceiptGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReceiptController extends Controller
{
    public function store(
        GenerateReceiptRequest $request,
        Club $club,
        Member $member,
        Payment $payment,
        ReceiptGenerationService $receipts
    ): JsonResponse {
        $relationshipError = $this->relationshipError($club, $member, $payment);

        if ($relationshipError instanceof JsonResponse) {
            return $relationshipError;
        }

        $receipt = $receipts->generate(
            $request->user(),
            $payment,
            $request->validated()
        );

        return response()->json([
            'receipt' => (new ReceiptResource($receipt))->resolve(),
        ], 201);
    }

    public function show(
        Request $request,
        Club $club,
        Member $member,
        Payment $payment,
        ClubPermissionService $permissions
    ): JsonResponse {
        $relationshipError = $this->relationshipError($club, $member, $payment);

        if ($relationshipError instanceof JsonResponse) {
            return $relationshipError;
        }

        $canViewAnyReceipt = $permissions->userHasPermission(
            $request->user(),
            PaymentRecordingService::PERMISSION_RECORD_PAYMENT,
            (int) $club->id
        );

        $isOwnMemberReceipt = $member->user_id !== null
            && (int) $member->user_id === (int) $request->user()->id;

        if (! $canViewAnyReceipt && ! $isOwnMemberReceipt) {
            return response()->json([
                'message' => 'This action is unauthorized.',
            ], 403);
        }

        $receipt = Receipt::query()
            ->where('club_id', $club->id)
            ->where('payment_id', $payment->id)
            ->where('status', 'issued')
            ->with(['payment.member', 'issuedBy'])
            ->first();

        if (! $receipt) {
            return response()->json([
                'message' => 'Receipt has not been generated for this payment.',
            ], 404);
        }

        return response()->json([
            'receipt' => (new ReceiptResource($receipt))->resolve(),
        ]);
    }

    private function relationshipError(Club $club, Member $member, Payment $payment): ?JsonResponse
    {
        if ($member->club_id !== $club->id) {
            return response()->json([
                'message' => 'Member does not belong to this club.',
            ], 404);
        }

        if ($payment->club_id !== $club->id || $payment->member_id !== $member->id) {
            return response()->json([
                'message' => 'Payment does not belong to this club member.',
            ], 404);
        }

        return null;
    }
}
