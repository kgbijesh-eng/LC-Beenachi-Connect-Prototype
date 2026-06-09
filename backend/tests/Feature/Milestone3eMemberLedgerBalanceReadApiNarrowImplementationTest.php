<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubMembership;
use App\Models\Member;
use App\Models\MemberLedgerEntry;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Milestone3eMemberLedgerBalanceReadApiNarrowImplementationTest extends TestCase
{
    use RefreshDatabase;

    private function createClub(string $code = 'LCB-TEST'): Club
    {
        return Club::query()->create([
            'name' => 'Lions Club Beenachi',
            'code' => $code,
            'status' => 'active',
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
                    default => 'Member',
                },
                'description' => '3E test role.',
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

    private function createMember(Club $club, ?User $user = null, string $memberCode = 'MEM-TEST-001'): Member
    {
        return Member::query()->create([
            'club_id' => $club->id,
            'user_id' => $user?->id,
            'member_code' => $memberCode,
            'full_name' => 'Lion Test Member',
            'phone' => '9999999999',
            'email' => $memberCode . '@example.test',
            'status' => 'active',
            'blood_donor_consent' => false,
        ]);
    }

    private function createLedgerEntry(
        Club $club,
        Member $member,
        string $entryType,
        string $sourceType,
        float $amount,
        string $status = MemberLedgerEntry::STATUS_POSTED
    ): void {
        MemberLedgerEntry::query()->create([
            'club_id' => $club->id,
            'member_id' => $member->id,
            'created_by_user_id' => User::factory()->create()->id,
            'entry_type' => $entryType,
            'source_type' => $sourceType,
            'amount' => $amount,
            'accounting_month' => '2026-06',
            'description' => '3E read API test entry',
            'status' => $status,
        ]);
    }

    private function seedBalance(Club $club, Member $member): void
    {
        $this->createLedgerEntry($club, $member, MemberLedgerEntry::ENTRY_TYPE_DEBIT, MemberLedgerEntry::SOURCE_MONTHLY_DUES, 1000.00);
        $this->createLedgerEntry($club, $member, MemberLedgerEntry::ENTRY_TYPE_DEBIT, MemberLedgerEntry::SOURCE_SPECIAL_COLLECTION, 500.00);
        $this->createLedgerEntry($club, $member, MemberLedgerEntry::ENTRY_TYPE_CREDIT, MemberLedgerEntry::SOURCE_PAYMENT, 600.00);
    }

    public function test_normal_member_can_read_own_balance_summary(): void
    {
        $club = $this->createClub();
        $user = $this->createUserWithRole($club, 'member');
        $member = $this->createMember($club, $user);

        $this->seedBalance($club, $member);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/clubs/{$club->id}/members/me/balance");

        $response->assertOk()
            ->assertJsonPath('balance.club_id', $club->id)
            ->assertJsonPath('balance.member_id', $member->id)
            ->assertJsonPath('balance.debit_total', '1500.00')
            ->assertJsonPath('balance.credit_total', '600.00')
            ->assertJsonPath('balance.balance', '900.00')
            ->assertJsonPath('balance.balance_type', 'amount_due');
    }

    public function test_normal_member_can_read_selected_balance_only_for_own_linked_member_record(): void
    {
        $club = $this->createClub();
        $user = $this->createUserWithRole($club, 'member');
        $ownMember = $this->createMember($club, $user, 'MEM-OWN');
        $otherMember = $this->createMember($club, null, 'MEM-OTHER');

        $this->seedBalance($club, $ownMember);
        $this->seedBalance($club, $otherMember);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/clubs/{$club->id}/members/{$ownMember->id}/balance")
            ->assertOk()
            ->assertJsonPath('balance.member_id', $ownMember->id);

        $this->getJson("/api/v1/clubs/{$club->id}/members/{$otherMember->id}/balance")
            ->assertForbidden();
    }

    public function test_treasurer_can_read_selected_member_balance_summary(): void
    {
        $club = $this->createClub();
        $treasurer = $this->createUserWithRole($club, 'treasurer');
        $member = $this->createMember($club, null, 'MEM-FINANCE');

        $this->seedBalance($club, $member);

        Sanctum::actingAs($treasurer);

        $this->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/balance")
            ->assertOk()
            ->assertJsonPath('balance.member_id', $member->id)
            ->assertJsonPath('balance.balance', '900.00');
    }

    public function test_secretary_can_read_selected_member_balance_summary(): void
    {
        $club = $this->createClub();
        $secretary = $this->createUserWithRole($club, 'secretary');
        $member = $this->createMember($club, null, 'MEM-SECRETARY');

        $this->seedBalance($club, $member);

        Sanctum::actingAs($secretary);

        $this->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/balance")
            ->assertOk()
            ->assertJsonPath('balance.member_id', $member->id)
            ->assertJsonPath('balance.balance_type', 'amount_due');
    }

    public function test_president_admin_can_read_selected_member_balance_summary(): void
    {
        $club = $this->createClub();
        $president = $this->createUserWithRole($club, 'president-admin');
        $member = $this->createMember($club, null, 'MEM-PRESIDENT');

        $this->seedBalance($club, $member);

        Sanctum::actingAs($president);

        $this->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/balance")
            ->assertOk()
            ->assertJsonPath('balance.member_id', $member->id)
            ->assertJsonPath('balance.debit_total', '1500.00');
    }

    public function test_cross_club_member_balance_lookup_fails_closed(): void
    {
        $club = $this->createClub('LCB-ONE');
        $otherClub = $this->createClub('LCB-TWO');

        $treasurer = $this->createUserWithRole($club, 'treasurer');
        $otherClubMember = $this->createMember($otherClub, null, 'MEM-OTHER-CLUB');

        Sanctum::actingAs($treasurer);

        $this->getJson("/api/v1/clubs/{$club->id}/members/{$otherClubMember->id}/balance")
            ->assertNotFound();
    }

    public function test_unauthenticated_user_cannot_read_balance_summary(): void
    {
        $club = $this->createClub();
        $member = $this->createMember($club, null, 'MEM-PUBLIC');

        $this->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/balance")
            ->assertUnauthorized();
    }

    public function test_balance_routes_are_get_only_and_finance_mutation_routes_remain_blocked(): void
    {
        $balanceRoutes = collect(Route::getRoutes())
            ->filter(fn ($route) => str_contains($route->uri(), 'balance'));

        $this->assertCount(2, $balanceRoutes);

        foreach ($balanceRoutes as $route) {
            $this->assertContains('GET', $route->methods());
            $this->assertNotContains('POST', $route->methods());
            $this->assertNotContains('PATCH', $route->methods());
            $this->assertNotContains('PUT', $route->methods());
            $this->assertNotContains('DELETE', $route->methods());
        }

        $blockedRouteFragments = [
            'payment',
            'payments',
            'receipt',
            'receipts',
            'reminder',
            'reminders',
            'report',
            'reports',
            'excel',
            'import',
        ];

        $registeredUris = collect(Route::getRoutes())
            ->map(fn ($route) => $route->uri())
            ->reject(fn (string $uri): bool => in_array($uri, [
                'api/v1/clubs/{club}/members/{member}/payments',
                'api/v1/clubs/{club}/members/{member}/payments/{payment}/receipt',
            ], true)) // authorized 3Q payment route and 3Y receipt route
            ->values();

        foreach ($blockedRouteFragments as $fragment) {
            $this->assertFalse(
                $registeredUris->contains(fn (string $uri) => str_contains($uri, $fragment)),
                "Milestone 3E must not register route containing [{$fragment}]."
            );
        }
    }

    public function test_3e_document_records_narrow_read_only_api_boundary(): void
    {
        $path = base_path('docs/milestone_3e_member_ledger_balance_read_api_narrow_implementation.md');

        $this->assertFileExists($path);

        $content = file_get_contents($path);

        $this->assertStringContainsString('Milestone 3E implements a narrow read-only API', $content);
        $this->assertStringContainsString('GET /api/v1/clubs/{club}/members/me/balance', $content);
        $this->assertStringContainsString('GET /api/v1/clubs/{club}/members/{member}/balance', $content);
        $this->assertStringContainsString('Normal members must not access other members\' balance summaries.', $content);
        $this->assertStringContainsString('No payment recording', $content);
        $this->assertStringContainsString('No receipt generation', $content);
        $this->assertStringContainsString('No ledger mutation', $content);
        $this->assertStringContainsString('No Excel import', $content);
        $this->assertStringContainsString('No frontend changes', $content);
    }
}