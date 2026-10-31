<?php

use App\Actions\PlaceOrder;
use App\Cart\Cart;
use App\Enums\OrderStatus;
use App\Enums\WalletTransactionType;
use App\Exceptions\EmptyCart;
use App\Exceptions\InsufficientBalance;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Support\Money;

function checkoutFixture(int $balance = 10000): array
{
    $user = User::factory()->withBalance(Money::fromCents($balance))->create();
    $beer = Product::factory()->create(['name' => 'Beer', 'price' => 200]);
    $cheese = Product::factory()->create(['name' => 'Cheese', 'price' => 374]);
    $cart = Cart::forUser($user);
    $cart->add($beer, 2);
    $cart->add($cheese, 1);

    return [$user, $beer, $cheese, ShippingMethod::factory()->ups()->create()];
}

it('places an order priced from the database', function () {
    [$user, $beer, $cheese, $ups] = checkoutFixture();

    $order = app(PlaceOrder::class)->handle($user, $ups);

    expect($order->status)->toBe(OrderStatus::Placed)
        ->and($order->subtotal->cents())->toBe(774)
        ->and($order->shipping_fee->cents())->toBe(500)
        ->and($order->total->cents())->toBe(1274)
        ->and($order->relationLoaded('items'))->toBeTrue()
        ->and($order->items)->toHaveCount(2)
        ->and($user->fresh()->balance->cents())->toBe(8726);

    $ledger = $user->walletTransactions()->where('type', WalletTransactionType::Purchase)->sole();
    expect($ledger->amount->cents())->toBe(-1274)
        ->and($ledger->order_id)->toBe($order->id);

    $items = $order->items->keyBy('product_name');
    expect($items['Beer']->unit_price->cents())->toBe(200)
        ->and($items['Beer']->quantity)->toBe(2)
        ->and($items['Beer']->line_total->cents())->toBe(400)
        ->and($items['Cheese']->unit_price->cents())->toBe(374)
        ->and($items['Cheese']->line_total->cents())->toBe(374);

    expect(Cart::forUser($user)->summary()->isEmpty())->toBeTrue();
});

it('charges the current price when the price changed after adding', function () {
    [$user, $beer, , $ups] = checkoutFixture();
    $beer->update(['price' => 300]);

    $order = app(PlaceOrder::class)->handle($user, $ups);

    expect($order->subtotal->cents())->toBe(600 + 374);
});

it('refuses an empty cart', function () {
    $user = User::factory()->withBalance(Money::fromCents(10000))->create();
    $ups = ShippingMethod::factory()->ups()->create();

    expect(fn () => app(PlaceOrder::class)->handle($user, $ups))->toThrow(EmptyCart::class);
    expect(Order::count())->toBe(0);
});

it('rolls everything back when the balance is too low', function () {
    [$user, , , $ups] = checkoutFixture(1000);

    expect(fn () => app(PlaceOrder::class)->handle($user, $ups))->toThrow(InsufficientBalance::class);

    expect(Order::count())->toBe(0)
        ->and(OrderItem::count())->toBe(0)
        ->and(Cart::forUser($user)->summary()->lines)->toHaveCount(2)
        ->and($user->fresh()->balance->cents())->toBe(1000)
        ->and($user->walletTransactions()->where('type', WalletTransactionType::Purchase)->count())->toBe(0);
});
