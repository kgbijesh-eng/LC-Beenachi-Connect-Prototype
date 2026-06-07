<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid login credentials.',
            ], 422);
        }

        $token = $user->createToken('lc-beenachi-api-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load([
            'clubMemberships.club',
            'clubMemberships.role.permissions',
        ]);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'club_memberships' => $user->clubMemberships->map(function ($membership): array {
                return [
                    'id' => $membership->id,
                    'status' => $membership->status,
                    'joined_at' => $membership->joined_at?->toISOString(),
                    'club' => [
                        'id' => $membership->club->id,
                        'name' => $membership->club->name,
                        'code' => $membership->club->code,
                        'district' => $membership->club->district,
                        'timezone' => $membership->club->timezone,
                        'is_active' => $membership->club->is_active,
                    ],
                    'role' => [
                        'id' => $membership->role->id,
                        'name' => $membership->role->name,
                        'slug' => $membership->role->slug,
                    ],
                    'permissions' => $membership->role->permissions
                        ->sortBy('slug')
                        ->values()
                        ->map(fn ($permission): array => [
                            'id' => $permission->id,
                            'name' => $permission->name,
                            'slug' => $permission->slug,
                            'group' => $permission->group,
                        ])
                        ->all(),
                ];
            })->values()->all(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}
