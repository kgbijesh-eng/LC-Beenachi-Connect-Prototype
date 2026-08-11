<?php

namespace App\Http\Resources\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberFinanceSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'member_id' => $this->resource['member_id'],
            'member_code' => $this->resource['member_code'],
            'member_name' => $this->resource['member_name'],
            'current_dues_amount' => $this->resource['current_dues_amount'],
            'current_dues_label' => $this->resource['current_dues_label'],
            'payment_status' => $this->resource['payment_status'],
            'last_payment_amount' => $this->resource['last_payment_amount'],
            'last_payment_date' => $this->resource['last_payment_date'],
            'receipt_available' => $this->resource['receipt_available'],
            'receipt_status' => $this->resource['receipt_status'],
            'pending_items' => $this->resource['pending_items'],
            'generated_at' => $this->resource['generated_at'],
        ];
    }
}
