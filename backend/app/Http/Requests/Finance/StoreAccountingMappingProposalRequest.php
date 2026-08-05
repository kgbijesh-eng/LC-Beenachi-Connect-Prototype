<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAccountingMappingProposalRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        $replace = $this->input('proposal_type') === 'mapping_replace';
        return [
            'client_request_id'=>['required','uuid'], 'proposal_type'=>['required',Rule::in(['mapping_create','mapping_replace'])],
            'source_type'=>['required',Rule::in(['member_charge','member_payment'])], 'source_key'=>['required','string','max:80'],
            'ledger_account_id'=>['required','integer'], 'mapping_version'=>['required','string','max:40'],
            'effective_date'=>['required','date_format:Y-m-d'], 'expires_at'=>['nullable','date_format:Y-m-d','after_or_equal:effective_date'],
            'mapping_id'=>[$replace?'required':'prohibited','nullable','integer'], 'reference'=>['required','string','max:120'],
        ];
    }
    public function after(): array { return [fn (Validator $validator) => $this->rejectUnknown($validator)]; }
    private function rejectUnknown(Validator $validator): void { if ($unknown=array_diff(array_keys($this->all()),array_keys($this->rules()))) $validator->errors()->add('payload','Unknown or prohibited fields: '.implode(', ',$unknown)); }
}
