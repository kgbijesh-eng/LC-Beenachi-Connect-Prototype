<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAccountingRevocationProposalRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        $mapping=$this->input('proposal_type')==='mapping_revocation';
        return [
            'client_request_id'=>['required','uuid'], 'proposal_type'=>['required',Rule::in(['mapping_revocation','activation_revocation'])],
            'source_type'=>['required',Rule::in(['member_charge','member_payment'])],
            'mapping_id'=>[$mapping?'required':'prohibited','nullable','integer'], 'activation_id'=>[$mapping?'prohibited':'required','nullable','integer'],
            'reference'=>['required','string','max:120'], 'reason'=>['required','string','max:1000'],
        ];
    }
    public function after(): array { return [function (Validator $validator): void { if ($unknown=array_diff(array_keys($this->all()),array_keys($this->rules()))) $validator->errors()->add('payload','Unknown or prohibited fields: '.implode(', ',$unknown)); }]; }
}
