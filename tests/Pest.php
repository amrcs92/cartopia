<?php

use App\Actions\StartOrderPayment;
use App\Cart\Cart;
use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Browser');

/**
 * A shopper with the given balance and a basket of 2 x $2.00 and 1 x $3.74,
 * plus UPS ($5.00): a total of $12.74.
 *
 * @return array{0: User, 1: ShippingMethod}
 */
function checkoutSetup(int $balance = 10000): array
{
    $user = User::factory()->withBalance(Money::fromCents($balance))->create();
    $cart = Cart::forUser($user);
    $cart->add(Product::factory()->create(['price' => 200]), 2);
    $cart->add(Product::factory()->create(['price' => 374]), 1);

    return [$user, ShippingMethod::factory()->ups()->create()];
}

/**
 * Starts a hosted payment for the checkoutSetup() basket. Needs a bound
 * PaymentGateways::fake().
 *
 * @return array{0: User, 1: Payment}
 */
function startedOrderPayment(PaymentMethod $method = PaymentMethod::Stripe): array
{
    [$user, $ups] = checkoutSetup();

    app(StartOrderPayment::class)->handle($user, $ups, $method);

    return [$user, Payment::sole()];
}
