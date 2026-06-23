<?php

namespace App\Http\Controllers\Api\V1\Events;

use App\Http\Controllers\Controller;
use App\Http\Requests\Events\ListClubEventsRequest;
use App\Http\Requests\Events\StoreClubEventRequest;
use App\Http\Requests\Events\TransitionClubEventRequest;
use App\Http\Requests\Events\UpdateClubEventRequest;
use App\Http\Resources\Events\ClubEventResource;
use App\Models\Club;
use App\Models\ClubEvent;
use App\Models\User;
use App\Services\Events\ClubEventReadService;
use App\Services\Events\ClubEventWriteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClubEventController extends Controller
{
    public function index(
        ListClubEventsRequest $request,
        Club $club,
        ClubEventReadService $events
    ): JsonResponse {
        $isManagement = $this->userCanManageEvents($request->user(), $club);

        if (! $isManagement && $this->containsManagementOnlyFilters($request->validated())) {
            return response()->json([
                'message' => 'This action is unauthorized.',
            ], 403);
        }

        $request->attributes->set('events_management_reader', $isManagement);

        $paginated = $events->listForClub($club, $request->validated(), $isManagement);

        return response()->json([
            'data' => ClubEventResource::collection($paginated->items())->resolve($request),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
                'filters' => $request->validated(),
            ],
        ]);
    }

    public function show(
        Request $request,
        Club $club,
        ClubEvent $event,
        ClubEventReadService $events
    ): JsonResponse {
        if ((int) $event->club_id !== (int) $club->id) {
            return response()->json([
                'message' => 'Event not found for this club.',
            ], 404);
        }

        $event->load('club');

        $isManagement = $this->userCanManageEvents($request->user(), $club);

        if (! $events->canRead($event, $isManagement)) {
            return response()->json([
                'message' => 'Event not found for this club.',
            ], 404);
        }

        $request->attributes->set('events_management_reader', $isManagement);

        return response()->json([
            'data' => (new ClubEventResource($event))->resolve($request),
        ]);
    }

    public function store(StoreClubEventRequest $request, Club $club, ClubEventWriteService $events): JsonResponse
    {
        if (! $this->userCanManageEvents($request->user(), $club)) {
            return response()->json([
                'message' => 'This action is unauthorized.',
            ], 403);
        }

        $request->attributes->set('events_management_reader', true);
        $event = $events->create($club, $request->user(), $request->validated());

        return response()->json([
            'data' => (new ClubEventResource($event))->resolve($request),
        ], 201);
    }

    public function update(
        UpdateClubEventRequest $request,
        Club $club,
        ClubEvent $event,
        ClubEventWriteService $events
    ): JsonResponse {
        if (! $this->userCanManageEvents($request->user(), $club)) {
            return response()->json([
                'message' => 'This action is unauthorized.',
            ], 403);
        }

        $request->attributes->set('events_management_reader', true);
        $event = $events->update($club, $event, $request->user(), $request->validated());

        return response()->json([
            'data' => (new ClubEventResource($event))->resolve($request),
        ]);
    }

    public function publish(
        TransitionClubEventRequest $request,
        Club $club,
        ClubEvent $event,
        ClubEventWriteService $events
    ): JsonResponse {
        return $this->transition($request, $club, $event, $events, 'publish');
    }

    public function cancel(
        TransitionClubEventRequest $request,
        Club $club,
        ClubEvent $event,
        ClubEventWriteService $events
    ): JsonResponse {
        return $this->transition($request, $club, $event, $events, 'cancel');
    }

    public function complete(
        TransitionClubEventRequest $request,
        Club $club,
        ClubEvent $event,
        ClubEventWriteService $events
    ): JsonResponse {
        return $this->transition($request, $club, $event, $events, 'complete');
    }

    private function transition(
        TransitionClubEventRequest $request,
        Club $club,
        ClubEvent $event,
        ClubEventWriteService $events,
        string $transition
    ): JsonResponse {
        if (! $this->userCanManageEvents($request->user(), $club)) {
            return response()->json([
                'message' => 'This action is unauthorized.',
            ], 403);
        }

        $request->attributes->set('events_management_reader', true);
        $validated = $request->validated();

        if ($transition !== 'cancel' && array_key_exists('cancellation_reason', $validated)) {
            return response()->json([
                'message' => 'The cancellation reason field is not allowed for this transition.',
                'errors' => [
                    'cancellation_reason' => ['The cancellation reason field is not allowed for this transition.'],
                ],
            ], 422);
        }

        $lockVersion = (int) $validated['lock_version'];

        $event = match ($transition) {
            'publish' => $events->publish($club, $event, $request->user(), $lockVersion),
            'cancel' => $events->cancel($club, $event, $request->user(), $lockVersion, $validated['cancellation_reason'] ?? null),
            'complete' => $events->complete($club, $event, $request->user(), $lockVersion),
        };

        return response()->json([
            'data' => (new ClubEventResource($event))->resolve($request),
        ]);
    }

    private function containsManagementOnlyFilters(array $filters): bool
    {
        return ($filters['scope'] ?? null) === 'all'
            || array_key_exists('status', $filters)
            || array_key_exists('visibility', $filters);
    }

    private function userCanManageEvents(User $user, Club $club): bool
    {
        $membership = $user->clubMemberships()
            ->with('role.permissions')
            ->where('club_id', $club->id)
            ->where('status', 'active')
            ->first();

        $role = $membership?->role;

        return $role !== null
            && in_array($role->slug, ClubEvent::MANAGEMENT_ROLE_SLUGS, true)
            && $role->permissions->contains('slug', ClubEvent::PERMISSION_MANAGE);
    }
}
