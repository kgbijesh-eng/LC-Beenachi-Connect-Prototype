<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreClubExpenseRequest;
use App\Http\Resources\Finance\ClubExpenseResource;
use App\Models\Club;
use App\Services\Finance\ClubExpenseRecordingService;
use App\Services\Finance\Exceptions\ClubExpenseConflictException;
use App\Services\Finance\Exceptions\ClubExpenseException;
use App\Services\Finance\Exceptions\JournalPostingException;
use Illuminate\Http\JsonResponse;

class ClubExpenseController extends Controller
{
    public function options(Club $club, ClubExpenseRecordingService $expenses): JsonResponse
    {
        return response()->json(['data' => $expenses->options($club)]);
    }

    public function store(StoreClubExpenseRequest $request, Club $club, ClubExpenseRecordingService $expenses): JsonResponse
    {
        try {
            $expense = $expenses->record($club, $request->user(), $request->validated());
            return response()->json(['data' => (new ClubExpenseResource($expense))->resolve()], 201);
        } catch (ClubExpenseConflictException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'code' => 'EXPENSE_IDEMPOTENCY_CONFLICT'], 409);
        } catch (ClubExpenseException|JournalPostingException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'code' => 'EXPENSE_POSTING_REJECTED'], 422);
        }
    }
}
