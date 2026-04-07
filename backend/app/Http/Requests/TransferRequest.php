<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransferRequest extends FormRequest
{
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
                'exists:accounts,account_id',
            ],
            'to_account_id' => [
                'bail',
                'required',
                'string',
                'exists:accounts,account_id',
                'different:from_account_id',
            ],
            'amount' => [
                'bail',
                'required',
                'numeric',
                'decimal:0,2',
                'gt:0',        // Must be strictly greater than zero
                'max:999999999999.99', // Prevent overflow
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'transaction_id.required'    => 'A unique transaction ID is required for idempotency.',
            'from_account_id.required'   => 'Source account is required.',
            'from_account_id.exists'     => 'Source account does not exist.',
            'to_account_id.required'     => 'Destination account is required.',
            'to_account_id.exists'       => 'Destination account does not exist.',
            'to_account_id.different'    => 'Cannot transfer to the same account.',
            'amount.required'            => 'Transfer amount is required.',
            'amount.numeric'             => 'Transfer amount must be a valid number.',
            'amount.decimal'             => 'Transfer amount must have at most 2 decimal places.',
            'amount.gt'                  => 'Transfer amount must be greater than zero.',
        ];
    }
}
