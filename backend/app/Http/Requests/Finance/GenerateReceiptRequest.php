<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class GenerateReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'receipt_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],

            'receipt_id' => ['prohibited'],
            'receipt_number' => ['prohibited'],
            'receipt_status' => ['prohibited'],
            'generated_by' => ['prohibited'],
            'generated_at' => ['prohibited'],
            'issued_by_user_id' => ['prohibited'],
            'club_id' => ['prohibited'],
            'member_id' => ['prohibited'],
            'payment_id' => ['prohibited'],
            'amount' => ['prohibited'],
            'payment_date' => ['prohibited'],
            'payment_method' => ['prohibited'],
            'payment_mode' => ['prohibited'],
            'status' => ['prohibited'],
            'cancelled_at' => ['prohibited'],
            'cancelled_by_user_id' => ['prohibited'],
            'cancellation_reason' => ['prohibited'],
        ];
    }
}
