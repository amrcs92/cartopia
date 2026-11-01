<?php

use App\Cart\Cart;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Support\Money;
use Inertia\Testing\AssertableInertia as Assert;

function checkoutSetup(int $balance = 10000): array
{
    $user = User::factory()->withBalance(Money::fromCents($balance))->create();
    $cart = Cart::forUser($user);
    $cart->add(Product::factory()->create(['price' => 200]), 2);
    $cart->add(Product::factory()->create(['price' => 374]), 1);

    return [$user, ShippingMethod::factory()->ups()->create()];
}

it('checks out and redirects to the order', function () {
    [$user, $ups] = checkoutSetup();

    $response = $this->actingAs($user)
        ->post(route('checkout.store'), ['shipping_method_id' => $ups->id]);

    $order = Order::sole();
    $response->assertRedirect(route('orders.show', $order));

    // Inertia's native flash: the receipt shows the banner once.
    $this->get(route('orders.show', $order))
        ->assertInertia(fn (Assert $page) => $page->hasFlash('orderPlaced', 'Order placed. $12.74 was paid from your wallet.'));
    $this->get(route('orders.show', $order))
        ->assertInertia(fn (Assert $page) => $page->missingFlash('orderPlaced'));

    expect($order->user_id)->toBe($user->id)
        ->and($order->total->cents())->toBe(1274)
        ->and($order->payment_method)->toBe(PaymentMethod::Wallet)
        ->and($order->wallet_amount->cents())->toBe(1274);
});

it('ignores client-sent prices', function () {
    [$user, $ups] = checkoutSetup();

    $this->actingAs($user)->post(route('checkout.store'), [
        'shipping_method_id' => $ups->id,
        'price' => 1,
        'total' => 1,
        'total_price' => 0.01,
    ]);

    expect(Order::sole()->total->cents())->toBe(1274)
        ->and($user->fresh()->balance->cents())->toBe(8726);
});

it('flashes an error on insufficient balance', function () {
    [$user, $ups] = checkoutSetup(1000);

    $this->actingAs($user)
        ->post(route('checkout.store'), ['shipping_method_id' => $ups->id])
        ->assertRedirect(route('cart.show'));

    $this->get(route('cart.show'))->assertInertia(fn (Assert $page) => $page
        ->hasFlash('checkoutError', 'Your balance is too low for this order.')
        ->hasFlash('toast', ['type' => 'error', 'message' => 'Your balance is too low for this order.']));
    $this->get(route('cart.show'))->assertInertia(fn (Assert $page) => $page->missingFlash('checkoutError'));

    expect(Order::count())->toBe(0)
        ->and(Cart::forUser($user)->summary()->lines)->toHaveCount(2)
        ->and($user->fresh()->balance->cents())->toBe(1000);
});

it('redirects guests to login', function () {
    $ups = ShippingMethod::factory()->ups()->create();

    $this->post(route('checkout.store'), ['shipping_method_id' => $ups->id])
        ->assertRedirect(route('login'));
});

it('places only one order on double submit', function () {
    [$user, $ups] = checkoutSetup();

    $this->actingAs($user)->post(route('checkout.store'), ['shipping_method_id' => $ups->id]);
    $this->actingAs($user)
        ->post(route('checkout.store'), ['shipping_method_id' => $ups->id])
        ->assertRedirect(route('cart.show'));

    $this->get(route('cart.show'))->assertInertia(fn (Assert $page) => $page
        ->hasFlash('checkoutError', 'Your basket is empty.'));

    expect(Order::count())->toBe(1);
});

it('throttles checkout', function () {
    [$user, $ups] = checkoutSetup();

    foreach (range(1, 10) as $i) {
        $this->actingAs($user)->post(route('checkout.store'), ['shipping_method_id' => $ups->id])
            ->assertStatus(302);
    }

    $this->actingAs($user)
        ->post(route('checkout.store'), ['shipping_method_id' => $ups->id])
        ->assertStatus(429);
});

it('validates the shipping method', function () {
    [$user] = checkoutSetup();

    $this->actingAs($user)
        ->post(route('checkout.store'), ['shipping_method_id' => 999])
        ->assertSessionHasErrors('shipping_method_id');

    expect(Order::count())->toBe(0);
});

test('a guest following the checkout link returns to the cart after logging in', function () {
    User::factory()->create(['email' => 'shopper@example.com']);

    $this->get(route('checkout.start'))->assertRedirect(route('login'));

    $this->post(route('login.store'), [
        'email' => 'shopper@example.com',
        'password' => 'password',
    ])->assertRedirect(route('checkout.start'));

    $this->get(route('checkout.start'))->assertRedirect(route('cart.show'));
});

it('exposes the subtotal and shipping fees in cents on the cart page', function () {
    [$user, $ups] = checkoutSetup();

    $this->actingAs($user)
        ->get(route('cart.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('cart.subtotal', '$7.74')
            ->where('cart.subtotal_cents', 774)
            ->where('shippingMethods.0.fee', $ups->fee->format())
            ->where('shippingMethods.0.fee_cents', $ups->fee->cents())
        );
});
