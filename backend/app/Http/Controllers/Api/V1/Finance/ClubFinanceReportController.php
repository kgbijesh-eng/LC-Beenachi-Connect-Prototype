<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Controller;
use App\Http\Resources\Finance\ClubFinanceReportResource;
use App\Models\Club;
use App\Models\User;
use App\Services\Finance\ClubFinanceReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClubFinanceReportController extends Controller
{
    private const CLUB_FINANCE_REPORT_ROLE_SLUGS = [
        'president-admin',
        'secretary',
        'treasurer',
    ];

    public function reports(Request $request, Club $club, ClubFinanceReportService $reports): JsonResponse
    {
        if (! $this->userCanReadClubFinanceReports($request->user(), $club)) {
            return response()->json([
                'message' => 'This action is unauthorized.',
            ], 403);
        }

        return response()->json([
            'finance_reports' => (new ClubFinanceReportResource(
                $reports->reportsForClub($club)
            ))->resolve(),
        ]);
    }

    public function balanceSheet(Request $request, Club $club, ClubFinanceReportService $reports): JsonResponse
    {
        if (! $this->userCanReadClubFinanceReports($request->user(), $club)) {
            return response()->json([
                'message' => 'This action is unauthorized.',
            ], 403);
        }

        return response()->json([
            'balance_sheet' => (new ClubFinanceReportResource(
                $reports->balanceSheetForClub($club)
            ))->resolve(),
        ]);
    }

    private function userCanReadClubFinanceReports(User $user, Club $club): bool
    {
        return in_array(
            $this->activeRoleSlug($user, $club),
            self::CLUB_FINANCE_REPORT_ROLE_SLUGS,
            true
        );
    }

    private function activeRoleSlug(User $user, Club $club): ?string
    {
        $membership = $user->clubMemberships()
            ->with('role')
            ->where('club_id', $club->id)
            ->where('status', 'active')
            ->first();

        return $membership?->role?->slug;
    }
}
