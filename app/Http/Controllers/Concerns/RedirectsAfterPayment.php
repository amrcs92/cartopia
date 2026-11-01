<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\PaymentMethod;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\PaymentWording;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Throwable;

/**
 * Where the shopper lands after the provider sends them back (spec 4.4, step 4).
 */
trait RedirectsAfterPayment
{
    protected function afterPayment(Payment $payment): RedirectResponse
    {
        $succeeded = $payment->status === PaymentStatus::Succeeded;
        $order = $payment->order;

        if ($payment->purpose === PaymentPurpose::Order && $order instanceof Order) {
            if ($succeeded) {
                Inertia::flash('orderPlaced', PaymentWording::placedMessage($order));

                return to_route('orders.show', $order);
            }

            return $this->backToBasket($this->toastTypeFor($payment), $this->messageFor($payment));
        }

        Inertia::flash('toast', [
            'type' => $succeeded ? 'success' : $this->toastTypeFor($payment),
            'message' => $succeeded ? "Added {$payment->amount->format()} to your wallet." : $this->messageFor($payment),
        ]);

        return to_route('wallet.show');
    }

    /**
     * The provider could not be asked; the payment stays pending.
     */
    protected function providerUnreachable(Payment $payment, Throwable $e): RedirectResponse
    {
        // The message only: the previous exception can carry request headers.
        Log::warning("Could not reach the provider for payment {$payment->id}: {$e->getMessage()}");

        $message = "We couldn't reach {$this->providerName($payment)}. Try again in a moment.";

        if ($payment->purpose === PaymentPurpose::Order) {
            return $this->backToBasket('error', $message);
        }

        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

        return to_route('wallet.show');
    }

    private function backToBasket(string $type, string $message): RedirectResponse
    {
        Inertia::flash([
            'toast' => ['type' => $type, 'message' => $message],
            'checkoutError' => $message,
        ]);

        return to_route('cart.show');
    }

    private function messageFor(Payment $payment): string
    {
        return match ($payment->status) {
            PaymentStatus::Failed => "{$this->providerName($payment)} couldn't take the payment. Try again or pick another way to pay.",
            PaymentStatus::Cancelled, PaymentStatus::Expired => $this->cancelledMessage($payment),
            PaymentStatus::Pending => "We're still waiting for the payment to be confirmed. Check your orders in a minute.",
            PaymentStatus::Succeeded => '',
        };
    }

    /**
     * A split order's wallet share was returned when the payment closed (ClosePayment).
     */
    private function cancelledMessage(Payment $payment): string
    {
        $message = 'Payment cancelled. Nothing was charged.';
        $order = $payment->order;

        if ($payment->purpose === PaymentPurpose::Order && $order instanceof Order && $order->isSplit()) {
            return "{$message} Your {$order->wallet_amount->format()} wallet balance is back.";
        }

        return $message;
    }

    private function toastTypeFor(Payment $payment): string
    {
        return $payment->status === PaymentStatus::Failed ? 'error' : 'info';
    }

    private function providerName(Payment $payment): string
    {
        return $payment->method === PaymentMethod::PayPal ? 'PayPal' : 'Stripe';
    }
}
