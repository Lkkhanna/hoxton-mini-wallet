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
            'amount'         => number_format((float) $this->amount, 2, '.', ''),
            'counterparty'   => $this->counterparty_account_id,
            'description'    => $this->description,
            'timestamp'      => $this->created_at->toIso8601String(),
        ];
    }
}
