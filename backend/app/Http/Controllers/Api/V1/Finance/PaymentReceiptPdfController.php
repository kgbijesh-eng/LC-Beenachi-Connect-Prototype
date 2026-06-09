<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\Member;
use App\Models\Payment;
use App\Services\Finance\PaymentReceiptPdfService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PaymentReceiptPdfController extends Controller
{
    public function show(
        Request $request,
        Club $club,
        Member $member,
        Payment $payment,
        PaymentReceiptPdfService $receiptPdf
    ): Response|JsonResponse {
        $relationshipError = $this->relationshipError($club, $member, $payment);

        if ($relationshipError instanceof JsonResponse) {
            return $relationshipError;
        }

        try {
            $response = $receiptPdf->download($request->user(), $club, $member, $payment);
        } catch (AuthorizationException) {
            return response()->json([
                'message' => 'This action is unauthorized.',
            ], 403);
        }

        if (! $response) {
            return response()->json([
                'message' => 'Receipt has not been generated for this payment.',
            ], 404);
        }

        return $response;
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