<?php

namespace App\Http\Controllers\Api\V1\Projects;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\ListClubProjectsRequest;
use App\Http\Requests\Projects\StoreClubProjectRequest;
use App\Http\Requests\Projects\TransitionClubProjectRequest;
use App\Http\Requests\Projects\UpdateClubProjectRequest;
use App\Http\Resources\Projects\ClubProjectResource;
use App\Models\Club;
use App\Models\ClubProject;
use App\Models\User;
use App\Services\Projects\ClubProjectReadService;
use App\Services\Projects\ClubProjectWriteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClubProjectController extends Controller
{
    public function index(
        ListClubProjectsRequest $request,
        Club $club,
        ClubProjectReadService $projects
    ): JsonResponse {
        $isManagement = $this->userCanManageProjects($request->user(), $club);

        if (! $isManagement && $this->containsManagementOnlyFilters($request->validated())) {
            return response()->json([
                'message' => 'This action is unauthorized.',
            ], 403);
        }

        $request->attributes->set('projects_management_reader', $isManagement);
        $paginated = $projects->listForClub($club, $request->validated(), $isManagement);

        return response()->json([
            'data' => ClubProjectResource::collection($paginated->items())->resolve($request),
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
        ClubProject $project,
        ClubProjectReadService $projects
    ): JsonResponse {
        if ((int) $project->club_id !== (int) $club->id) {
            return response()->json([
                'message' => 'Project or Activity not found for this club.',
            ], 404);
        }

        $project->load(['club', 'leadMember']);
        $isManagement = $this->userCanManageProjects($request->user(), $club);

        if (! $projects->canRead($project, $isManagement)) {
            return response()->json([
                'message' => 'Project or Activity not found for this club.',
            ], 404);
        }

        $request->attributes->set('projects_management_reader', $isManagement);

        return response()->json([
            'data' => (new ClubProjectResource($project))->resolve($request),
        ]);
    }

    public function store(StoreClubProjectRequest $request, Club $club, ClubProjectWriteService $projects): JsonResponse
    {
        if (! $this->userCanManageProjects($request->user(), $club)) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }

        $request->attributes->set('projects_management_reader', true);
        $project = $projects->create($club, $request->user(), $request->validated());

        return response()->json(['data' => (new ClubProjectResource($project))->resolve($request)], 201);
    }

    public function update(UpdateClubProjectRequest $request, Club $club, ClubProject $project, ClubProjectWriteService $projects): JsonResponse
    {
        if (! $this->userCanManageProjects($request->user(), $club)) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }

        $request->attributes->set('projects_management_reader', true);
        $project = $projects->update($club, $project, $request->user(), $request->validated());

        return response()->json(['data' => (new ClubProjectResource($project))->resolve($request)]);
    }

    public function plan(TransitionClubProjectRequest $request, Club $club, ClubProject $project, ClubProjectWriteService $projects): JsonResponse
    {
        return $this->transition($request, $club, $project, $projects, 'plan');
    }

    public function start(TransitionClubProjectRequest $request, Club $club, ClubProject $project, ClubProjectWriteService $projects): JsonResponse
    {
        return $this->transition($request, $club, $project, $projects, 'start');
    }

    public function complete(TransitionClubProjectRequest $request, Club $club, ClubProject $project, ClubProjectWriteService $projects): JsonResponse
    {
        return $this->transition($request, $club, $project, $projects, 'complete');
    }

    public function cancel(TransitionClubProjectRequest $request, Club $club, ClubProject $project, ClubProjectWriteService $projects): JsonResponse
    {
        return $this->transition($request, $club, $project, $projects, 'cancel');
    }

    private function transition(TransitionClubProjectRequest $request, Club $club, ClubProject $project, ClubProjectWriteService $projects, string $transition): JsonResponse
    {
        if (! $this->userCanManageProjects($request->user(), $club)) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }

        $request->attributes->set('projects_management_reader', true);
        $validated = $request->validated();
        $lockVersion = (int) $validated['lock_version'];

        if ($transition !== 'cancel' && array_key_exists('cancellation_reason', $validated)) {
            return response()->json([
                'message' => 'The cancellation reason field is not allowed for this transition.',
                'errors' => ['cancellation_reason' => ['The cancellation reason field is not allowed for this transition.']],
            ], 422);
        }

        $project = match ($transition) {
            'plan' => $projects->plan($club, $project, $request->user(), $lockVersion),
            'start' => $projects->start($club, $project, $request->user(), $lockVersion, $validated['actual_start_date'] ?? null),
            'complete' => $projects->complete($club, $project, $request->user(), $lockVersion, $validated),
            'cancel' => $projects->cancel($club, $project, $request->user(), $lockVersion, $validated['cancellation_reason'] ?? null),
        };

        return response()->json(['data' => (new ClubProjectResource($project))->resolve($request)]);
    }
    private function containsManagementOnlyFilters(array $filters): bool
    {
        return array_key_exists('status', $filters)
            || array_key_exists('visibility', $filters);
    }

    private function userCanManageProjects(User $user, Club $club): bool
    {
        $membership = $user->clubMemberships()
            ->with('role.permissions')
            ->where('club_id', $club->id)
            ->where('status', 'active')
            ->first();

        $role = $membership?->role;

        return $role !== null
            && in_array($role->slug, ClubProject::MANAGEMENT_ROLE_SLUGS, true)
            && $role->permissions->contains('slug', ClubProject::PERMISSION_MANAGE);
    }
}