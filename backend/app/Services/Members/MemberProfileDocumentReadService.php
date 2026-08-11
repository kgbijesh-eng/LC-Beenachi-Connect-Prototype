<?php

namespace App\Services\Members;

use App\Models\Club;
use App\Models\Member;
use App\Models\MemberProfileDocument;
use Illuminate\Support\Collection;

class MemberProfileDocumentReadService
{
    public function forMember(Club $club, Member $member): Collection
    {
        return MemberProfileDocument::query()
            ->where('club_id', $club->id)
            ->where('member_id', $member->id)
            ->orderBy('document_type')
            ->orderByDesc('id')
            ->get();
    }
}
