<?php

namespace App\Services\Finance;

use App\Models\Member;
use App\Models\MemberLedgerEntry;

class MemberLedgerBalanceService
{
    public function calculateForMember(Member $member): array
    {
        $baseQuery = MemberLedgerEntry::query()
            ->where('club_id', $member->club_id)
            ->where('member_id', $member->id)
            ->where('status', MemberLedgerEntry::STATUS_POSTED);

        $debitTotal = (float) (clone $baseQuery)
            ->where('entry_type', MemberLedgerEntry::ENTRY_TYPE_DEBIT)
            ->sum('amount');

        $creditTotal = (float) (clone $baseQuery)
            ->where('entry_type', MemberLedgerEntry::ENTRY_TYPE_CREDIT)
            ->sum('amount');

        $balance = round($debitTotal - $creditTotal, 2);

        return [
            'club_id' => $member->club_id,
            'member_id' => $member->id,
            'debit_total' => $this->formatAmount($debitTotal),
            'credit_total' => $this->formatAmount($creditTotal),
            'balance' => $this->formatAmount($balance),
            'balance_type' => $this->balanceType($balance),
        ];
    }

    private function balanceType(float $balance): string
    {
        if ($balance > 0) {
            return 'amount_due';
        }

        if ($balance < 0) {
            return 'advance';
        }

        return 'clear';
    }

    private function formatAmount(float $amount): string
    {
        return number_format(round($amount, 2), 2, '.', '');
    }
}