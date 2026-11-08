<?php

namespace App\Actions;

use App\Cart\Cart;
use App\Cart\PricedBasket;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Enums\WalletTransactionType;
use App\Exceptions\EmptyCart;
use App\Exceptions\UnavailableProduct;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Payments\Exceptions\AmountTooSmall;
use App\Payments\Exceptions\PaymentProviderRejected;
use App\Payments\Exceptions\PaymentProviderUnavailable;
use App\Payments\PaymentGateways;
use App\Payments\PaymentLine;
use App\Payments\PaymentRequest;
use App\Payments\SplitPlan;
use App\Payments\StartedPayment;
use App\Wallet\Wallet;
use Illuminate\Support\Facades\DB;

/**
 * Prices the basket the way PlaceOrder does (PricedBasket), works out the
 * split with the wallet (spec 5), then creates an unpaid order, holds the
 * wallet share and opens a pending payment for the rest with the provider,
 * all in one transaction: if anything throws, nothing is kept, the wallet
 * debit included. The basket is left alone.
 *
 * Lock order: this holds the user row and only creates a payment. It must
 * never lock an existing payment row, because the payment actions lock the
 * payment first and the user (through Wallet) second.
 */
class StartOrderPayment
{
    public function __construct(
        private readonly PaymentGateways $gateways,
        private readonly Wallet $wallet,
    ) {}

    /**
     * @throws EmptyCart
     * @throws UnavailableProduct
     * @throws AmountTooSmall
     * @throws PaymentProviderUnavailable
     * @throws PaymentProviderRejected
     */
    public function handle(User $user, ShippingMethod $method, PaymentMethod $paymentMethod, bool $useWallet = false): StartedPayment
    {
        return DB::transaction(function () use ($user, $method, $paymentMethod, $useWallet): StartedPayment {
            // Serialise concurrent checkouts per user: lock first, read the cart and the balance after.
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $basket = PricedBasket::of(Cart::forUser($user), $method);
            $total = $basket->total;

            // Worked out from the locked balance, never from the client's numbers.
            $plan = SplitPlan::for($total, $lockedUser->balance, $useWallet);

            if ($plan->hosted->cents() < PaymentGateways::MIN_CENTS) {
                throw new AmountTooSmall('The hosted part is below the minimum Stripe or PayPal payment.');
            }

            $order = Order::create([
                'user_id' => $user->id,
                'shipping_method_id' => $method->id,
                'subtotal' => $basket->subtotal(),
                'shipping_fee' => $basket->fee,
                'total' => $total,
                'status' => OrderStatus::AwaitingPayment,
                'payment_method' => $paymentMethod,
                'wallet_amount' => $plan->wallet,
            ]);

            $items = $order->items()->createMany($basket->itemAttributes());

            if ($plan->isSplit()) {
                // The share is held from here; ClosePayment returns it if the payment never succeeds.
                // It can't overdraw: the plan never takes more than the locked balance.
                $this->wallet->debit($user, $plan->wallet, WalletTransactionType::Purchase, "Order #{$order->id}", $order);
            }

            $payment = Payment::create([
                'user_id' => $user->id,
                'purpose' => PaymentPurpose::Order,
                'order_id' => $order->id,
                'method' => $paymentMethod,
                'status' => PaymentStatus::Pending,
                'amount' => $plan->hosted,
                'currency' => 'usd',
            ]);

            $returnUrl = route('payments.return', $payment);
            $cancelUrl = route('payments.cancel', $payment);

            $request = $plan->isSplit()
                ? PaymentRequest::single(
                    "Cartopia order #{$order->id} (after {$plan->wallet->format()} from your wallet)",
                    $plan->hosted,
                    "Cartopia order #{$order->id}",
                    $returnUrl,
                    $cancelUrl,
                )
                : new PaymentRequest(
                    array_values($items->map(fn (OrderItem $item): PaymentLine => new PaymentLine($item->product_name, $item->unit_price, $item->quantity))->all()),
                    $order->shipping_fee,
                    "Cartopia order #{$order->id}",
                    $returnUrl,
                    $cancelUrl,
                );

            // Called inside the transaction, so a provider error rolls everything back.
            $started = $this->gateways->for($paymentMethod)->start($payment, $request);

            $payment->update(['provider_reference' => $started->reference]);

            return $started;
        });
    }
}
