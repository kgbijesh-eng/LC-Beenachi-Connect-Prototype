<?php

namespace App\Http\Resources\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['payment.member']);

        $payment = $this->payment;
        $member = $payment?->member;

        return [
            'receipt_id' => $this->id,
            'receipt_number' => $this->receipt_number,
            'club_id' => $this->club_id,
            'member_id' => $payment?->member_id,
            'member_name' => $member?->full_name,
            'payment_id' => $this->payment_id,
            'amount' => $payment ? number_format((float) $payment->amount, 2, '.', '') : null,
            'payment_date' => $payment?->payment_date?->toDateString(),
            'payment_method' => $payment?->payment_mode,
            'receipt_status' => $this->status,
            'generated_at' => $this->created_at?->toISOString(),
            'generated_by' => $this->issued_by_user_id,
        ];
    }
}
