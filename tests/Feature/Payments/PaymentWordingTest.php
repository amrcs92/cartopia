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
    expect(PaymentWording::slipLines(hostedOrder(PaymentMethod::Stripe), null))->toBe([['label' => 'Paid through Stripe', 'value' => '$2.90']])
        ->and(PaymentWording::slipLines(hostedOrder(PaymentMethod::PayPal), null))->toBe([['label' => 'Paid through PayPal', 'value' => '$2.90']]);

    $wallet = Order::factory()->make(['payment_method' => PaymentMethod::Wallet]);
    expect(PaymentWording::slipLines($wallet, Money::fromCents(11092)))->toBe([['label' => 'Wallet balance after', 'value' => '$110.92']])
        ->and(PaymentWording::slipLines($wallet, null))->toBe([]);
});

it('tags each order', function () {
    expect(PaymentWording::tag(hostedOrder(PaymentMethod::Wallet)))->toBe('Paid with wallet')
        ->and(PaymentWording::tag(hostedOrder(PaymentMethod::Stripe)))->toBe('Paid with Stripe')
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

function splitOrder(PaymentMethod $method, bool $withCard = true): Order
{
    $order = Order::factory()->make([
        'payment_method' => $method,
        'total' => 434,
        'wallet_amount' => 210,
    ]);
    $order->setRelation('succeededPayment', $withCard && $method === PaymentMethod::Stripe
        ? Payment::factory()->stripe()->make(['card_brand' => 'visa', 'card_last4' => '4242'])
        : null);

    return $order;
}

it('words the order placed message for a split', function () {
    expect(PaymentWording::placedMessage(splitOrder(PaymentMethod::Stripe)))->toBe('Order placed. $2.10 came from your wallet and your Stripe payment of $2.24 was approved.')
        ->and(PaymentWording::placedMessage(splitOrder(PaymentMethod::PayPal)))->toBe('Order placed. $2.10 came from your wallet and your PayPal payment of $2.24 was approved.');
});

it('says how a split was paid', function () {
    expect(PaymentWording::paidWith(splitOrder(PaymentMethod::Stripe)))->toBe('Cartopia wallet $2.10 + Visa ending in 4242 $2.24')
        ->and(PaymentWording::paidWith(splitOrder(PaymentMethod::Stripe, withCard: false)))->toBe('Cartopia wallet $2.10 + Stripe $2.24')
        ->and(PaymentWording::paidWith(splitOrder(PaymentMethod::PayPal)))->toBe('Cartopia wallet $2.10 + PayPal $2.24');
});

it('writes the end of the slip for a split', function () {
    expect(PaymentWording::slipLines(splitOrder(PaymentMethod::Stripe), Money::fromCents(0)))->toBe([
        ['label' => 'Paid from wallet', 'value' => '-$2.10'],
        ['label' => 'Paid through Stripe', 'value' => '$2.24'],
        ['label' => 'Wallet balance after', 'value' => '$0.00'],
    ])
        ->and(PaymentWording::slipLines(splitOrder(PaymentMethod::PayPal), null))->toBe([
            ['label' => 'Paid from wallet', 'value' => '-$2.10'],
            ['label' => 'Paid through PayPal', 'value' => '$2.24'],
        ]);
});

it('tags a split', function () {
    expect(PaymentWording::tag(splitOrder(PaymentMethod::Stripe)))->toBe('Wallet + Stripe')
        ->and(PaymentWording::tag(splitOrder(PaymentMethod::PayPal)))->toBe('Wallet + PayPal');
});
