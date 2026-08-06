<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ClubAccountStatementRequest;
use App\Http\Resources\Finance\ClubAccountStatementResource;
use App\Models\Club;
use App\Services\Finance\ClubAccountStatementProjectionService;
use App\Services\Finance\ClubAccountStatementQuery;
use App\Services\Finance\Exceptions\AccountingNotActivatedException;
use App\Services\Finance\Exceptions\ClubAccountStatementIntegrityException;
use App\Services\Finance\Exceptions\InvalidClubAccountStatementQueryException;
use Illuminate\Http\JsonResponse;

final class ClubAccountStatementController extends Controller
{
    public function show(
        ClubAccountStatementRequest $request,
        Club $club,
        ClubAccountStatementProjectionService $projection,
    ): JsonResponse {
        try {
            $result = $projection->project(new ClubAccountStatementQuery(
                clubId: $club->id,
                lionsYear: (string) $request->validated('lions_year'),
                periodId: $request->filled('period_id') ? (int) $request->validated('period_id') : null,
                from: $request->validated('from'),
                to: $request->validated('to'),
            ));
        } catch (AccountingNotActivatedException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'code' => 'ACCOUNTING_NOT_ACTIVATED'], 409);
        } catch (ClubAccountStatementIntegrityException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'code' => 'STATEMENT_INTEGRITY_FAILURE'], 409);
        } catch (InvalidClubAccountStatementQueryException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'data' => (new ClubAccountStatementResource($result))->resolve($request),
        ]);
    }
}
