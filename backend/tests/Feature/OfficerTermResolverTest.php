<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Access\OfficerTermResolver;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Support\OfficerTermTestCase;

class OfficerTermResolverTest extends OfficerTermTestCase
{
    public function test_uses_explicit_utc_instant_and_half_open_early_end(): void
    {
        $club = $this->club();
        $holder = $this->membership($club);
        $id = DB::table('club_officer_terms')->insertGetId($this->term($holder, [
            'ended_at' => '2027-01-15 00:00:00',
            'termination_reason' => 'Synthetic resignation',
            'termination_reference' => 'TERM-END',
        ]));
        $resolver = app(OfficerTermResolver::class);

        $this->assertNull($resolver->forOffice($club->id, 'president', new DateTimeImmutable('2026-12-31T23:59:59.999Z')));
        $this->assertSame($id, $resolver->forOffice($club->id, 'president', new DateTimeImmutable('2027-01-01T05:30:00+05:30'))?->id);
        $this->assertSame($id, $resolver->forMembership($club->id, $holder->id, new DateTimeImmutable('2027-01-14T23:59:59.999Z'))?->id);
        $this->assertNull($resolver->forOffice($club->id, 'president', new DateTimeImmutable('2027-01-15T00:00:00Z')));
    }

    public function test_fails_closed_for_foreign_inactive_expired_or_missing_authority(): void
    {
        $club = $this->club();
        $foreign = $this->club();
        $holder = $this->membership($club, 'president-admin');
        $at = new DateTimeImmutable('2027-01-02T00:00:00Z');
        $resolver = app(OfficerTermResolver::class);
        $this->assertNull($resolver->forOffice($club->id, 'president', $at));
        DB::table('club_officer_terms')->insert($this->term($holder));
        $this->assertNotNull($resolver->forMembership($club->id, $holder->id, $at));
        $this->assertNull($resolver->forMembership($foreign->id, $holder->id, $at));
        $this->assertNull($resolver->forOffice($foreign->id, 'president', $at));
        $this->assertNull($resolver->forOffice($club->id, 'president-admin', $at));
        $this->assertNull($resolver->forOffice($club->id, 'president', new DateTimeImmutable('2027-02-01T00:00:00Z')));
        $holder->update(['status' => 'inactive']);
        $this->assertNull($resolver->forOffice($club->id, 'president', $at));
        $holder->update(['status' => 'active']);
        $club->update(['is_active' => false]);
        $this->assertNull($resolver->forMembership($club->id, $holder->id, $at));
    }

    public function test_fails_closed_if_imported_rows_are_ambiguous(): void
    {
        $club = $this->club();
        $first = $this->membership($club);
        $second = $this->membership($club, status: 'inactive');
        // Simulate corrupt imported rows in this disposable test only.
        DB::statement(DB::getDriverName() === 'pgsql'
            ? 'ALTER TABLE club_officer_terms DROP CONSTRAINT club_officer_terms_office_no_overlap'
            : 'DROP TRIGGER club_officer_terms_insert_guard');
        DB::table('club_officer_terms')->insert([$this->term($first), $this->term($second)]);

        $this->assertNull(app(OfficerTermResolver::class)
            ->forOffice($club->id, 'president', new DateTimeImmutable('2027-01-02T00:00:00Z')));
    }
}
