<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class TransferRequest extends FormRequest
{
    private const MAX_TRANSFER_AMOUNT = '10000000';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transaction_id' => [
                'bail',
                'required',
                'string',
                'max:100',
            ],
            'from_account_id' => [
                'bail',
                'required',
                'string',
                'min:3',
                'max:10',
                'regex:/^[A-Z0-9_-]+$/',
                'exists:accounts,account_id',
            ],
            'to_account_id' => [
                'bail',
                'required',
                'string',
                'min:3',
                'max:10',
                'regex:/^[A-Z0-9_-]+$/',
                'exists:accounts,account_id',
                'different:from_account_id',
            ],
            'amount' => [
                'bail',
                'required',
                'numeric',
                'decimal:0,2',
                'gt:0',        // Must be strictly greater than zero
                'max:' . self::MAX_TRANSFER_AMOUNT,
            ],
        ];
    }

    /**
     * Normalize transfer identifiers before validation so the API treats
     * account IDs consistently across endpoints.
     */
    protected function prepareForValidation(): void
    {
        $transactionId = $this->input('transaction_id');
        $fromAccountId = $this->input('from_account_id');
        $toAccountId = $this->input('to_account_id');

        $this->merge([
            'transaction_id' => is_string($transactionId)
                ? trim($transactionId)
                : $transactionId,
            'from_account_id' => is_string($fromAccountId)
                ? Str::upper(trim($fromAccountId))
                : $fromAccountId,
            'to_account_id' => is_string($toAccountId)
                ? Str::upper(trim($toAccountId))
                : $toAccountId,
        ]);
    }

    public function messages(): array
    {
        return [
            'transaction_id.required'    => 'A unique transaction ID is required for idempotency.',
            'from_account_id.required'   => 'Source account is required.',
            'from_account_id.min'        => 'Source account ID must be at least 3 characters.',
            'from_account_id.max'        => 'Source account ID must not exceed 10 characters.',
            'from_account_id.regex'      => 'Source account ID may only contain uppercase letters, numbers, hyphens, and underscores.',
            'from_account_id.exists'     => 'Source account does not exist.',
            'to_account_id.required'     => 'Destination account is required.',
            'to_account_id.min'          => 'Destination account ID must be at least 3 characters.',
            'to_account_id.max'          => 'Destination account ID must not exceed 10 characters.',
            'to_account_id.regex'        => 'Destination account ID may only contain uppercase letters, numbers, hyphens, and underscores.',
            'to_account_id.exists'       => 'Destination account does not exist.',
            'to_account_id.different'    => 'Cannot transfer to the same account.',
            'amount.required'            => 'Transfer amount is required.',
            'amount.numeric'             => 'Transfer amount must be a valid number.',
            'amount.decimal'             => 'Transfer amount must have at most 2 decimal places.',
            'amount.gt'                  => 'Transfer amount must be greater than zero.',
            'amount.max'                 => 'Transfer amount may not exceed 10,000,000.',
        ];
    }
}
