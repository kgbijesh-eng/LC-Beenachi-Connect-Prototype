<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CompleteAccountActivationRequest;
use App\Http\Requests\Auth\IssueAccountActivationRequest;
use App\Models\Club;
use App\Services\Auth\AccountActivationException;
use App\Services\Auth\AccountActivationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;

class AccountActivationController extends Controller
{
    public function issue(
        IssueAccountActivationRequest $request,
        Club $club,
        AccountActivationService $service
    ): JsonResponse {
        try {
            $issued = $service->issue(
                $club,
                $request->validated('member_code'),
                $request->validated('email'),
                'authenticated',
                $request->user()
            );
        } catch (AccountActivationException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Account activation issued.',
            'activation_url' => $issued['link'],
            'expires_at' => $issued['activation']->expires_at->toISOString(),
        ], 201);
    }

    public function complete(
        CompleteAccountActivationRequest $request,
        AccountActivationService $service
    ): JsonResponse {
        try {
            $service->complete(
                $request->validated('token'),
                $request->validated('email'),
                $request->validated('password')
            );
        } catch (AccountActivationException|QueryException) {
            return $this->genericFailure();
        }

        return response()->json([
            'message' => 'Account activation completed. Sign in with your operational credentials.',
        ]);
    }

    private function genericFailure(): JsonResponse
    {
        return response()->json([
            'message' => 'Account activation could not be completed.',
        ], 422);
    }
}
