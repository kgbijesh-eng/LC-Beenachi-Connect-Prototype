<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\RecordPaymentRequest;
use App\Http\Resources\Finance\PaymentResource;
use App\Models\Club;
use App\Models\Member;
use App\Services\Finance\PaymentRecordingService;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function store(
        RecordPaymentRequest $request,
        Club $club,
        Member $member,
        PaymentRecordingService $payments
    ): JsonResponse {
        if ($member->club_id !== $club->id) {
            return response()->json([
                'message' => 'Member does not belong to this club.',
            ], 404);
        }

        $payment = $payments->record(
            $request->user(),
            $member,
            $request->validated()
        );

        return response()->json([
            'payment' => (new PaymentResource($payment))->resolve(),
        ], 201);
    }
}