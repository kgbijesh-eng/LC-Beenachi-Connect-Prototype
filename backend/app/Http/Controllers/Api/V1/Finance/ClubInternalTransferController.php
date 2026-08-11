<?php
namespace App\Http\Controllers\Api\V1\Finance;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreClubInternalTransferRequest;
use App\Http\Resources\Finance\ClubInternalTransferResource;
use App\Models\Club;
use App\Services\Finance\ClubInternalTransferService;
use App\Services\Finance\Exceptions\{ClubInternalTransferConflictException, ClubInternalTransferException, JournalPostingException};
use Illuminate\Http\JsonResponse;
class ClubInternalTransferController extends Controller
{
    public function options(Club $club, ClubInternalTransferService $service): JsonResponse { return response()->json(['data' => $service->options($club)]); }
    public function index(Club $club, ClubInternalTransferService $service): JsonResponse { $page = $service->list($club); return response()->json(['data' => ClubInternalTransferResource::collection($page->items())->resolve(), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()]]); }
    public function show(Club $club, string $transfer, ClubInternalTransferService $service): JsonResponse { return response()->json(['data' => (new ClubInternalTransferResource($service->find($club, $transfer)))->resolve()]); }
    public function store(StoreClubInternalTransferRequest $request, Club $club, ClubInternalTransferService $service): JsonResponse { try { return response()->json(['data' => (new ClubInternalTransferResource($service->record($club, $request->user(), $request->validated())))->resolve()], 201); } catch (ClubInternalTransferConflictException $e) { return response()->json(['message' => $e->getMessage(), 'code' => 'TRANSFER_IDEMPOTENCY_CONFLICT'], 409); } catch (ClubInternalTransferException|JournalPostingException $e) { return response()->json(['message' => $e->getMessage(), 'code' => 'TRANSFER_POSTING_REJECTED'], 422); } }
}
