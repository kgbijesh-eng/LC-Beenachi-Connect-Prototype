<?php

namespace App\Http\Controllers\Api\V1\Members;

use App\Http\Controllers\Controller;
use App\Http\Requests\Members\MemberDataImportCommitRequest;
use App\Http\Resources\Members\MemberDataImportCommitResource;
use App\Models\Club;
use App\Services\Members\MemberDataImportCommitService;
use Illuminate\Http\JsonResponse;

class MemberDataImportCommitController extends Controller
{
    public function __construct(
        private readonly MemberDataImportCommitService $commitService
    ) {
    }

    public function store(MemberDataImportCommitRequest $request, Club $club): JsonResponse
    {
        $result = $this->commitService->commit(
            $club->id,
            $request->validated('rows')
        );

        return response()->json([
            'import_commit' => (new MemberDataImportCommitResource($result))->resolve($request),
        ]);
    }
}
