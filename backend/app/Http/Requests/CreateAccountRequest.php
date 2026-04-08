<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Validate and canonicalize account creation input.
 */
class CreateAccountRequest extends FormRequest
{
    /**
     * This exercise does not include authentication, so all requests are allowed.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return the validation rules for account creation.
     */
    public function rules(): array
    {
        return [
            'account_id' => [
                'bail',
                'required',
                'string',
                'max:10',
                'regex:/^[A-Z0-9_-]+$/',
            ],
            'name' => ['bail', 'nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Normalize the payload before validation so the API stores a canonical ID.
     */
    protected function prepareForValidation(): void
    {
        $accountId = $this->input('account_id');
        $name = $this->input('name');

        $this->merge([
            'account_id' => is_string($accountId)
                ? Str::upper(trim($accountId))
                : $accountId,
            'name' => is_string($name)
                ? (trim($name) !== '' ? trim($name) : null)
                : $name,
        ]);
    }

    /**
     * Return the custom validation messages for client-facing errors.
     */
    public function messages(): array
    {
        return [
            'account_id.required' => 'Account ID is required.',
            'account_id.regex' => 'Account ID may only contain uppercase letters, numbers, hyphens, and underscores.',
            'account_id.max' => 'Account ID must not exceed 10 characters.',
            'name.max' => 'Name must not exceed 100 characters.',
        ];
    }
}
