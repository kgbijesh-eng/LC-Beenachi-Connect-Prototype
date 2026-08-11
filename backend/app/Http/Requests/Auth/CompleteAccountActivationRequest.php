<?php

namespace App\Http\Requests\Auth;

use App\Support\Auth\OperationalPassword;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CompleteAccountActivationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'min:40', 'max:512'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'confirmed', OperationalPassword::rule()],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Account activation could not be completed.',
        ], 422));
    }
}
