<?php

use App\Payments\SplitPlan;
use App\Support\Money;

it('splits a total', function (int $total, int $balance, bool $use, int $wallet, int $hosted) {
    $plan = SplitPlan::for(Money::fromCents($total), Money::fromCents($balance), $use);

    expect($plan->wallet->cents())->toBe($wallet)
        ->and($plan->hosted->cents())->toBe($hosted)
        ->and($plan->isSplit())->toBe($wallet > 0);
})->with([
    'short wallet' => [434, 210, true, 210, 224],
    'fifty-cent rule' => [434, 400, true, 384, 50],
    'empty wallet' => [434, 0, true, 0, 434],
    'switch off' => [434, 210, false, 0, 434],
    'wallet covers it' => [434, 434, true, 0, 434],
    'too small to split' => [40, 20, true, 0, 40],
]);
