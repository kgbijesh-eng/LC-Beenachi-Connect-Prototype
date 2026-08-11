<?php

namespace App\Http\Middleware;

use App\Models\Club;
use App\Services\Access\ClubPermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClubPermission
{
    public function __construct(
        private readonly ClubPermissionService $permissions
    ) {
    }

    public function handle(Request $request, Closure $next, string $permissionSlug, string $clubRouteParameter = 'club'): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $club = $request->route($clubRouteParameter);

        $clubId = $club instanceof Club ? $club->id : $club;

        if (! is_numeric($clubId)) {
            return response()->json([
                'message' => 'A valid club context is required.',
            ], 403);
        }

        if (! $this->permissions->userHasPermission($user, $permissionSlug, (int) $clubId)) {
            return response()->json([
                'message' => 'This action is unauthorized.',
            ], 403);
        }

        return $next($request);
    }
}
