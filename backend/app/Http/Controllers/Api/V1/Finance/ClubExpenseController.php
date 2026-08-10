<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreClubExpenseRequest;
use App\Http\Requests\Finance\ListClubExpensesRequest;
use App\Http\Requests\Finance\ReverseClubExpenseRequest;
use App\Http\Resources\Finance\ClubExpenseResource;
use App\Models\Club;
use App\Services\Finance\ClubExpenseRecordingService;
use App\Services\Finance\ClubExpenseHistoryService;
use App\Services\Finance\ClubExpenseReversalService;
use App\Services\Finance\Exceptions\ClubExpenseConflictException;
use App\Services\Finance\Exceptions\ClubExpenseException;
use App\Services\Finance\Exceptions\ClubExpenseReversalConflictException;
use App\Services\Finance\Exceptions\ClubExpenseReversalException;
use App\Services\Finance\Exceptions\JournalPostingException;
use Illuminate\Http\JsonResponse;

class ClubExpenseController extends Controller
{
    public function index(ListClubExpensesRequest $request, Club $club, ClubExpenseHistoryService $history): JsonResponse
    {
        $page = $history->list($club, $request->validated());
        return response()->json(['data' => ClubExpenseResource::collection($page->items())->resolve(), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()]]);
    }

    public function show(Club $club, string $expense, ClubExpenseHistoryService $history): JsonResponse
    {
        return response()->json(['data' => (new ClubExpenseResource($history->find($club, $expense)))->resolve()]);
    }

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

    public function reverse(ReverseClubExpenseRequest $request, Club $club, string $expense, ClubExpenseReversalService $reversals): JsonResponse
    {
        try {
            $record = $reversals->reverse($club, $expense, $request->user(), $request->validated());
            return response()->json(['data' => (new ClubExpenseResource($record))->resolve()]);
        } catch (ClubExpenseReversalConflictException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'code' => 'EXPENSE_REVERSAL_CONFLICT'], 409);
        } catch (ClubExpenseReversalException|JournalPostingException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'code' => 'EXPENSE_REVERSAL_REJECTED'], 422);
        }
    }
}
