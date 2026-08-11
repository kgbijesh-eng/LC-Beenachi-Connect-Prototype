<?php

namespace App\Http\Requests\Finance;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_date' => ['required', 'date'],
            'payment_mode' => ['required', Rule::in(Payment::MODES)],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],

            'club_id' => ['prohibited'],
            'member_id' => ['prohibited'],
            'received_by_user_id' => ['prohibited'],
            'ledger_entry_id' => ['prohibited'],
            'status' => ['prohibited'],
            'cancelled_at' => ['prohibited'],
            'cancelled_by_user_id' => ['prohibited'],
            'cancellation_reason' => ['prohibited'],
            'receipt_number' => ['prohibited'],
            'receipt_id' => ['prohibited'],
        ];
    }
}