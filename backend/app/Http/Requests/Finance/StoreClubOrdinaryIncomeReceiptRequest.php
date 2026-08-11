<?php

namespace App\Http\Requests\Finance;

use App\Services\Finance\{ClubOrdinaryIncomeReceiptService, FundCode};
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClubOrdinaryIncomeReceiptRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'subtype' => ['required', Rule::in(ClubOrdinaryIncomeReceiptService::SUBTYPES)], 'accounting_date' => ['required', 'date_format:Y-m-d'],
            'receiving_account' => ['required', 'uuid'], 'income_account' => ['required', 'uuid', 'different:receiving_account'],
            'fund' => ['required', Rule::in(array_column(FundCode::cases(), 'value'))], 'amount_paise' => ['required', 'integer', 'min:1', 'max:9223372036854775807'],
            'description' => ['required', 'string', 'max:500'], 'payer_name' => ['nullable', 'string', 'max:160'], 'external_reference' => ['nullable', 'string', 'max:120'], 'event_label' => ['nullable', 'string', 'max:160'], 'idempotency_key' => ['required', 'uuid'],
            'currency' => ['prohibited'], 'club_id' => ['prohibited'], 'journal_lines' => ['prohibited'], 'debit' => ['prohibited'], 'credit' => ['prohibited'],
        ];
    }
}
