<?php

namespace App\Http\Resources\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'club_id' => $this->club_id,
            'member_id' => $this->member_id,
            'amount' => number_format((float) $this->amount, 2, '.', ''),
            'payment_date' => $this->payment_date?->toDateString(),
            'payment_mode' => $this->payment_mode,
            'reference_number' => $this->reference_number,
            'notes' => $this->notes,
            'status' => $this->status,
            'ledger_entry_id' => $this->ledger_entry_id,
            'received_by_user_id' => $this->received_by_user_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}