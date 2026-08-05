<?php
namespace App\Http\Requests\Finance;
use Illuminate\Foundation\Http\FormRequest;
class DecideAccountingIntegrationProposalRequest extends FormRequest { public function authorize(): bool { return true; } public function rules(): array { return ['lock_version'=>['required','integer','min:0'],'reason'=>['nullable','string','max:1000'],'status'=>['prohibited'],'decision'=>['prohibited'],'approver_user_id'=>['prohibited'],'checksum'=>['prohibited'],'audit'=>['prohibited']]; } }
