<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubMembership;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Receipt;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Milestone3bmPaymentHistoryReadOnlyNarrowImplementationTest extends TestCase
{
    use RefreshDatabase;

    private string $docPath = __DIR__ . '/../../docs/milestone_3bm_payment_history_read_only_narrow_implementation.md';

    public function test_3bm_document_records_narrow_read_only_implementation_boundary(): void
    {
        $document = $this->implementationDocument();

        $this->assertStringContainsString('Milestone 3BM - Payment History Read-Only Narrow Implementation', $document);
        $this->assertStringContainsString('narrow backend read-only API implementation milestone', $document);
        $this->assertStringContainsString('It implements payment history read APIs only.', $document);
        $this->assertStringContainsString('It does not implement frontend payment history display.', $document);
        $this->assertStringContainsString('It does not implement an all-member payment history endpoint.', $document);
        $this->assertStringContainsString('It does not mutate payment, receipt, ledger, member, or club records.', $document);
    }

    public function test_payment_history_routes_are_registered_as_get_only_without_all_member_endpoint(): void
    {
        $routes = collect(Route::getRoutes())
            ->filter(fn ($route): bool => str_contains($route->uri(), 'payments/history'))
            ->map(fn ($route): string => implode('|', $route->methods()).' '.$route->uri())
            ->sort()
            ->values()
            ->all();

        $this->assertSame([
            'GET|HEAD api/v1/clubs/{club}/members/me/payments/history',
            'GET|HEAD api/v1/clubs/{club}/members/{member}/payments/history',
        ], $routes);

        foreach (Route::getRoutes() as $route) {
            if (! str_contains($route->uri(), 'payments/history')) {
                continue;
            }

            $this->assertContains('GET', $route->methods());
            $this->assertNotContains('POST', $route->methods());
            $this->assertNotContains('PATCH', $route->methods());
            $this->assertNotContains('PUT', $route->methods());
            $this->assertNotContains('DELETE', $route->methods());
            $this->assertNotSame('api/v1/clubs/{club}/payments/history', $route->uri());
        }
    }

    public function test_normal_member_can_read_only_own_payment_history(): void
    {
        $club = $this->createClub();
        $normalUser = $this->createUserWithRole($club, 'member');
        $member = $this->createMember($club, $normalUser, 'MEM-3BM-OWN');
        $officer = $this->createUserWithRole($club, 'treasurer');

        $olderPayment = $this->createPayment($club, $member, $officer, 100.00, '2026-06-01', 'cash', 'posted');
        $newerPayment = $this->createPayment($club, $member, $officer, 250.50, '2026-06-10', 'upi', 'posted');
        $receipt = $this->createIssuedReceipt($club, $newerPayment, $officer, 'RCP-3BM-001');

        Sanctum::actingAs($normalUser);

        config(['instance.organisation_code' => $club->code]);
        $response = $this->getJson("/api/v1/clubs/{$club->id}/members/me/payments/history");

        $response->assertOk()
            ->assertJsonPath('payment_history.0.payment_id', $newerPayment->id)
            ->assertJsonPath('payment_history.0.payment_date', '2026-06-10')
            ->assertJsonPath('payment_history.0.amount', '250.50')
            ->assertJsonPath('payment_history.0.payment_mode', 'upi')
            ->assertJsonPath('payment_history.0.status', 'posted')
            ->assertJsonPath('payment_history.0.member.id', $member->id)
            ->assertJsonPath('payment_history.0.member.member_code', 'MEM-3BM-OWN')
            ->assertJsonPath('payment_history.0.receipt.available', true)
            ->assertJsonPath('payment_history.0.receipt.receipt_number', $receipt->receipt_number)
            ->assertJsonPath('payment_history.0.recorded_by.name', $officer->name)
            ->assertJsonPath('payment_history.1.payment_id', $olderPayment->id)
            ->assertJsonPath('payment_history.1.receipt.available', false)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 2);

        $response->assertJsonMissingPath('payment_history.0.notes');
        $response->assertJsonMissingPath('payment_history.0.cancellation_reason');
        $response->assertJsonMissingPath('payment_history.0.ledger_entry_id');
    }

    public function test_normal_member_cannot_read_selected_member_payment_history(): void
    {
        $club = $this->createClub();
        $normalUser = $this->createUserWithRole($club, 'member');
        $ownMember = $this->createMember($club, $normalUser, 'MEM-3BM-NORMAL');
        $otherMember = $this->createMember($club, null, 'MEM-3BM-OTHER');
        $officer = $this->createUserWithRole($club, 'treasurer');

        $this->createPayment($club, $ownMember, $officer, 100.00);
        $this->createPayment($club, $otherMember, $officer, 200.00);

        Sanctum::actingAs($normalUser);

        config(['instance.organisation_code' => $club->code]);
        $this->getJson("/api/v1/clubs/{$club->id}/members/{$ownMember->id}/payments/history")
            ->assertForbidden();

        $this->getJson("/api/v1/clubs/{$club->id}/members/{$otherMember->id}/payments/history")
            ->assertForbidden();
    }

    public function test_president_secretary_and_treasurer_can_read_selected_member_payment_history(): void
    {
        foreach (['president-admin', 'secretary', 'treasurer'] as $roleSlug) {
            $club = $this->createClub('LCB-3BM-'.strtoupper($roleSlug).uniqid());
            $officer = $this->createUserWithRole($club, $roleSlug);
            $member = $this->createMember($club, null, 'MEM-3BM-'.strtoupper($roleSlug).uniqid());
            $payment = $this->createPayment($club, $member, $officer, 333.33, '2026-06-12', 'bank_transfer');

            Sanctum::actingAs($officer);

            config(['instance.organisation_code' => $club->code]);
            $this->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/payments/history")
                ->assertOk()
                ->assertJsonPath('payment_history.0.payment_id', $payment->id)
                ->assertJsonPath('payment_history.0.amount', '333.33')
                ->assertJsonPath('payment_history.0.member.id', $member->id)
                ->assertJsonPath('meta.total', 1);
        }
    }

    public function test_cross_club_selected_member_payment_history_fails_closed(): void
    {
        $club = $this->createClub('LCB-3BM-ONE');
        $otherClub = $this->createClub('LCB-3BM-TWO');
        $treasurer = $this->createUserWithRole($club, 'treasurer');
        $otherMember = $this->createMember($otherClub, null, 'MEM-3BM-CROSS');
        $otherOfficer = $this->createUserWithRole($otherClub, 'treasurer');

        $this->createPayment($otherClub, $otherMember, $otherOfficer, 900.00);

        Sanctum::actingAs($treasurer);

        config(['instance.organisation_code' => $club->code]);
        $this->getJson("/api/v1/clubs/{$club->id}/members/{$otherMember->id}/payments/history")
            ->assertNotFound()
            ->assertJson([
                'message' => 'Member does not belong to this club.',
            ]);
    }

    public function test_unauthorized_role_fails_closed_for_selected_member_payment_history(): void
    {
        $club = $this->createClub();
        $viewer = $this->createUserWithRole($club, 'board-viewer');
        $member = $this->createMember($club, null, 'MEM-3BM-VIEWER');
        $officer = $this->createUserWithRole($club, 'treasurer');

        $this->createPayment($club, $member, $officer, 400.00);

        Sanctum::actingAs($viewer);

        config(['instance.organisation_code' => $club->code]);
        $this->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/payments/history")
            ->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_read_payment_history(): void
    {
        $club = $this->createClub();
        $member = $this->createMember($club, null, 'MEM-3BM-PUBLIC');

        config(['instance.organisation_code' => $club->code]);
        $this->getJson("/api/v1/clubs/{$club->id}/members/me/payments/history")
            ->assertUnauthorized();

        $this->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/payments/history")
            ->assertUnauthorized();
    }

    public function test_payment_history_uses_capped_pagination_without_filters(): void
    {
        $club = $this->createClub();
        $officer = $this->createUserWithRole($club, 'treasurer');
        $member = $this->createMember($club, null, 'MEM-3BM-PAGE');

        foreach (range(1, 30) as $index) {
            $this->createPayment($club, $member, $officer, 10 + $index, '2026-06-'.str_pad((string) min($index, 28), 2, '0', STR_PAD_LEFT));
        }

        Sanctum::actingAs($officer);

        config(['instance.organisation_code' => $club->code]);
        $this->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/payments/history?per_page=100")
            ->assertOk()
            ->assertJsonCount(25, 'payment_history')
            ->assertJsonPath('meta.per_page', 25)
            ->assertJsonPath('meta.total', 30);

        $this->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/payments/history?per_page=0")
            ->assertOk()
            ->assertJsonCount(10, 'payment_history')
            ->assertJsonPath('meta.per_page', 10);
    }

    public function test_payment_history_does_not_mutate_payment_or_receipt_records(): void
    {
        $club = $this->createClub();
        $officer = $this->createUserWithRole($club, 'treasurer');
        $member = $this->createMember($club, null, 'MEM-3BM-READONLY');
        $payment = $this->createPayment($club, $member, $officer, 555.00);
        $receipt = $this->createIssuedReceipt($club, $payment, $officer, 'RCP-3BM-READONLY');

        Sanctum::actingAs($officer);

        config(['instance.organisation_code' => $club->code]);
        $this->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/payments/history")
            ->assertOk();

        $this->assertSame(1, Payment::query()->count());
        $this->assertSame(1, Receipt::query()->count());
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'posted',
            'amount' => '555.00',
        ]);
        $this->assertDatabaseHas('receipts', [
            'id' => $receipt->id,
            'status' => 'issued',
        ]);
    }

    public function test_3bm_does_not_add_blocked_runtime_scope_files(): void
    {
        foreach ([
            app_path('Http/Requests/Finance/PaymentHistoryRequest.php'),
            app_path('Services/Finance/PaymentHistoryService.php'),
            app_path('Models/PaymentHistory.php'),
            app_path('Http/Middleware/EnsurePaymentHistoryAccess.php'),
            database_path('migrations/create_payment_histories_table.php'),
            database_path('seeders/PaymentHistorySeeder.php'),
            app_path('Services/Finance/PaymentCancellationService.php'),
            app_path('Services/Finance/PaymentReversalService.php'),
            app_path('Services/Finance/PaymentAllocationService.php'),
            app_path('Services/Finance/PaymentReminderService.php'),
            app_path('Services/Finance/PaymentHistoryExportService.php'),
            base_path('../app/page.tsx.3bm'),
        ] as $blockedPath) {
            $this->assertFileDoesNotExist($blockedPath);
        }
    }

    public function test_3bm_selects_3bn_as_next_validation_and_closure_milestone(): void
    {
        $document = $this->implementationDocument();

        $this->assertStringContainsString('Milestone 3BN - Payment History Read-Only Validation and Closure', $document);
    }

    private function implementationDocument(): string
    {
        $this->assertFileExists($this->docPath);

        return file_get_contents($this->docPath);
    }

    private function createClub(string $code = 'LCB-3BM'): Club
    {
        return Club::query()->create([
            'name' => 'Lions Club Beenachi',
            'code' => $code,
            'district' => '318E',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function createUserWithRole(Club $club, string $roleSlug): User
    {
        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'club.view'],
            [
                'name' => 'View Club',
                'group' => 'club',
                'description' => 'View club foundation details.',
            ]
        );

        $role = Role::query()->firstOrCreate(
            ['slug' => $roleSlug],
            [
                'name' => match ($roleSlug) {
                    'president-admin' => 'President/Admin',
                    'secretary' => 'Secretary',
                    'treasurer' => 'Treasurer',
                    'member' => 'Member',
                    default => 'Board Viewer',
                },
                'description' => '3BM test role.',
                'is_system' => true,
            ]
        );

        $role->permissions()->syncWithoutDetaching([$permission->id]);

        $user = User::factory()->create();

        ClubMembership::query()->create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return $user;
    }

    private function createMember(Club $club, ?User $user = null, string $memberCode = 'MEM-3BM-001'): Member
    {
        return Member::query()->create([
            'club_id' => $club->id,
            'user_id' => $user?->id,
            'member_code' => $memberCode,
            'full_name' => 'Lion Payment History '.$memberCode,
            'phone' => '9999999999',
            'email' => strtolower($memberCode).'@lcbeenachi.test',
            'status' => 'active',
            'joined_at' => '2026-06-01',
            'blood_group' => 'O+',
            'blood_donor_consent' => true,
        ]);
    }

    private function createPayment(
        Club $club,
        Member $member,
        User $receivedBy,
        float $amount,
        string $paymentDate = '2026-06-10',
        string $paymentMode = 'cash',
        string $status = 'posted'
    ): Payment {
        return Payment::query()->create([
            'club_id' => $club->id,
            'member_id' => $member->id,
            'amount' => $amount,
            'payment_date' => $paymentDate,
            'payment_mode' => $paymentMode,
            'reference_number' => '3BM-PAY-'.uniqid(),
            'notes' => 'Sensitive internal note not exposed by payment history.',
            'received_by_user_id' => $receivedBy->id,
            'ledger_entry_id' => null,
            'status' => $status,
        ]);
    }

    private function createIssuedReceipt(
        Club $club,
        Payment $payment,
        User $issuedBy,
        string $receiptNumber
    ): Receipt {
        return Receipt::query()->create([
            'club_id' => $club->id,
            'payment_id' => $payment->id,
            'receipt_number' => $receiptNumber,
            'receipt_date' => $payment->payment_date,
            'issued_by_user_id' => $issuedBy->id,
            'status' => 'issued',
            'notes' => 'Sensitive receipt note not exposed by payment history.',
        ]);
    }
}
