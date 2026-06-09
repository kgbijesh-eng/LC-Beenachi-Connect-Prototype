<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Member;
use App\Models\MemberLedgerEntry;
use App\Models\User;
use App\Services\Finance\MemberLedgerBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone3cMemberLedgerBalanceCalculationFoundationTest extends TestCase
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
    ): MemberLedgerEntry {
        return MemberLedgerEntry::query()->create([
            'club_id' => $club->id,
            'member_id' => $member->id,
            'created_by_user_id' => User::factory()->create()->id,
            'entry_type' => $entryType,
            'source_type' => $sourceType,
            'amount' => $amount,
            'accounting_month' => '2026-06',
            'description' => '3C balance calculation test entry',
            'status' => $status,
        ]);
    }

    public function test_service_calculates_amount_due_as_debits_minus_credits(): void
    {
        $club = $this->createClub();
        $member = $this->createMember($club);

        $this->createLedgerEntry($club, $member, MemberLedgerEntry::ENTRY_TYPE_DEBIT, MemberLedgerEntry::SOURCE_MONTHLY_DUES, 1000.00);
        $this->createLedgerEntry($club, $member, MemberLedgerEntry::ENTRY_TYPE_DEBIT, MemberLedgerEntry::SOURCE_SPECIAL_COLLECTION, 500.00);
        $this->createLedgerEntry($club, $member, MemberLedgerEntry::ENTRY_TYPE_CREDIT, MemberLedgerEntry::SOURCE_PAYMENT, 600.00);

        $balance = app(MemberLedgerBalanceService::class)->calculateForMember($member);

        $this->assertSame($club->id, $balance['club_id']);
        $this->assertSame($member->id, $balance['member_id']);
        $this->assertSame('1500.00', $balance['debit_total']);
        $this->assertSame('600.00', $balance['credit_total']);
        $this->assertSame('900.00', $balance['balance']);
        $this->assertSame('amount_due', $balance['balance_type']);
    }

    public function test_service_returns_clear_when_debits_equal_credits(): void
    {
        $club = $this->createClub();
        $member = $this->createMember($club);

        $this->createLedgerEntry($club, $member, MemberLedgerEntry::ENTRY_TYPE_DEBIT, MemberLedgerEntry::SOURCE_MONTHLY_DUES, 1000.00);
        $this->createLedgerEntry($club, $member, MemberLedgerEntry::ENTRY_TYPE_CREDIT, MemberLedgerEntry::SOURCE_PAYMENT, 1000.00);

        $balance = app(MemberLedgerBalanceService::class)->calculateForMember($member);

        $this->assertSame('1000.00', $balance['debit_total']);
        $this->assertSame('1000.00', $balance['credit_total']);
        $this->assertSame('0.00', $balance['balance']);
        $this->assertSame('clear', $balance['balance_type']);
    }

    public function test_service_returns_advance_when_credits_exceed_debits(): void
    {
        $club = $this->createClub();
        $member = $this->createMember($club);

        $this->createLedgerEntry($club, $member, MemberLedgerEntry::ENTRY_TYPE_DEBIT, MemberLedgerEntry::SOURCE_MONTHLY_DUES, 500.00);
        $this->createLedgerEntry($club, $member, MemberLedgerEntry::ENTRY_TYPE_CREDIT, MemberLedgerEntry::SOURCE_PAYMENT, 750.00);

        $balance = app(MemberLedgerBalanceService::class)->calculateForMember($member);

        $this->assertSame('500.00', $balance['debit_total']);
        $this->assertSame('750.00', $balance['credit_total']);
        $this->assertSame('-250.00', $balance['balance']);
        $this->assertSame('advance', $balance['balance_type']);
    }

    public function test_service_ignores_cancelled_entries(): void
    {
        $club = $this->createClub();
        $member = $this->createMember($club);

        $this->createLedgerEntry($club, $member, MemberLedgerEntry::ENTRY_TYPE_DEBIT, MemberLedgerEntry::SOURCE_MONTHLY_DUES, 1000.00);
        $this->createLedgerEntry($club, $member, MemberLedgerEntry::ENTRY_TYPE_CREDIT, MemberLedgerEntry::SOURCE_PAYMENT, 400.00);

        $this->createLedgerEntry(
            $club,
            $member,
            MemberLedgerEntry::ENTRY_TYPE_DEBIT,
            MemberLedgerEntry::SOURCE_SPECIAL_COLLECTION,
            9999.00,
            MemberLedgerEntry::STATUS_CANCELLED
        );

        $this->createLedgerEntry(
            $club,
            $member,
            MemberLedgerEntry::ENTRY_TYPE_CREDIT,
            MemberLedgerEntry::SOURCE_PAYMENT,
            9999.00,
            MemberLedgerEntry::STATUS_CANCELLED
        );

        $balance = app(MemberLedgerBalanceService::class)->calculateForMember($member);

        $this->assertSame('1000.00', $balance['debit_total']);
        $this->assertSame('400.00', $balance['credit_total']);
        $this->assertSame('600.00', $balance['balance']);
        $this->assertSame('amount_due', $balance['balance_type']);
    }

    public function test_service_calculates_only_for_the_selected_member(): void
    {
        $club = $this->createClub();
        $selectedMember = $this->createMember($club, 'MEM-TEST-001');
        $otherMember = $this->createMember($club, 'MEM-TEST-002');

        $this->createLedgerEntry($club, $selectedMember, MemberLedgerEntry::ENTRY_TYPE_DEBIT, MemberLedgerEntry::SOURCE_MONTHLY_DUES, 1000.00);
        $this->createLedgerEntry($club, $selectedMember, MemberLedgerEntry::ENTRY_TYPE_CREDIT, MemberLedgerEntry::SOURCE_PAYMENT, 300.00);

        $this->createLedgerEntry($club, $otherMember, MemberLedgerEntry::ENTRY_TYPE_DEBIT, MemberLedgerEntry::SOURCE_MONTHLY_DUES, 9999.00);

        $balance = app(MemberLedgerBalanceService::class)->calculateForMember($selectedMember);

        $this->assertSame('1000.00', $balance['debit_total']);
        $this->assertSame('300.00', $balance['credit_total']);
        $this->assertSame('700.00', $balance['balance']);
        $this->assertSame('amount_due', $balance['balance_type']);
    }

    public function test_3c_does_not_add_routes_or_payment_workflows(): void
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
                'api/v1/clubs/{club}/members/{member}/payments/{payment}/receipt',
            ], true)) // authorized 3Q payment route and 3Y receipt route
            ->values();

        foreach ($blockedRouteFragments as $fragment) {
            $this->assertFalse(
                $registeredUris->contains(fn (string $uri) => str_contains($uri, $fragment)),
                "Milestone 3C must not register route containing [{$fragment}]."
            );
        }
    }

    public function test_3c_document_records_pure_balance_calculation_boundary(): void
    {
        $path = base_path('docs/milestone_3c_member_ledger_balance_calculation_foundation.md');

        $this->assertFileExists($path);

        $content = file_get_contents($path);

        $this->assertStringContainsString('Milestone 3C implements a pure backend balance calculation foundation', $content);
        $this->assertStringContainsString('Total Debits - Total Credits = Current Balance', $content);
        $this->assertStringContainsString('Cancelled entries must be ignored.', $content);
        $this->assertStringContainsString('No API route', $content);
        $this->assertStringContainsString('No controller', $content);
        $this->assertStringContainsString('No frontend changes', $content);
        $this->assertStringContainsString('No payment recording API', $content);
        $this->assertStringContainsString('No receipt generation', $content);
        $this->assertStringContainsString('No Excel import', $content);
    }
}