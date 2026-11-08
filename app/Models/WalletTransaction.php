<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\WalletTransactionType;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property WalletTransactionType $type
 * @property Money $amount Signed: positive credits, negative debits.
 * @property Money $balance_after
 * @property int|null $order_id
 * @property int|null $payment_id
 * @property int|null $admin_id
 * @property string|null $description
 * @property Carbon|null $created_at
 */
#[Fillable(['user_id', 'type', 'amount', 'balance_after', 'order_id', 'payment_id', 'admin_id', 'description'])]
class WalletTransaction extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => WalletTransactionType::class,
            'amount' => MoneyCast::class,
            'balance_after' => MoneyCast::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
