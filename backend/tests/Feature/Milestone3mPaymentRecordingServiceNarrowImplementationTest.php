<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubMembership;
use App\Models\Member;
use App\Models\MemberLedgerEntry;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\PaymentRecordingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Tests\Support\FinancialPostgresTestCase;

class Milestone3mPaymentRecordingServiceNarrowImplementationTest extends FinancialPostgresTestCase
{

    public function test_milestone_3m_document_records_narrow_implementation_boundary(): void
    {
        $path = base_path('docs/milestone_3m_payment_recording_service_narrow_implementation.md');

        $this->assertFileExists($path);

        $content = file_get_contents($path);

        $this->assertStringContainsString('PaymentRecordingService', $content);
        $this->assertStringContainsString('Transaction-safe payment creation', $content);
        $this->assertStringContainsString('Transaction-safe member ledger credit creation', $content);
        $this->assertStringContainsString('payments.ledger_entry_id', $content);
        $this->assertStringContainsString('No route', $content);
        $this->assertStringContainsString('No controller', $content);
        $this->assertStringContainsString('No receipt generation', $content);
        $this->assertStringContainsString('No Excel import', $content);
    }

    public function test_service_records_posted_payment_creates_posted_ledger_credit_and_links_them(): void
    {
        $club = $this->createClub();
        $member = $this->createMember($club);
        $receivedByUser = $this->createUserWithPaymentRecordingPermission($club);

        $payment = app(PaymentRecordingService::class)->record($receivedByUser, $member, [
            'amount' => '500',
            'payment_date' => '2026-06-08',
            'payment_mode' => 'upi',
            'reference_number' => 'UPI-3M-001',
            'notes' => 'June dues payment',
        ]);

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertSame($club->id, $payment->club_id);
        $this->assertSame($member->id, $payment->member_id);
        $this->assertSame('500.00', $payment->amount);
        $this->assertSame('2026-06-08', $payment->payment_date->toDateString());
        $this->assertSame('upi', $payment->payment_mode);
        $this->assertSame('UPI-3M-001', $payment->reference_number);
        $this->assertSame('posted', $payment->status);
        $this->assertSame($receivedByUser->id, $payment->received_by_user_id);
        $this->assertNotNull($payment->ledger_entry_id);

        $ledgerEntry = $payment->ledgerEntry;

        $this->assertInstanceOf(MemberLedgerEntry::class, $ledgerEntry);
        $this->assertSame($club->id, $ledgerEntry->club_id);
        $this->assertSame($member->id, $ledgerEntry->member_id);
        $this->assertSame($receivedByUser->id, $ledgerEntry->created_by_user_id);
        $this->assertSame(MemberLedgerEntry::ENTRY_TYPE_CREDIT, $ledgerEntry->entry_type);
        $this->assertSame(MemberLedgerEntry::SOURCE_PAYMENT, $ledgerEntry->source_type);
        $this->assertSame('500.00', $ledgerEntry->amount);
        $this->assertSame('2026-06-08', $ledgerEntry->transaction_date->toDateString());
        $this->assertSame('UPI-3M-001', $ledgerEntry->reference_number);
        $this->assertSame(MemberLedgerEntry::STATUS_POSTED, $ledgerEntry->status);
        $this->assertSame($payment->id, $ledgerEntry->metadata['payment_id']);
        $this->assertSame('upi', $ledgerEntry->metadata['payment_mode']);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'ledger_entry_id' => $ledgerEntry->id,
            'status' => 'posted',
        ]);

        $this->assertDatabaseHas('member_ledger_entries', [
            'id' => $ledgerEntry->id,
            'entry_type' => MemberLedgerEntry::ENTRY_TYPE_CREDIT,
            'source_type' => MemberLedgerEntry::SOURCE_PAYMENT,
            'status' => MemberLedgerEntry::STATUS_POSTED,
        ]);
    }

    public function test_service_rejects_user_without_payment_recording_permission(): void
    {
        $club = $this->createClub();
        $member = $this->createMember($club);
        $userWithoutPermission = $this->createUserWithClubMembershipButNoPaymentPermission($club);

        try {
            app(PaymentRecordingService::class)->record($userWithoutPermission, $member, [
                'amount' => '250',
                'payment_date' => '2026-06-08',
                'payment_mode' => 'cash',
            ]);

            $this->fail('The service should reject users without the payments.record permission.');
        } catch (AuthorizationException $exception) {
            $this->assertStringContainsString('permission', $exception->getMessage());
        }

        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(0, MemberLedgerEntry::query()->count());
    }

    public function test_service_rejects_cross_club_payment_recording(): void
    {
        $authorizedClub = $this->createClub('LCB', 'Lions Club Beenachi');
        $otherClub = $this->createClub('OTHER', 'Other Club');

        $receivedByUser = $this->createUserWithPaymentRecordingPermission($authorizedClub);
        $otherClubMember = $this->createMember($otherClub, 'MEM-OTHER-001');

        try {
            app(PaymentRecordingService::class)->record($receivedByUser, $otherClubMember, [
                'amount' => '300',
                'payment_date' => '2026-06-08',
                'payment_mode' => 'cash',
            ]);

            $this->fail('The service should reject cross-club payment recording.');
        } catch (AuthorizationException $exception) {
            $this->assertStringContainsString('access', $exception->getMessage());
        }

        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(0, MemberLedgerEntry::query()->count());
    }

    public function test_service_validates_amount_payment_date_and_payment_mode(): void
    {
        $club = $this->createClub();
        $member = $this->createMember($club);
        $receivedByUser = $this->createUserWithPaymentRecordingPermission($club);

        try {
            app(PaymentRecordingService::class)->record($receivedByUser, $member, [
                'amount' => '0',
                'payment_date' => 'not-a-date',
                'payment_mode' => 'crypto',
            ]);

            $this->fail('The service should reject invalid payment attributes.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
            $this->assertArrayHasKey('payment_date', $exception->errors());
            $this->assertArrayHasKey('payment_mode', $exception->errors());
        }

        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(0, MemberLedgerEntry::query()->count());
    }

    private function createClub(string $code = 'LCB', string $name = 'Lions Club Beenachi'): Club
    {
        $club = Club::query()->create([
            'name' => $name,
            'code' => $code,
            'district' => '318E',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);
        config(['instance.organisation_code' => $club->code]);
        return $club;
    }

    private function createMember(Club $club, string $memberCode = 'MEM-3M-001'): Member
    {
        return Member::query()->create([
            'club_id' => $club->id,
            'user_id' => null,
            'member_code' => $memberCode,
            'full_name' => 'Lion Test Member',
            'phone' => '9999999999',
            'email' => 'member-'.uniqid().'@lcbeenachi.test',
            'status' => 'active',
            'joined_at' => '2026-06-01',
            'blood_group' => 'O+',
            'blood_donor_consent' => true,
        ]);
    }

    private function createUserWithPaymentRecordingPermission(Club $club): User
    {
        $permission = Permission::query()->firstOrCreate(
            ['slug' => PaymentRecordingService::PERMISSION_RECORD_PAYMENT],
            [
                'name' => 'Record Payments',
                'group' => 'finance',
                'description' => 'Allows recording member payments.',
            ]
        );

        $role = Role::query()->create([
            'name' => 'Treasurer',
            'slug' => 'treasurer-'.uniqid(),
            'description' => 'Treasurer role for payment recording tests.',
            'is_system' => true,
        ]);

        $role->permissions()->attach($permission);

        $user = User::query()->create([
            'name' => 'Treasurer User',
            'email' => 'treasurer-'.uniqid().'@lcbeenachi.test',
            'password' => 'password',
        ]);

        ClubMembership::query()->create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return $user;
    }

    private function createUserWithClubMembershipButNoPaymentPermission(Club $club): User
    {
        $role = Role::query()->create([
            'name' => 'Member Viewer',
            'slug' => 'member-viewer-'.uniqid(),
            'description' => 'Role without payment recording permission.',
            'is_system' => true,
        ]);

        $user = User::query()->create([
            'name' => 'Viewer User',
            'email' => 'viewer-'.uniqid().'@lcbeenachi.test',
            'password' => 'password',
        ]);

        ClubMembership::query()->create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return $user;
    }
}
