<?php
namespace App\Http\Requests\Finance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class StoreAccountingRevocationProposalRequest extends FormRequest { public function authorize(): bool { return true; } public function rules(): array { return ['client_request_id'=>['required','uuid'],'proposal_type'=>['required',Rule::in(['mapping_revocation','activation_revocation'])],'source_type'=>['required',Rule::in(['member_charge','member_payment'])],'mapping_id'=>['required_if:proposal_type,mapping_revocation','nullable','integer'],'activation_id'=>['required_if:proposal_type,activation_revocation','nullable','integer'],'reference'=>['required','string','max:120'],'reason'=>['required','string','max:1000'],'proposer_user_id'=>['prohibited'],'status'=>['prohibited'],'checksum'=>['prohibited'],'lock_version'=>['prohibited'],'audit'=>['prohibited']]; } }
