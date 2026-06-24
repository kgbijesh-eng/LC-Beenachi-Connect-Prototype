<?php

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\RunClubReportRequest;
use App\Models\Club;
use App\Services\Access\ClubPermissionService;
use App\Services\Reports\ClubReportService;
use App\Services\Reports\FinancialReportService;
use App\Services\Reports\ReportCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ClubReportController extends Controller
{
    public function index(Request $request, Club $club, ReportCatalog $catalog, ClubPermissionService $permissions): JsonResponse
    {
        $includeFinancial = $permissions->userHasPermission(
            $request->user(),
            ReportCatalog::PERMISSION_FINANCE,
            $club->id
        );

        return response()->json(['data' => $catalog->definitionsFor($includeFinancial)]);
    }

    public function show(
        RunClubReportRequest $request,
        Club $club,
        string $reportKey,
        ReportCatalog $catalog,
        ClubReportService $reports,
        FinancialReportService $financialReports,
        ClubPermissionService $permissions
    ): JsonResponse {
        $definition = $catalog->definition($reportKey);

        if (($definition['financial'] ?? false) && ! $permissions->userHasPermission(
            $request->user(),
            ReportCatalog::PERMISSION_FINANCE,
            $club->id
        )) {
            throw new AccessDeniedHttpException('Financial report access denied.');
        }

        $result = ($definition['financial'] ?? false)
            ? $financialReports->run($club, $definition, $request->validated())
            : $reports->run($club, $definition, $request->validated());

        return response()->json($result);
    }
}