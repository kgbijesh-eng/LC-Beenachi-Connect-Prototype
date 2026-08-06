<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubMembership;
use App\Models\Member;
use App\Models\MemberCharge;
use App\Models\MemberPayment;
use App\Models\PaymentAllocation;
use App\Models\PaymentReceipt;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Milestone3grFinanceCollectionsReceiptsWorkflowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_charge_payment_receipt_idempotency_close_and_void_workflow(): void
    {
        $this->seed(DatabaseSeeder::class);
        [$club,$president] = $this->actor('president-admin', 'A');
        $m1 = $this->member($club, 'M1');
        $m2 = $this->member($club, 'M2');
        Sanctum::actingAs($president);
        $draft = $this->postJson("/api/v1/clubs/{$club->id}/finance/charges", ['title' => 'Duplicate Charge', 'category' => 'membership_dues', 'amount_paise' => 100050, 'due_date' => '2026-07-31', 'financial_year' => '2026-27', 'member_ids' => [$m1->id, $m2->id], 'assign_all_active_members' => false])->assertCreated()->json('data');
        $this->assertSame('draft', $draft['status']);
        $this->assertSame(100050, $draft['amount_paise']);
        $this->assertArrayHasKey('lock_version', $draft);
        $this->assertNotNull($draft['lock_version']);
        $updated = $this->patchJson("/api/v1/clubs/{$club->id}/finance/charges/{$draft['id']}", ['title' => 'Duplicate Charge', 'category' => 'membership_dues', 'amount_paise' => 100050, 'due_date' => '2026-07-31', 'financial_year' => '2026-27', 'member_ids' => [$m1->id, $m2->id], 'assign_all_active_members' => false, 'lock_version' => $draft['lock_version']])->assertOk()->json('data');
        $issued = $this->postJson("/api/v1/clubs/{$club->id}/finance/charges/{$draft['id']}/issue", ['lock_version' => $updated['lock_version']])->assertOk()->json('data');
        $this->assertSame('issued', $issued['status']);
        $this->assertSame(2, MemberCharge::count());
        $mc = MemberCharge::where('member_id', $m1->id)->firstOrFail();
        $payload = ['payment_date' => '2026-07-10', 'payment_mode' => 'upi', 'external_reference' => 'UPI-SAFE-1', 'amount_paise' => 50025, 'allocations' => [['member_charge_id' => $mc->id, 'amount_paise' => 50025]], 'idempotency_key' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'];
        $first = $this->postJson("/api/v1/clubs/{$club->id}/finance/members/{$m1->id}/payments", $payload)->assertCreated()->assertJsonPath('replayed', false);
        $paymentId = $first->json('data.id');
        $receiptId = $first->json('data.receipt.id');
        $this->assertSame('FA/2026-27/RCP/0001', $first->json('data.receipt.receipt_number'));
        $this->postJson("/api/v1/clubs/{$club->id}/finance/members/{$m1->id}/payments", $payload)->assertOk()->assertJsonPath('replayed', true)->assertJsonPath('data.id', $paymentId);
        $this->assertSame(1, MemberPayment::count());
        $this->assertSame(1, PaymentReceipt::count());
        $this->assertSame(1, PaymentAllocation::count());
        $changed = $payload;
        $changed['amount_paise'] = 50024;
        $changed['allocations'][0]['amount_paise'] = 50024;
        $this->postJson("/api/v1/clubs/{$club->id}/finance/members/{$m1->id}/payments", $changed)->assertConflict();
        $this->getJson("/api/v1/clubs/{$club->id}/finance/members/{$m1->id}/summary")->assertJsonPath('data.paid_paise', 50025)->assertJsonPath('data.outstanding_paise', 50025);
        $payment = MemberPayment::findOrFail($paymentId);
        $this->postJson("/api/v1/clubs/{$club->id}/finance/payments/{$paymentId}/void", ['reason' => 'Wrong entry', 'lock_version' => $payment->lock_version])->assertOk()->assertJsonPath('data.status', 'voided')->assertJsonPath('data.receipt.status', 'voided');
        $this->assertSame($receiptId, PaymentReceipt::firstOrFail()->id);
        $this->getJson("/api/v1/clubs/{$club->id}/finance/members/{$m1->id}/summary")->assertJsonPath('data.paid_paise', 0)->assertJsonPath('data.outstanding_paise', 100050);
        $this->assertDatabaseHas('audit_logs', ['action' => 'finance.charge.issued']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'finance.payment.recorded']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'finance.payment.voided']);
    }

    public function test_multi_allocation_exact_totals_close_and_authorization_guards(): void
    {
        $this->seed(DatabaseSeeder::class);
        [$club,$treasurer] = $this->actor('treasurer', 'B');
        [, $secretary] = $this->actorForClub($club, 'secretary');
        $m = $this->member($club, 'M');
        Sanctum::actingAs($treasurer);
        $ids = [];
        foreach ([30000, 20000] as $i => $amount) {
            $d = $this->postJson("/api/v1/clubs/{$club->id}/finance/charges", ['title' => 'Same', 'category' => 'general_club_event_contribution', 'amount_paise' => $amount, 'due_date' => '2026-08-01', 'financial_year' => '2026-27', 'member_ids' => [$m->id], 'assign_all_active_members' => false])->assertCreated()->json('data');
            $this->postJson("/api/v1/clubs/{$club->id}/finance/charges/{$d['id']}/issue", ['lock_version' => $d['lock_version'] ?? -1])->assertOk();
            $ids[] = MemberCharge::where('club_charge_id', $d['id'])->value('id');
        }
        $pay = $this->postJson("/api/v1/clubs/{$club->id}/finance/members/{$m->id}/payments", ['payment_date' => '2026-07-11', 'payment_mode' => 'cash', 'amount_paise' => 50000, 'allocations' => [['member_charge_id' => $ids[0], 'amount_paise' => 30000], ['member_charge_id' => $ids[1], 'amount_paise' => 20000]], 'idempotency_key' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'])->assertCreated()->json('data');
        $this->getJson("/api/v1/clubs/{$club->id}/finance/members/{$m->id}/summary")->assertJsonPath('data.outstanding_paise', 0);
        foreach (MemberCharge::with('charge')->get() as $mc) {
            $this->postJson("/api/v1/clubs/{$club->id}/finance/charges/{$mc->club_charge_id}/close", ['lock_version' => $mc->charge->lock_version])->assertOk();
        }
        Sanctum::actingAs($secretary);
        $this->postJson("/api/v1/clubs/{$club->id}/finance/payments/{$pay['id']}/void", ['reason' => 'No permission', 'lock_version' => $pay['lock_version']])->assertForbidden();
        Sanctum::actingAs($treasurer);
        $bad = ['payment_date' => '2026-07-12', 'payment_mode' => 'cash', 'amount_paise' => 1, 'allocations' => [['member_charge_id' => $ids[0], 'amount_paise' => 1]], 'idempotency_key' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc'];
        $this->postJson("/api/v1/clubs/{$club->id}/finance/members/{$m->id}/payments", $bad)->assertStatus(409);
        $this->assertSame(1, MemberPayment::count());
        $this->assertSame(0, AuditLog::where('action', 'finance.payment.recorded')->where('auditable_id', '!=', $pay['id'])->count());
    }

    public function test_member_cross_club_invalid_assignment_and_no_delete(): void
    {
        $this->seed(DatabaseSeeder::class);
        [$a,$u] = $this->actor('president-admin', 'C');
        [$b] = $this->actor('president-admin', 'D');
        $other = $this->member($b, 'X');
        Sanctum::actingAs($u);
        $base = ['title' => 'X', 'category' => 'bad', 'amount_paise' => 0, 'due_date' => '2026-07-31', 'financial_year' => 'bad', 'member_ids' => [$other->id], 'assign_all_active_members' => false];
        $this->postJson("/api/v1/clubs/{$a->id}/finance/charges", $base)->assertUnprocessable();
        $this->deleteJson("/api/v1/clubs/{$a->id}/finance/charges/1")->assertMethodNotAllowed();
    }

    private function actor(string $role, string $s): array
    {
        $c = Club::create(['name' => "F$s", 'code' => "F$s", 'timezone' => 'Asia/Kolkata', 'is_active' => true]);

        return $this->actorForClub($c, $role);
    }

    private function actorForClub(Club $c, string $role): array
    {
        $u = User::factory()->create();
        ClubMembership::create(['club_id' => $c->id, 'user_id' => $u->id, 'role_id' => Role::where('slug', $role)->firstOrFail()->id, 'status' => 'active', 'joined_at' => now()]);

        return [$c, $u];
    }

    private function member(Club $c, string $code): Member
    {
        return Member::create(['club_id' => $c->id, 'member_code' => $code.uniqid(), 'full_name' => 'Duplicate Member', 'status' => 'active']);
    }
}
