<?php

namespace App\Http\Controllers\Api\V1\Club;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Services\Access\ClubPermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClubController extends Controller
{
    public function show(Club $club): JsonResponse
    {
        return response()->json([
            'club' => [
                'id' => $club->id,
                'name' => $club->name,
                'code' => $club->code,
                'district' => $club->district,
                'timezone' => $club->timezone,
                'is_active' => $club->is_active,
            ],
        ]);
    }

    public function me(Request $request, Club $club, ClubPermissionService $permissions): JsonResponse
    {
        $user = $request->user()->load([
            'clubMemberships' => fn ($query) => $query->where('club_id', $club->id),
            'clubMemberships.role.permissions',
        ]);

        $membership = $user->clubMemberships->first();

        return response()->json([
            'club' => [
                'id' => $club->id,
                'name' => $club->name,
                'code' => $club->code,
                'district' => $club->district,
                'timezone' => $club->timezone,
                'is_active' => $club->is_active,
            ],
            'membership' => $membership ? [
                'id' => $membership->id,
                'status' => $membership->status,
                'joined_at' => $membership->joined_at?->toISOString(),
                'role' => [
                    'id' => $membership->role->id,
                    'name' => $membership->role->name,
                    'slug' => $membership->role->slug,
                ],
            ] : null,
            'permissions' => $permissions->userPermissionSlugs($user, $club->id),
        ]);
    }
}
