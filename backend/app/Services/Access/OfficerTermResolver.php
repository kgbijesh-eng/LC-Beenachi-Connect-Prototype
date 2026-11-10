<?php

declare(strict_types=1);

namespace App\Services\Access;

use App\Models\ClubOfficerTerm;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

final class OfficerTermResolver
{
    public function forOffice(int $clubId, string $office, DateTimeInterface $at): ?ClubOfficerTerm
    {
        if (! array_key_exists($office, ClubOfficerTerm::LEGACY_ROLES)) {
            return null;
        }

        return $this->resolve($clubId, 'office', $office, $at);
    }

    public function forMembership(int $clubId, int $membershipId, DateTimeInterface $at): ?ClubOfficerTerm
    {
        return $this->resolve($clubId, 'membership_id', $membershipId, $at);
    }

    private function resolve(int $clubId, string $column, int|string $value, DateTimeInterface $at): ?ClubOfficerTerm
    {
        $query = ClubOfficerTerm::query()->useWritePdo();
        $utc = DateTimeImmutable::createFromInterface($at)->setTimezone(new DateTimeZone('UTC'));
        // Terms have second precision. Truncation preserves comparisons at either bound.
        $instant = $utc->format($query->getConnection()->getDriverName() === 'pgsql' ? 'Y-m-d H:i:sP' : 'Y-m-d H:i:s');
        $terms = $query->select('club_officer_terms.*')
            ->selectRaw("EXISTS (
                SELECT 1 FROM club_memberships
                JOIN clubs ON clubs.id = club_memberships.club_id
                WHERE club_memberships.id = club_officer_terms.membership_id
                AND club_memberships.club_id = club_officer_terms.club_id
                AND club_memberships.status = ? AND clubs.is_active = ?
            ) AS eligible", ['active', true])
            ->withCasts(['eligible' => 'boolean'])
            ->where('club_id', $clubId)
            ->where($column, $value)
            ->where('starts_at', '<=', $instant)
            ->where('ends_at', '>', $instant)
            ->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>', $instant))
            ->limit(2)->get();

        return $terms->count() === 1 && $terms->first()->eligible ? $terms->first() : null;
    }
}
