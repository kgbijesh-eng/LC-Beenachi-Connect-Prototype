<?php

namespace App\Services\Finance;

use App\Models\Club;
use App\Models\Member;
use App\Models\MemberLedgerEntry;
use App\Models\Payment;
use App\Models\Receipt;

class ClubFinanceReportService
{
    /**
     * Build a read-only club finance report from existing posted finance records.
     *
     * This service does not create, update, delete, allocate, reverse, cancel,
     * remind, export, generate receipts, generate PDFs, or mutate finance data.
     *
     * @return array<string, mixed>
     */
    public function reportsForClub(Club $club): array
    {
        $postedLedgerQuery = MemberLedgerEntry::query()
            ->where('club_id', $club->id)
            ->where('status', MemberLedgerEntry::STATUS_POSTED);

        $totalDebits = (float) (clone $postedLedgerQuery)
            ->where('entry_type', MemberLedgerEntry::ENTRY_TYPE_DEBIT)
            ->sum('amount');

        $totalCredits = (float) (clone $postedLedgerQuery)
            ->where('entry_type', MemberLedgerEntry::ENTRY_TYPE_CREDIT)
            ->sum('amount');

        $currentDues = round($totalDebits - $totalCredits, 2);

        $postedPaymentsQuery = Payment::query()
            ->where('club_id', $club->id)
            ->where('status', 'posted');

        $issuedReceiptsQuery = Receipt::query()
            ->where('club_id', $club->id)
            ->where('status', 'issued');

        return [
            'club_id' => $club->id,
            'club_name' => $club->name,
            'club_finance_overview' => [
                'total_debit_amount' => $this->formatAmount($totalDebits),
                'total_credit_amount' => $this->formatAmount($totalCredits),
                'current_dues_amount' => $this->formatAmount($currentDues),
                'current_dues_label' => $this->duesLabel($currentDues),
                'posted_payment_count' => (clone $postedPaymentsQuery)->count(),
                'issued_receipt_count' => (clone $issuedReceiptsQuery)->count(),
            ],
            'outstanding_dues_summary' => $this->outstandingDuesSummary($club),
            'member_wise_dues_summary' => $this->memberWiseDuesSummary($club),
            'payment_collection_summary' => [
                'posted_payment_total' => $this->formatAmount((float) (clone $postedPaymentsQuery)->sum('amount')),
                'posted_payment_count' => (clone $postedPaymentsQuery)->count(),
            ],
            'receipt_summary' => [
                'issued_receipt_count' => (clone $issuedReceiptsQuery)->count(),
            ],
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Build a read-only balance sheet style summary from approved records.
     *
     * @return array<string, mixed>
     */
    public function balanceSheetForClub(Club $club): array
    {
        $postedLedgerQuery = MemberLedgerEntry::query()
            ->where('club_id', $club->id)
            ->where('status', MemberLedgerEntry::STATUS_POSTED);

        $totalDebits = (float) (clone $postedLedgerQuery)
            ->where('entry_type', MemberLedgerEntry::ENTRY_TYPE_DEBIT)
            ->sum('amount');

        $totalCredits = (float) (clone $postedLedgerQuery)
            ->where('entry_type', MemberLedgerEntry::ENTRY_TYPE_CREDIT)
            ->sum('amount');

        $netPosition = round($totalCredits - $totalDebits, 2);

        return [
            'club_id' => $club->id,
            'club_name' => $club->name,
            'assets' => [
                'member_receivables' => $this->formatAmount(max($totalDebits - $totalCredits, 0.0)),
                'cash_collected' => $this->formatAmount($totalCredits),
            ],
            'liabilities' => [
                'member_advances' => $this->formatAmount(max($totalCredits - $totalDebits, 0.0)),
            ],
            'net_position' => [
                'amount' => $this->formatAmount($netPosition),
                'label' => $netPosition >= 0.0 ? 'Positive Position' : 'Amount Receivable',
            ],
            'source' => 'existing_posted_ledger_entries_only',
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function outstandingDuesSummary(Club $club): array
    {
        $memberSummaries = $this->memberWiseDuesSummary($club);

        $membersWithDues = array_filter(
            $memberSummaries,
            fn (array $memberSummary): bool => (float) $memberSummary['current_dues_amount'] > 0.0
        );

        return [
            'members_with_dues_count' => count($membersWithDues),
            'total_outstanding_amount' => $this->formatAmount(array_sum(array_map(
                fn (array $memberSummary): float => max((float) $memberSummary['current_dues_amount'], 0.0),
                $memberSummaries
            ))),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function memberWiseDuesSummary(Club $club): array
    {
        return Member::query()
            ->where('club_id', $club->id)
            ->orderBy('full_name')
            ->get()
            ->map(function (Member $member): array {
                $ledgerQuery = MemberLedgerEntry::query()
                    ->where('club_id', $member->club_id)
                    ->where('member_id', $member->id)
                    ->where('status', MemberLedgerEntry::STATUS_POSTED);

                $debits = (float) (clone $ledgerQuery)
                    ->where('entry_type', MemberLedgerEntry::ENTRY_TYPE_DEBIT)
                    ->sum('amount');

                $credits = (float) (clone $ledgerQuery)
                    ->where('entry_type', MemberLedgerEntry::ENTRY_TYPE_CREDIT)
                    ->sum('amount');

                $currentDues = round($debits - $credits, 2);

                return [
                    'member_id' => $member->id,
                    'member_code' => $member->member_code,
                    'member_name' => $member->full_name,
                    'total_debit_amount' => $this->formatAmount($debits),
                    'total_credit_amount' => $this->formatAmount($credits),
                    'current_dues_amount' => $this->formatAmount($currentDues),
                    'current_dues_label' => $this->duesLabel($currentDues),
                ];
            })
            ->values()
            ->all();
    }

    private function duesLabel(float $currentDues): string
    {
        if ($currentDues > 0.0) {
            return 'Current Dues';
        }

        if ($currentDues < 0.0) {
            return 'Advance';
        }

        return 'Paid';
    }

    private function formatAmount(float $amount): string
    {
        $rounded = round($amount, 2);

        if (abs($rounded) < 0.005) {
            $rounded = 0.0;
        }

        return number_format($rounded, 2, '.', '');
    }
}
