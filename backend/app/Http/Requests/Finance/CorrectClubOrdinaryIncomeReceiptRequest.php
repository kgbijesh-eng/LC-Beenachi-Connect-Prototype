<?php

namespace App\Http\Requests\Finance;

use App\Services\Finance\{ClubOrdinaryIncomeReceiptService, FundCode};
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CorrectClubOrdinaryIncomeReceiptRequest extends FormRequest
{
    private const NEVER_CALLER_SUPPLIED = [
        'amount_paise', 'fund', 'currency', 'club_id', 'status', 'public_id', 'request_digest', 'created_by_user_id',
        'journal', 'journal_id', 'journal_entry_id', 'journal_lines', 'lines', 'accounts', 'debit', 'credit',
        'original_receipt_id', 'original_journal_entry_id', 'reversal_journal', 'reversal_journal_id', 'reversal_journal_entry_id',
        'replacement_receipt_id', 'corrected_from_receipt', 'partial_amount_paise', 'reversal_amount_paise',
        'refund', 'refund_amount', 'refund_method', 'refund_account', 'payment_out', 'payment_method', 'payment_reference', 'payout', 'returned_cash',
        'replacement.public_id', 'replacement.currency', 'replacement.status', 'replacement.request_digest', 'replacement.idempotency_key',
        'replacement.journal', 'replacement.journal_id', 'replacement.journal_entry_id', 'replacement.journal_lines',
        'replacement.corrected_from_receipt', 'replacement.corrected_from_receipt_id', 'replacement.replacement_receipt_id',
        'replacement.refund', 'replacement.refund_amount', 'replacement.refund_method', 'replacement.refund_account',
        'replacement.payment_out', 'replacement.payment_method', 'replacement.payment_reference', 'replacement.payout', 'replacement.returned_cash',
    ];

    public function authorize(): bool
    {
        if (! $this->has('replacement') || $this->input('replacement') === null) return true;
        $club = $this->route('club');
        return $this->user() !== null && $club !== null && app(\App\Services\Access\ClubPermissionService::class)->userHasPermission($this->user(), 'finance.club.ordinary_income_receipts.create', (int) $club->id);
    }
    public function rules(): array
    {
        return [
            'accounting_date' => ['required', 'date_format:Y-m-d'], 'reason' => ['required', 'string', 'max:1000'], 'idempotency_key' => ['required', 'uuid'],
            'replacement' => ['nullable', 'array'],
            'replacement.subtype' => ['required_with:replacement', Rule::in(ClubOrdinaryIncomeReceiptService::SUBTYPES)],
            'replacement.accounting_date' => ['required_with:replacement', 'date_format:Y-m-d'],
            'replacement.receiving_account' => ['required_with:replacement', 'uuid'], 'replacement.income_account' => ['required_with:replacement', 'uuid', 'different:replacement.receiving_account'],
            'replacement.fund' => ['required_with:replacement', Rule::in(array_column(FundCode::cases(), 'value'))], 'replacement.amount_paise' => ['required_with:replacement', 'integer', 'min:1', 'max:9223372036854775807'],
            'replacement.description' => ['required_with:replacement', 'string', 'max:500'], 'replacement.payer_name' => ['nullable', 'string', 'max:160'], 'replacement.external_reference' => ['nullable', 'string', 'max:120'], 'replacement.event_label' => ['nullable', 'string', 'max:160'],
            'amount_paise' => ['prohibited'], 'journal_lines' => ['prohibited'], 'fund' => ['prohibited'],
            'refund' => ['prohibited'], 'refund_amount' => ['prohibited'], 'refund_method' => ['prohibited'], 'refund_account' => ['prohibited'], 'payment_out' => ['prohibited'], 'payment_method' => ['prohibited'], 'payment_reference' => ['prohibited'], 'payout' => ['prohibited'], 'returned_cash' => ['prohibited'],
            'replacement.refund' => ['prohibited'], 'replacement.refund_amount' => ['prohibited'], 'replacement.refund_method' => ['prohibited'], 'replacement.refund_account' => ['prohibited'], 'replacement.payment_out' => ['prohibited'], 'replacement.payment_method' => ['prohibited'], 'replacement.payment_reference' => ['prohibited'], 'replacement.payout' => ['prohibited'], 'replacement.returned_cash' => ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (self::NEVER_CALLER_SUPPLIED as $field) {
                if ($this->exists($field)) $validator->errors()->add($field, 'This server-owned field must not be provided.');
            }
        }];
    }
}
