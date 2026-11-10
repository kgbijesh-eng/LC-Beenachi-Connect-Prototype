<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\Access\InstanceOrganisation;
use App\Services\Access\OrganisationAccessRejected;
use App\Services\Access\OrganisationTransactionGuard;
use App\Services\Auth\UserCredentialLock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $request, OrganisationTransactionGuard $transactions, UserCredentialLock $credentialsLock): JsonResponse
    {
        $credentials = $request->validated();

        $candidate = User::query()->useWritePdo()->where('email', $credentials['email'])->first(['id']);
        if (! $candidate) {
            return response()->json(['message' => 'Invalid login credentials.'], 422);
        }

        $organisation = app(InstanceOrganisation::class)->resolve();

        return $transactions->run($organisation, function () use ($candidate, $credentials, $credentialsLock, $organisation): JsonResponse {
            $credentialsLock->acquire(DB::connection(), $candidate->id);
            $user = User::query()->whereKey($candidate->id)->where('email', $credentials['email'])->first();
            if (! $user || ! Hash::check($credentials['password'], $user->password)) {
                return response()->json(['message' => 'Invalid login credentials.'], 422);
            }

            if (! $user->clubMemberships()->where('club_id', $organisation->id)->where('status', 'active')->whereHas('role')->exists()) {
                return response()->json(['message' => 'This action is unauthorized.'], 403);
            }

            return response()->json([
                'message' => 'Login successful.',
                'token' => $user->createToken('lc-beenachi-api-token')->plainTextToken,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ]);
        });
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $organisation = app(InstanceOrganisation::class)->resolve();
        $memberships = $user->clubMemberships()
            ->where('club_id', $organisation->id)->where('status', 'active')
            ->with(['club', 'role.permissions'])->get();
        abort_unless($memberships->count() === 1 && $memberships->sole()->role, 403, 'This action is unauthorized.');
        $user->load(['members' => fn ($query) => $query->where('club_id', $organisation->id)
            ->select('id', 'club_id', 'user_id', 'member_code', 'full_name')]);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'instance_organisation' => ['id' => $organisation->id, 'code' => $organisation->code, 'name' => $organisation->name],
            'club_memberships' => $memberships->map(function ($membership) use ($user): array {
                $clubId = $membership->club_id;

                $clubMembers = $user->members->filter(function ($member) use ($user, $clubId) {
                    return (int) $member->user_id === (int) $user->id && (int) $member->club_id === (int) $clubId;
                });

                if ($clubMembers->count() > 1) {
                    \Illuminate\Support\Facades\Log::error(
                        "Data integrity error: multiple same-club linked members found for user ID {$user->id} in club ID {$clubId}."
                    );
                    abort(response()->json([
                        'message' => 'Data integrity error.',
                    ], 500));
                }

                $member = $clubMembers->first();
                $memberData = null;
                if ($member) {
                    $memberData = [
                        'id' => $member->id,
                        'member_code' => $member->member_code,
                        'full_name' => $member->full_name,
                    ];
                }

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
                    'member' => $memberData,
                ];
            })->values()->all(),
        ]);
    }

    public function logout(Request $request, UserCredentialLock $credentialsLock): JsonResponse
    {
        $user = $request->user();
        $token = $user->currentAccessToken();
        if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) {
            $connection = DB::connection();
            if ($connection->getDriverName() !== 'pgsql' || $connection->transactionLevel() !== 0
                || $connection->getPdo()->inTransaction()) {
                throw OrganisationAccessRejected::unsupported();
            }

            $connection->transaction(function () use ($connection, $credentialsLock, $user, $token): void {
                $credentialsLock->acquire($connection, $user->id);
                $user->tokens()->whereKey($token->getKey())->delete();
            });
        }
        Auth::guard('sanctum')->forgetUser();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}
