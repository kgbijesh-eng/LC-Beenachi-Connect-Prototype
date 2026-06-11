<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Member;
use App\Models\MemberLedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3bMemberAccountLedgerDatabaseFoundationTest extends TestCase
{
    use RefreshDatabase;

    private function createClub(): Club
    {
        return Club::query()->create([
            'name' => 'Lions Club Beenachi',
            'code' => 'LCB-TEST',
            'status' => 'active',
        ]);
    }

    private function createMember(Club $club, string $memberCode = 'MEM-TEST-001'): Member
    {
        return Member::query()->create([
            'club_id' => $club->id,
            'member_code' => $memberCode,
            'full_name' => 'Lion Test Member',
            'phone' => '9999999999',
            'email' => 'member@example.test',
            'status' => 'active',
            'blood_donor_consent' => false,
        ]);
    }

    public function test_member_ledger_entries_can_store_debit_and_credit_foundation_records(): void
    {
        $club = $this->createClub();
        $member = $this->createMember($club);
        $user = User::factory()->create();

        $debit = MemberLedgerEntry::create([
            'club_id' => $club->id,
            'member_id' => $member->id,
            'created_by_user_id' => $user->id,
            'entry_type' => MemberLedgerEntry::ENTRY_TYPE_DEBIT,
            'source_type' => MemberLedgerEntry::SOURCE_MONTHLY_DUES,
            'amount' => 1000.00,
            'accounting_month' => '2026-06',
            'due_date' => '2026-06-30',
            'description' => 'June monthly dues',
            'metadata' => [
                'milestone' => '3B',
            ],
            'status' => MemberLedgerEntry::STATUS_POSTED,
        ]);

        $credit = MemberLedgerEntry::create([
            'club_id' => $club->id,
            'member_id' => $member->id,
            'created_by_user_id' => $user->id,
            'entry_type' => MemberLedgerEntry::ENTRY_TYPE_CREDIT,
            'source_type' => MemberLedgerEntry::SOURCE_PAYMENT,
            'amount' => 500.00,
            'accounting_month' => '2026-06',
            'transaction_date' => '2026-06-15',
            'reference_number' => 'RCP-001',
            'description' => 'Partial payment received',
            'metadata' => [
                'payment_mode' => 'cash',
            ],
            'status' => MemberLedgerEntry::STATUS_POSTED,
        ]);

        $this->assertSame('1000.00', $debit->refresh()->amount);
        $this->assertSame('500.00', $credit->refresh()->amount);

        $this->assertDatabaseHas('member_ledger_entries', [
            'id' => $debit->id,
            'club_id' => $club->id,
            'member_id' => $member->id,
            'entry_type' => 'debit',
            'source_type' => 'monthly_dues',
            'accounting_month' => '2026-06',
            'status' => 'posted',
        ]);

        $this->assertDatabaseHas('member_ledger_entries', [
            'id' => $credit->id,
            'club_id' => $club->id,
            'member_id' => $member->id,
            'entry_type' => 'credit',
            'source_type' => 'payment',
            'reference_number' => 'RCP-001',
            'status' => 'posted',
        ]);
    }

    public function test_member_ledger_entry_relationships_resolve_club_member_and_creator(): void
    {
        $club = $this->createClub();
        $member = $this->createMember($club);
        $user = User::factory()->create();

        $entry = MemberLedgerEntry::factory()->create([
            'club_id' => $club->id,
            'member_id' => $member->id,
            'created_by_user_id' => $user->id,
        ]);

        $this->assertTrue($entry->club->is($club));
        $this->assertTrue($entry->member->is($member));
        $this->assertTrue($entry->createdByUser->is($user));
    }

    public function test_club_and_member_expose_ledger_entry_relationships(): void
    {
        $club = $this->createClub();
        $member = $this->createMember($club);

        $entry = MemberLedgerEntry::factory()->create([
            'club_id' => $club->id,
            'member_id' => $member->id,
        ]);

        $this->assertTrue($club->memberLedgerEntries()->whereKey($entry->id)->exists());
        $this->assertTrue($member->ledgerEntries()->whereKey($entry->id)->exists());
    }

    public function test_special_collection_source_type_is_identifiable_in_the_ledger(): void
    {
        $club = $this->createClub();
        $member = $this->createMember($club);

        $entry = MemberLedgerEntry::factory()->create([
            'club_id' => $club->id,
            'member_id' => $member->id,
            'entry_type' => MemberLedgerEntry::ENTRY_TYPE_DEBIT,
            'source_type' => MemberLedgerEntry::SOURCE_SPECIAL_COLLECTION,
            'amount' => 2500.00,
            'description' => 'Service project contribution',
        ]);

        $this->assertSame(MemberLedgerEntry::SOURCE_SPECIAL_COLLECTION, $entry->refresh()->source_type);

        $this->assertDatabaseHas('member_ledger_entries', [
            'id' => $entry->id,
            'entry_type' => 'debit',
            'source_type' => 'special_collection',
        ]);
    }

    public function test_3b_does_not_add_payment_receipt_balance_reminder_report_or_excel_routes(): void
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
        ];

        $registeredUris = collect(Route::getRoutes())
            ->map(fn ($route) => $route->uri())
            ->reject(fn (string $uri): bool => in_array($uri, [
                'api/v1/clubs/{club}/members/{member}/payments',
                'api/v1/clubs/{club}/members/me/payments/history',
                'api/v1/clubs/{club}/members/{member}/payments/history',
                'api/v1/clubs/{club}/members/{member}/payments/{payment}/receipt',
                'api/v1/clubs/{club}/members/{member}/payments/{payment}/receipt/pdf',
            ], true)) // authorized 3Q payment route, 3BM payment history routes, 3Y receipt route, and 3AD PDF route
            ->values();

        foreach ($blockedRouteFragments as $fragment) {
            $this->assertFalse(
                $registeredUris->contains(fn (string $uri) => ! in_array($uri, [
                    'api/v1/clubs/{club}/finance/reports',
                    'api/v1/clubs/{club}/finance/balance-sheet',
                ], true) && str_contains($uri, $fragment)),
                "Milestone 3B must not register route containing [{$fragment}]."
            );
        }
    }

    public function test_3b_document_records_database_foundation_boundary(): void
    {
        $path = base_path('docs/milestone_3b_member_account_ledger_dues_database_foundation.md');

        $this->assertFileExists($path);

        $content = file_get_contents($path);

        $this->assertStringContainsString('Milestone 3B implements the database/model foundation', $content);
        $this->assertStringContainsString('member_ledger_entries', $content);
        $this->assertStringContainsString('Total Debits - Total Credits = Current Balance', $content);
        $this->assertStringContainsString('No payment recording API', $content);
        $this->assertStringContainsString('No receipt generation', $content);
        $this->assertStringContainsString('No balance display API', $content);
        $this->assertStringContainsString('No frontend changes', $content);
        $this->assertStringContainsString('No reminder automation', $content);
        $this->assertStringContainsString('No reports', $content);
        $this->assertStringContainsString('No Excel import', $content);
    }
}
