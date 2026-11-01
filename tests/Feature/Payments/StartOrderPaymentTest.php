<?php

use App\Cart\Cart;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Payments\PaymentGateways;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->fake = PaymentGateways::fake();
});

it('creates an unpaid order and a pending payment, then redirects', function () {
    [$user, $ups] = checkoutSetup();

    $response = $this->actingAs($user)->post(route('checkout.store'), [
        'shipping_method_id' => $ups->id,
        'payment_method' => 'stripe',
    ]);

    $order = Order::sole();
    $payment = Payment::sole();

    $response->assertRedirect(url("/payments/{$payment->id}/return"));

    expect($order->status)->toBe(OrderStatus::AwaitingPayment)
        ->and($order->total->cents())->toBe(1274)
        ->and($order->payment_method)->toBe(PaymentMethod::Stripe)
        ->and($order->wallet_amount->cents())->toBe(0)
        ->and($order->items)->toHaveCount(2)
        ->and($payment->purpose)->toBe(PaymentPurpose::Order)
        ->and($payment->order_id)->toBe($order->id)
        ->and($payment->user_id)->toBe($user->id)
        ->and($payment->amount->cents())->toBe(1274)
        ->and($payment->currency)->toBe('usd')
        ->and($payment->method)->toBe(PaymentMethod::Stripe)
        ->and($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->provider_reference)->toBe("fake_{$payment->id}")
        ->and(Cart::forUser($user)->count())->toBe(3)
        ->and($user->fresh()->balance->cents())->toBe(10000);

    // An Inertia visit gets a 409 with the location, so the browser leaves the SPA.
    $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true'])
        ->post(route('checkout.store'), [
            'shipping_method_id' => $ups->id,
            'payment_method' => 'paypal',
        ])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', url('/payments/'.Payment::query()->latest('id')->value('id').'/return'));

    expect(Payment::query()->latest('id')->first()->method)->toBe(PaymentMethod::PayPal);
});

it('rolls back when the provider is down', function () {
    [$user, $ups] = checkoutSetup();
    $this->fake->failToStart();

    $this->actingAs($user)
        ->post(route('checkout.store'), ['shipping_method_id' => $ups->id, 'payment_method' => 'stripe'])
        ->assertRedirect(route('cart.show'));

    $this->get(route('cart.show'))->assertInertia(fn (Assert $page) => $page
        ->hasFlash('checkoutError', "We couldn't reach Stripe. Try again in a moment.")
        ->hasFlash('toast', ['type' => 'error', 'message' => "We couldn't reach Stripe. Try again in a moment."]));

    expect(Order::count())->toBe(0)
        ->and(Payment::count())->toBe(0)
        ->and(Cart::forUser($user)->count())->toBe(3);
});

it('names PayPal when PayPal is down', function () {
    [$user, $ups] = checkoutSetup();
    $this->fake->failToStart();

    $this->actingAs($user)
        ->post(route('checkout.store'), ['shipping_method_id' => $ups->id, 'payment_method' => 'paypal'])
        ->assertRedirect(route('cart.show'));

    $this->get(route('cart.show'))->assertInertia(fn (Assert $page) => $page
        ->hasFlash('checkoutError', "We couldn't reach PayPal. Try again in a moment."));
});

it('refuses an empty basket', function () {
    $user = User::factory()->create();
    $ups = ShippingMethod::factory()->ups()->create();

    $this->actingAs($user)
        ->post(route('checkout.store'), ['shipping_method_id' => $ups->id, 'payment_method' => 'stripe'])
        ->assertRedirect(route('cart.show'));

    $this->get(route('cart.show'))->assertInertia(fn (Assert $page) => $page
        ->hasFlash('checkoutError', 'Your basket is empty.'));

    expect(Order::count())->toBe(0)
        ->and(Payment::count())->toBe(0)
        ->and($this->fake->started)->toBe([]);
});

it('refuses a method that is not available', function () {
    // No fake: the real registry, with no PayPal credentials, lists no PayPal.
    app()->forgetInstance(PaymentGateways::class);
    config(['services.paypal.client_id' => null, 'services.paypal.secret' => null]);
    [$user, $ups] = checkoutSetup();

    $this->actingAs($user)
        ->post(route('checkout.store'), ['shipping_method_id' => $ups->id, 'payment_method' => 'paypal'])
        ->assertSessionHasErrors('payment_method');

    expect(Order::count())->toBe(0);
});

it('refuses an unknown method', function () {
    [$user, $ups] = checkoutSetup();

    $this->actingAs($user)
        ->post(route('checkout.store'), ['shipping_method_id' => $ups->id, 'payment_method' => 'bitcoin'])
        ->assertSessionHasErrors('payment_method');
});

it('refuses card payments under 50 cents', function () {
    $user = User::factory()->create();
    Cart::forUser($user)->add(Product::factory()->create(['price' => 30]), 1);
    $pickup = ShippingMethod::factory()->pickup()->create();

    $this->actingAs($user)
        ->post(route('checkout.store'), ['shipping_method_id' => $pickup->id, 'payment_method' => 'stripe'])
        ->assertRedirect(route('cart.show'));

    $this->get(route('cart.show'))->assertInertia(fn (Assert $page) => $page
        ->hasFlash('checkoutError', 'Stripe and PayPal payments start at $0.50.'));

    expect(Order::count())->toBe(0)
        ->and(Payment::count())->toBe(0)
        ->and($this->fake->started)->toBe([]);
});

it('passes the order lines to the gateway', function () {
    [$user, $ups] = checkoutSetup();

    $this->actingAs($user)
        ->post(route('checkout.store'), ['shipping_method_id' => $ups->id, 'payment_method' => 'stripe']);

    $order = Order::sole();
    [$payment, $request] = $this->fake->started[0];

    expect($this->fake->started)->toHaveCount(1)
        ->and($payment->id)->toBe(Payment::sole()->id)
        ->and($request->lines)->toHaveCount(2)
        ->and($request->lines[0]->unitPrice->cents())->toBe(200)
        ->and($request->lines[0]->quantity)->toBe(2)
        ->and($request->lines[1]->unitPrice->cents())->toBe(374)
        ->and($request->lines[1]->quantity)->toBe(1)
        ->and($request->deliveryFee->cents())->toBe(500)
        ->and($request->total()->cents())->toBe(1274)
        ->and($request->description)->toBe("Cartopia order #{$order->id}")
        ->and($request->returnUrl)->toBe(url("/payments/{$payment->id}/return"))
        ->and($request->cancelUrl)->toBe(url("/payments/{$payment->id}/cancel"));
});
