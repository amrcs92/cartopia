<?php

namespace App\Http\Resources;

use App\Enums\WalletTransactionType;
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
            // Like a purchase, a refund reads "Refund · Order #14".
            'description' => $this->type === WalletTransactionType::Refund && $this->order_id !== null
                ? "Order #{$this->order_id}"
                : $this->description,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
