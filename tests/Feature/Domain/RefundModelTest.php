<?php

use App\Enums\OrderStatus;
use App\Enums\RefundStatus;
use App\Enums\WalletTransactionType;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Payments\Exceptions\PaymentProviderUnavailable;
use App\Payments\FakePaymentGateway;
use App\Payments\Outcomes\RefundAccepted;
use App\Payments\Outcomes\RefundRefused;
use App\Support\Money;
use App\Wallet\Wallet;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

it('belongs to an order, a payment and an admin', function () {
    $order = Order::factory()->create();
    $payment = Payment::factory()->forOrder($order)->succeeded()->create();
    $refund = Refund::factory()->for($order)->for($payment)->create(['provider_amount' => 1500, 'wallet_amount' => 250]);

    expect($refund->order->is($order))->toBeTrue()
        ->and($order->refund->is($refund))->toBeTrue()
        ->and($refund->payment->is($payment))->toBeTrue()
        ->and($refund->admin->is_admin)->toBeTrue()
        ->and($refund->status)->toBe(RefundStatus::Pending)
        ->and($refund->attempt)->toBe(1)
        ->and($refund->provider_amount)->toEqual(Money::fromCents(1500))
        ->and($refund->wallet_amount)->toEqual(Money::fromCents(250))
        ->and($refund->completed_at)->toBeNull();
});

it('allows one refund per order', function () {
    $order = Order::factory()->create();
    Refund::factory()->for($order)->create();
    Refund::factory()->for($order)->create();
})->throws(QueryException::class);

it('builds a stable idempotency key from the row and attempt', function () {
    $refund = Refund::factory()->create(['id' => 9, 'attempt' => 3]);

    expect($refund->idempotencyKey())->toBe("refund-9-{$refund->created_at->getTimestamp()}-3")
        ->and($refund->fresh()->idempotencyKey())->toBe($refund->idempotencyKey());
});

it('has the new order status and ledger type', function () {
    expect(OrderStatus::Refunded->value)->toBe('refunded')
        ->and(WalletTransactionType::Refund->value)->toBe('refund')
        ->and(WalletTransactionType::Refund->label())->toBe('Refund');
});

it('credits the wallet once per refund', function () {
    $user = User::factory()->create();
    $refund = Refund::factory()->create();
    $wallet = app(Wallet::class);

    $tx = $wallet->credit($user, Money::fromCents(500), WalletTransactionType::Refund, null, $refund->order, refund: $refund);

    expect($tx->refund_id)->toBe($refund->id);

    expect(fn () => $wallet->credit($user, Money::fromCents(500), WalletTransactionType::Refund, null, $refund->order, refund: $refund))
        ->toThrow(QueryException::class);
});

it('answers a refund with an accepted fake id when nothing is queued', function () {
    $gateway = new FakePaymentGateway;
    $payment = Payment::factory()->succeeded()->create();
    $refund = Refund::factory()->create();

    $outcome = $gateway->refund($payment, $refund);

    expect($outcome)->toBeInstanceOf(RefundAccepted::class)
        ->and($outcome->providerRefundId)->toStartWith('re_fake_')
        ->and($gateway->refunded)->toHaveCount(1)
        ->and($gateway->refunded[0][0]->is($payment))->toBeTrue()
        ->and($gateway->refunded[0][1]->is($refund))->toBeTrue();
});

it('answers queued refund outcomes in order and throws queued throwables', function () {
    $gateway = (new FakePaymentGateway)->willRefund(new RefundRefused('No.'), new PaymentProviderUnavailable('Down.'));
    $payment = Payment::factory()->succeeded()->create();
    $refund = Refund::factory()->create();

    expect($gateway->refund($payment, $refund))->toEqual(new RefundRefused('No.'));
    expect(fn () => $gateway->refund($payment, $refund))->toThrow(PaymentProviderUnavailable::class);
    expect($gateway->refunded)->toHaveCount(2);
});

it('rolls the 2026-11-02 migrations back and forward again', function () {
    $this->artisan('migrate:rollback', ['--step' => 8])->assertSuccessful();

    expect(Schema::hasTable('refunds'))->toBeFalse()
        ->and(Schema::hasColumn('wallet_transactions', 'refund_id'))->toBeFalse()
        ->and(Schema::hasColumn('wallet_transactions', 'payment_id'))->toBeFalse();

    $this->artisan('migrate')->assertSuccessful();

    expect(Schema::hasTable('refunds'))->toBeTrue()
        ->and(Schema::hasColumn('wallet_transactions', 'refund_id'))->toBeTrue();
});
