<?php

namespace App\Http\Resources\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClubExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'accounting_date' => $this->accounting_date->format('Y-m-d'),
            'amount_paise' => $this->amount_paise,
            'currency' => $this->currency,
            'fund' => $this->fund_code,
            'expense_account' => ['id' => $this->expenseAccount->public_id, 'code' => $this->expenseAccount->code, 'name' => $this->expenseAccount->name],
            'payment_account' => ['id' => $this->paymentAccount->public_id, 'code' => $this->paymentAccount->code, 'name' => $this->paymentAccount->name],
            'payee' => $this->payee,
            'description' => $this->description,
            'external_reference' => $this->external_reference,
            'status' => 'posted',
            'journal' => ['id' => $this->journalEntry->public_id, 'reference' => $this->journalEntry->reference],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
