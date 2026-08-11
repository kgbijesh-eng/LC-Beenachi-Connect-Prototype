<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Member;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberMasterFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_be_created_for_a_club_with_basic_identity_fields(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();
        $user = User::where('email', 'president@lcbeenachi.test')->firstOrFail();

        $member = Member::create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'member_code' => 'LCB-001',
            'full_name' => 'LC Beenachi President',
            'phone' => '9999999999',
            'email' => 'president@lcbeenachi.test',
            'status' => 'active',
            'joined_at' => '2026-07-01',
            'blood_group' => 'O+',
            'blood_donor_consent' => true,
        ]);

        $this->assertSame($club->id, $member->club->id);
        $this->assertSame($user->id, $member->user->id);
        $this->assertSame('LCB-001', $member->member_code);
        $this->assertSame('LC Beenachi President', $member->full_name);
        $this->assertSame('active', $member->status);
        $this->assertSame('O+', $member->blood_group);
        $this->assertTrue($member->blood_donor_consent);
        $this->assertTrue($club->members->contains($member));
        $this->assertTrue($user->members->contains($member));
    }

    public function test_member_defaults_are_safe_for_new_records(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();

        $member = Member::create([
            'club_id' => $club->id,
            'member_code' => 'LCB-002',
            'full_name' => 'Normal Member',
        ]);

        $member->refresh();

        $this->assertSame('active', $member->status);
        $this->assertFalse($member->blood_donor_consent);
        $this->assertNull($member->user_id);
        $this->assertNull($member->email);
        $this->assertNull($member->blood_group);
    }

    public function test_member_code_must_be_unique_within_same_club(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();

        Member::create([
            'club_id' => $club->id,
            'member_code' => 'LCB-003',
            'full_name' => 'First Member',
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        Member::create([
            'club_id' => $club->id,
            'member_code' => 'LCB-003',
            'full_name' => 'Duplicate Member',
        ]);
    }

    public function test_same_member_code_can_exist_in_different_clubs(): void
    {
        $this->seed(DatabaseSeeder::class);

        $club = Club::where('code', 'LCB')->firstOrFail();

        $secondClub = Club::create([
            'name' => 'Another Lions Club',
            'code' => 'ALC',
            'district' => '318E',
        ]);

        Member::create([
            'club_id' => $club->id,
            'member_code' => 'MEM-001',
            'full_name' => 'Beenachi Member',
        ]);

        Member::create([
            'club_id' => $secondClub->id,
            'member_code' => 'MEM-001',
            'full_name' => 'Another Club Member',
        ]);

        $this->assertDatabaseCount('members', 2);
    }
}
