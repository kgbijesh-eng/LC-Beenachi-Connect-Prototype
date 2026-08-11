<?php

namespace App\Http\Resources\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClubOrdinaryIncomeReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $account = fn ($value) => ['id' => $value->public_id, 'code' => $value->code, 'name' => $value->name];
        $correction = $this->relationLoaded('correction') ? $this->correction : null;
        $replacement = $correction?->replacementReceipt;
        $line = fn ($value) => ['id' => $value->public_id, 'account' => $account($value->ledgerAccount), 'direction' => $value->direction, 'amount_paise' => $value->amount_paise, 'fund' => $value->fund_code];
        return ['id' => $this->public_id, 'subtype' => $this->subtype, 'accounting_date' => $this->accounting_date->format('Y-m-d'), 'amount_paise' => $this->amount_paise, 'currency' => $this->currency, 'fund' => $this->fund_code, 'receiving_account' => $account($this->receivingAccount), 'income_account' => $account($this->incomeAccount), 'description' => $this->description, 'payer_name' => $this->payer_name, 'external_reference' => $this->external_reference, 'event_label' => $this->event_label,
            'status' => $correction ? ($replacement ? 'replaced' : 'reversed') : 'posted',
            'journal' => ['id' => $this->journalEntry->public_id, 'reference' => $this->journalEntry->reference, 'lines' => $this->journalEntry->relationLoaded('lines') ? $this->journalEntry->lines->map($line)->values() : null],
            'created_by' => $this->relationLoaded('creator') ? ['name' => $this->creator?->name] : null, 'created_at' => $this->created_at?->toISOString(),
            'correction' => $correction ? ['id' => $correction->public_id, 'accounting_date' => $correction->accounting_date->format('Y-m-d'), 'reason' => $correction->reason,
                'reversal_journal' => ['id' => $correction->reversalJournal->public_id, 'reference' => $correction->reversalJournal->reference, 'lines' => $correction->reversalJournal->relationLoaded('lines') ? $correction->reversalJournal->lines->map($line)->values() : null],
                'created_by' => $correction->relationLoaded('creator') ? ['name' => $correction->creator?->name] : null, 'created_at' => $correction->created_at?->toISOString(),
                'replacement_receipt' => $replacement ? ['id' => $replacement->public_id, 'accounting_date' => $replacement->accounting_date->format('Y-m-d'), 'amount_paise' => $replacement->amount_paise, 'journal' => ['id' => $replacement->journalEntry->public_id, 'reference' => $replacement->journalEntry->reference]] : null,
            ] : null,
            'corrected_from_receipt' => $this->correctedFrom?->public_id,
        ];
    }
}
