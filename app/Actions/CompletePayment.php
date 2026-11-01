<?php

namespace App\Actions;

use App\Cart\Cart;
use App\Enums\OrderStatus;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\Outcomes\Succeeded;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;

/**
 * Fulfils a payment the provider confirmed (spec 6.1), exactly once. The
 * order was priced and snapshotted when the payment started; nothing here
 * reprices it or reads the products again.
 */
class CompletePayment
{
    public function __construct(private readonly ClosePayment $closePayment) {}

    public function handle(Payment $payment, Succeeded $outcome): Payment
    {
        return DB::transaction(function () use ($payment, $outcome): Payment {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === PaymentStatus::Succeeded) {
                return $locked;
            }

            if ($outcome->amount->cents() !== $locked->amount->cents() || $outcome->currency !== $locked->currency) {
                Log::error('The provider confirmed a payment with a different amount or currency.', [
                    'payment_id' => $locked->id,
                    'expected_cents' => $locked->amount->cents(),
                    'received_cents' => $outcome->amount->cents(),
                    'currency' => $outcome->currency,
                ]);

                return $this->closePayment->handle($locked, PaymentStatus::Failed, notify: false);
            }

            // Closed meanwhile (cancelled, failed or expired): money the provider confirms wins (spec 6.3).
            $closedMeanwhile = ! $locked->isPending();

            $locked->update([
                'status' => PaymentStatus::Succeeded,
                'provider_payment_id' => $outcome->providerPaymentId,
                'card_brand' => $outcome->cardBrand,
                'card_last4' => $outcome->cardLast4,
                'completed_at' => now(),
            ]);

            match ($locked->purpose) {
                PaymentPurpose::Order => $this->placeOrder($locked, $closedMeanwhile),
                PaymentPurpose::TopUp => throw new LogicException('Top-up payments are completed in Task 24'),
            };

            return $locked;
        });
    }

    private function placeOrder(Payment $payment, bool $closedMeanwhile): void
    {
        $order = $payment->order;

        if (! $order instanceof Order) {
            // The order was deleted meanwhile; the money is still recorded as received.
            Log::critical('A paid order payment has no order.', ['payment_id' => $payment->id]);

            return;
        }

        $order->update(['status' => OrderStatus::Placed]);

        if ($closedMeanwhile) {
            // The shopper may have filled the basket again, so it stays. Task 20 re-debits a returned wallet share.
            return;
        }

        Cart::forUser($order->user)->clear();
    }
}
