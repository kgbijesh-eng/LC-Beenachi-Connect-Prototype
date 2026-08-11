<?php

namespace App\Http\Resources\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClubExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $reversal = $this->whenLoaded('reversal');
        $replacement = $this->whenLoaded('correctedReplacement');
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
            'status' => $reversal && ! ($reversal instanceof \Illuminate\Http\Resources\MissingValue) ? 'reversed' : 'posted',
            'journal' => [
                'id' => $this->journalEntry->public_id, 'reference' => $this->journalEntry->reference,
                'lines' => $this->journalEntry->relationLoaded('lines') ? $this->journalEntry->lines->map(fn ($line) => ['id' => $line->public_id, 'account' => ['id' => $line->ledgerAccount->public_id, 'code' => $line->ledgerAccount->code, 'name' => $line->ledgerAccount->name], 'direction' => $line->direction, 'amount_paise' => $line->amount_paise, 'fund' => $line->fund_code])->values() : null,
            ],
            'created_by' => $this->relationLoaded('creator') ? ['name' => $this->creator?->name] : null,
            'reversal' => $reversal && ! ($reversal instanceof \Illuminate\Http\Resources\MissingValue) ? [
                'id' => $reversal->public_id, 'accounting_date' => $reversal->accounting_date->format('Y-m-d'),
                'reason' => $reversal->reason, 'journal' => ['id' => $reversal->journalEntry->public_id, 'reference' => $reversal->journalEntry->reference,
                    'lines' => $reversal->journalEntry->relationLoaded('lines') ? $reversal->journalEntry->lines->map(fn ($line) => ['id' => $line->public_id, 'account' => ['id' => $line->ledgerAccount->public_id, 'code' => $line->ledgerAccount->code, 'name' => $line->ledgerAccount->name], 'direction' => $line->direction, 'amount_paise' => $line->amount_paise, 'fund' => $line->fund_code])->values() : null],
                'created_by' => $reversal->relationLoaded('creator') ? ['name' => $reversal->creator?->name] : null,
                'created_at' => $reversal->created_at?->toISOString(),
            ] : null,
            'corrected_expense' => $replacement && ! ($replacement instanceof \Illuminate\Http\Resources\MissingValue) ? ['id' => $replacement->public_id, 'amount_paise' => $replacement->amount_paise, 'journal_reference' => $replacement->journalEntry?->reference] : null,
            'corrected_from_expense' => $this->correctedFrom?->public_id,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
