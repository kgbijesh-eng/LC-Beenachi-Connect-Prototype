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

class Milestone3fMemberLedgerBalanceReadApiValidationAndClosureTest extends TestCase
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
                'description' => '3F validation role.',
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
            'description' => '3F validation ledger entry',
            'status' => $status,
        ]);
    }

    private function seedBalance(Club $club, Member $member): void
    {
        $this->createLedgerEntry($club, $member, MemberLedgerEntry::ENTRY_TYPE_DEBIT, MemberLedgerEntry::SOURCE_MONTHLY_DUES, 1000.00);
        $this->createLedgerEntry($club, $member, MemberLedgerEntry::ENTRY_TYPE_DEBIT, MemberLedgerEntry::SOURCE_SPECIAL_COLLECTION, 500.00);
        $this->createLedgerEntry($club, $member, MemberLedgerEntry::ENTRY_TYPE_CREDIT, MemberLedgerEntry::SOURCE_PAYMENT, 600.00);
        $this->createLedgerEntry($club, $member, MemberLedgerEntry::ENTRY_TYPE_DEBIT, MemberLedgerEntry::SOURCE_FINE, 9999.00, MemberLedgerEntry::STATUS_CANCELLED);
    }

    public function test_3f_validation_document_exists_and_records_closure_boundary(): void
    {
        $path = base_path('docs/milestone_3f_member_ledger_balance_read_api_validation_and_closure.md');

        $this->assertFileExists($path);

        $content = file_get_contents($path);

        $this->assertStringContainsString('Milestone 3F is a docs/tests-only validation and closure milestone.', $content);
        $this->assertStringContainsString('This milestone validates and closes Milestone 3E', $content);
        $this->assertStringContainsString('No runtime implementation files are changed', $content);
        $this->assertStringContainsString('next logical milestone is a fresh payment recording scope gate', $content);
    }

    public function test_balance_routes_remain_read_only_and_limited_to_two_authorized_routes(): void
    {
        $balanceRoutes = collect(Route::getRoutes())
            ->filter(fn ($route) => str_contains($route->uri(), 'balance'))
            ->values();

        $this->assertCount(2, $balanceRoutes);

        $uris = $balanceRoutes->map(fn ($route) => $route->uri())->sort()->values()->all();

        $this->assertSame([
            'api/v1/clubs/{club}/members/me/balance',
            'api/v1/clubs/{club}/members/{member}/balance',
        ], $uris);

        foreach ($balanceRoutes as $route) {
            $this->assertContains('GET', $route->methods());
            $this->assertNotContains('POST', $route->methods());
            $this->assertNotContains('PATCH', $route->methods());
            $this->assertNotContains('PUT', $route->methods());
            $this->assertNotContains('DELETE', $route->methods());
        }
    }

    public function test_normal_member_balance_privacy_remains_enforced(): void
    {
        $club = $this->createClub();
        $user = $this->createUserWithRole($club, 'member');
        $ownMember = $this->createMember($club, $user, 'MEM-OWN');
        $otherMember = $this->createMember($club, null, 'MEM-OTHER');

        $this->seedBalance($club, $ownMember);
        $this->seedBalance($club, $otherMember);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/clubs/{$club->id}/members/me/balance")
            ->assertOk()
            ->assertJsonPath('balance.member_id', $ownMember->id)
            ->assertJsonPath('balance.debit_total', '1500.00')
            ->assertJsonPath('balance.credit_total', '600.00')
            ->assertJsonPath('balance.balance', '900.00')
            ->assertJsonPath('balance.balance_type', 'amount_due');

        $this->getJson("/api/v1/clubs/{$club->id}/members/{$ownMember->id}/balance")
            ->assertOk()
            ->assertJsonPath('balance.member_id', $ownMember->id);

        $this->getJson("/api/v1/clubs/{$club->id}/members/{$otherMember->id}/balance")
            ->assertForbidden();
    }

    public function test_treasurer_secretary_and_president_admin_balance_lookup_remains_read_only_authorized(): void
    {
        foreach (['treasurer', 'secretary', 'president-admin'] as $roleSlug) {
            $club = $this->createClub('LCB-' . strtoupper($roleSlug));
            $user = $this->createUserWithRole($club, $roleSlug);
            $member = $this->createMember($club, null, 'MEM-' . strtoupper($roleSlug));

            $this->seedBalance($club, $member);

            Sanctum::actingAs($user);

            $this->getJson("/api/v1/clubs/{$club->id}/members/{$member->id}/balance")
                ->assertOk()
                ->assertJsonPath('balance.member_id', $member->id)
                ->assertJsonPath('balance.debit_total', '1500.00')
                ->assertJsonPath('balance.credit_total', '600.00')
                ->assertJsonPath('balance.balance', '900.00')
                ->assertJsonPath('balance.balance_type', 'amount_due');
        }
    }

    public function test_cross_club_and_unauthenticated_balance_access_fail_closed(): void
    {
        $club = $this->createClub('LCB-ONE');
        $otherClub = $this->createClub('LCB-TWO');

        $otherClubMember = $this->createMember($otherClub, null, 'MEM-OTHER-CLUB');

        $this->getJson("/api/v1/clubs/{$club->id}/members/{$otherClubMember->id}/balance")
            ->assertUnauthorized();

        $treasurer = $this->createUserWithRole($club, 'treasurer');

        Sanctum::actingAs($treasurer);

        $this->getJson("/api/v1/clubs/{$club->id}/members/{$otherClubMember->id}/balance")
            ->assertNotFound();
    }

    public function test_payment_receipt_report_reminder_excel_and_mutation_routes_remain_absent(): void
    {
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
            'dues',
            'special-collection',
            'special-collections',
        ];

        $registeredUris = collect(Route::getRoutes())
            ->map(fn ($route) => $route->uri())
            ->reject(fn (string $uri): bool => in_array($uri, [
                'api/v1/clubs/{club}/members/{member}/payments',
                'api/v1/clubs/{club}/members/{member}/payments/{payment}/receipt',
                'api/v1/clubs/{club}/members/{member}/payments/{payment}/receipt/pdf',
            ], true)) // authorized 3Q payment route, 3Y receipt route, and 3AD PDF route
            ->values();

        foreach ($blockedRouteFragments as $fragment) {
            $this->assertFalse(
                $registeredUris->contains(fn (string $uri) => str_contains($uri, $fragment)),
                "Milestone 3F confirms route containing [{$fragment}] must remain absent."
            );
        }
    }
}