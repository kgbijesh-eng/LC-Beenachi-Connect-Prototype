<?php

namespace App\Http\Controllers\Api\V1\Announcements;

use App\Exceptions\Announcements\InvalidClubAnnouncementReadStateException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Announcements\ListClubAnnouncementsRequest;
use App\Http\Resources\Announcements\ClubAnnouncementResource;
use App\Models\Club;
use App\Models\ClubAnnouncement;
use App\Services\Access\ClubPermissionService;
use App\Services\Announcements\ClubAnnouncementReadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ClubAnnouncementController extends Controller
{
    public function index(
        ListClubAnnouncementsRequest $request,
        Club $club,
        ClubPermissionService $permissions,
        ClubAnnouncementReadService $announcements,
    ): JsonResponse {
        $management = $permissions->userHasPermission($request->user(), ClubAnnouncement::PERMISSION_MANAGEMENT_VIEW, (int) $club->id);
        $now = Carbon::now('UTC');
        $filters = $request->normalizedFilters();
        try {
            $page = $announcements->listForClub($club, $filters, $management, $now);
        } catch (InvalidClubAnnouncementReadStateException) {
            return response()->json(['message' => 'Announcement records are unavailable because stored data failed integrity validation.'], 500);
        }

        $request->attributes->set('announcements_management_reader', $management);
        $request->attributes->set('announcements_effective_now', $now);

        return response()->json([
            'data' => ClubAnnouncementResource::collection($page->items())->resolve($request),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
                'filters' => $filters,
            ],
        ]);
    }

    public function show(
        Request $request,
        Club $club,
        ClubAnnouncement $announcement,
        ClubPermissionService $permissions,
        ClubAnnouncementReadService $announcements,
    ): JsonResponse {
        $management = $permissions->userHasPermission($request->user(), ClubAnnouncement::PERMISSION_MANAGEMENT_VIEW, (int) $club->id);
        $now = Carbon::now('UTC');
        try {
            if (! $announcements->canReadDetail($club, $announcement, $management, $now)) {
                return $this->notFound();
            }
        } catch (InvalidClubAnnouncementReadStateException) {
            return $this->notFound();
        }

        $request->attributes->set('announcements_management_reader', $management);
        $request->attributes->set('announcements_effective_now', $now);

        return response()->json(['data' => (new ClubAnnouncementResource($announcement))->resolve($request)]);
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['message' => 'Announcement not found.'], 404);
    }
}
