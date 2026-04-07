<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => [
                'bail',
                'required',
                'string',
                'max:50',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('accounts', 'account_id'),
            ],
            'name' => ['bail', 'nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'account_id.required' => 'Account ID is required.',
            'account_id.unique'   => 'An account with this ID already exists.',
            'account_id.regex'    => 'Account ID may only contain letters, numbers, hyphens, and underscores.',
            'account_id.max'      => 'Account ID must not exceed 50 characters.',
        ];
    }
}
