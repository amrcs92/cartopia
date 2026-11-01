<?php

use App\Payments\PaymentLine;
use App\Payments\PaymentRequest;
use App\Support\Money;

it('adds the lines and the delivery fee', function () {
    $request = new PaymentRequest(
        [new PaymentLine('A', Money::fromCents(250), 2), new PaymentLine('B', Money::fromCents(99), 1)],
        Money::fromCents(500),
        'Order',
        'https://x.test/return',
        'https://x.test/cancel',
    );

    expect($request->total()->cents())->toBe(1099);
});

it('builds a single-line request with no delivery fee', function () {
    $request = PaymentRequest::single('Order #5', Money::fromCents(224), 'Order', 'r', 'c');

    expect($request->lines)->toHaveCount(1)
        ->and($request->lines[0]->quantity)->toBe(1)
        ->and($request->deliveryFee->cents())->toBe(0)
        ->and($request->total()->cents())->toBe(224);
});
