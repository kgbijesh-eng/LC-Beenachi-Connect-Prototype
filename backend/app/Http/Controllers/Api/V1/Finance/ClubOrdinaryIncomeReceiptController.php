<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\{ListClubOrdinaryIncomeReceiptsRequest, StoreClubOrdinaryIncomeReceiptRequest};
use App\Http\Resources\Finance\ClubOrdinaryIncomeReceiptResource;
use App\Models\Club;
use App\Services\Finance\{ClubOrdinaryIncomeReceiptHistoryService, ClubOrdinaryIncomeReceiptService};
use App\Services\Finance\Exceptions\{ClubOrdinaryIncomeReceiptConflictException, ClubOrdinaryIncomeReceiptException, JournalPostingException};
use Illuminate\Http\JsonResponse;

class ClubOrdinaryIncomeReceiptController extends Controller
{
    public function index(ListClubOrdinaryIncomeReceiptsRequest $request, Club $club, ClubOrdinaryIncomeReceiptHistoryService $history): JsonResponse { $page = $history->list($club, $request->validated()); return response()->json(['data' => ClubOrdinaryIncomeReceiptResource::collection($page->items())->resolve(), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()]]); }
    public function show(Club $club, string $receipt, ClubOrdinaryIncomeReceiptHistoryService $history): JsonResponse { return response()->json(['data' => (new ClubOrdinaryIncomeReceiptResource($history->find($club, $receipt)))->resolve()]); }
    public function options(Club $club, ClubOrdinaryIncomeReceiptService $receipts): JsonResponse { return response()->json(['data' => $receipts->options($club)]); }
    public function store(StoreClubOrdinaryIncomeReceiptRequest $request, Club $club, ClubOrdinaryIncomeReceiptService $receipts): JsonResponse
    {
        try { return response()->json(['data' => (new ClubOrdinaryIncomeReceiptResource($receipts->record($club, $request->user(), $request->validated())))->resolve()], 201); }
        catch (ClubOrdinaryIncomeReceiptConflictException $exception) { return response()->json(['message' => $exception->getMessage(), 'code' => 'ORDINARY_INCOME_RECEIPT_IDEMPOTENCY_CONFLICT'], 409); }
        catch (ClubOrdinaryIncomeReceiptException|JournalPostingException $exception) { return response()->json(['message' => $exception->getMessage(), 'code' => 'ORDINARY_INCOME_RECEIPT_POSTING_REJECTED'], 422); }
    }
}
