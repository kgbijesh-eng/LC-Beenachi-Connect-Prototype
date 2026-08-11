<?php

namespace App\Http\Resources\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $receipt = $this->receipts->first();

        return [
            'payment_id' => $this->id,
            'payment_date' => $this->payment_date?->toDateString(),
            'amount' => number_format((float) $this->amount, 2, '.', ''),
            'payment_mode' => $this->payment_mode,
            'status' => $this->status,
            'member' => [
                'id' => $this->member?->id,
                'member_code' => $this->member?->member_code,
                'name' => $this->member?->full_name,
            ],
            'receipt' => [
                'available' => $receipt !== null,
                'receipt_number' => $receipt?->receipt_number,
                'status' => $receipt?->status,
            ],
            'recorded_by' => [
                'id' => $this->receivedBy?->id,
                'name' => $this->receivedBy?->name,
            ],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
