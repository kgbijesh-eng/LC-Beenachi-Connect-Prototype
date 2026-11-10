<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\OfficerTermTestCase;

class LegacyOfficerCandidateReportTest extends OfficerTermTestCase
{
    public function test_reports_all_clubs_and_offices_from_a_single_read(): void
    {
        $club = $this->club();
        $empty = $this->club();
        $suspended = $this->club(false);
        $president = $this->membership($club, 'president-admin');
        $inactive = $this->membership($club, 'president-admin', 'inactive');
        $first = $this->membership($club, 'secretary');
        $second = $this->membership($club, 'secretary');
        $excluded = $this->membership($suspended, 'treasurer');
        $this->membership($club);
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $this->assertSame(0, Artisan::call('officers:report-legacy-candidates'));
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
        }
        $this->assertCount(1, $queries);
        $this->assertStringStartsWith('select ', strtolower($queries[0]['query']));
        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('6e3ad4fe0bdcc1922de413c94ae7d0a2a14af72b', $report['baseline_sha']);
        $this->assertStringEndsWith('Z', $report['snapshot_at']);
        $rows = collect($report['clubs'])->keyBy('club_id');
        $this->assertSame([$club->id, $empty->id, $suspended->id], $rows->keys()->all());
        $offices = $rows[$club->id]['offices'];
        $this->assertSame(['president', 'secretary', 'treasurer'], array_keys($offices));
        $this->assertSame('unique', $offices['president']['classification']);
        $this->assertSame([['membership_id' => $president->id, 'role' => 'president-admin', 'status' => 'active']], $offices['president']['candidates']);
        $this->assertSame([['membership_id' => $inactive->id, 'role' => 'president-admin', 'status' => 'inactive', 'reason' => 'inactive_membership']], $offices['president']['excluded']);
        $this->assertSame('ambiguous', $offices['secretary']['classification']);
        $this->assertSame([$first->id, $second->id], array_column($offices['secretary']['candidates'], 'membership_id'));
        $this->assertSame('vacant', $offices['treasurer']['classification']);
        $this->assertSame('vacant', $rows[$empty->id]['offices']['president']['classification']);
        $this->assertSame([['membership_id' => $excluded->id, 'role' => 'treasurer', 'status' => 'active', 'reason' => 'inactive_club']], $rows[$suspended->id]['offices']['treasurer']['excluded']);
        $this->assertStringNotContainsString('starts_at', Artisan::output());
        $this->assertDatabaseCount('club_officer_terms', 0);
        $this->assertDatabaseCount('club_memberships', 6);
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
