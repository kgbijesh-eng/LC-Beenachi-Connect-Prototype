<?php

namespace App\Services\Finance;

use App\Models\Member;
use App\Models\MemberLedgerEntry;
use App\Models\Payment;
use App\Models\Receipt;

class MemberFinanceSummaryService
{
    /**
     * Build a read-only member-friendly finance summary from existing posted records.
     *
     * This service does not create, update, delete, allocate, reverse, cancel,
     * remind, export, report, receipt-generate, PDF-generate, or mutate ledger data.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function summaryForMember(Member $member, array $options = []): array
    {
        $includePendingItems = $this->booleanOption($options, 'include_pending_items', true);
        $includeLastPayment = $this->booleanOption($options, 'include_last_payment', true);
        $includeReceiptStatus = $this->booleanOption($options, 'include_receipt_status', true);

        $baseLedgerQuery = MemberLedgerEntry::query()
            ->where('club_id', $member->club_id)
            ->where('member_id', $member->id)
            ->where('status', MemberLedgerEntry::STATUS_POSTED);

        $amountOwed = (float) (clone $baseLedgerQuery)
            ->where('entry_type', MemberLedgerEntry::ENTRY_TYPE_DEBIT)
            ->sum('amount');

        $amountPaid = (float) (clone $baseLedgerQuery)
            ->where('entry_type', MemberLedgerEntry::ENTRY_TYPE_CREDIT)
            ->sum('amount');

        $currentDuesAmount = round($amountOwed - $amountPaid, 2);

        $lastPayment = null;

        if ($includeLastPayment || $includeReceiptStatus) {
            $lastPayment = Payment::query()
                ->where('club_id', $member->club_id)
                ->where('member_id', $member->id)
                ->where('status', 'posted')
                ->orderByDesc('payment_date')
                ->orderByDesc('id')
                ->first();
        }

        $receipt = null;

        if ($includeReceiptStatus && $lastPayment) {
            $receipt = Receipt::query()
                ->where('club_id', $member->club_id)
                ->where('payment_id', $lastPayment->id)
                ->where('status', 'issued')
                ->first();
        }

        return [
            'member_id' => $member->id,
            'member_code' => $member->member_code,
            'member_name' => $member->full_name,
            'current_dues_amount' => $this->formatAmount($currentDuesAmount),
            'current_dues_label' => $this->currentDuesLabel($currentDuesAmount),
            'payment_status' => $this->paymentStatus($currentDuesAmount, $amountPaid),
            'last_payment_amount' => $includeLastPayment && $lastPayment
                ? $this->formatAmount((float) $lastPayment->amount)
                : null,
            'last_payment_date' => $includeLastPayment && $lastPayment
                ? $lastPayment->payment_date?->toDateString()
                : null,
            'receipt_available' => $includeReceiptStatus
                ? $receipt !== null
                : null,
            'receipt_status' => $includeReceiptStatus && $receipt
                ? $receipt->status
                : null,
            'pending_items' => $includePendingItems
                ? $this->pendingItemsForMember($member, max($currentDuesAmount, 0.0))
                : [],
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pendingItemsForMember(Member $member, float $currentDuesAmount): array
    {
        if ($currentDuesAmount <= 0.0) {
            return [];
        }

        $debitEntries = MemberLedgerEntry::query()
            ->where('club_id', $member->club_id)
            ->where('member_id', $member->id)
            ->where('status', MemberLedgerEntry::STATUS_POSTED)
            ->where('entry_type', MemberLedgerEntry::ENTRY_TYPE_DEBIT)
            ->orderBy('due_date')
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        $remaining = round($currentDuesAmount, 2);
        $pendingItems = [];

        foreach ($debitEntries->reverse() as $entry) {
            if ($remaining <= 0.0) {
                break;
            }

            $entryAmount = round((float) $entry->amount, 2);
            $pendingAmount = min($entryAmount, $remaining);

            $pendingItems[] = [
                'source_type' => $entry->source_type,
                'source_label' => $this->sourceLabel((string) $entry->source_type),
                'amount' => $this->formatAmount($pendingAmount),
                'due_date' => $entry->due_date?->toDateString(),
                'status' => $pendingAmount < $entryAmount ? 'Partly Paid' : 'Pending',
            ];

            $remaining = round($remaining - $pendingAmount, 2);
        }

        return array_reverse($pendingItems);
    }

    private function currentDuesLabel(float $currentDuesAmount): string
    {
        if ($currentDuesAmount > 0.0) {
            return 'Current Dues';
        }

        if ($currentDuesAmount < 0.0) {
            return 'Advance';
        }

        return 'Paid';
    }

    private function paymentStatus(float $currentDuesAmount, float $amountPaid): string
    {
        if ($currentDuesAmount < 0.0) {
            return 'Advance';
        }

        if ($currentDuesAmount === 0.0) {
            return 'Paid';
        }

        if ($amountPaid > 0.0) {
            return 'Partly Paid';
        }

        return 'Pending';
    }

    private function sourceLabel(string $sourceType): string
    {
        return match ($sourceType) {
            MemberLedgerEntry::SOURCE_MONTHLY_DUES => 'Monthly Dues',
            MemberLedgerEntry::SOURCE_MEMBERSHIP_FEE => 'Membership Fee',
            MemberLedgerEntry::SOURCE_FELLOWSHIP => 'Fellowship',
            MemberLedgerEntry::SOURCE_PROJECT_SHARE => 'Project Share',
            MemberLedgerEntry::SOURCE_FINE => 'Fine',
            MemberLedgerEntry::SOURCE_SPECIAL_COLLECTION => 'Special Collection',
            MemberLedgerEntry::SOURCE_PAYMENT => 'Payment',
            MemberLedgerEntry::SOURCE_ADJUSTMENT => 'Adjustment',
            default => ucwords(str_replace('_', ' ', $sourceType)),
        };
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function booleanOption(array $options, string $key, bool $default): bool
    {
        if (! array_key_exists($key, $options)) {
            return $default;
        }

        return filter_var($options[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
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
