<?php

namespace App\Actions;

use App\Cart\Cart;
use App\Cart\CartLine;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Exceptions\EmptyCart;
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
use App\Payments\StartedPayment;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Prices the basket like PlaceOrder, then creates an unpaid order and a
 * pending payment and opens it with the provider, all in one transaction:
 * if the provider refuses, nothing is kept. The basket is left alone.
 */
class StartOrderPayment
{
    public function __construct(private readonly PaymentGateways $gateways) {}

    /**
     * @throws EmptyCart
     * @throws AmountTooSmall
     * @throws PaymentProviderUnavailable
     * @throws PaymentProviderRejected
     */
    public function handle(User $user, ShippingMethod $method, PaymentMethod $paymentMethod, bool $useWallet = false): StartedPayment
    {
        // $useWallet (a split with the wallet) is ignored until Task 20.
        return DB::transaction(function () use ($user, $method, $paymentMethod): StartedPayment {
            // Serialise concurrent checkouts per user: lock first, read the cart after.
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $summary = Cart::forUser($user)->summary();

            if ($summary->isEmpty()) {
                throw new EmptyCart('Your basket is empty.');
            }

            $fee = $method->fee;
            $total = $summary->subtotal->add($fee);

            if ($total->cents() < PaymentGateways::MIN_CENTS) {
                throw new AmountTooSmall('The total is below the minimum card or PayPal payment.');
            }

            $order = Order::create([
                'user_id' => $user->id,
                'shipping_method_id' => $method->id,
                'subtotal' => $summary->subtotal,
                'shipping_fee' => $fee,
                'total' => $total,
                'status' => OrderStatus::AwaitingPayment,
                'payment_method' => $paymentMethod,
                'wallet_amount' => Money::zero(),
            ]);

            $items = $order->items()->createMany(array_map(fn (CartLine $line): array => [
                'product_id' => $line->product->id,
                'product_name' => $line->product->name,
                'unit_price' => $line->unitPrice,
                'quantity' => $line->quantity,
                'line_total' => $line->lineTotal,
            ], $summary->lines));

            $payment = Payment::create([
                'user_id' => $user->id,
                'purpose' => PaymentPurpose::Order,
                'order_id' => $order->id,
                'method' => $paymentMethod,
                'status' => PaymentStatus::Pending,
                'amount' => $total,
                'currency' => 'usd',
            ]);

            // Task 11 switches these to the payments.return and payments.cancel routes.
            $request = new PaymentRequest(
                array_values($items->map(fn (OrderItem $item): PaymentLine => new PaymentLine($item->product_name, $item->unit_price, $item->quantity))->all()),
                $order->shipping_fee,
                "Cartopia order #{$order->id}",
                url("/payments/{$payment->id}/return"),
                url("/payments/{$payment->id}/cancel"),
            );

            // Called inside the transaction, so a provider error rolls everything back.
            $started = $this->gateways->for($paymentMethod)->start($payment, $request);

            $payment->update(['provider_reference' => $started->reference]);

            return $started;
        });
    }
}
