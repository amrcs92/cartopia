<?php

namespace App\Actions;

use App\Cart\Cart;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Enums\WalletTransactionType;
use App\Exceptions\InsufficientBalance;
use App\Mail\OrderReceiptMail;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\Outcomes\Succeeded;
use App\Wallet\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Fulfils a payment the provider confirmed (spec 6.1), exactly once. The
 * order was priced and snapshotted when the payment started; nothing here
 * reprices it or reads the products again.
 */
class CompletePayment
{
    public function __construct(
        private readonly ClosePayment $closePayment,
        private readonly Wallet $wallet,
    ) {}

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
                PaymentPurpose::TopUp => $this->creditTopUp($locked),
            };

            return $locked;
        });
    }

    private function creditTopUp(Payment $payment): void
    {
        $provider = $payment->method === PaymentMethod::PayPal ? 'PayPal' : 'Stripe';

        // wallet_transactions.payment_id is unique: the database backstop against a double credit.
        $this->wallet->credit($payment->user, $payment->amount, WalletTransactionType::TopUp, "Top-up with {$provider}", payment: $payment);
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

        // Only reached by the settle that flips the status, so a duplicate queues nothing.
        Mail::to($order->user)->queue(new OrderReceiptMail($order));

        if ($closedMeanwhile) {
            // The shopper may have filled the basket again, so it stays.
            $this->takeReturnedShareAgain($payment, $order);

            return;
        }

        Cart::forUser($order->user)->clear();
    }

    /**
     * Closing gave a split order's wallet share back (a Release row); now the
     * order is paid after all, so the share is debited again if the balance
     * still allows. If not, the order stays placed and the admin is told.
     */
    private function takeReturnedShareAgain(Payment $payment, Order $order): void
    {
        $released = $order->walletTransactions()->where('type', WalletTransactionType::Release)->exists();

        if (! $order->isSplit() || ! $released) {
            return;
        }

        try {
            $this->wallet->debit($order->user, $order->wallet_amount, WalletTransactionType::Purchase, "Order #{$order->id}", $order);
        } catch (InsufficientBalance) {
            Log::critical('A split order was paid after its wallet share was returned, and the balance no longer covers the share.', [
                'payment_id' => $payment->id,
                'order_id' => $order->id,
                'unrecovered_cents' => $order->wallet_amount->cents(),
            ]);
        }
    }
}
