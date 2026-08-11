<?php

namespace Database\Factories;

use App\Models\Club;
use App\Models\Member;
use App\Models\MemberLedgerEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MemberLedgerEntry>
 */
class MemberLedgerEntryFactory extends Factory
{
    protected $model = MemberLedgerEntry::class;

    public function definition(): array
    {
        return [
            'club_id' => fn () => Club::query()->value('id'),
            'member_id' => fn (array $attributes) => Member::query()
                ->when($attributes['club_id'] ?? null, fn ($query, $clubId) => $query->where('club_id', $clubId))
                ->value('id'),
            'created_by_user_id' => fn () => User::query()->value('id'),
            'entry_type' => MemberLedgerEntry::ENTRY_TYPE_DEBIT,
            'source_type' => MemberLedgerEntry::SOURCE_MONTHLY_DUES,
            'amount' => 1000.00,
            'accounting_month' => now()->format('Y-m'),
            'due_date' => now()->toDateString(),
            'transaction_date' => null,
            'reference_number' => null,
            'description' => 'Member ledger foundation entry',
            'metadata' => [],
            'status' => MemberLedgerEntry::STATUS_POSTED,
        ];
    }

    public function debit(): static
    {
        return $this->state(fn () => [
            'entry_type' => MemberLedgerEntry::ENTRY_TYPE_DEBIT,
            'source_type' => MemberLedgerEntry::SOURCE_MONTHLY_DUES,
        ]);
    }

    public function credit(): static
    {
        return $this->state(fn () => [
            'entry_type' => MemberLedgerEntry::ENTRY_TYPE_CREDIT,
            'source_type' => MemberLedgerEntry::SOURCE_PAYMENT,
            'due_date' => null,
            'transaction_date' => now()->toDateString(),
        ]);
    }
}