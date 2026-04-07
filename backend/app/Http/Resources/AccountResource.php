<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'account_id' => $this->account_id,
            'name'       => $this->name,
            'created_at' => $this->created_at->toIso8601String(),
        ];

        if (isset($this->balance)) {
            $data['balance'] = $this->balance;
        }

        return $data;
    }
}
