<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClubExpenseRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'accounting_date' => ['required', 'date_format:Y-m-d'],
            'expense_account' => ['required', 'uuid'],
            'payment_account' => ['required', 'uuid', 'different:expense_account'],
            'fund' => ['required', Rule::in(['GENERAL_CLUB_FUND', 'SERVICE_ACTIVITY_FUND', 'FELLOWSHIP_MEMBER_ACTIVITY_FUND'])],
            'amount_paise' => ['required', 'integer', 'min:1', 'max:9223372036854775807'],
            'payee' => ['nullable', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:500'],
            'external_reference' => ['nullable', 'string', 'max:120'],
            'idempotency_key' => ['required', 'uuid'],
            'corrected_from_expense' => ['nullable', 'uuid'],
            'currency' => ['prohibited'], 'journal_lines' => ['prohibited'], 'club_id' => ['prohibited'],
        ];
    }
}
