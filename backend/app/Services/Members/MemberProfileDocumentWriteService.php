<?php

namespace App\Services\Members;

use App\Models\Club;
use App\Models\Member;
use App\Models\MemberProfileDocument;
use App\Models\User;

class MemberProfileDocumentWriteService
{
    private const WRITABLE_METADATA_FIELDS = [
        'document_type',
        'status',
        'external_reference',
        'remarks',
    ];

    public function createForMember(Club $club, Member $member, User $user, array $metadata): MemberProfileDocument
    {
        return MemberProfileDocument::query()->create(array_merge(
            $this->metadataOnly($metadata),
            [
                'club_id' => $club->id,
                'member_id' => $member->id,
                'created_by_user_id' => $user->id,
                'updated_by_user_id' => $user->id,
            ]
        ));
    }

    public function updateForMember(MemberProfileDocument $document, User $user, array $metadata): MemberProfileDocument
    {
        $document->fill(array_merge(
            $this->metadataOnly($metadata),
            [
                'updated_by_user_id' => $user->id,
            ]
        ));

        $document->save();

        return $document->refresh();
    }

    private function metadataOnly(array $metadata): array
    {
        return array_intersect_key($metadata, array_flip(self::WRITABLE_METADATA_FIELDS));
    }
}
