<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class ReverseClubExpenseRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'accounting_date' => ['required', 'date_format:Y-m-d'],
            'reason' => ['required', 'string', 'max:1000'],
            'idempotency_key' => ['required', 'uuid'],
            'amount_paise' => ['prohibited'], 'account_ids' => ['prohibited'],
            'journal_lines' => ['prohibited'], 'fund' => ['prohibited'],
        ];
    }
}
