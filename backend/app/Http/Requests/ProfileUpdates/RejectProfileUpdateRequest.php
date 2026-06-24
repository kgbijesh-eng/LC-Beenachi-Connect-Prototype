<?php
namespace App\Http\Requests\ProfileUpdates;
class RejectProfileUpdateRequest extends TransitionProfileUpdateRequest{public function rules():array{return [...parent::rules(),'reason'=>['required','string','min:3','max:500']];}}
