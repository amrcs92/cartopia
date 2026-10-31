<?php

namespace App\Actions;

use App\Cart\Cart;
use App\Cart\CartLine;
use App\Enums\OrderStatus;
use App\Enums\WalletTransactionType;
use App\Exceptions\EmptyCart;
use App\Exceptions\InsufficientBalance;
use App\Models\Order;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Wallet\Wallet;
use Illuminate\Support\Facades\DB;

/**
 * Prices the cart from the database, debits the wallet and snapshots the
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
            $cart = Cart::forUser($user);
            $summary = $cart->summary();

            if ($summary->isEmpty()) {
                throw new EmptyCart('Your cart is empty.');
            }

            $fee = $method->fee;
            $total = $summary->subtotal->add($fee);

            $order = Order::create([
                'user_id' => $user->id,
                'shipping_method_id' => $method->id,
                'subtotal' => $summary->subtotal,
                'shipping_fee' => $fee,
                'total' => $total,
                'status' => OrderStatus::Placed,
            ]);

            $order->items()->createMany(array_map(fn (CartLine $line): array => [
                'product_id' => $line->product->id,
                'product_name' => $line->product->name,
                'unit_price' => $line->unitPrice,
                'quantity' => $line->quantity,
                'line_total' => $line->lineTotal,
            ], $summary->lines));

            $this->wallet->debit($user, $total, WalletTransactionType::Purchase, "Order #{$order->id}", $order);

            $cart->clear();

            return $order->load('items');
        });
    }
}
