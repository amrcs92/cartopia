<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Enums\WalletTransactionType;

it('labels payment methods the way shoppers say them', function () {
    expect(PaymentMethod::Wallet->label())->toBe('Wallet')
        ->and(PaymentMethod::Stripe->label())->toBe('Stripe')
        ->and(PaymentMethod::PayPal->label())->toBe('PayPal')
        ->and(PaymentMethod::hosted())->toBe([PaymentMethod::Stripe, PaymentMethod::PayPal])
        ->and(PaymentMethod::Wallet->isHosted())->toBeFalse();
});

it('has the new order and ledger cases', function () {
    expect(OrderStatus::from('awaiting_payment'))->toBe(OrderStatus::AwaitingPayment)
        ->and(OrderStatus::from('cancelled'))->toBe(OrderStatus::Cancelled)
        ->and(WalletTransactionType::Release->label())->toBe('Returned')
        ->and(PaymentStatus::from('expired'))->toBe(PaymentStatus::Expired)
        ->and(PaymentPurpose::from('top_up'))->toBe(PaymentPurpose::TopUp);
});
