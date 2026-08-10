<?php

namespace App\Http\Requests\Finance;

use App\Services\Finance\FundCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ListClubExpensesRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'expense_account' => ['nullable', 'uuid'],
            'payment_account' => ['nullable', 'uuid'],
            'fund' => ['nullable', Rule::in(array_column(FundCode::cases(), 'value'))],
            'external_reference' => ['nullable', 'string', 'max:120'],
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['posted', 'reversed'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $from = $this->date('date_from');
            $to = $this->date('date_to');
            if ($from && $to && $from->diffInDays($to) > 366) {
                $validator->errors()->add('date_to', 'The expense history date range may not exceed 366 days.');
            }
        }];
    }
}
