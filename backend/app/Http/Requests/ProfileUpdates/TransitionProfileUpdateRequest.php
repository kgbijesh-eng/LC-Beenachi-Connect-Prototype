<?php
namespace App\Http\Requests\ProfileUpdates;
use Illuminate\Foundation\Http\FormRequest;
class TransitionProfileUpdateRequest extends FormRequest{public function authorize():bool{return true;}public function rules():array{return ['lock_version'=>['required','integer','min:1']];}}
