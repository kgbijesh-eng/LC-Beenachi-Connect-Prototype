<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\{CorrectClubOrdinaryIncomeReceiptRequest, ListClubOrdinaryIncomeReceiptsRequest, StoreClubOrdinaryIncomeReceiptRequest};
use App\Http\Resources\Finance\ClubOrdinaryIncomeReceiptResource;
use App\Models\Club;
use App\Services\Finance\{ClubOrdinaryIncomeReceiptCorrectionService, ClubOrdinaryIncomeReceiptHistoryService, ClubOrdinaryIncomeReceiptService};
use App\Services\Finance\Exceptions\{ClubOrdinaryIncomeReceiptConflictException, ClubOrdinaryIncomeReceiptCorrectionConflictException, ClubOrdinaryIncomeReceiptCorrectionException, ClubOrdinaryIncomeReceiptException, JournalPostingException};
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Throwable;

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
    public function correct(CorrectClubOrdinaryIncomeReceiptRequest $request, Club $club, string $receipt, ClubOrdinaryIncomeReceiptCorrectionService $corrections): JsonResponse
    {
        try { return response()->json(['data' => (new ClubOrdinaryIncomeReceiptResource($corrections->correct($club, $receipt, $request->user(), $request->validated())))->resolve()]); }
        catch (ClubOrdinaryIncomeReceiptCorrectionException $exception) { return response()->json(['message' => $exception->getMessage(), 'code' => $exception->errorCode], $exception->httpStatus); }
        catch (ClubOrdinaryIncomeReceiptException $exception) { return response()->json(['message' => $exception->getMessage(), 'code' => 'ORDINARY_INCOME_RECEIPT_CORRECTION_INVALID_REPLACEMENT'], 422); }
        catch (JournalPostingException $exception) { return response()->json(['message' => 'The correction journal could not be posted.', 'code' => 'ORDINARY_INCOME_RECEIPT_CORRECTION_POSTING_FAILED'], 422); }
        catch (ModelNotFoundException) { return response()->json(['message' => 'Resource not found.'], 404); }
        catch (Throwable) { return response()->json(['message' => 'The correction could not be completed.', 'code' => 'ORDINARY_INCOME_RECEIPT_CORRECTION_UNEXPECTED'], 500); }
    }
}
