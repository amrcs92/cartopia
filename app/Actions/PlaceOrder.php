<?php

namespace App\Actions;

use App\Cart\Cart;
use App\Cart\PricedBasket;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\WalletTransactionType;
use App\Exceptions\EmptyCart;
use App\Exceptions\InsufficientBalance;
use App\Models\Order;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Wallet\Wallet;
use Illuminate\Support\Facades\DB;

/**
 * Prices the basket from the database, debits the wallet and snapshots the
 * order items in one transaction. Nothing the client sends is trusted.
 */
class PlaceOrder
{
    public function __construct(private readonly Wallet $wallet) {}

    /**
     * @throws EmptyCart
     * @throws InsufficientBalance
     */
    public function handle(User $user, ShippingMethod $method): Order
    {
        return DB::transaction(function () use ($user, $method): Order {
            // Serialise concurrent checkouts per user: lock first, read the cart after.
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $cart = Cart::forUser($user);
            $basket = PricedBasket::of($cart, $method);
            $total = $basket->total;

            $order = Order::create([
                'user_id' => $user->id,
                'shipping_method_id' => $method->id,
                'subtotal' => $basket->subtotal(),
                'shipping_fee' => $basket->fee,
                'total' => $total,
                'status' => OrderStatus::Placed,
                'payment_method' => PaymentMethod::Wallet,
                'wallet_amount' => $total,
            ]);

            $order->items()->createMany($basket->itemAttributes());

            $this->wallet->debit($user, $total, WalletTransactionType::Purchase, "Order #{$order->id}", $order);

            $cart->clear();

            return $order->load('items');
        });
    }
}
