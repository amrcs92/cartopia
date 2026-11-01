<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\PaymentMethod;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Support\Money;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $user_id
 * @property PaymentPurpose $purpose
 * @property int|null $order_id
 * @property PaymentMethod $method
 * @property PaymentStatus $status
 * @property Money $amount The hosted part only, fixed when the shopper clicks.
 * @property string $currency
 * @property string|null $provider_reference
 * @property string|null $provider_payment_id
 * @property string|null $card_brand
 * @property string|null $card_last4
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id', 'purpose', 'order_id', 'method', 'status', 'amount', 'currency',
    'provider_reference', 'provider_payment_id', 'card_brand', 'card_last4', 'completed_at',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => PaymentPurpose::class,
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'amount' => MoneyCast::class,
            'completed_at' => 'datetime',
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
     * The Stripe Idempotency-Key or PayPal-Request-Id for one step (start,
     * cancel or capture). The creation time keeps keys apart when a reset
     * database hands out the same ids again.
     */
    public function idempotencyKey(string $step): string
    {
        if ($this->created_at === null) {
            throw new LogicException("Payment {$this->id} has no creation time for an idempotency key.");
        }

        return "payment-{$this->id}-{$this->created_at->getTimestamp()}-{$step}";
    }

    public function isPending(): bool
    {
        return $this->status === PaymentStatus::Pending;
    }

    /**
     * @param  Builder<Payment>  $query
     * @return Builder<Payment>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::Pending);
    }
}
