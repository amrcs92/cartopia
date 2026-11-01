<?php

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\PaymentWording;
use App\Support\Money;

function hostedOrder(PaymentMethod $method): Order
{
    return Order::factory()->make([
        'payment_method' => $method,
        'total' => 290,
        'wallet_amount' => 0,
    ]);
}

it('says how an order was paid', function () {
    $stripe = hostedOrder(PaymentMethod::Stripe);
    $stripe->setRelation('succeededPayment', Payment::factory()->stripe()->make(['card_brand' => 'visa', 'card_last4' => '4242']));

    expect(PaymentWording::paidWith(Order::factory()->make(['payment_method' => PaymentMethod::Wallet])))->toBe('Cartopia wallet')
        ->and(PaymentWording::paidWith($stripe))->toBe('Visa ending in 4242, through Stripe')
        ->and(PaymentWording::paidWith(hostedOrder(PaymentMethod::PayPal)))->toBe('PayPal');
});

it('falls back to a plain card sentence without card details', function () {
    $order = hostedOrder(PaymentMethod::Stripe);
    $order->setRelation('succeededPayment', null);

    expect(PaymentWording::paidWith($order))->toBe('Card, through Stripe');
});

it('shortens a payment reference and keeps the full one', function () {
    $stripe = Payment::factory()->stripe()->make(['provider_payment_id' => 'pi_3Q8xKLMNOP7Kd2']);
    $paypal = Payment::factory()->paypal()->make(['provider_payment_id' => '7XK21ABCDE48N']);

    expect(PaymentWording::reference($stripe))->toBe(['short' => 'Stripe payment pi_3Q8xK…7Kd2', 'full' => 'Stripe payment pi_3Q8xKLMNOP7Kd2'])
        ->and(PaymentWording::reference($paypal))->toBe(['short' => 'PayPal capture 7XK21ABC…E48N', 'full' => 'PayPal capture 7XK21ABCDE48N']);
});

it('shows a short payment id whole and gives none without an id', function () {
    $short = Payment::factory()->paypal()->make(['provider_payment_id' => 'ABC123456789']);
    $none = Payment::factory()->paypal()->make(['provider_payment_id' => null]);

    expect(PaymentWording::reference($short)['short'])->toBe('PayPal capture ABC123456789')
        ->and(PaymentWording::reference($none))->toBeNull();
});

it('writes the end of the slip', function () {
    expect(PaymentWording::slipLines(hostedOrder(PaymentMethod::Stripe), null))->toBe([['label' => 'Charged to card', 'value' => '$2.90']])
        ->and(PaymentWording::slipLines(hostedOrder(PaymentMethod::PayPal), null))->toBe([['label' => 'Paid through PayPal', 'value' => '$2.90']]);

    $wallet = Order::factory()->make(['payment_method' => PaymentMethod::Wallet]);
    expect(PaymentWording::slipLines($wallet, Money::fromCents(11092)))->toBe([['label' => 'Wallet balance after', 'value' => '$110.92']])
        ->and(PaymentWording::slipLines($wallet, null))->toBe([]);
});

it('tags each order', function () {
    expect(PaymentWording::tag(hostedOrder(PaymentMethod::Wallet)))->toBe('Paid with wallet')
        ->and(PaymentWording::tag(hostedOrder(PaymentMethod::Stripe)))->toBe('Paid by card')
        ->and(PaymentWording::tag(hostedOrder(PaymentMethod::PayPal)))->toBe('Paid with PayPal');
});

it('names card brands', function () {
    expect(PaymentWording::cardBrand('visa'))->toBe('Visa')
        ->and(PaymentWording::cardBrand('mastercard'))->toBe('Mastercard')
        ->and(PaymentWording::cardBrand('amex'))->toBe('American Express')
        ->and(PaymentWording::cardBrand('discover'))->toBe('Discover')
        ->and(PaymentWording::cardBrand('jcb'))->toBe('Jcb')
        ->and(PaymentWording::cardBrand(null))->toBe('Card');
});
