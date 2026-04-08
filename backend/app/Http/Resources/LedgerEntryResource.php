<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LedgerEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'transaction_id' => $this->transaction_id,
            'type'           => $this->entry_type,
            'amount'         => $this->formatDecimalString($this->amount),
            'counterparty'   => $this->counterparty_account_id,
            'description'    => $this->description,
            'timestamp'      => $this->created_at->toIso8601String(),
        ];
    }

    protected function formatDecimalString($value): string
    {
        $stringValue = trim((string) ($value ?? '0'));

        if ($stringValue === '') {
            return '0.00';
        }

        $negative = str_starts_with($stringValue, '-');
        $unsignedValue = ltrim($stringValue, '+-');

        if ($unsignedValue === '') {
            return '0.00';
        }

        [$wholePart, $fractionPart] = array_pad(explode('.', $unsignedValue, 2), 2, '');

        $wholePart = preg_replace('/\D/', '', $wholePart ?? '') ?? '';
        $fractionPart = preg_replace('/\D/', '', $fractionPart ?? '') ?? '';

        $wholePart = ltrim($wholePart, '0');
        $wholePart = $wholePart === '' ? '0' : $wholePart;
        $fractionPart = str_pad(substr($fractionPart, 0, 2), 2, '0');

        return ($negative ? '-' : '') . $wholePart . '.' . $fractionPart;
    }
}
