<?php

namespace App\Http\Controllers\Api\V1\Members;

use App\Http\Controllers\Controller;
use App\Http\Requests\Members\MemberDataImportPreviewRequest;
use App\Http\Resources\Members\MemberDataImportPreviewResource;
use App\Models\Club;
use App\Services\Members\MemberDataImportPreviewService;
use Illuminate\Http\JsonResponse;

class MemberDataImportPreviewController extends Controller
{
    public function __construct(
        private readonly MemberDataImportPreviewService $previewService
    ) {
    }

    public function store(MemberDataImportPreviewRequest $request, Club $club): JsonResponse
    {
        $preview = $this->previewService->preview(
            $club->id,
            $request->validated('rows')
        );

        return response()->json([
            'import_preview' => (new MemberDataImportPreviewResource($preview))->resolve($request),
        ]);
    }
}
