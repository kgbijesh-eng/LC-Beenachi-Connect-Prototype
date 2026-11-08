<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubJournalEntry;
use App\Models\Member;
use App\Services\Access\OrganisationAccessRejected;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\FinancialOperationFixture as Fixture;
use Tests\Support\FinancialPostgresTestCase;

class FinancialBoundaryMatrixTest extends FinancialPostgresTestCase
{
    public static function actions(): array
    {
        return array_combine(Fixture::actions(), array_map(fn ($a) => [$a], Fixture::actions()));
    }

    public static function deniedActions(): array
    {
        $cases = [];
        foreach (Fixture::actions() as $action) {
            foreach (['suspended', 'foreign', 'missing', 'invalid'] as $state) {
                $cases[$action.'-'.$state] = [$action, $state];
            }
        }

        return $cases;
    }

    public static function mutations(): array
    {
        return array_filter(self::actions(), fn ($a) => ! str_ends_with($a[0], '-replay'));
    }

    #[DataProvider('actions')]
    public function test_active_direct_boundary_and_exact_replay_preserve_financial_effects(string $action): void
    {
        $p = (new Fixture)->prepare($action);
        $before = Fixture::snapshot($p['club']);
        $journals = ClubJournalEntry::where('club_id', $p['club'])->count();
        $result = Fixture::perform($p);
        $this->assertNotNull($result);
        $this->assertSame($journals + Fixture::journalDelta($action), ClubJournalEntry::where('club_id', $p['club'])->count());
        $this->assertSame(0, DB::transactionLevel());
        if (str_ends_with($action, '-replay')) {
            $this->assertSame($before, Fixture::snapshot($p['club']));
        }
        $this->assertBalanced($p['club']);
    }

    #[DataProvider('deniedActions')]
    public function test_direct_mutations_and_replay_fail_before_any_financial_change(string $action, string $state): void
    {
        $p = (new Fixture)->prepare($action);
        $club = Club::findOrFail($p['club']);
        $member = Member::findOrFail($p['member']);
        $member->setRelation('club', $club);
        $before = Fixture::snapshot($p['club']);
        if ($state === 'suspended') {
            Club::whereKey($club->id)->update(['is_active' => false]);
        }
        if ($state === 'foreign') {
            $other = Club::create(['name' => 'Foreign synthetic', 'code' => 'FOREIGN']);
            config(['instance.organisation_code' => $other->code]);
        }
        if ($state === 'missing') {
            config(['instance.organisation_code' => 'ASSIGNED-ABSENT']);
        }
        if ($state === 'invalid') {
            config(['instance.organisation_code' => null]);
        }
        try {
            Fixture::perform($p, $club, $member);
            $this->fail('No mutation/replay return may bypass current eligibility.');
        } catch (OrganisationAccessRejected $e) {
            $this->assertSame(match ($state) {
                'suspended' => 'ORGANISATION_SUSPENDED', 'foreign' => 'ORGANISATION_FOREIGN', default => 'ORGANISATION_CONFIGURATION_UNAVAILABLE'
            }, $e->reason);
        }
        $this->assertTrue($club->is_active, 'The supplied model deliberately retains its stale active flag.');
        $this->assertSame($before, Fixture::snapshot($p['club']));
        $this->assertSame(0, DB::transactionLevel());
    }

    #[DataProvider('mutations')]
    public function test_root_failure_rolls_back_source_journal_and_provenance_atomically(string $action): void
    {
        $p = (new Fixture)->prepare($action);
        $before = Fixture::snapshot($p['club']);
        $injected = false;
        $journal = Fixture::journalDelta($action) > 0;
        DB::listen(function ($query) use (&$injected, $journal): void {
            $isWrite = str_starts_with($query->sql, 'insert into ') || str_starts_with($query->sql, 'update ');
            if (! $injected && $isWrite && (! $journal || str_starts_with($query->sql, 'insert into "club_journal_entries"'))) {
                $injected = true;
                throw new RuntimeException('controlled financial boundary rollback');
            }
        });
        try {
            Fixture::perform($p);
            $this->fail('Controlled failure must propagate.');
        } catch (RuntimeException $e) {
            $this->assertSame('controlled financial boundary rollback', $e->getMessage());
        }
        $this->assertTrue($injected);
        $this->assertSame($before, Fixture::snapshot($p['club']));
        $this->assertSame(0, DB::transactionLevel());
    }

    private function assertBalanced(int $club): void
    {
        $bad = DB::select("SELECT journal_entry_id FROM club_journal_lines WHERE club_id=? GROUP BY journal_entry_id,fund_code HAVING sum(CASE WHEN direction='debit' THEN amount_paise ELSE -amount_paise END)<>0", [$club]);
        $this->assertSame([], $bad);
    }
}
