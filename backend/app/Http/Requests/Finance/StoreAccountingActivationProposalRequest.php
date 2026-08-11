<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAccountingActivationProposalRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        $type=$this->input('proposal_type'); $targeted=in_array($type,['reactivation','coverage_start_date_change','mapping_version_change'],true);
        return [
            'client_request_id'=>['required','uuid'], 'proposal_type'=>['required',Rule::in(['charge_activation','payment_activation','reactivation','coverage_start_date_change','mapping_version_change'])],
            'source_type'=>['required',Rule::in(['member_charge','member_payment'])], 'mapping_version'=>['required','string','max:40'],
            'coverage_start_date'=>['required','date_format:Y-m-d'], 'activation_id'=>[$targeted?'required':'prohibited','nullable','integer'],
            'reference'=>['required','string','max:120'],
        ];
    }
    public function after(): array { return [function (Validator $validator): void { if ($unknown=array_diff(array_keys($this->all()),array_keys($this->rules()))) $validator->errors()->add('payload','Unknown or prohibited fields: '.implode(', ',$unknown)); }]; }
}
