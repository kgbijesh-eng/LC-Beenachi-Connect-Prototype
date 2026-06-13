<?php

namespace App\Http\Controllers\Api\V1\Members;

use App\Http\Controllers\Controller;
use App\Http\Requests\Members\StoreMemberProfileDocumentRequest;
use App\Http\Requests\Members\UpdateMemberProfileDocumentRequest;
use App\Http\Resources\Members\MemberProfileDocumentResource;
use App\Models\Club;
use App\Models\Member;
use App\Models\MemberProfileDocument;
use App\Models\User;
use App\Services\Members\MemberProfileDocumentReadService;
use App\Services\Members\MemberProfileDocumentWriteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberProfileDocumentController extends Controller
{
    private const OFFICER_READ_ROLE_SLUGS = [
        'president-admin',
        'secretary',
    ];

    private const OFFICER_WRITE_ROLE_SLUGS = [
        'president-admin',
        'secretary',
    ];

    public function index(
        Request $request,
        Club $club,
        Member $member,
        MemberProfileDocumentReadService $documents
    ): JsonResponse {
        if ((int) $member->club_id !== (int) $club->id) {
            return response()->json([
                'message' => 'Member does not belong to this club.',
            ], 404);
        }

        if (! $this->userCanReadMemberDocuments($request->user(), $club, $member)) {
            return response()->json([
                'message' => 'This action is unauthorized.',
            ], 403);
        }

        return response()->json([
            'profile_documents' => MemberProfileDocumentResource::collection(
                $documents->forMember($club, $member)
            )->resolve(),
        ]);
    }

    public function show(
        Request $request,
        Club $club,
        Member $member,
        MemberProfileDocument $document
    ): JsonResponse {
        if ((int) $member->club_id !== (int) $club->id) {
            return response()->json([
                'message' => 'Member does not belong to this club.',
            ], 404);
        }

        if ((int) $document->club_id !== (int) $club->id || (int) $document->member_id !== (int) $member->id) {
            return response()->json([
                'message' => 'Profile document not found for this member.',
            ], 404);
        }

        if (! $this->userCanReadMemberDocuments($request->user(), $club, $member)) {
            return response()->json([
                'message' => 'This action is unauthorized.',
            ], 403);
        }

        return response()->json([
            'profile_document' => (new MemberProfileDocumentResource($document))->resolve(),
        ]);
    }

    public function store(
        StoreMemberProfileDocumentRequest $request,
        Club $club,
        Member $member,
        MemberProfileDocumentWriteService $documents
    ): JsonResponse {
        if ((int) $member->club_id !== (int) $club->id) {
            return response()->json([
                'message' => 'Member does not belong to this club.',
            ], 404);
        }

        if (! $this->userCanWriteMemberDocuments($request->user(), $club)) {
            return response()->json([
                'message' => 'This action is unauthorized.',
            ], 403);
        }

        $document = $documents->createForMember(
            $club,
            $member,
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'profile_document' => (new MemberProfileDocumentResource($document))->resolve(),
        ], 201);
    }

    public function update(
        UpdateMemberProfileDocumentRequest $request,
        Club $club,
        Member $member,
        MemberProfileDocument $document,
        MemberProfileDocumentWriteService $documents
    ): JsonResponse {
        if ((int) $member->club_id !== (int) $club->id) {
            return response()->json([
                'message' => 'Member does not belong to this club.',
            ], 404);
        }

        if ((int) $document->club_id !== (int) $club->id || (int) $document->member_id !== (int) $member->id) {
            return response()->json([
                'message' => 'Profile document not found for this member.',
            ], 404);
        }

        if (! $this->userCanWriteMemberDocuments($request->user(), $club)) {
            return response()->json([
                'message' => 'This action is unauthorized.',
            ], 403);
        }

        $document = $documents->updateForMember(
            $document,
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'profile_document' => (new MemberProfileDocumentResource($document))->resolve(),
        ]);
    }

    private function userCanReadMemberDocuments(User $user, Club $club, Member $member): bool
    {
        $roleSlug = $this->activeRoleSlug($user, $club);

        if (in_array($roleSlug, self::OFFICER_READ_ROLE_SLUGS, true)) {
            return true;
        }

        return $roleSlug === 'member' && (int) $member->user_id === (int) $user->id;
    }

    private function userCanWriteMemberDocuments(User $user, Club $club): bool
    {
        return in_array($this->activeRoleSlug($user, $club), self::OFFICER_WRITE_ROLE_SLUGS, true);
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
