<?php

namespace App\Http\Controllers\Api\V1\Announcements;

use App\Exceptions\Announcements\InvalidClubAnnouncementReadStateException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Announcements\AnnouncementLockVersionRequest;
use App\Http\Requests\Announcements\DecideClubAnnouncementRequest;
use App\Http\Requests\Announcements\ListClubAnnouncementsRequest;
use App\Http\Requests\Announcements\RejectClubAnnouncementApprovalRequest;
use App\Http\Requests\Announcements\StoreClubAnnouncementReplacementRequest;
use App\Http\Requests\Announcements\StoreClubAnnouncementRequest;
use App\Http\Requests\Announcements\UpdateClubAnnouncementRequest;
use App\Http\Requests\Announcements\WithdrawClubAnnouncementRequest;
use App\Http\Resources\Announcements\ClubAnnouncementPublicationDecisionResource;
use App\Http\Resources\Announcements\ClubAnnouncementResource;
use App\Models\Club;
use App\Models\ClubAnnouncement;
use App\Models\ClubAnnouncementPublicationDecision;
use App\Services\Access\ClubPermissionService;
use App\Services\Announcements\ClubAnnouncementReadService;
use App\Services\Announcements\ClubAnnouncementReadStateService;
use App\Services\Announcements\ClubAnnouncementWriteService;
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
        ClubAnnouncementReadStateService $readState,
    ): JsonResponse {
        $management = $permissions->userHasPermission($request->user(), ClubAnnouncement::PERMISSION_MANAGEMENT_VIEW, (int) $club->id);
        $readablePublishedAudiences = $readState->readablePublishedAudiences($request->user(), $club, $management);
        $now = Carbon::now('UTC');
        $filters = $request->normalizedFilters();
        try {
            $page = $announcements->listForClub($club, $filters, $management, $readablePublishedAudiences, $now);
        } catch (InvalidClubAnnouncementReadStateException) {
            return response()->json(['message' => 'Announcement records are unavailable because stored data failed integrity validation.'], 500);
        }
        $readState->attachToList($page->items(), $club, $request->user());

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
        ClubAnnouncementReadStateService $readState,
    ): JsonResponse {
        $management = $permissions->userHasPermission($request->user(), ClubAnnouncement::PERMISSION_MANAGEMENT_VIEW, (int) $club->id);
        $readablePublishedAudiences = $readState->readablePublishedAudiences($request->user(), $club, $management);
        $now = Carbon::now('UTC');
        try {
            if (! $announcements->canReadDetail($club, $announcement, $management, $readablePublishedAudiences, $now)) {
                return $this->notFound();
            }
        } catch (InvalidClubAnnouncementReadStateException) {
            return $this->notFound();
        }
        $readState->attachToDetail($announcement, $club, $request->user());

        $request->attributes->set('announcements_management_reader', $management);
        $request->attributes->set('announcements_effective_now', $now);

        return response()->json(['data' => (new ClubAnnouncementResource($announcement))->resolve($request)]);
    }

    public function markRead(
        Request $request,
        Club $club,
        ClubAnnouncement $announcement,
        ClubAnnouncementReadStateService $readState,
    ): JsonResponse {
        try {
            $read = $readState->markRead($club, $announcement, $request->user());
        } catch (InvalidClubAnnouncementReadStateException) {
            return $this->notFound();
        }

        if ($read === null) {
            return $this->notFound();
        }

        return response()->json([
            'data' => [
                'announcement_id' => (int) $read->club_announcement_id,
                'is_read' => true,
                'read_at' => $read->read_at->toISOString(),
            ],
        ]);
    }

    public function store(StoreClubAnnouncementRequest $request, Club $club, ClubAnnouncementWriteService $announcements): JsonResponse
    {
        $announcement = $announcements->createDraft($club, $request->user(), $request->validated());

        return $this->managementResource($request, $announcement, 201);
    }

    public function update(UpdateClubAnnouncementRequest $request, Club $club, ClubAnnouncement $announcement, ClubAnnouncementWriteService $announcements): JsonResponse
    {
        $data = $request->validated();
        $lockVersion = (int) $data['lock_version'];
        unset($data['lock_version']);

        return $this->managementResource(
            $request,
            $announcements->updateDraft($club, $announcement, $request->user(), $lockVersion, $data),
        );
    }

    public function storeReplacement(StoreClubAnnouncementReplacementRequest $request, Club $club, ClubAnnouncement $announcement, ClubAnnouncementWriteService $announcements): JsonResponse
    {
        return $this->managementResource(
            $request,
            $announcements->createReplacementDraft($club, $announcement, $request->user(), $request->validated()),
            201,
        );
    }

    public function approve(DecideClubAnnouncementRequest $request, Club $club, ClubAnnouncement $announcement, ClubAnnouncementWriteService $announcements): JsonResponse
    {
        $data = $request->validated();
        $decision = $announcements->approve(
            $club,
            $announcement,
            $request->user(),
            (int) $data['lock_version'],
            $data['reason'] ?? null,
        );

        return $this->decisionResource($request, $decision, 201);
    }

    public function rejectApproval(RejectClubAnnouncementApprovalRequest $request, Club $club, ClubAnnouncement $announcement, ClubAnnouncementWriteService $announcements): JsonResponse
    {
        $data = $request->validated();
        $decision = $announcements->rejectApproval(
            $club,
            $announcement,
            $request->user(),
            (int) $data['lock_version'],
            $data['reason'],
        );

        return $this->decisionResource($request, $decision, 201);
    }

    public function revokeApproval(RejectClubAnnouncementApprovalRequest $request, Club $club, ClubAnnouncement $announcement, ClubAnnouncementPublicationDecision $approval, ClubAnnouncementWriteService $announcements): JsonResponse
    {
        $data = $request->validated();
        $decision = $announcements->revokeApproval(
            $club,
            $announcement,
            $request->user(),
            (int) $data['lock_version'],
            $approval,
            $data['reason'],
        );

        return $this->decisionResource($request, $decision, 201);
    }

    public function publish(AnnouncementLockVersionRequest $request, Club $club, ClubAnnouncement $announcement, ClubAnnouncementWriteService $announcements): JsonResponse
    {
        return $this->managementResource(
            $request,
            $announcements->publish($club, $announcement, $request->user(), (int) $request->validated('lock_version')),
        );
    }

    public function withdraw(WithdrawClubAnnouncementRequest $request, Club $club, ClubAnnouncement $announcement, ClubAnnouncementWriteService $announcements): JsonResponse
    {
        $data = $request->validated();

        return $this->managementResource(
            $request,
            $announcements->withdraw($club, $announcement, $request->user(), (int) $data['lock_version'], $data['reason']),
        );
    }

    public function archive(AnnouncementLockVersionRequest $request, Club $club, ClubAnnouncement $announcement, ClubAnnouncementWriteService $announcements): JsonResponse
    {
        return $this->managementResource(
            $request,
            $announcements->archive($club, $announcement, $request->user(), (int) $request->validated('lock_version')),
        );
    }

    private function managementResource(Request $request, ClubAnnouncement $announcement, int $status = 200): JsonResponse
    {
        $request->attributes->set('announcements_management_reader', true);
        $request->attributes->set('announcements_effective_now', Carbon::now('UTC'));

        return response()->json(['data' => (new ClubAnnouncementResource($announcement))->resolve($request)], $status);
    }

    private function decisionResource(Request $request, ClubAnnouncementPublicationDecision $decision, int $status): JsonResponse
    {
        return response()->json(['data' => (new ClubAnnouncementPublicationDecisionResource($decision))->resolve($request)], $status);
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['message' => 'Announcement not found.'], 404);
    }
}
