<?php

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\RunClubReportRequest;
use App\Models\Club;
use App\Services\Reports\ClubReportService;
use App\Services\Reports\ReportCatalog;
use Illuminate\Http\JsonResponse;

class ClubReportController extends Controller
{
    public function index(ReportCatalog $catalog): JsonResponse
    {
        return response()->json(['data' => $catalog->definitions()]);
    }

    public function show(RunClubReportRequest $request, Club $club, string $reportKey, ReportCatalog $catalog, ClubReportService $reports): JsonResponse
    {
        return response()->json($reports->run($club, $catalog->definition($reportKey), $request->validated()));
    }
}