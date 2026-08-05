<?php
namespace App\Http\Requests\Finance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class StoreAccountingActivationProposalRequest extends FormRequest { public function authorize(): bool { return true; } public function rules(): array { return ['client_request_id'=>['required','uuid'],'proposal_type'=>['required',Rule::in(['charge_activation','payment_activation','reactivation','coverage_start_date_change','mapping_version_change'])],'source_type'=>['required',Rule::in(['member_charge','member_payment'])],'mapping_version'=>['required','string','max:40'],'coverage_start_date'=>['required','date_format:Y-m-d'],'activation_id'=>['required_if:proposal_type,reactivation,coverage_start_date_change,mapping_version_change','nullable','integer'],'reference'=>['required','string','max:120'],'proposer_user_id'=>['prohibited'],'status'=>['prohibited'],'checksum'=>['prohibited'],'lock_version'=>['prohibited'],'audit'=>['prohibited']]; } }
