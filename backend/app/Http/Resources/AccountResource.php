<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Standard API representation for an account resource.
 */
class AccountResource extends JsonResource
{
    /**
     * Transform the resource into an API-friendly array.
     */
    public function toArray(Request $request): array
    {
        return [
            'account_id' => $this->account_id,
            'name' => $this->name,
            'balance' => $this->balance ?? '0.00',
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
