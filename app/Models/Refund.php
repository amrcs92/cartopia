<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\RefundStatus;
use App\Support\Money;
use Database\Factories\RefundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $order_id
 * @property int|null $payment_id
 * @property int $admin_id
 * @property string $reason Shown only in the panel.
 * @property Money $provider_amount The payment's amount, or zero.
 * @property Money $wallet_amount The order's wallet part, or zero.
 * @property RefundStatus $status
 * @property string|null $provider_refund_id
 * @property string|null $failure_reason
 * @property int $attempt Goes up each time a failed refund is tried again.
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'order_id', 'payment_id', 'admin_id', 'reason', 'provider_amount', 'wallet_amount', 'status',
    'provider_refund_id', 'failure_reason', 'attempt', 'completed_at',
])]
class Refund extends Model
{
    /** @use HasFactory<RefundFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider_amount' => MoneyCast::class,
            'wallet_amount' => MoneyCast::class,
            'status' => RefundStatus::class,
            'attempt' => 'integer',
            'completed_at' => 'datetime',
        ];
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

    /**
     * The Stripe Idempotency-Key or PayPal-Request-Id for this try. It stays the
     * same for a row and attempt, so a repeated call can't refund twice; the
     * creation time keeps keys apart when a reset database reuses ids.
     */
    public function idempotencyKey(): string
    {
        if ($this->created_at === null) {
            throw new LogicException("Refund {$this->id} has no creation time for an idempotency key.");
        }

        return "refund-{$this->id}-{$this->created_at->getTimestamp()}-{$this->attempt}";
    }
}
