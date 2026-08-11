<?php

namespace App\Http\Resources\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClubOrdinaryIncomeReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $account = fn ($value) => ['id' => $value->public_id, 'code' => $value->code, 'name' => $value->name];
        return ['id' => $this->public_id, 'subtype' => $this->subtype, 'accounting_date' => $this->accounting_date->format('Y-m-d'), 'amount_paise' => $this->amount_paise, 'currency' => $this->currency, 'fund' => $this->fund_code, 'receiving_account' => $account($this->receivingAccount), 'income_account' => $account($this->incomeAccount), 'description' => $this->description, 'payer_name' => $this->payer_name, 'external_reference' => $this->external_reference, 'event_label' => $this->event_label, 'journal' => ['id' => $this->journalEntry->public_id, 'reference' => $this->journalEntry->reference, 'lines' => $this->journalEntry->relationLoaded('lines') ? $this->journalEntry->lines->map(fn ($line) => ['id' => $line->public_id, 'account' => $account($line->ledgerAccount), 'direction' => $line->direction, 'amount_paise' => $line->amount_paise, 'fund' => $line->fund_code])->values() : null], 'created_by' => $this->relationLoaded('creator') ? ['name' => $this->creator?->name] : null, 'created_at' => $this->created_at?->toISOString()];
    }
}
