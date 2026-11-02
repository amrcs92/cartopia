<?php

use App\Cart\Cart;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Payments\PaymentGateways;
use App\Support\Money;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The checkoutSetup() basket ($12.74, two products) plus a third product
 * that is then hidden, so the basket still holds it.
 *
 * @return array{0: User, 1: ShippingMethod, 2: Product}
 */
function basketWithHiddenProduct(int $balance = 10000): array
{
    [$user, $ups] = checkoutSetup($balance);

    $hidden = Product::factory()->create(['name' => 'Retired Mug', 'price' => 900]);
    Cart::forUser($user)->add($hidden, 2);
    $hidden->update(['is_visible' => false]);

    return [$user, $ups, $hidden];
}

it('marks a hidden product in the basket and leaves it out of the totals', function () {
    [$user, , $hidden] = basketWithHiddenProduct();

    $this->actingAs($user)->get(route('cart.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('cart.lines', 3)
            ->where('cart.lines.2.product.id', $hidden->id)
            ->where('cart.lines.2.available', false)
            ->where('cart.lines.0.available', true)
            ->where('cart.subtotal', '$7.74')
            ->where('cart.subtotal_cents', 774)
            ->where('cart.total_quantity', 3)
            ->where('cart.count', 3)
            ->where('cart.has_unavailable', true));

    $this->get(route('products.index'))
        ->assertInertia(fn (Assert $page) => $page->where('cart.count', 3));
});

it('refuses wallet checkout and names the hidden product', function () {
    [$user, $ups] = basketWithHiddenProduct();
    $transactions = WalletTransaction::count();

    $this->actingAs($user)
        ->post(route('checkout.store'), ['shipping_method_id' => $ups->id, 'payment_method' => 'wallet'])
        ->assertRedirect(route('cart.show'));

    $message = 'Remove Retired Mug from your basket to continue.';
    $this->get(route('cart.show'))->assertInertia(fn (Assert $page) => $page
        ->hasFlash('checkoutError', $message)
        ->hasFlash('toast', ['type' => 'error', 'message' => $message]));

    expect(Order::count())->toBe(0)
        ->and(WalletTransaction::count())->toBe($transactions)
        ->and($user->fresh()->balance->cents())->toBe(10000)
        ->and(Cart::forUser($user)->summary()->lines)->toHaveCount(3);
});

it('refuses Stripe, PayPal and split checkout without an order, payment or wallet change', function (string $method, bool $useWallet) {
    $fake = PaymentGateways::fake();
    [$user, $ups] = basketWithHiddenProduct(500);
    $transactions = WalletTransaction::count();

    $this->actingAs($user)
        ->post(route('checkout.store'), [
            'shipping_method_id' => $ups->id,
            'payment_method' => $method,
            'use_wallet' => $useWallet ? '1' : '0',
        ])
        ->assertRedirect(route('cart.show'));

    $this->get(route('cart.show'))->assertInertia(fn (Assert $page) => $page
        ->hasFlash('checkoutError', 'Remove Retired Mug from your basket to continue.'));

    expect(Order::count())->toBe(0)
        ->and(Payment::count())->toBe(0)
        ->and(WalletTransaction::count())->toBe($transactions)
        ->and($fake->started)->toBeEmpty()
        ->and($user->fresh()->balance->cents())->toBe(500);
})->with([
    'stripe' => ['stripe', false],
    'paypal' => ['paypal', false],
    'split stripe' => ['stripe', true],
    'split paypal' => ['paypal', true],
]);

it('names the first hidden product by basket order, by its current name', function () {
    [$user, $ups, $hidden] = basketWithHiddenProduct();
    $second = Product::factory()->hidden()->create(['name' => 'Second Hidden']);
    Cart::forUser($user)->add($second, 1);
    $hidden->update(['name' => 'Renamed Mug']);

    $this->actingAs($user)
        ->post(route('checkout.store'), ['shipping_method_id' => $ups->id, 'payment_method' => 'wallet']);

    $this->get(route('cart.show'))->assertInertia(fn (Assert $page) => $page
        ->hasFlash('checkoutError', 'Remove Renamed Mug from your basket to continue.'));
});

it('refuses a basket holding only a hidden product', function () {
    $user = User::factory()->withBalance(Money::fromCents(10000))->create();
    $product = Product::factory()->create(['name' => 'Lone Item']);
    Cart::forUser($user)->add($product, 1);
    $product->update(['is_visible' => false]);
    $ups = ShippingMethod::factory()->ups()->create();

    $this->actingAs($user)
        ->post(route('checkout.store'), ['shipping_method_id' => $ups->id, 'payment_method' => 'wallet']);

    $this->get(route('cart.show'))->assertInertia(fn (Assert $page) => $page
        ->hasFlash('checkoutError', 'Remove Lone Item from your basket to continue.'));
    expect(Order::count())->toBe(0);
});

it('lets checkout through once the hidden line is removed', function () {
    [$user, $ups, $hidden] = basketWithHiddenProduct();

    $this->actingAs($user)->delete(route('cart.items.destroy', $hidden))->assertRedirect();
    $this->post(route('checkout.store'), ['shipping_method_id' => $ups->id, 'payment_method' => 'wallet']);

    $order = Order::sole();
    expect($order->total->cents())->toBe(1274)
        ->and($order->items)->toHaveCount(2);
});

it('refuses a basket whose product was hidden after the page was loaded', function () {
    [$user, $ups] = checkoutSetup();
    $visible = Product::query()->first();
    $visible->update(['is_visible' => false]);

    $this->actingAs($user)
        ->post(route('checkout.store'), ['shipping_method_id' => $ups->id, 'payment_method' => 'wallet']);

    expect(Order::count())->toBe(0);
});

it('404s a rating for a hidden product', function () {
    $product = Product::factory()->hidden()->create();

    $this->actingAs(User::factory()->create())
        ->put(route('products.rating.update', $product), ['stars' => 5])
        ->assertNotFound();

    expect($product->ratings()->count())->toBe(0);
});
