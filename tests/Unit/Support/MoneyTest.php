<?php

use App\Support\Money;

it('formats cents as dollars', function (int $cents, string $expected) {
    expect(Money::fromCents($cents)->format())->toBe($expected);
})->with([[0, '$0.00'], [374, '$3.74'], [123456789, '$1,234,567.89'], [-500, '-$5.00']]);

it('parses dollar strings without float drift', function (string $in, int $cents) {
    expect(Money::fromDollars($in)->cents())->toBe($cents);
})->with([['12.5', 1250], ['0.30', 30], ['500', 50000], ['4.99', 499], ['0.1', 10]]);

it('does arithmetic immutably', function () {
    $a = Money::fromCents(374);
    expect($a->multiply(3)->cents())->toBe(1122)
        ->and($a->add(Money::fromCents(26))->cents())->toBe(400)
        ->and($a->cents())->toBe(374)
        ->and($a->subtract(Money::fromCents(400))->isNegative())->toBeTrue()
        ->and(Money::fromCents(1)->isLessThan(Money::fromCents(2)))->toBeTrue()
        ->and(Money::zero()->equals(Money::fromCents(0)))->toBeTrue();
});
