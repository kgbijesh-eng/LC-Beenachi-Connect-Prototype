<?php

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\ReportCatalog;
use Illuminate\Http\JsonResponse;

class ClubReportController extends Controller
{
    public function index(ReportCatalog $catalog): JsonResponse
    {
        return response()->json(['data' => $catalog->definitions()]);
    }
}
