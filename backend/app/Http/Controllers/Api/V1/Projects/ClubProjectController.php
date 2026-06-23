<?php

namespace App\Http\Controllers\Api\V1\Projects;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\ListClubProjectsRequest;
use App\Http\Resources\Projects\ClubProjectResource;
use App\Models\Club;
use App\Models\ClubProject;
use App\Models\User;
use App\Services\Projects\ClubProjectReadService;
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