<?php

namespace App\Http\Resources;

use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WalletTransaction
 */
class WalletTransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'amount' => ($this->amount->isNegative() ? '' : '+').$this->amount->format(),
            'balance_after' => $this->balance_after->format(),
            'description' => $this->description,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
