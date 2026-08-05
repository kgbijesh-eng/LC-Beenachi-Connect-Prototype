<?php
namespace App\Http\Requests\Finance;
use Illuminate\Foundation\Http\FormRequest;use Illuminate\Validation\Validator;
class DecideAccountingIntegrationProposalRequest extends FormRequest { public function authorize(): bool { return true; } public function rules(): array { return ['lock_version'=>['required','integer','min:0'],'reason'=>['nullable','string','max:1000']]; } public function after():array{return[function(Validator $validator):void{if($unknown=array_diff(array_keys($this->all()),array_keys($this->rules())))$validator->errors()->add('payload','Unknown or prohibited fields: '.implode(', ',$unknown));}];} }
