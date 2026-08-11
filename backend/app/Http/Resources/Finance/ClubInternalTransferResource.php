<?php
namespace App\Http\Resources\Finance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class ClubInternalTransferResource extends JsonResource
{
    public function toArray(Request $request): array { return ['id' => $this->public_id, 'accounting_date' => $this->accounting_date->format('Y-m-d'), 'amount_paise' => $this->amount_paise, 'currency' => $this->currency, 'fund' => $this->fund_code, 'source_account' => ['id' => $this->sourceAccount->public_id, 'code' => $this->sourceAccount->code, 'name' => $this->sourceAccount->name], 'destination_account' => ['id' => $this->destinationAccount->public_id, 'code' => $this->destinationAccount->code, 'name' => $this->destinationAccount->name], 'description' => $this->description, 'external_reference' => $this->external_reference, 'journal' => ['id' => $this->journalEntry->public_id, 'reference' => $this->journalEntry->reference, 'lines' => $this->journalEntry->relationLoaded('lines') ? $this->journalEntry->lines->map(fn ($line) => ['id' => $line->public_id, 'account' => ['id' => $line->ledgerAccount->public_id, 'code' => $line->ledgerAccount->code, 'name' => $line->ledgerAccount->name], 'direction' => $line->direction, 'amount_paise' => $line->amount_paise, 'fund' => $line->fund_code])->values() : null], 'created_by' => $this->relationLoaded('creator') ? ['name' => $this->creator?->name] : null, 'created_at' => $this->created_at?->toISOString()]; }
}
