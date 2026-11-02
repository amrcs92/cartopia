<?php

namespace App\Actions\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\RefundStatus;
use App\Enums\WalletTransactionType;
use App\Exceptions\AdminActionRefused;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Payments\Exceptions\PaymentProviderRejected;
use App\Payments\Exceptions\PaymentProviderUnavailable;
use App\Payments\Outcomes\RefundAccepted;
use App\Payments\Outcomes\RefundRefused;
use App\Payments\PaymentGateways;
use App\Wallet\Wallet;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * An admin refunds an order in full (spec 7.2): the provider's part through
 * Stripe or PayPal, the wallet part to the wallet. Opening, the provider call
 * and finishing are separate transactions, so a click repeated at any point
 * resumes where the last one stopped. Locks follow payments → orders →
 * refunds → users; the unique refunds.order_id and
 * wallet_transactions.refund_id make a double refund or credit fail loudly.
 */
class RefundOrder
{
    /** refunds.reason is TEXT, which MySQL caps at 65,535 bytes. */
    public const MAX_REASON_BYTES = 65_535;

    /** refunds.failure_reason is a 255-character string. */
    private const MAX_FAILURE_REASON_LENGTH = 255;

    public function __construct(
        private readonly PaymentGateways $gateways,
        private readonly Wallet $wallet,
    ) {}

    /**
     * @throws AdminActionRefused
     * @throws PaymentProviderUnavailable the refund stays pending, to retry with the same key
     */
    public function handle(Order $order, User $admin, string $reason): Refund
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException('A reason is required.');
        }

        if (strlen($reason) > self::MAX_REASON_BYTES) {
            throw new InvalidArgumentException('The reason is too long.');
        }

        try {
            $refund = $this->open($order, $admin, $reason);
        } catch (UniqueConstraintViolationException) {
            // Another first click created the row meanwhile; this one resumes it.
            $refund = $this->open($order, $admin, $reason);
        }

        $providerRefundId = $refund->provider_amount->cents() > 0
            ? $this->refundAtProvider($refund)
            : null;

        return $this->finish($refund, $providerRefundId);
    }

    /**
     * Step 1: creates the pending refund, reopens a failed one with the next
     * attempt, or hands back a pending one untouched (same admin, reason and
     * attempt, so the retry sends the same idempotency key).
     */
    private function open(Order $order, User $admin, string $reason): Refund
    {
        return DB::transaction(function () use ($order, $admin, $reason): Refund {
            // The order lock serialises clicks on the same order.
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $refund = Refund::query()->where('order_id', $locked->id)->lockForUpdate()->first();

            if ($refund?->status === RefundStatus::Succeeded) {
                throw new AdminActionRefused('This order is already refunded.');
            }

            if ($locked->status !== OrderStatus::Placed) {
                throw new AdminActionRefused('Only placed orders can be refunded.');
            }

            if ($refund?->status === RefundStatus::Pending) {
                return $refund;
            }

            if ($refund?->status === RefundStatus::Failed) {
                // Stripe and PayPal replay a key's refusal, so a retry needs a new attempt.
                $refund->update([
                    'status' => RefundStatus::Pending,
                    'attempt' => $refund->attempt + 1,
                    'admin_id' => $admin->id,
                    'reason' => $reason,
                    'failure_reason' => null,
                    'provider_refund_id' => null,
                ]);

                return $refund;
            }

            $providerAmount = $locked->total->subtract($locked->wallet_amount);
            $payment = null;

            if ($providerAmount->cents() > 0) {
                $payment = $locked->succeededPayment;

                if (! $payment instanceof Payment || ! $payment->amount->equals($providerAmount)) {
                    throw new LogicException("Order {$locked->id} has no succeeded payment matching its provider part.");
                }
            }

            // On MySQL the payment_id foreign key takes a shared lock on the payment
            // row here, payment after order, against the usual order. It can't
            // deadlock: rows are created only when the order has no refund yet, so no
            // other click holds this payment in steps 2 or 3, and nothing else locks a
            // succeeded payment and then waits on its order.
            return Refund::create([
                'order_id' => $locked->id,
                'payment_id' => $payment?->id,
                'admin_id' => $admin->id,
                'reason' => $reason,
                'provider_amount' => $providerAmount,
                'wallet_amount' => $locked->wallet_amount,
                'status' => RefundStatus::Pending,
                'attempt' => 1,
            ]);
        });
    }

    /**
     * Step 2: refunds the payment at its provider, under the payment's row
     * lock as the other provider calls are.
     *
     * @return string|null the provider's refund id
     *
     * @throws AdminActionRefused
     * @throws PaymentProviderUnavailable
     */
    private function refundAtProvider(Refund $refund): ?string
    {
        $payment = $refund->payment ?? throw new LogicException("Refund {$refund->id} has a provider part but no payment.");
        $provider = $this->providerName($payment->method);

        $outcome = DB::transaction(function () use ($refund, $provider): Refund|RefundRefused {
            $payment = Payment::query()->whereKey($refund->payment_id)->lockForUpdate()->firstOrFail();
            $locked = Refund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();

            // Another click got here first and finished, or was refused.
            if ($locked->status === RefundStatus::Succeeded) {
                return $locked;
            }

            if ($locked->status === RefundStatus::Failed) {
                return new RefundRefused((string) $locked->failure_reason);
            }

            // The provider took this attempt already but finishing never ran (a crash,
            // say). Its key may have expired since (Stripe keeps them 24h), so the
            // provider isn't asked again.
            if ($locked->provider_refund_id !== null) {
                return $locked;
            }

            try {
                $outcome = $this->gateways->for($payment->method)->refund($payment, $locked);
            } catch (PaymentProviderRejected $e) {
                // Bad credentials aren't a refusal: the refund stays pending, to retry with the same key.
                throw new PaymentProviderUnavailable("{$provider} couldn't be reached. Try again in a moment.", previous: $e);
            }

            if ($outcome instanceof RefundRefused) {
                $locked->update([
                    'status' => RefundStatus::Failed,
                    'failure_reason' => mb_substr($outcome->reason, 0, self::MAX_FAILURE_REASON_LENGTH),
                ]);

                return $outcome;
            }

            if (! $outcome instanceof RefundAccepted) {
                throw new LogicException('Unknown refund outcome '.$outcome::class.'.');
            }

            // Kept on the still-pending refund, so a retry can finish without the provider.
            $locked->update(['provider_refund_id' => $outcome->providerRefundId]);

            return $locked;
        });

        // Thrown after the commit, so the failed refund is kept.
        if ($outcome instanceof RefundRefused) {
            $reason = trim($outcome->reason) === '' ? 'no reason given' : $outcome->reason;

            throw new AdminActionRefused("{$provider} refused the refund: {$reason}.");
        }

        return $outcome->provider_refund_id;
    }

    /**
     * Step 3: credits the wallet part, marks the refund succeeded and the
     * order refunded, once. A second click that also got here finds the
     * refund succeeded and returns it.
     */
    private function finish(Refund $refund, ?string $providerRefundId): Refund
    {
        return DB::transaction(function () use ($refund, $providerRefundId): Refund {
            if ($refund->payment_id !== null) {
                Payment::query()->whereKey($refund->payment_id)->lockForUpdate()->firstOrFail();
            }

            $order = Order::query()->whereKey($refund->order_id)->lockForUpdate()->firstOrFail();
            $locked = Refund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === RefundStatus::Succeeded) {
                return $locked;
            }

            if ($locked->status !== RefundStatus::Pending || $order->status !== OrderStatus::Placed) {
                throw new LogicException("Refund {$locked->id} can't finish: it is {$locked->status->value} and its order {$order->status->value}.");
            }

            if ($locked->wallet_amount->cents() > 0) {
                // wallet_transactions.refund_id is unique: the database backstop against a double credit.
                $this->wallet->credit(
                    $order->user,
                    $locked->wallet_amount,
                    WalletTransactionType::Refund,
                    "Refund for order #{$order->id}",
                    order: $order,
                    admin: $locked->admin,
                    refund: $locked,
                );
            }

            $locked->update([
                'status' => RefundStatus::Succeeded,
                'provider_refund_id' => $providerRefundId,
                'completed_at' => now(),
            ]);

            $order->update(['status' => OrderStatus::Refunded]);

            // Task 15: queue OrderRefundedMail for $order->user here. It is afterCommit,
            // so it waits for this transaction, and only the click that finishes gets here.

            return $locked;
        });
    }

    private function providerName(PaymentMethod $method): string
    {
        return match ($method) {
            PaymentMethod::Stripe => 'Stripe',
            PaymentMethod::PayPal => 'PayPal',
            PaymentMethod::Wallet => throw new LogicException('A wallet payment has no provider to refund through.'),
        };
    }
}
