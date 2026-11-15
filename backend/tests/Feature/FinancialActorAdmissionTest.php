<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ClubMembership;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FinancialOperationFixture;
use Tests\Support\FinancialPostgresTestCase;

class FinancialActorAdmissionTest extends FinancialPostgresTestCase
{
    public static function operations(): array
    {
        $cases = [];
        foreach (FinancialOperationFixture::actions() as $action) {
            if (preg_match('/^(collections-|expense|transfer|income|correction)/', $action)) {
                $cases[$action] = [$action];
            }
        }

        return $cases;
    }

    #[DataProvider('operations')]
    public function test_actor_permission_loss_blocks_mutation_and_replay(string $action): void
    {
        $p = (new FinancialOperationFixture)->prepare($action);
        $membership = ClubMembership::where('club_id', $p['club'])->where('user_id', $p['actor'])->firstOrFail();
        $required = match (true) {
            $action === 'collections-void' => 'finance.void',
            str_starts_with($action, 'collections-') => 'finance.manage',
            str_starts_with($action, 'expense-reversal') => 'finance.club.expenses.reverse',
            str_starts_with($action, 'expense') => 'finance.club.expenses.create',
            str_starts_with($action, 'transfer') => 'finance.club.transfers.create',
            str_starts_with($action, 'income') => 'finance.club.ordinary_income_receipts.create',
            default => 'finance.club.ordinary_income_receipts.reverse',
        };
        $permission = $membership->role->permissions()->where('slug', $required)->firstOrFail();
        $membership->role->permissions()->detach($permission->id);
        $before = FinancialOperationFixture::snapshot($p['club']);
        FinancialOperationFixture::perform($p);
        $after = FinancialOperationFixture::snapshot($p['club']);
        if (str_ends_with($action, '-replay')) {
            $this->assertSame($before, $after);
        } else {
            $this->assertNotSame($before, $after);
        }
        $this->assertSame(0, DB::transactionLevel());
        $membership->role->permissions()->attach($permission->id);

        $p = (new FinancialOperationFixture)->prepare($action, 'B6A-ended');
        $membership = ClubMembership::where('club_id', $p['club'])->where('user_id', $p['actor'])->sole();
        $this->assertTrue($membership->role->permissions()->where('slug', $required)->exists());
        if (str_starts_with($action, 'correction-replacement')) {
            $this->assertTrue($membership->role->permissions()->where('slug', 'finance.club.ordinary_income_receipts.create')->exists());
        }
        $this->assertSame(1, DB::table('club_officer_terms')->where('club_id', $p['club'])
            ->where('membership_id', $membership->id)->update([
                'ended_at' => '2020-01-01 00:00:00+00',
                'termination_reference' => 'fixture-ended-term',
                'termination_reason' => 'Synthetic loss of officer authority',
            ]));
        $before = FinancialOperationFixture::snapshot($p['club']);

        try {
            FinancialOperationFixture::perform($p);
            $this->fail('A revoked operation permission must reject.');
        } catch (AuthorizationException) {
            $this->assertSame($before, FinancialOperationFixture::snapshot($p['club']));
            $this->assertSame(0, DB::transactionLevel());
        }
    }
}
